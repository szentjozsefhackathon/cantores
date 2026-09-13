import { onAlpineInit } from './alpine-init.js';
import { createBusyFlag, layoutSignature, renderDelayFor } from './booklet-pacing.js';
import { SPLIT_DEFAULT, beginSplitDrag, clampSplitPercent } from './booklet-split.js';
import { renderDeck, slideCounts } from './projection-deck.js';
import { inheritedSlideSetting, resolveSlideSettings, fileSlideSettings } from './projection-settings.js';
import { steppedValue, movesSetting } from './booklet-settings.js';

/**
 * The projection editor's browser half.
 *
 * Built on the booklet editor's, and on purpose: the two are the same shape of
 * problem — a list on one side, a slow layout on the other, a server that must
 * be told about each nudge without being asked to draw anything. Everything in
 * booklet-pacing.js and booklet-split.js is used here unchanged, because none of
 * it ever knew anything about paper.
 *
 * Three of its hard-won habits are copied verbatim, each because of a real bug:
 * `wire` is a closure rather than state; the payload arrives in a data attribute
 * rather than in x-data; and a render is tokened so a slow one cannot overwrite
 * a newer one. See booklet-editor.js for what each of them cost to learn.
 */

const SAVE_DEBOUNCE_MS = 600;

onAlpineInit(() => {
    Alpine.data('projectionEditor', (config = {}) => {
        // Taken once in init() rather than read per call. `$wire` resolves to
        // whichever element a method was called from, and a row is its own
        // Livewire component — so saving an override from a row's panel would
        // send it to the row. Alpine also runs toRaw() over anything assigned
        // into state, and Livewire's proxy answers any unknown property with a
        // server-method stub, which is then kept as the "raw" object.
        let wire = null;

        return {
            geometry: config.geometry ?? {},
            entries: withPlainOverrides(config.entries),
            overflowText: config.overflowText ?? '',

            slides: [],
            slideCount: 0,
            counts: {},
            hoveredEntryId: null,

            splitPercent: SPLIT_DEFAULT,
            splitDragging: false,

            busy: false,
            message: '',

            _renderTimer: null,
            _renderToken: 0,
            _lastRenderMs: 0,
            _drawnSignature: null,
            _busy: null,
            _saveTimers: {},
            _pendingOverrides: {},

            init() {
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
             * A fresh picture of the deck, pushed from the server.
             *
             * Usually it is the deck already on screen — the browser made the
             * change itself and drew it a moment ago — and drawing it again
             * would freeze the browser to arrive at the same slides. The
             * signature is what tells a deck that has moved on from one that has
             * not.
             */
            applyUpdate(detail = {}) {
                if (detail.payload) { this.entries = withPlainOverrides(detail.payload); }
                if (detail.geometry) { this.geometry = detail.geometry; }

                // A payload that left before the knob currently turning was
                // saved carries the older value, so anything still pending is
                // laid back on top.
                Object.entries(this._pendingOverrides).forEach(([entryId, override]) => {
                    const entry = this.entries.find((row) => String(row.id) === entryId);
                    if (entry) { entry.override = override; }
                });

                if (layoutSignature(this.entries, this.geometry) === this._drawnSignature) {
                    this._busy?.settle();

                    return;
                }

                this.scheduleRender();
            },

            scheduleRender() {
                this.markBusy();
                clearTimeout(this._renderTimer);
                this._renderTimer = setTimeout(() => this.render(), renderDelayFor(this._lastRenderMs));
            },

            async render() {
                const token = ++this._renderToken;
                const startedAt = performance.now();
                const signature = layoutSignature(this.entries, this.geometry);

                try {
                    const slides = await renderDeck(this.entries, this.geometry);

                    if (token !== this._renderToken) { return; }

                    this._drawnSignature = signature;
                    this.slides = slides;
                    this.slideCount = slides.length;
                    this.counts = slideCounts(slides);
                    this.paint();
                } catch (e) {
                    console.error('[projection] render failed', e);
                } finally {
                    if (token === this._renderToken) {
                        this._lastRenderMs = performance.now() - startedAt;
                        this._busy?.settle();
                    }
                }
            },

            /**
             * Every slide, in order, each in a box the shape of the screen —
             * which is what makes a slide that does not fit obvious here rather
             * than during the service.
             */
            paint() {
                const host = this.$refs.slides;
                if (!host) { return; }

                host.replaceChildren();

                this.slides.forEach((slide, index) => {
                    const figure = document.createElement('figure');
                    figure.className = 'projection-slide';
                    figure.dataset.projectionEntry = slide.entryId;

                    const box = document.createElement('div');
                    box.className = 'projection-slide-box';
                    box.style.aspectRatio = this.geometry.aspectRatio ?? '16/9';
                    box.appendChild(slide.svg.cloneNode(true));
                    figure.appendChild(box);

                    const caption = document.createElement('figcaption');
                    caption.className = 'projection-slide-number';
                    caption.textContent = String(index + 1);
                    if (slide.overflows) {
                        caption.classList.add('projection-slide-overflows');
                        caption.title = this.overflowText ?? '';
                    }
                    figure.appendChild(caption);

                    host.appendChild(figure);
                });

                this.highlight();
            },

            /** How many screens one row came to — what the row's badge reads. */
            slidesOf(entryId) {
                return this.counts[entryId] ?? 0;
            },

            hoverEntry(entryId) {
                this.hoveredEntryId = entryId;
                this.highlight();
            },

            highlight() {
                const host = this.$refs.slides;
                if (!host) { return; }

                host.querySelectorAll('[data-projection-entry]').forEach((figure) => {
                    const active = this.hoveredEntryId !== null
                        && figure.dataset.projectionEntry === String(this.hoveredEntryId);
                    figure.classList.toggle('projection-slide-hovered', active);
                });
            },

            markBusy(event = null) {
                if (event?.target?.closest?.('[data-projection-quiet]')) { return; }
                this._busy?.start();
            },

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

            nudgeSplit(delta) { this.splitPercent = clampSplitPercent(this.splitPercent + delta); },

            resetSplit() { this.splitPercent = SPLIT_DEFAULT; },

            /**
             * The settings one row is actually drawn with — recomputed on every
             * read rather than snapshotted when its panel opened, because the
             * deck's ratio can move underneath an open panel.
             */
            settingsOf(entryId) {
                const entry = this.entries.find((row) => row.id === entryId);
                if (!entry) { return {}; }

                if (entry.kind === 'file') { return fileSlideSettings(entry.override); }
                if (entry.kind === 'text') { return {}; }

                return resolveSlideSettings(entry.format, entry.settings ?? {}, this.geometry.ratio, entry.override);
            },

            /**
             * Whether a knob is showing something other than what the score's own
             * author chose — "is it different", not "was it touched", so a knob
             * stepped away and back is not marked.
             */
            isOverridden(entryId, key) {
                const entry = this.entries.find((row) => row.id === entryId);
                if (!entry || entry.kind === 'text') { return false; }

                const override = entry.override ?? {};
                if (!Object.prototype.hasOwnProperty.call(override, key)) { return false; }

                const inherited = entry.kind === 'file'
                    ? fileSlideSettings({})[key]
                    : inheritedSlideSetting(entry.format, entry.settings ?? {}, this.geometry.ratio, override, key);

                return movesSetting(override[key], inherited);
            },

            hasOverride(entryId) {
                const entry = this.entries.find((row) => row.id === entryId);
                if (!entry) { return false; }

                return Object.keys(entry.override ?? {}).some((key) => this.isOverridden(entryId, key));
            },

            atLimit(entryId, field, direction) {
                const current = Number(this.settingsOf(entryId)[field.key]);
                const next = steppedValue(current, field, direction);

                return !Number.isFinite(current) ? false : next === current;
            },

            nudgeOverride(entryId, field, direction) {
                this.setOverride(entryId, field.key, steppedValue(this.settingsOf(entryId)[field.key], field, direction));
            },

            setOverride(entryId, key, value) {
                const entry = this.entries.find((row) => row.id === entryId);
                if (!entry) { return; }

                // Built plainly and then assigned, so what goes to the server is
                // an ordinary object rather than the reactive proxy the row holds.
                const override = { ...(entry.override ?? {}), [key]: value };
                entry.override = override;

                this.scheduleRender();
                this.scheduleSave(entryId, override);
            },

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
                    console.error('[projection] could not save an override', e);
                }
            },

            flushOverrides() {
                Object.keys(this._pendingOverrides).forEach((id) => this.saveNow(id));
            },

            resetOverride(entryId) {
                const entry = this.entries.find((row) => row.id === entryId);
                if (entry) { entry.override = {}; }

                clearTimeout(this._saveTimers[entryId]);
                delete this._saveTimers[entryId];
                delete this._pendingOverrides[entryId];

                this.scheduleRender();

                try {
                    wire.resetOverride(Number(entryId));
                } catch (e) {
                    console.error('[projection] could not reset an override', e);
                }
            },
        };
    });
});

/**
 * Each row's override copied into an ordinary object.
 *
 * The payload arrives parsed from an attribute, so its nested objects are shared
 * with nothing — but they are about to become reactive state, and an override is
 * read back out of state and sent to the server. Copying here keeps that a plain
 * object throughout.
 */
function withPlainOverrides(entries) {
    return (entries ?? []).map((entry) => (
        entry?.override === undefined ? entry : { ...entry, override: { ...entry.override } }
    ));
}
