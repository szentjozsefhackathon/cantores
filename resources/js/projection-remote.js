import { onAlpineInit } from './alpine-init.js';
import { isExcluded, renderDeck } from './projection-deck.js';
import { POLL_MS, addressAt, indexOfAddress, screenClient, shownExclusions, stateClient } from './projection-follow.js';

/**
 * The deck in the cantor's hand.
 *
 * The same payload the wall engraves, engraved again here, so that what the
 * phone shows is what the room is looking at rather than a description of it.
 * Around that it shows what no display program's remote can, because no display
 * program has it: the slot the row stands in, the music's title and variation,
 * and how many slides the row came to.
 *
 * It is optimistic. The phone moves its own view on the tap and reconciles when
 * the answer comes: a round trip is 20–50ms on a good connection and
 * occasionally far worse on a bad cell, and the wall may lag a beat, but a
 * remote must never feel like it is thinking.
 *
 * Engraving on a phone is the one performance risk in this design. If it proves
 * too slow, the fallback is to label the deck and engrave only the current slide
 * and its successor — the rest of this would not change.
 *
 * What it follows is the *screen*, not the deck on it. A screen showing nothing
 * is a state this page can say out loud rather than an empty page, a deck put up
 * from somewhere else arrives without a reload, and taking the deck off is a
 * sentence the phone can say — which is what ends a service when the person
 * ending it is at the organ and the laptop is at the back of the church.
 */
onAlpineInit(() => {
    Alpine.data('projectionRemote', (config = {}) => ({
        geometry: config.geometry ?? {},
        entries: config.entries ?? [],
        excluded: config.excluded ?? {},

        /** The verses brought back for this service alone, keyed by row. */
        reveals: {},

        /** Every slide as engraved, and the subset the room is being shown. */
        drawn: [],
        slides: [],

        index: 0,
        total: 0,
        blanked: false,

        busy: true,

        /**
         * What the screen is showing, and whether it has got there.
         *
         * `preparing` is the wall between two decks: it has been pointed at
         * something and has not finished engraving it. Worth saying on the phone,
         * because the room is looking at black and the cantor did not ask for
         * black.
         */
        presentationId: config.presentationId ?? null,
        title: config.title ?? '',
        preparing: false,

        /**
         * The deck the server has, and the deck the wall has finished engraving.
         *
         * Two numbers rather than one, because "the edit is saved" and "the room
         * can see it" are different answers and the phone is the only device in
         * a position to say both.
         */
        serverRevision: config.revision ?? '',
        wallRevision: config.revision ?? '',

        /** And the deck this phone itself has finished engraving. */
        ownRevision: config.revision ?? '',

        appliedVersion: 0,

        _client: null,
        _screen: null,
        _pollTimer: null,

        /** Whether the screen has anything on it at all. */
        get waiting() {
            return this.presentationId === null;
        },

        /**
         * Whether the wall is still catching up with the last edit.
         *
         * Not while it is preparing a different deck: that is a bigger thing and
         * has a sentence of its own, and saying both at once would tell the
         * cantor twice over that the room cannot see them yet.
         */
        get wallBehind() {
            return !this.preparing && !this.waiting && this.serverRevision !== this.wallRevision;
        },

        get aspectRatio() {
            return this.geometry.aspectRatio ?? '16/9';
        },

        init() {
            this._screen = screenClient(config);

            const listen = () => {
                this.pull();
                this._pollTimer = setInterval(() => this.pull(), POLL_MS);
            };

            if (this.presentationId !== null) {
                this._client = stateClient(config);
                this.draw().then(listen);
            } else {
                this.busy = false;
                listen();
            }
        },

        destroy() {
            clearInterval(this._pollTimer);
        },

        async draw() {
            this.busy = true;

            try {
                this.drawn = await renderDeck(this.entries, this.geometry);
                this.repaint(addressAt(this.slides, this.index));
            } catch (e) {
                console.error('[remote] could not draw the deck', e);
            } finally {
                this.busy = false;
            }
        },

        /** The engraved deck filtered again — what a revealed verse costs. */
        repaint(address) {
            const shown = shownExclusions(this.excluded, this.reveals);

            this.slides = this.drawn.filter((slide) => !isExcluded(slide, shown));
            this.total = this.slides.length;
            this.index = indexOfAddress(this.slides, this.entries, address);
            this.show();
        },

        /**
         * The slide the room is on and the one it is about to be on.
         *
         * Drawn into the DOM by hand rather than bound, because a slide is a
         * finished SVG element and not a string, and cloning it is cheaper than
         * serialising it twice a second.
         */
        show() {
            const draw = (box, slide) => {
                if (!box) { return; }

                box.replaceChildren(slide ? slide.svg.cloneNode(true) : document.createComment('empty'));
            };

            draw(this.$refs.currentBox, this.slides[this.index]);
            draw(this.$refs.nextBox, this.slides[this.index + 1]);
        },

        /**
         * The deck as the plan it came from — one entry per row, each with the
         * slides it came to.
         *
         * This is the half of the remote that no display program's has, because
         * no display program knows any of it: a slide on a screen is a picture,
         * and a row here is a slot in a liturgy, a piece of music, a variation of
         * it, and however many screens it happens to break into.
         */
        get rows() {
            const on = this.slides[this.index];

            return this.entries.map((entry) => ({
                id: entry.id,
                heading: this.headingOf(entry),
                reference: (entry.reference ?? '').trim(),
                slides: this.drawn
                    .filter((slide) => slide.entryId === entry.id)
                    .map((slide) => ({
                        index: slide.index,
                        skipped: this.isSkipped(entry.id, slide.index),
                        revealed: this.isRevealed(entry.id, slide.index),
                        current: Boolean(on) && on.entryId === entry.id && on.index === slide.index,
                    })),
            }));
        },

        /**
         * What a row is called in the cantor's hand.
         *
         * The same parts the slide itself carries a line of, and for a row of
         * words — which has none of them — the words themselves, cut short: on a
         * phone the list has to be read at a glance while something else is
         * being played.
         */
        headingOf(entry) {
            const line = [entry.slot, entry.music, entry.variation]
                .map((part) => (typeof part === 'string' ? part.trim() : ''))
                .filter((part) => part !== '')
                .join(' – ');

            if (line !== '') { return line; }

            const words = (entry.text ?? '').replace(/[#*_>\-]/g, ' ').replace(/\s+/g, ' ').trim();

            return words.length > 48 ? `${words.slice(0, 48)}…` : words;
        },

        /*
         * ---------------------------------------------------------------
         * Driving the service.
         * ---------------------------------------------------------------
         */

        go(index) {
            if (this.total === 0) { return; }

            this.index = Math.min(Math.max(index, 0), this.total - 1);
            this.show();
            this.push();
        },

        next() {
            this.go(this.index + 1);
        },

        previous() {
            this.go(this.index - 1);
        },

        toggleBlank() {
            this.blanked = !this.blanked;
            this.push();
        },

        /** Jump to a row — its first slide that this service is being shown. */
        goToEntry(entryId) {
            const at = this.slides.findIndex((slide) => slide.entryId === Number(entryId));

            if (at !== -1) { this.go(at); }
        },

        /** Jump to one particular slide of a row. */
        goToSlide(entryId, slideIndex) {
            const at = this.slides.findIndex(
                (slide) => slide.entryId === Number(entryId) && slide.index === Number(slideIndex),
            );

            if (at !== -1) { this.go(at); }
        },

        /*
         * ---------------------------------------------------------------
         * Today's deviation from the deck.
         * ---------------------------------------------------------------
         */

        /**
         * Whether a slide is one the deck leaves out and today has brought back.
         */
        isRevealed(entryId, slideIndex) {
            return (this.reveals[entryId] ?? this.reveals[String(entryId)] ?? []).includes(Number(slideIndex));
        },

        /** Whether the deck itself leaves this slide out. */
        isSkipped(entryId, slideIndex) {
            return isExcluded({ entryId: Number(entryId), index: Number(slideIndex) }, this.excluded);
        },

        /**
         * Bring a verse back for this service, or put it away again.
         *
         * A reveal costs nothing at render time — every slide was engraved at
         * load, the walked-past ones included — which is the feature that makes
         * this a remote rather than a clicker. And it is today's deviation, not
         * an edit: the projection is left as its author arranged it.
         */
        toggleReveal(entryId, slideIndex) {
            const id = Number(entryId);
            const index = Number(slideIndex);
            const key = this.reveals[id] !== undefined ? id : String(id);
            const current = this.reveals[key] ?? [];

            const next = current.includes(index)
                ? current.filter((one) => one !== index)
                : [...current, index].sort((a, b) => a - b);

            // JSON gives the key back as a string and a tap gives it as a
            // number, so both spellings are cleared before one is written.
            const reveals = { ...this.reveals };
            delete reveals[id];
            delete reveals[String(id)];

            if (next.length > 0) { reveals[id] = next; }

            this.reveals = reveals;

            const address = current.includes(index)
                ? addressAt(this.slides, this.index)
                : { entryId: id, slideIndex: index };

            this.repaint(address);
            this.push();
        },

        /*
         * ---------------------------------------------------------------
         * The wire.
         * ---------------------------------------------------------------
         */

        /** What this phone has just done, sent after it has already been done. */
        push() {
            // A screen with nothing on it has nowhere to put a tap.
            if (this._client === null) { return; }

            this._client
                .write({ ...addressAt(this.slides, this.index), blanked: this.blanked, reveals: this.reveals })
                .then((state) => {
                    if (state !== null) { this.appliedVersion = Math.max(this.appliedVersion, state.version); }
                })
                .catch(() => {});
        },

        /**
         * One read of the screen — what is on it, and where in it the service
         * has got to.
         *
         * Both in one answer, so the phone asks once a second and not twice. A
         * failed read is not an event: the remote keeps showing what it last
         * knew, and the next poll tries again.
         */
        async pull() {
            const answer = await this._screen.read();

            if (answer === null) { return; }

            this.title = answer.title ?? '';

            if ((answer.presentationId ?? null) !== this.presentationId) {
                await this.followDeck(answer);

                return;
            }

            const state = answer.state;

            if (state === null || state === undefined) { return; }

            this.serverRevision = state.revision ?? this.serverRevision;
            this.wallRevision = state.drawnRevision ?? '';
            this.ended = state.endedAt !== null && state.endedAt !== undefined;

            // The wall reports the deck it has actually finished engraving. On a
            // deck just put up it has none yet, which is exactly what "the screen
            // is preparing" means, and it is the same field that answers "has the
            // room seen my correction" one level down.
            this.preparing = this.wallRevision === '';

            if (state.version > this.appliedVersion) {
                this.appliedVersion = state.version;
                this.reveals = state.reveals ?? {};
                this.blanked = Boolean(state.blanked);
                this.repaint({ entryId: state.entryId, slideIndex: state.slideIndex });
            }

            if (this.serverRevision !== this.ownRevision) {
                this.refresh();
            }
        },

        /**
         * A different deck on the screen — put there from here, from the laptop,
         * or from another phone.
         *
         * The phone engraves it for itself, because what it shows is what the
         * room is looking at rather than a description of it. It is allowed to
         * take a moment over that: the wall is doing the same thing at the same
         * time, and until both have finished the room is looking at black and the
         * cantor is told so.
         */
        async followDeck(answer) {
            this.presentationId = answer.presentationId ?? null;
            this.appliedVersion = 0;
            this.reveals = {};
            this.blanked = false;
            this.ended = false;

            if (this.presentationId === null) {
                this._client = null;
                this.preparing = false;
                this.drawn = [];
                this.repaint({ entryId: null, slideIndex: 0 });

                return;
            }

            this._client = stateClient({ ...config, stateUrl: answer.stateUrl, payloadUrl: answer.payloadUrl });
            this.preparing = true;
            this.index = 0;
            this.ownRevision = '';
            this.serverRevision = '';
            this.wallRevision = '';

            await this.refresh();
        },

        /*
         * ---------------------------------------------------------------
         * What is on the screen at all.
         * ---------------------------------------------------------------
         */

        /**
         * Take the deck off the screen and end the service.
         *
         * Deliberately not what the back arrow does. Leaving the remote means
         * this phone is done driving and the wall keeps what it has; stopping
         * mid-service by accident is a far worse failure than a slide lingering
         * after everyone has gone home, so the two are different gestures and
         * only this one is worded as an ending.
         */
        async clearScreen() {
            const answer = await this._screen.point(null);

            if (answer !== null) { await this.followDeck(answer); }
        },

        /**
         * Read the deck again and engrave it again, without the list under the
         * cantor's thumb jumping about: the new deck is drawn in the background
         * and swapped in finished, and the slide being shown survives the swap.
         */
        async refresh() {
            if (this._refreshing) { return; }

            this._refreshing = true;

            try {
                const payload = await this._client?.payload();

                if (!payload) { return; }

                const drawn = await renderDeck(payload.entries ?? [], payload.geometry ?? {});

                this.entries = payload.entries ?? [];
                this.geometry = payload.geometry ?? {};
                this.excluded = payload.excluded ?? {};
                this.drawn = drawn;

                this.repaint(addressAt(this.slides, this.index));

                this.ownRevision = payload.revision ?? this.ownRevision;
            } catch (e) {
                console.error('[remote] could not re-draw the deck', e);
            } finally {
                this._refreshing = false;
            }
        },

        _refreshing: false,
    }));
});
