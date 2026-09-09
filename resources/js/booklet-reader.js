import { pageGeometry } from './booklet-geometry.js';
import { createBusyFlag, renderDelayFor } from './booklet-pacing.js';
import {
    clampZoom,
    readReaderSettings,
    readerGeometry,
    writeReaderSettings,
    ZOOM_DEFAULT,
    ZOOM_MAX,
    ZOOM_MIN,
    ZOOM_STEP,
} from './booklet-reading.js';
import { renderBookletFlow } from './booklet-render.js';
import { fileSettings, resolveSettings, travellingOverride } from './booklet-settings.js';
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

        zoom: 1,
        textFont: null,
        overrides: {},

        busy: false,
        ready: false,
        fullscreenId: null,

        _renderTimer: null,
        _resizeTimer: null,
        _renderToken: 0,
        _busy: null,
        _lastRenderMs: 0,
        _width: 0,
        _observer: null,
        _onFullscreen: null,

        init() {
            const saved = readReaderSettings(window.localStorage, this.token);

            this.zoom = saved.zoom;
            this.textFont = saved.textFont;
            this.overrides = saved.overrides;

            this._busy = createBusyFlag({ onChange: (busy) => { this.busy = busy; } });

            // Left by the escape key as often as by the button, and the button
            // has to know.
            this._onFullscreen = () => {
                if (!document.fullscreenElement) { this.fullscreenId = null; }
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
                textFont: this.textFont,
            });
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

            const geometry = pageGeometry(this.geometry());
            const format = entry.kind === 'file' ? 'file' : entry.format;
            const override = {
                ...travellingOverride(format, entry.override, geometry),
                ...(this.overrides[entryId] ?? {}),
            };

            if (entry.kind === 'file') { return fileSettings(override); }

            return resolveSettings(entry.format, formatDefaults(entry.format), entry.settings ?? {}, geometry, override);
        },

        isOverridden(entryId, key) {
            return Object.prototype.hasOwnProperty.call(this.overrides[entryId] ?? {}, key);
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

        setFont(family) {
            this.textFont = family || null;

            this.remember();
            this.scheduleRender();
        },

        /** Everything back to the booklet as its cantor set it. */
        resetAll() {
            this.zoom = ZOOM_DEFAULT;
            this.textFont = null;
            this.overrides = {};

            this.remember();
            this.scheduleRender();
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
         * One score, or the whole booklet, taking the whole screen.
         *
         * A phone on a music stand is mostly browser chrome, and the thing being
         * sung from is the part that matters. Asked of an element rather than of
         * the document so that a musician can put one score full screen and
         * scroll it, which is what a page turn during a piece actually looks
         * like.
         */
        toggleFullscreen(entryId = null) {
            const target = entryId === null
                ? this.$refs.reader
                : this.$refs.pages?.querySelector(`[data-reader-entry="${entryId}"]`);

            if (!target) { return; }

            // The same button pressed twice leaves; a different one moves — and
            // moving has to wait for the leaving, because a browser will refuse
            // a second full screen while it is still in the first.
            if (document.fullscreenElement) {
                const leaving = this.fullscreenId;

                Promise.resolve(document.exitFullscreen?.())
                    .catch(() => {})
                    .then(() => {
                        this.fullscreenId = null;

                        if (leaving !== entryId) { this.enterFullscreen(target, entryId); }
                    });

                return;
            }

            this.enterFullscreen(target, entryId);
        },

        enterFullscreen(target, entryId) {
            Promise.resolve(target.requestFullscreen?.())
                .then(() => { this.fullscreenId = entryId; })
                .catch(() => { this.fullscreenId = null; });
        },

        remember() {
            writeReaderSettings(window.localStorage, this.token, {
                zoom: this.zoom,
                textFont: this.textFont,
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
