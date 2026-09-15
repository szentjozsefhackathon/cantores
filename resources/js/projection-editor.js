import { onAlpineInit } from './alpine-init.js';
import { createBusyFlag, layoutSignature, renderDelayFor } from './booklet-pacing.js';
import { SPLIT_DEFAULT, beginSplitDrag, clampSplitPercent } from './booklet-split.js';
import { RESTORE_ICON, SKIP_ICON, isExcluded, renderDeck, slideCounts } from './projection-deck.js';
import { inheritedSlideSetting, resolveSlideSettings, fileSlideSettings, textSlideSettings } from './projection-settings.js';
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

            /**
             * Which slides the service walks past, keyed by row.
             *
             * State of its own rather than a field on a row, and that is what
             * makes leaving a verse out cost nothing: the deck is re-engraved
             * whenever what is *drawn* has changed, and skipping a slide changes
             * only what is shown. Folded into a row it would freeze the browser
             * re-cutting thirty scores per click.
             */
            excluded: plainExclusions(config.excluded),

            overflowText: config.overflowText ?? '',
            skipText: config.skipText ?? '',
            unskipText: config.unskipText ?? '',
            skippedText: config.skippedText ?? '',

            slides: [],
            slideCount: 0,

            /**
             * How many slides the room will actually be shown — the same number
             * the presenter counts to, which is what the sheet's numbering and
             * the Present button both have to agree with.
             */
            shownCount: 0,

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
                if (detail.excluded !== undefined) { this.excluded = plainExclusions(detail.excluded); }

                // A payload that left before the knob currently turning was
                // saved carries the older value, so anything still pending is
                // laid back on top.
                Object.entries(this._pendingOverrides).forEach(([entryId, override]) => {
                    const entry = this.entries.find((row) => String(row.id) === entryId);
                    if (entry) { entry.override = override; }
                });

                if (layoutSignature(this.entries, this.geometry) === this._drawnSignature) {
                    // Nothing to engrave again — but which slides are shown may
                    // still have moved, and that is settled by painting rather
                    // than by drawing.
                    this.paint();
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

                // The number a slide carries is its number in the room, so the
                // ones being walked past do not take one — the contact sheet and
                // the projector count the same way, or the sheet is no use for
                // finding a place in the service.
                let shown = 0;

                this.slides.forEach((slide) => {
                    const skipped = this.isSkipped(slide);
                    const figure = document.createElement('figure');
                    figure.className = skipped ? 'projection-slide projection-slide-skipped' : 'projection-slide';
                    figure.dataset.projectionEntry = slide.entryId;

                    const box = document.createElement('div');
                    box.className = 'projection-slide-box';
                    box.style.aspectRatio = this.geometry.aspectRatio ?? '16/9';
                    box.appendChild(slide.svg.cloneNode(true));
                    figure.appendChild(box);

                    if (!skipped) { shown += 1; }

                    const caption = document.createElement('figcaption');
                    caption.className = 'projection-slide-number';

                    const number = document.createElement('span');
                    number.textContent = skipped ? this.skippedText : String(shown);
                    if (slide.overflows && !skipped) {
                        number.classList.add('projection-slide-overflows');
                        number.title = this.overflowText ?? '';
                    }
                    caption.appendChild(number);
                    caption.appendChild(this.skipButton(slide, skipped));

                    figure.appendChild(caption);

                    host.appendChild(figure);
                });

                this.shownCount = shown;

                this.highlight();
            },

            /**
             * The one control the contact sheet carries: leave this slide out of
             * the service, or put it back.
             *
             * Built by hand rather than written into the page, because the sheet
             * itself is: the slides are engraved in the browser and there is no
             * markup for them until they exist. It sits beside the number instead
             * of over the picture — what is seen here is what the room will see,
             * and nothing may cover it.
             */
            skipButton(slide, skipped) {
                const button = document.createElement('button');

                button.type = 'button';
                button.className = 'projection-slide-skip';
                button.title = skipped ? this.unskipText : this.skipText;
                button.setAttribute('aria-label', button.title);
                button.setAttribute('aria-pressed', skipped ? 'true' : 'false');
                button.innerHTML = skipped ? RESTORE_ICON : SKIP_ICON;
                button.addEventListener('click', () => this.toggleSkip(slide));

                return button;
            },

            isSkipped(slide) {
                return isExcluded(slide, this.excluded);
            },

            /**
             * Leave a slide out, or put it back — on screen at once, and told to
             * the server afterwards.
             *
             * Nothing is engraved again: the slide already exists and stays where
             * it is. What comes back from the server is the same answer this just
             * made, so applyUpdate finds the deck unchanged and paints.
             */
            toggleSkip(slide) {
                const list = this.excluded[slide.entryId] ?? [];

                this.excluded = {
                    ...this.excluded,
                    [slide.entryId]: list.includes(slide.index)
                        ? list.filter((index) => index !== slide.index)
                        : [...list, slide.index].sort((a, b) => a - b),
                };

                this.paint();

                try {
                    wire.toggleSlideExclusion(Number(slide.entryId), Number(slide.index));
                } catch (e) {
                    console.error('[projection] could not save a skipped slide', e);
                }
            },

            /** How many screens one row came to — what the row's badge reads. */
            slidesOf(entryId) {
                return this.counts[entryId] ?? 0;
            },

            /** ...and how many of them this service walks past. */
            skippedOf(entryId) {
                return (this.excluded[entryId] ?? []).filter((index) => index < this.slidesOf(entryId)).length;
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
                // A screen of words has no engine behind it: the deck's own text
                // size and leading are the whole of what there is to resolve.
                if (entry.kind === 'text') { return textSlideSettings(entry.override, this.geometry); }

                return resolveSlideSettings(entry.format, entry.settings ?? {}, this.geometry.ratio, entry.override);
            },

            /**
             * Whether a knob is showing something other than what the score's own
             * author chose — "is it different", not "was it touched", so a knob
             * stepped away and back is not marked.
             */
            isOverridden(entryId, key) {
                const entry = this.entries.find((row) => row.id === entryId);
                if (!entry) { return false; }

                const override = entry.override ?? {};
                if (!Object.prototype.hasOwnProperty.call(override, key)) { return false; }

                const without = { ...override };
                delete without[key];

                const inherited = entry.kind === 'file'
                    ? fileSlideSettings({})[key]
                    : entry.kind === 'text'
                        ? textSlideSettings(without, this.geometry)[key]
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
 * The exclusion map as ordinary arrays of ordinary numbers.
 *
 * It arrives keyed by row id, which JSON writes as a string and PHP may have
 * meant as an integer; it is read back against slide positions and sent to the
 * server. Normalised once here so neither end has to be careful.
 *
 * @param {object} excluded
 */
function plainExclusions(excluded) {
    const map = {};

    Object.entries(excluded ?? {}).forEach(([entryId, list]) => {
        if (Array.isArray(list) && list.length > 0) {
            map[Number(entryId)] = list.map(Number).filter(Number.isFinite);
        }
    });

    return map;
}

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
