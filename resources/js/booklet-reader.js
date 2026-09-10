import { pageGeometry } from './booklet-geometry.js';
import { createBusyFlag, renderDelayFor } from './booklet-pacing.js';
import {
    clampZoom,
    readReaderSettings,
    readerGeometry,
    styleForFont,
    writeReaderSettings,
    ZOOM_DEFAULT,
    ZOOM_MAX,
    ZOOM_MIN,
    ZOOM_STEP,
} from './booklet-reading.js';
import { renderBookletFlow } from './booklet-render.js';
import { fileSettings, movesSetting, readerStep, resolveSettings, steppedValue, travellingOverride } from './booklet-settings.js';
import { abcMixin } from './score-editor-abc.js';
import { aretinoMixin } from './score-editor-aretino.js';
import { chordproMixin } from './score-editor-chordpro.js';
import { gabcMixin } from './score-editor-gabc.js';

/**
 * The shared booklet, in the hands of the people singing from it.
 *
 * The same engraving as the editor's preview, from the same payload, through the
 * same renderers — and that is the point of drawing it here rather than sending
 * a PDF. A picture of a page is one size for everybody; this is the booklet
 * itself, laid out for the width of the screen it landed on, at whatever size
 * the person holding it can actually read.
 *
 * Two things it deliberately does not do. It does not write: what a reader
 * changes is theirs, kept on their own device, and the cantor's handout is
 * untouched by a dozen people adjusting it. And it does not download: this is a
 * booklet lent for singing from, not a file handed over.
 */

/** How long a screen has to stop changing size before it is laid out again. */
const RESIZE_SETTLE_MS = 250;

document.addEventListener('alpine:init', () => {
    Alpine.data('bookletReader', (config = {}) => ({
        token: config.token ?? '',
        entries: config.entries ?? [],
        booklet: config.geometry ?? {},

        styles: config.styles ?? {},

        zoom: 1,
        style: null,
        overrides: {},

        busy: false,
        ready: false,
        isFullscreen: false,

        _renderTimer: null,
        _resizeTimer: null,
        _renderToken: 0,
        _busy: null,
        _lastRenderMs: 0,
        _width: 0,
        _observer: null,
        _onFullscreen: null,

        init() {
            const saved = readReaderSettings(window.localStorage, this.token, this.styles);

            this.zoom = saved.zoom;
            this.style = saved.style;
            this.overrides = saved.overrides;

            this._busy = createBusyFlag({ onChange: (busy) => { this.busy = busy; } });

            // Left by the escape key as often as by the button, and the button
            // has to know.
            this._onFullscreen = () => {
                this.isFullscreen = !!document.fullscreenElement;
            };
            document.addEventListener('fullscreenchange', this._onFullscreen);

            // Everything that needs an element waits a tick: Alpine runs this
            // before it has walked the children, so there are no refs yet.
            this.$nextTick(() => {
                // The width is the one input nobody types: it comes from the
                // device, and it changes when the phone is turned. Watched
                // rather than asked for once, because a booklet laid out in
                // portrait and then read in landscape is a booklet half the
                // width of its screen.
                this._observer = new ResizeObserver(() => this.widthChanged());
                this._observer.observe(this.$refs.pages);

                this._width = this.measuredWidth();
                this.scheduleRender();
            });
        },

        destroy() {
            clearTimeout(this._renderTimer);
            clearTimeout(this._resizeTimer);
            this._observer?.disconnect();
            this._busy?.stop();
            document.removeEventListener('fullscreenchange', this._onFullscreen);
        },

        /**
         * The booklet was read again from the server — the leader changed
         * something and somebody pressed refresh.
         *
         * Only the booklet is replaced. What this reader set is theirs and
         * survives, which is what makes the button safe to press mid-rehearsal.
         */
        applyUpdate(detail = {}) {
            if (detail.payload) { this.entries = detail.payload; }
            if (detail.geometry) { this.booklet = detail.geometry; }

            this.scheduleRender();
        },

        measuredWidth() {
            return Math.round(this.$refs.pages?.clientWidth ?? 0);
        },

        /**
         * A resize that did not change the width changes nothing. Phones fire
         * these by the dozen as toolbars slide in and out, and every one of them
         * would otherwise re-engrave the whole booklet.
         */
        widthChanged() {
            clearTimeout(this._resizeTimer);

            this._resizeTimer = setTimeout(() => {
                const width = this.measuredWidth();

                if (width === this._width || width === 0) { return; }

                this._width = width;
                this.scheduleRender();
            }, RESIZE_SETTLE_MS);
        },

        /** The geometry this screen is drawing at, at this reader's size. */
        geometry() {
            return readerGeometry(this.booklet, this._width || this.measuredWidth(), {
                zoom: this.zoom,
                style: this.style,
            }, this.styles);
        },

        scheduleRender() {
            this._busy?.start();

            clearTimeout(this._renderTimer);
            this._renderTimer = setTimeout(() => this.render(), renderDelayFor(this._lastRenderMs));
        },

        async render() {
            const token = ++this._renderToken;
            const startedAt = performance.now();

            try {
                const { items } = await renderBookletFlow(this.readerEntries(), this.geometry(), this.$refs.measure);

                if (token !== this._renderToken) { return; }

                this.paint(items);
                this.ready = true;
            } catch (e) {
                console.error('[booklet] could not draw the booklet', e);
            } finally {
                if (token === this._renderToken) {
                    this._lastRenderMs = performance.now() - startedAt;
                    this._busy?.settle();
                }
            }
        },

        /**
         * The payload as this reader is entitled to change it.
         *
         * The cantor's per-score nudges arrive on every entry, and only some of
         * them survive the trip to a screen — travellingOverride draws that line.
         * What this reader has set since goes on top, which is the same layering
         * the editor uses, one layer deeper.
         */
        readerEntries() {
            const geometry = pageGeometry(this.geometry());

            return this.entries.map((entry) => {
                if (entry.kind === 'text') { return entry; }

                const format = entry.kind === 'file' ? 'file' : entry.format;

                return {
                    ...entry,
                    override: {
                        ...travellingOverride(format, entry.override, geometry),
                        ...(this.overrides[entry.id] ?? {}),
                    },
                };
            });
        },

        /**
         * Put the drawings where their toolbars already are.
         *
         * Each entry has a slot of its own in the markup, rendered by Livewire
         * with its controls, and the engraving is dropped into it — rather than
         * the whole list being rebuilt — so that a score whose settings someone
         * is turning does not lose the panel they are turning them in.
         */
        paint(items) {
            const container = this.$refs.pages;

            if (!container) { return; }

            items.forEach(({ id, svg }) => {
                const slot = container.querySelector(`[data-reader-sheet="${id}"]`);

                if (!slot) { return; }

                const drawing = svg.cloneNode(true);
                drawing.removeAttribute('width');
                drawing.removeAttribute('height');
                drawing.style.width = '100%';
                drawing.style.height = 'auto';
                drawing.style.display = 'block';

                slot.replaceChildren(drawing);
            });
        },

        /**
         * What one score is being drawn at, for its own toolbar to show.
         */
        settingsOf(entryId) {
            const entry = this.entries.find((candidate) => candidate.id === entryId);

            if (!entry || entry.kind === 'text') { return {}; }

            return this.resolvedFor(entry, this.overrides[entryId] ?? {});
        },

        /**
         * One score resolved with a given set of this reader's own nudges, so the
         * same stack can be asked what it would show without one of them.
         *
         * @param {object} own the reader's overrides to lay on top of the booklet's
         */
        resolvedFor(entry, own) {
            const geometry = pageGeometry(this.geometry());
            const format = entry.kind === 'file' ? 'file' : entry.format;
            const override = {
                ...travellingOverride(format, entry.override, geometry),
                ...own,
            };

            if (entry.kind === 'file') { return fileSettings(override); }

            return resolveSettings(entry.format, formatDefaults(entry.format), entry.settings ?? {}, geometry, override);
        },

        /**
         * Whether this knob is showing something other than the booklet's own value.
         *
         * A transposition nudged up and back down leaves a 0 behind, and a 0 is
         * where the booklet already was — so the mark asks what the score is drawn
         * at, not what this reader happens to have pressed. See movesSetting().
         */
        isOverridden(entryId, key) {
            const entry = this.entries.find((candidate) => candidate.id === entryId);
            const own = this.overrides[entryId] ?? {};

            if (!entry || entry.kind === 'text' || ! Object.prototype.hasOwnProperty.call(own, key)) { return false; }

            const inherited = { ...own };
            delete inherited[key];

            return movesSetting(own[key], this.resolvedFor(entry, inherited)[key]);
        },

        /**
         * One knob on one score, a step at a time.
         *
         * It steps from whatever the score is actually being drawn at — which
         * may be the booklet's value, the score author's, or this reader's own
         * from a minute ago — so a press means "a bit more than this", never "a
         * bit more than some default nobody is looking at".
         *
         * @param {object} field one entry of BookletSettingFields::readerPanelFor
         * @param {number} direction -1 or 1
         */
        nudgeOverride(entryId, field, direction) {
            const knob = { ...field, step: readerStep(field) };

            this.setOverride(entryId, field.key, steppedValue(this.settingsOf(entryId)[field.key], knob, direction));
        },

        /** A knob at the end of its travel, so the button can say so. */
        atLimit(entryId, field, direction) {
            const value = Number(this.settingsOf(entryId)[field.key]);

            if (!Number.isFinite(value)) { return false; }

            return direction < 0 ? value <= Number(field.min) : value >= Number(field.max);
        },

        /** A transposition the way a musician says it: three up, not three. */
        signed(value) {
            const semitones = Number(value) || 0;

            return semitones > 0 ? `+${semitones}` : String(semitones);
        },

        setOverride(entryId, key, value) {
            this.overrides = {
                ...this.overrides,
                [entryId]: { ...(this.overrides[entryId] ?? {}), [key]: value },
            };

            this.remember();
            this.scheduleRender();
        },

        resetOverride(entryId) {
            const overrides = { ...this.overrides };
            delete overrides[entryId];
            this.overrides = overrides;

            this.remember();
            this.scheduleRender();
        },

        setZoom(value) {
            this.zoom = clampZoom(value);

            this.remember();
            this.scheduleRender();
        },

        nudgeZoom(delta) {
            this.setZoom(this.zoom + delta);
        },

        /**
         * The whole typography at once, never a face on its own.
         *
         * A face and the gaps that face needs are one decision — see
         * App\Support\BookletStyles — so a reader picking Graduále gets EB
         * Garamond's lyrics over EB Garamond's gaps, rather than a new face
         * over the last one's spacing.
         */
        setStyle(style) {
            this.style = this.styles[style] ? style : null;

            this.remember();
            this.scheduleRender();
        },

        /** Everything back to the booklet as its cantor set it. */
        resetAll() {
            this.zoom = ZOOM_DEFAULT;
            this.style = null;
            this.overrides = {};

            this.remember();
            this.scheduleRender();
        },

        /**
         * The style the cantor set the booklet in, so the select shows where a
         * reader who has chosen nothing actually is. Read off the face, exactly
         * as the server reads it: see BookletStyles::forFont().
         */
        get bookletStyle() {
            return styleForFont(this.styles, this.booklet.textFont) ?? '';
        },

        get zoomPercent() {
            return Math.round(this.zoom * 100);
        },

        get canZoomIn() {
            return this.zoom < ZOOM_MAX;
        },

        get canZoomOut() {
            return this.zoom > ZOOM_MIN;
        },

        get zoomStep() {
            return ZOOM_STEP;
        },

        /**
         * The booklet taking the whole screen.
         *
         * A phone on a music stand is mostly chrome — the browser's bars, the
         * site's own menu — and none of it is being sung from. So the reader
         * itself goes full screen, toolbar and all, and keeps scrolling: a page
         * turn during a piece is a scroll, and one that cannot reach the second
         * half of a score is the one thing a music stand cannot forgive.
         *
         * One button for the whole page, rather than one per score. A musician
         * mid-piece is not choosing which score to expand; they have already
         * expanded the booklet and are scrolling through it.
         */
        toggleFullscreen() {
            if (document.fullscreenElement) {
                Promise.resolve(document.exitFullscreen?.()).catch(() => {});

                return;
            }

            // The state itself is left to fullscreenchange, which fires however
            // the screen was left — the button, or the escape key.
            Promise.resolve(this.$refs.reader?.requestFullscreen?.()).catch(() => {});
        },

        remember() {
            writeReaderSettings(window.localStorage, this.token, {
                zoom: this.zoom,
                style: this.style,
                overrides: this.overrides,
            });
        },
    }));
});

function formatDefaults(format) {
    if (format === 'gabc') { return gabcMixin(); }
    if (format === 'abc') { return abcMixin(); }
    if (format === 'chordpro') { return chordproMixin(); }
    if (format === 'aretino') { return aretinoMixin(); }

    return {};
}
