import { onAlpineInit } from './alpine-init.js';
import { revealEntryRow, revealOffset } from './booklet-hover.js';
import { createBusyFlag, layoutSignature, renderDelayFor } from './booklet-pacing.js';
import { SPLIT_DEFAULT, beginSplitDrag, clampSplitPercent } from './booklet-split.js';
import { RESTORE_ICON, SKIP_ICON, isExcluded, renderDeck, slideCounts } from './projection-deck.js';
import { FROM_STYLE, divergingKeys, inheritedSlideSetting, resolveSlideSettings, fileSlideSettings, textSlideSettings } from './projection-settings.js';
import { ratioSuffix } from './score-editor-pages.js';
import { steppedValue, movesSetting } from './booklet-settings.js';
import './projection-style-editor.js';
import './score-preview.js';

/**
 * Panes mid-scroll, keyed by the pane itself, so a second click landing before
 * the first has settled cancels it rather than fighting it. The browser's own
 * `behavior: 'smooth'` has no such guard — two calls in a row visibly race —
 * and its duration is too short to read as a glide over the short hops this is
 * mostly used for.
 */
const scrollAnimations = new WeakMap();

function easeOutCubic(t) {
    return 1 - (1 - t) ** 3;
}

/** Ease a pane's scroll position to `top`, over `duration` milliseconds. */
function scrollPaneTo(pane, top, duration = 320) {
    cancelAnimationFrame(scrollAnimations.get(pane));

    const start = pane.scrollTop;
    const change = top - start;

    if (change === 0) { return; }

    const startTime = performance.now();

    const step = (now) => {
        const progress = Math.min(1, (now - startTime) / duration);
        pane.scrollTop = start + change * easeOutCubic(progress);

        if (progress < 1) {
            scrollAnimations.set(pane, requestAnimationFrame(step));
        } else {
            scrollAnimations.delete(pane);
        }
    };

    scrollAnimations.set(pane, requestAnimationFrame(step));
}

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

/**
 * How long a knob waits before its row is drawn again. Only that row is: the
 * rest come back out of the deck's slide cache, so the booklet's pacing — sized
 * for a change that engraves everything — would be the slowest part of a nudge.
 */
const KNOB_RENDER_DELAY_MS = 16;

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

            /** The knobs a style holds, per format — what a score can disagree with it on. */
            styleKeys: config.styleKeys ?? {},

            /**
             * The page the preview modal draws one score against, since a deck
             * has none — see Booklet::previewGeometry().
             */
            previewGeometryBase: config.previewGeometry ?? {},

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
            autoSplitText: config.autoSplitText ?? '',
            autoSplitLabel: config.autoSplitLabel ?? '',
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

            /**
             * Overrides sent and not yet answered, keyed by row, each with the
             * number of the save that carries it. Until the answer lands, any
             * payload the server pushes may predate it — another row's save,
             * a skipped slide — and would put the knob back where it was.
             */
            _sendingOverrides: {},
            _saveSequence: 0,

            /**
             * Saves go out one after another, never side by side: two in flight
             * can be answered out of order, and the older answer then has the
             * last word on the screen.
             */
            _saveQueue: Promise.resolve(),

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
                // saved carries the older value, so anything not yet confirmed
                // — still waiting to be sent, or sent and not yet answered — is
                // laid back on top, the newest last.
                const unconfirmed = {};
                Object.entries(this._sendingOverrides).forEach(([entryId, { override }]) => { unconfirmed[entryId] = override; });
                Object.assign(unconfirmed, this._pendingOverrides);

                Object.entries(unconfirmed).forEach(([entryId, override]) => {
                    const entry = this.entries.find((row) => String(row.id) === entryId);
                    if (entry) { entry.override = { ...override }; }
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

            /**
             * @param {number|null} delay a wait of the caller's own; left out,
             *   the pacing decides from what the last render cost
             */
            scheduleRender(delay = null) {
                this.markBusy();
                clearTimeout(this._renderTimer);
                this._renderTimer = setTimeout(() => this.render(), delay ?? renderDelayFor(this._lastRenderMs));
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
                    if (slide.autoSplit && !skipped) {
                        caption.appendChild(this.autoSplitNote());
                    }
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

            /**
             * Said beside a slide the deck cut off on its own, because the score
             * ran past the bottom of the one before it.
             *
             * Nothing is wrong with it — that is the point of the cut — but the
             * packer only knows where the systems end, not where the phrase
             * does, and a `%pagebreak169` in the score is how the author says so.
             */
            autoSplitNote() {
                const note = document.createElement('span');

                note.className = 'projection-slide-auto-split';
                note.textContent = this.autoSplitLabel;
                note.title = this.autoSplitText.replace(':marker', `%pagebreak${ratioSuffix(this.geometry.ratio) ?? ''}`);

                return note;
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

            /**
             * Pointing at a row or at a slide only lights its partner up on the
             * other side; neither pane moves under the pointer. A pane that
             * scrolled on hover pulled the list away from whoever was merely
             * moving the mouse across it. Going there is a click — see
             * revealEntry() and revealSlideRow().
             */
            hoverEntry(entryId) {
                this.hoveredEntryId = entryId;
                this.highlight();
            },

            /** The gaps between slides keep whatever was last pointed at. */
            hoverPreview(target) {
                let entryId = null;

                if (target !== null) {
                    const figure = target.closest?.('[data-projection-entry]');
                    if (!figure) { return; }
                    entryId = Number(figure.dataset.projectionEntry);
                }

                if (this.hoveredEntryId === entryId) { return; }

                this.hoveredEntryId = entryId;
                this.highlight();
            },

            /** A row clicked in the plan brings its slides into view. */
            revealEntry(entryId) {
                this.hoveredEntryId = entryId;
                this.highlight(true);
            },

            /** A slide clicked on the contact sheet brings its row into view in the plan. */
            revealSlideRow(target) {
                const figure = target?.closest?.('[data-projection-entry]');
                if (!figure) { return; }

                const entryId = Number(figure.dataset.projectionEntry);
                this.hoveredEntryId = entryId;
                this.highlight();
                revealEntryRow(this.$root.querySelector('[data-projection-pane="plan"]'), entryId, scrollPaneTo);
            },

            /**
             * Paint the hovered slide, and — only when a click asked for it
             * rather than this being a hover or a redraw's housekeeping call —
             * bring it into view. The plan panel can be scrolled far from
             * where the contact sheet has settled, and a highlight nobody can
             * see is not one that helps anybody find the slide they are
             * looking at.
             */
            highlight(scroll = false) {
                const host = this.$refs.slides;
                if (!host) { return; }

                let first = null;

                host.querySelectorAll('[data-projection-entry]').forEach((figure) => {
                    const active = this.hoveredEntryId !== null
                        && figure.dataset.projectionEntry === String(this.hoveredEntryId);
                    figure.classList.toggle('projection-slide-hovered', active);
                    if (active && !first) { first = figure; }
                });

                if (!scroll || !first) { return; }

                // The scrollbar is on the pane wrapping this element, not on
                // this element itself — `host` is sized to its content and
                // never overflows, so scrolling it does nothing.
                const pane = host.closest('[data-projection-pane="slides"]');
                const top = revealOffset(pane, first);
                if (top !== null) { scrollPaneTo(pane, top); }
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
             * One row handed to the preview modal beside it — see
             * score-preview.js.
             *
             * The deck's own overrides are left behind, and that is not a
             * simplification. They are written against a slide — a ratio, a
             * screen's worth of type, a lyric size chosen for a room at the
             * back — and the preview is a page of music. So the score is drawn
             * with its author's settings and none of the deck's, which is also
             * what makes this the same preview a booklet row opens.
             */
            previewEntry(entryId) {
                const entry = this.entries.find((row) => row.id === entryId);

                return entry ? { ...entry, override: {} } : null;
            },

            /** The default page, since a deck has no paper of its own. */
            previewGeometry() {
                return this.previewGeometryBase;
            },

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

                return resolveSlideSettings(entry.format, entry.settings ?? {}, this.geometry.ratio, entry.override, this.geometry.style ?? null);
            },

            /**
             * Whether a knob is showing something other than what it would show
             * without this deck's hand on it — "is it different", not "was it
             * touched", so a knob stepped away and back is not marked.
             */
            isOverridden(entryId, key) {
                const entry = this.entries.find((row) => row.id === entryId);
                if (!entry || key === FROM_STYLE) { return false; }

                const override = entry.override ?? {};
                if (!Object.prototype.hasOwnProperty.call(override, key)) { return false; }

                const without = { ...override };
                delete without[key];

                const inherited = entry.kind === 'file'
                    ? fileSlideSettings({})[key]
                    : entry.kind === 'text'
                        ? textSlideSettings(without, this.geometry)[key]
                        : inheritedSlideSetting(entry.format, entry.settings ?? {}, this.geometry.ratio, override, key, this.geometry.style ?? null);

                return movesSetting(override[key], inherited);
            },

            hasOverride(entryId) {
                const entry = this.entries.find((row) => row.id === entryId);
                if (!entry) { return false; }

                return Object.keys(entry.override ?? {}).some((key) => this.isOverridden(entryId, key));
            },

            /** Whether a row follows the deck's style rather than its score's own layout. */
            followsStyle(entryId) {
                return !!this.entries.find((row) => row.id === entryId)?.override?.[FROM_STYLE];
            },

            /**
             * The knobs where the score's own layout for this shape differs from
             * the deck's style — whatever the row is currently following, so the
             * row can say what switching would change.
             */
            scoreDiverging(entryId) {
                const entry = this.entries.find((row) => row.id === entryId);
                const keys = this.styleKeys[entry?.format];

                if (!entry || entry.kind !== 'score' || !keys) { return []; }

                return divergingKeys(entry.format, entry.settings ?? {}, this.geometry.ratio, this.geometry.style ?? null, keys, movesSetting);
            },

            /**
             * Whether the row has a choice of layout to offer at all: only a
             * score whose own layout for this shape differs from the style.
             * One without draws the same slide either way, so following the
             * style is a switch that does nothing — and offering it, as the
             * row once did for every score "follow the style everywhere" had
             * touched, showed a badge that vanished the moment it was clicked.
             */
            offersLayoutChoice(entryId) {
                return this.scoreDiverging(entryId).length > 0;
            },

            /** Whether a knob's value is the score's own, where it differs from the style. */
            fromScore(entryId, key) {
                return !this.followsStyle(entryId)
                    && !this.isOverridden(entryId, key)
                    && this.scoreDiverging(entryId).includes(key);
            },

            /** Have one row follow the deck's style, or go back to its score's layout. */
            followStyle(entryId, follow) {
                const entry = this.entries.find((row) => row.id === entryId);
                if (!entry) { return; }

                const { [FROM_STYLE]: _, ...changed } = entry.override ?? {};
                const override = follow ? { ...changed, [FROM_STYLE]: true } : changed;
                entry.override = override;

                this._pendingOverrides[entryId] = override;
                this.saveNow(entryId);
                this.scheduleRender(KNOB_RENDER_DELAY_MS);
            },

            /** Make how this row is drawn the deck's style for its format. */
            saveToStyle(entryId) {
                this.flushOverrides();

                const settings = { ...this.settingsOf(entryId) };
                this._saveQueue = this._saveQueue
                    .then(() => wire.saveSlideToStyle(Number(entryId), settings))
                    .catch((e) => console.error('[projection] could not save to the style', e));
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

                this.scheduleRender(KNOB_RENDER_DELAY_MS);
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

                const sequence = ++this._saveSequence;
                this._sendingOverrides[entryId] = { sequence, override };

                this._saveQueue = this._saveQueue
                    .then(() => wire.saveOverride(Number(entryId), override))
                    .catch((e) => console.error('[projection] could not save an override', e))
                    .finally(() => {
                        // A newer save of the same row may already be queued
                        // behind this one; its value is still unconfirmed.
                        if (this._sendingOverrides[entryId]?.sequence === sequence) {
                            delete this._sendingOverrides[entryId];
                        }
                    });
            },

            flushOverrides() {
                Object.keys(this._pendingOverrides).forEach((id) => this.saveNow(id));
            },

            /**
             * An empty override, sent at once — through the same queue as every
             * other save, so a nudge still in flight cannot land after it.
             */
            resetOverride(entryId) {
                const entry = this.entries.find((row) => row.id === entryId);
                // Whether the row follows the style is not a change made to it
                // but a choice of what to start from, and outlives an undo.
                const kept = entry?.override?.[FROM_STYLE] ? { [FROM_STYLE]: true } : {};
                if (entry) { entry.override = kept; }

                this._pendingOverrides[entryId] = { ...kept };
                this.saveNow(entryId);

                this.scheduleRender(KNOB_RENDER_DELAY_MS);
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
