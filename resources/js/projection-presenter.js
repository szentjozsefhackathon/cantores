import { onAlpineInit } from './alpine-init.js';
import { isExcluded, renderDeck } from './projection-deck.js';

/**
 * The deck on the wall.
 *
 * Everything here is arranged around one rule: nothing slow may happen after the
 * service has started. The whole deck is engraved once, on load, into an array
 * of finished SVGs, and moving between slides is then a single DOM swap. Both
 * engines that draw music block the browser while they work, and a stutter
 * between two verses of a hymn is the one failure a projection cannot recover
 * from.
 *
 * The controls fade out when the room goes quiet, and come back on any movement
 * of the mouse. A projector showing a toolbar is showing the wrong thing.
 */

/** How long the bar stays up after the last sign of life. */
const IDLE_MS = 2500;

onAlpineInit(() => {
    Alpine.data('projectionPresenter', (config = {}) => ({
        geometry: config.geometry ?? {},
        entries: config.entries ?? [],

        /**
         * The slides this service walks past, keyed by row.
         *
         * Every slide is still engraved: the deck is drawn once and in full, and
         * the skipping is a filter over the result. Cutting them out before
         * engraving would save a moment of load time and cost the one thing that
         * matters here — that what the editor showed and what the wall shows are
         * the same slides, made the same way.
         */
        excluded: config.excluded ?? {},

        slides: [],
        index: 0,
        total: 0,

        busy: true,
        blanked: false,
        idle: false,

        _idleTimer: null,

        get aspectRatio() {
            return this.geometry.aspectRatio ?? '16/9';
        },

        init() {
            this.draw();
            this.wake();
        },

        destroy() {
            clearTimeout(this._idleTimer);
        },

        async draw() {
            this.busy = true;

            try {
                const drawn = await renderDeck(this.entries, this.geometry);

                this.slides = drawn.filter((slide) => !isExcluded(slide, this.excluded));
                this.total = this.slides.length;
                this.index = Math.min(this.index, Math.max(0, this.total - 1));
                this.show();
            } catch (e) {
                console.error('[projection] could not draw the deck', e);
            } finally {
                this.busy = false;
            }
        },

        /**
         * A correction made in the editor, fetched without leaving the
         * projector. The slide being shown is kept if it still exists, so
         * refreshing mid-service does not send the room back to the beginning.
         */
        applyUpdate(detail = {}) {
            if (detail.payload) { this.entries = detail.payload; }
            if (detail.geometry) { this.geometry = detail.geometry; }
            if (detail.excluded !== undefined) { this.excluded = detail.excluded ?? {}; }

            this.draw();
        },

        show() {
            const box = this.$refs.stageBox;
            if (!box) { return; }

            const slide = this.slides[this.index];

            box.replaceChildren(slide ? slide.svg.cloneNode(true) : document.createComment('empty'));
        },

        go(index) {
            if (this.total === 0) { return; }

            this.index = Math.min(Math.max(index, 0), this.total - 1);
            this.show();
        },

        next() {
            // A blanked screen takes the next key as "come back" rather than as
            // "move on": the alternative is a cantor pressing space to return and
            // silently losing a slide.
            if (this.blanked) { this.blanked = false; return; }

            this.go(this.index + 1);
        },

        previous() {
            if (this.blanked) { this.blanked = false; return; }

            this.go(this.index - 1);
        },

        onKey(event) {
            this.wake();

            const keys = {
                ArrowRight: () => this.next(),
                ArrowDown: () => this.next(),
                PageDown: () => this.next(),
                ' ': () => this.next(),
                Enter: () => this.next(),
                ArrowLeft: () => this.previous(),
                ArrowUp: () => this.previous(),
                PageUp: () => this.previous(),
                Backspace: () => this.previous(),
                Home: () => this.go(0),
                End: () => this.go(this.total - 1),
                b: () => { this.blanked = !this.blanked; },
                B: () => { this.blanked = !this.blanked; },
                f: () => this.toggleFullscreen(),
                F: () => this.toggleFullscreen(),
            };

            const handler = keys[event.key];
            if (!handler) { return; }

            event.preventDefault();
            handler();
        },

        /**
         * The bar is up while anything is happening and down once nothing has
         * for a moment.
         */
        wake() {
            this.idle = false;
            clearTimeout(this._idleTimer);
            this._idleTimer = setTimeout(() => { this.idle = true; }, IDLE_MS);
        },

        toggleFullscreen() {
            if (document.fullscreenElement) {
                document.exitFullscreen?.().catch(() => {});

                return;
            }

            Promise.resolve(this.$refs.stage?.requestFullscreen?.()).catch(() => {});
        },
    }));
});
