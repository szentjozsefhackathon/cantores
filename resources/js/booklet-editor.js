import { pageGeometry } from './booklet-geometry.js';
import { createBusyFlag, layoutSignature, renderDelayFor } from './booklet-pacing.js';
import { renderBooklet, serializeBookletPages } from './booklet-render.js';
import { fileSettings, movesSetting, resolveSettings, steppedValue, withPlainOverrides } from './booklet-settings.js';
import { beginSplitDrag, clampSplitPercent, SPLIT_DEFAULT } from './booklet-split.js';
import { abcMixin } from './score-editor-abc.js';
import { aretinoMixin } from './score-editor-aretino.js';
import { chordproMixin } from './score-editor-chordpro.js';
import { gabcMixin } from './score-editor-gabc.js';

/**
 * The booklet editor's browser half.
 *
 * Livewire owns what is in the booklet; this owns what it looks like. Every
 * change — a page size, a lyric size, one score nudged wider — redraws the whole
 * thing, because a booklet flows and there is no such thing as re-rendering only
 * the score you touched: making one score two lines shorter moves everything
 * after it.
 */

/**
 * How long a knob is left alone before the change is sent to the server.
 *
 * A number field steps once per arrow click, and every step used to be a round
 * trip that re-rendered the whole component and pushed a fresh payload back —
 * so nudging a staff size from 7 to 12 cost five of them, each one landing in
 * the middle of the next. The preview waits its own gap before redrawing; only
 * the saving waits this one.
 */
const SAVE_DEBOUNCE_MS = 600;

document.addEventListener('alpine:init', () => {
    Alpine.data('bookletEditor', (config = {}) => {
        /**
         * The booklet's own half of the server, kept rather than asked for, and
         * kept here rather than on the component.
         *
         * $wire is answered by whichever element a method was called from, not
         * by the one this data belongs to, and a score's toolbar now stands
         * inside a component of its own — so a knob turned there was sending the
         * booklet's business to the row it was turned in, which knows nothing
         * about saving overrides. Taken once in init(), where the answer is the
         * editor, it stays the editor wherever it is used from.
         *
         * It cannot live on the component itself. Alpine runs toRaw() over
         * everything assigned into its state, and toRaw() asks a value for
         * __v_raw — a question Livewire's wire answers, like every other one it
         * does not recognise, with a function that would call a method of that
         * name on the server. Alpine takes that function for the raw object and
         * keeps it, so a wire put into the state comes back out as a stub with
         * no saveOverride on it, and every override was lost on the way to the
         * database while the preview went on showing it.
         */
        let wire = null;

        return {
            geometry: config.geometry ?? {},
            entries: withPlainOverrides(config.entries),
            exportUrl: config.exportUrl ?? '',
            csrfToken: config.csrfToken ?? '',
            exportFailedText: config.exportFailedText ?? '',

            pages: [],
            pageCount: 0,
            splitPercent: SPLIT_DEFAULT,
            splitDragging: false,
            busy: false,
            exporting: false,
            message: '',

            _renderTimer: null,
            _renderToken: 0,
            _saveTimers: {},
            _pendingOverrides: {},
            _busy: null,
            _lastRenderMs: 0,
            _drawnSignature: null,

            init() {
                console.log('[booklet debug] INIT — the component was created', Math.round(performance.now()));

                wire = this.$wire;

                this._busy = createBusyFlag({ onChange: (busy) => { this.busy = busy; } });

                this.$nextTick(() => this.scheduleRender());
            },

            destroy() {
                clearTimeout(this._renderTimer);
                this._busy?.stop();
                this.flushOverrides();
            },

            /**
             * A knob was touched. Said now, rather than when the layout run starts,
             * because between the two lie a trip to the server and the render
             * debounce — a second or so in which the preview would sit there looking
             * settled and finished while showing the booklet as it was.
             *
             * The title is the one control that changes nothing on the page, so a
             * field that opts out with data-booklet-quiet is left alone.
             */
            markBusy(event = null) {
                if (event?.target?.closest?.('[data-booklet-quiet]')) { return; }

                console.log('[booklet debug] busy raised at', Math.round(performance.now()), new Error().stack);

                this._busy?.start();
            },

            /**
             * A change came back from the server. The payload is pushed rather than
             * read, because it is a computed property with no client-side existence.
             *
             * A payload that left the server before the knob currently being turned
             * was saved carries the older value, so anything still waiting to be
             * sent is put back on top of it — otherwise the preview would flick back
             * to where the score was a moment ago and then forward again.
             *
             * Usually what comes back is the booklet already on screen: a knob was
             * turned, the preview redrew at once, and the save that followed a
             * moment later is answered with the same booklet. Laying it out again
             * would freeze the browser a second time to arrive at the pages it is
             * already showing, so a payload that describes them is only adopted —
             * and the flag comes down, since there is nothing left to wait for.
             */
            applyUpdate(detail = {}) {
                if (detail.payload) { this.entries = withPlainOverrides(detail.payload); }
                if (detail.geometry) { this.geometry = detail.geometry; }

                Object.entries(this._pendingOverrides).forEach(([entryId, override]) => {
                    const entry = this.entries.find((candidate) => String(candidate.id) === entryId);

                    if (entry) { entry.override = override; }
                });

                const arrived = layoutSignature(this.entries, this.geometry);

                if (arrived === this._drawnSignature) {
                    console.log('[booklet debug] payload matches what was drawn', Math.round(performance.now()));

                    this._busy?.settle();

                    return;
                }

                const at = [...arrived].findIndex((c, i) => c !== (this._drawnSignature ?? '')[i]);

                console.log(
                    '[booklet debug] payload differs from what was drawn', Math.round(performance.now()),
                    '\n  at character', at,
                    '\n  DRAWN:   ...' + (this._drawnSignature ?? '(nothing drawn yet)').slice(Math.max(0, at - 200), at + 200),
                    '\n  ARRIVED: ...' + arrived.slice(Math.max(0, at - 200), at + 200),
                );

                this.scheduleRender();
            },

            /** The handle between the plan and the pages was grabbed. */
            startSplitDrag(event) {
                const handle = event.currentTarget;
                const row = handle.parentElement;

                if (!row) { return; }

                event.preventDefault();
                this.splitDragging = true;

                beginSplitDrag(handle, row, event, {
                    onMove: (percent) => { this.splitPercent = percent; },
                    onEnd: () => { this.splitDragging = false; },
                });
            },

            nudgeSplit(delta) {
                this.splitPercent = clampSplitPercent(this.splitPercent + delta);
            },

            resetSplit() {
                this.splitPercent = SPLIT_DEFAULT;
            },

            /**
             * Put off laying the booklet out until the knobs have been still for a
             * moment. The wait is as long as the last layout took, because that is
             * the stretch in which the browser answers nothing: start it under a
             * finger going back for a second click and the click is what suffers.
             */
            scheduleRender() {
                this.markBusy();

                clearTimeout(this._renderTimer);
                this._renderTimer = setTimeout(() => this.render(), renderDelayFor(this._lastRenderMs));
            },

            async render() {
                // A slow render must not overwrite a newer one that finished first.
                const token = ++this._renderToken;
                const startedAt = performance.now();

                // Taken before the layout rather than after it, so that a knob
                // turned while the booklet is being drawn is not written down as
                // drawn — what is about to go on screen is the booklet as it stands
                // now, and the change that came in the middle earns its own run.
                const signature = layoutSignature(this.entries, this.geometry);

                try {
                    const { pages, fonts } = await renderBooklet(this.entries, this.geometry, this.$refs.measure);

                    if (token !== this._renderToken) { return; }

                    console.log('[booklet debug] laid out', Math.round(performance.now()));
                    this._drawnSignature = signature;
                    this._fonts = fonts;
                    this.pages = pages;
                    this.pageCount = pages.length;
                    this.paint();
                } catch (e) {
                    console.error('[booklet] render failed', e);
                } finally {
                    if (token === this._renderToken) {
                        this._lastRenderMs = performance.now() - startedAt;
                        this._busy?.settle();
                    }
                }
            },

            paint() {
                const container = this.$refs.pages;
                if (!container) { return; }

                container.replaceChildren();

                this.pages.forEach((page) => {
                    const sheet = document.createElement('div');
                    sheet.className = 'booklet-page';
                    const svg = page.cloneNode(true);
                    svg.removeAttribute('width');
                    svg.removeAttribute('height');
                    svg.style.width = '100%';
                    svg.style.height = 'auto';
                    svg.style.display = 'block';
                    sheet.appendChild(svg);
                    container.appendChild(sheet);
                });
            },

            /**
             * The values one score is actually being drawn at — the booklet's, unless
             * something was changed by hand.
             *
             * Worked out afresh whenever the panel reads it rather than snapshotted
             * when the panel opens: the panel is one card per entry, the booklet's own
             * size can move underneath it, and a snapshot taken at the wrong moment is
             * how a panel ends up showing a blank where a number belongs.
             */
            settingsOf(entryId) {
                const entry = this.entries.find((candidate) => candidate.id === entryId);

                if (!entry) { return {}; }

                // An uploaded score is a picture: there is nothing to resolve, only
                // the factor it is being taken down by.
                if (entry.kind === 'file') { return fileSettings(entry.override); }

                if (entry.kind !== 'score') { return {}; }

                return resolveSettings(
                    entry.format,
                    formatDefaults(entry.format),
                    entry.settings ?? {},
                    pageGeometry(this.geometry),
                    entry.override ?? {},
                );
            },

            /**
             * A knob moved. Only the keys someone actually touched are stored, so a
             * booklet resized later still re-unifies everything nobody pinned.
             */
            setOverride(entryId, key, value) {
                const entry = this.entries.find((candidate) => candidate.id === entryId);

                if (!entry) { return; }

                // Built plainly and then assigned, so what goes to the server is an
                // ordinary object rather than the reactive proxy the entry holds.
                const override = { ...(entry.override ?? {}), [key]: value };
                entry.override = override;

                this.scheduleRender();
                this.scheduleSave(entryId, override);
            },

            /**
             * A knob offered as bigger and smaller, moved one step.
             *
             * Stepped from what the score is actually drawn at — the booklet's own
             * computed size, unless this score has already been nudged — and onto
             * the step's own grid, so a size the geometry left at 4.6667 becomes 5
             * rather than 5.1667.
             *
             * @param {object} field one entry of BookletSettingFields::panelFor
             * @param {number} direction -1 or 1
             */
            nudgeOverride(entryId, field, direction) {
                this.setOverride(entryId, field.key, steppedValue(this.settingsOf(entryId)[field.key], field, direction));
            },

            /** A knob at the end of its travel, so the button can say so. */
            atLimit(entryId, field, direction) {
                const value = Number(this.settingsOf(entryId)[field.key]);

                if (!Number.isFinite(value)) { return false; }

                return direction < 0 ? value <= Number(field.min) : value >= Number(field.max);
            },

            resetOverride(entryId) {
                const entry = this.entries.find((candidate) => candidate.id === entryId);

                if (!entry) { return; }

                entry.override = {};

                clearTimeout(this._saveTimers[entryId]);
                delete this._saveTimers[entryId];
                delete this._pendingOverrides[entryId];

                this.scheduleRender();
                wire.resetOverride(entryId);
            },

            /**
             * Hold one entry's changes back until the knob stops moving.
             *
             * Held per entry rather than globally: two panels may be open, and a
             * score whose settings someone finished with should not wait on another
             * they have only just started on.
             */
            scheduleSave(entryId, override) {
                this._pendingOverrides[entryId] = override;

                clearTimeout(this._saveTimers[entryId]);
                this._saveTimers[entryId] = setTimeout(() => this.saveNow(entryId), SAVE_DEBOUNCE_MS);
            },

            saveNow(entryId) {
                const override = this._pendingOverrides[entryId];

                clearTimeout(this._saveTimers[entryId]);
                delete this._saveTimers[entryId];
                delete this._pendingOverrides[entryId];

                if (!override) { return; }

                try {
                    wire.saveOverride(Number(entryId), override);
                } catch (e) {
                    // Reached from destroy() as well, where the component may
                    // already be half gone; a redraw is not worth a broken teardown.
                    console.error('[booklet] could not save an override', e);
                }
            },

            /** Nothing half-turned may be lost to a page leaving. */
            flushOverrides() {
                Object.keys(this._pendingOverrides).forEach((entryId) => this.saveNow(entryId));
            },

            /**
             * What one knob would read if it had never been touched here.
             *
             * The score's own value, or the booklet's, whichever owns that key —
             * the same stack settingsOf() resolves, with this one key's override
             * lifted out of it.
             */
            inheritedSetting(entry, key) {
                const override = { ...(entry.override ?? {}) };
                delete override[key];

                if (entry.kind === 'file') { return fileSettings(override)[key]; }

                if (entry.kind !== 'score') { return undefined; }

                return resolveSettings(
                    entry.format,
                    formatDefaults(entry.format),
                    entry.settings ?? {},
                    pageGeometry(this.geometry),
                    override,
                )[key];
            },

            /**
             * Whether this knob is showing something other than what it inherited.
             *
             * Not "was it touched": a knob stepped away and back is a knob nobody
             * has changed, and marking it as changed sends the cantor looking for
             * a difference that is not on the page. See movesSetting().
             */
            isOverridden(entryId, key) {
                const entry = this.entries.find((candidate) => candidate.id === entryId);
                const override = entry?.override ?? {};

                if (!entry || !Object.prototype.hasOwnProperty.call(override, key)) { return false; }

                return movesSetting(override[key], this.inheritedSetting(entry, key));
            },

            /**
             * Whether anything at all on this score has been moved.
             *
             * Asked here rather than of the server for the same reason each knob is:
             * a row keeps itself now, and it hears nothing of a save the booklet
             * made on its behalf — so a score marked as adjusted only once its row
             * happened to be drawn again would be a score marked hours late.
             */
            hasOverride(entryId) {
                const entry = this.entries.find((candidate) => candidate.id === entryId);

                return !!entry && Object.keys(entry.override ?? {})
                    .some((key) => this.isOverridden(entryId, key));
            },

            async exportPdf() {
                if (this.exporting || this.pages.length === 0) { return; }

                this.exporting = true;
                this.message = '';

                try {
                    const svgs = await serializeBookletPages(this.pages, this._fonts ?? []);

                    const response = await fetch(this.exportUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken,
                            Accept: 'application/pdf',
                        },
                        body: JSON.stringify({ pages: svgs }),
                    });

                    if (!response.ok) {
                        throw new Error(`export failed with ${response.status}`);
                    }

                    const blob = await response.blob();
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = (wire.get('title') || 'fuzet') + '.cantores.hu.pdf';
                    link.click();
                    URL.revokeObjectURL(url);
                } catch (e) {
                    console.error('[booklet] export failed', e);
                    this.message = this.exportFailedText;
                } finally {
                    this.exporting = false;
                }
            },
        };
    });
});

function formatDefaults(format) {
    if (format === 'gabc') { return gabcMixin(); }
    if (format === 'abc') { return abcMixin(); }
    if (format === 'chordpro') { return chordproMixin(); }
    if (format === 'aretino') { return aretinoMixin(); }

    return {};
}
