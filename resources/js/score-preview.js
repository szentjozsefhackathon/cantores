import { onAlpineInit } from './alpine-init.js';
import { createBusyFlag, renderDelayFor } from './booklet-pacing.js';
import {
    ZOOM_DEFAULT,
    ZOOM_MAX,
    ZOOM_MIN,
    ZOOM_STEP,
    clampZoom,
    readerGeometry,
} from './booklet-reading.js';
import { renderBookletFlow } from './booklet-render.js';
import { measuringHost } from './measuring-room.js';

/**
 * One score, looked at on its own, at the size of whatever is holding it.
 *
 * The same engraving the people singing from the booklet get — readerGeometry
 * for the shape, renderBookletFlow for the drawing, exactly as booklet-reader.js
 * uses them — asked for one row instead of a whole handout. That is the whole
 * reason it is built this way rather than by opening the score's own page in a
 * frame: the score editor draws onto a sheet of paper, and a sheet of paper
 * dropped into a modal is a picture of A4 with the notes too small to read.
 * The reader's geometry has no paper in it. It takes the width it is given and
 * sets the music to it, which is what someone checking a row actually wants.
 *
 * Nothing here writes. What the row prints is decided by the row's own knobs,
 * next to it; this only looks.
 */

/** How long the modal has to stop changing size before it is drawn again. */
const RESIZE_SETTLE_MS = 200;

onAlpineInit(() => {
    Alpine.data('scorePreview', (entry = null, geometry = {}) => ({
        entry,

        /**
         * The typography the score is set in — the booklet's own where there is
         * a booklet, and the default page where there is not.
         *
         * Only its proportions survive: readerGeometry scales every millimetre
         * in it to the width this modal actually has, so what is inherited is
         * the relation between staff, lyric and heading rather than any size.
         */
        base: geometry ?? {},

        zoom: ZOOM_DEFAULT,
        busy: false,
        ready: false,
        failed: false,

        _width: 0,
        _renderTimer: null,
        _resizeTimer: null,
        _renderToken: 0,
        _busy: null,
        _lastRenderMs: 0,
        _observer: null,

        init() {
            this._busy = createBusyFlag({ onChange: (busy) => { this.busy = busy; } });

            // A modal is not its full width until it has been laid out, and a
            // score engraved against a width of zero is a score engraved twice.
            this.$nextTick(() => {
                if (!this.draws) {
                    this.ready = true;

                    return;
                }

                this._observer = new ResizeObserver(() => this.widthChanged());
                this._observer.observe(this.$refs.sheet);

                this._width = this.measuredWidth();

                // A modal is a <dialog>, and a dialog that has not been opened
                // yet has no box at all. Engraving against that width would draw
                // the score once at its floor and again the moment the modal
                // appeared; the observer above is what actually starts it.
                if (this._width > 0) { this.scheduleRender(); }
            });
        },

        destroy() {
            clearTimeout(this._renderTimer);
            clearTimeout(this._resizeTimer);
            this._observer?.disconnect();
            this._busy?.stop();
        },

        /**
         * Whether there is anything here to engrave.
         *
         * A deck's uploaded score travels as whole scanned pages rather than as
         * the systems cut out of them — a projection shows a scan a page at a
         * time — and those are shown as the pictures they are.
         */
        get draws() {
            return this.entry !== null && this.pages.length === 0;
        },

        get pages() {
            return this.entry?.pages ?? [];
        },

        measuredWidth() {
            return Math.round(this.$refs.sheet?.clientWidth ?? 0);
        },

        /**
         * Only a width that actually moved is worth redrawing for. A modal
         * opening animates its way to its size, and every frame of that would
         * otherwise engrave the score again.
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

        geometry() {
            return readerGeometry(this.base, this._width || this.measuredWidth(), { zoom: this.zoom });
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
                const { items } = await renderBookletFlow([this.entry], this.geometry(), measuringHost());

                if (token !== this._renderToken) { return; }

                this.paint(items[0]?.svg ?? null);
                this.failed = false;
                this.ready = true;
            } catch (e) {
                console.error('[score preview] could not draw the score', e);

                if (token === this._renderToken) {
                    this.failed = true;
                    this.ready = true;
                }
            } finally {
                if (token === this._renderToken) {
                    this._lastRenderMs = performance.now() - startedAt;
                    this._busy?.settle();
                }
            }
        },

        /**
         * The drawing takes the width it was given and finds its own height, so
         * a long score scrolls rather than being squeezed.
         */
        paint(svg) {
            const sheet = this.$refs.sheet;

            if (!sheet) { return; }

            if (svg === null) {
                sheet.replaceChildren();

                return;
            }

            const drawing = svg.cloneNode(true);
            drawing.removeAttribute('width');
            drawing.removeAttribute('height');
            drawing.style.width = '100%';
            drawing.style.height = 'auto';
            drawing.style.display = 'block';

            sheet.replaceChildren(drawing);
        },

        setZoom(value) {
            this.zoom = clampZoom(value);
            this.scheduleRender();
        },

        nudgeZoom(delta) {
            this.setZoom(this.zoom + delta);
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
    }));
});
