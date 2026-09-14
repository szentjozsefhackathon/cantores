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
 * The page is three bands that never move: the slide the room is reading across
 * the top half, a scrollable strip of what is coming under it, and the two
 * controls pressed a hundred times a service across the bottom quarter. Nothing
 * about a service is worth a thumb hunting for the Next button, so nothing here
 * scrolls except the strip and the plan behind the swipe.
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


/**
 * How much of what is coming the strip under the slide holds.
 *
 * A window rather than the whole deck, and the one place this page spends
 * thought on performance: a thumbnail is a second clone of an engraved slide,
 * and a Sunday deck is sixty of them. Eighteen live at a time and the window is
 * re-cut only when the service walks out of it — about every twelve taps —
 * which keeps a tap's work constant however long the deck is. If even that
 * proves too slow on an old phone, the fallback is to drop the pictures and
 * leave the two arrows: nothing else here depends on the strip.
 */
const STRIP_AHEAD = 16;
const STRIP_BEHIND = 2;

/** How near the end of the window the service may get before it is re-cut. */
const STRIP_MARGIN = 4;

/** A swipe, as opposed to a tap that wandered or a scroll of the strip. */
const SWIPE_DISTANCE = 60;
const SWIPE_DRIFT = 50;

/**
 * How long a control refuses to be pressed again.
 *
 * The other hand is on the organ and the eyes are on the music, so a press is
 * made without looking and often made twice — the thumb bounces, or the cantor
 * cannot remember a second later whether the first one happened. Half a second
 * is longer than any of that and shorter than the gap between two verses, so a
 * fumble costs nothing and a deliberate second tap still lands.
 *
 * The blue stands for exactly as long as the lock, which is the other half of
 * the answer: a control that is still lit is a control that has just been
 * pressed, and pressing it again would do nothing anyway.
 */
const PRESS_LOCK_MS = 500;

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

        /** The plan, behind a swipe: read twice a service, in the way the rest of it. */
        listOpen: false,

        fullscreen: false,

        _stripFrom: 0,
        _stripTo: -1,
        _thumbs: [],
        _touch: null,
        _askedFullscreen: false,

        /** The control lit blue, and when each of them was last obeyed. */
        pressed: null,
        _pressedAt: {},
        _pressTimer: null,

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

            // The page is a fixed three-band panel, and a body that still
            // scrolls behind it is a body that bounces under the thumb.
            this._bodyOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            this.syncFullscreen();

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
            clearTimeout(this._pressTimer);
            document.body.style.overflow = this._bodyOverflow ?? '';
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
            this.buildStrip();
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
            this.syncStrip();
        },

        /*
         * ---------------------------------------------------------------
         * What is coming.
         * ---------------------------------------------------------------
         */

        /**
         * The strip of slides under the one the room is reading.
         *
         * Built by hand rather than bound, for the same reason the slide above
         * it is: a slide is a finished SVG and cloning it is cheaper than
         * asking Alpine to reason about it. What it is for is the jump a
         * clicker cannot make — three verses on at a glance, tapped, without
         * reading a list or counting numbers.
         */
        buildStrip() {
            const strip = this.$refs.strip;

            if (!strip) { return; }

            const from = Math.max(0, this.index - STRIP_BEHIND);
            const to = Math.min(this.slides.length - 1, this.index + STRIP_AHEAD);

            this._stripFrom = from;
            this._stripTo = to;
            this._thumbs = [];

            const thumbs = [];

            for (let at = from; at <= to; at += 1) {
                thumbs.push(this.thumbnail(at));
            }

            strip.replaceChildren(...thumbs);
            strip.scrollLeft = 0;
        },

        /** One slide of the strip: the picture, its number, and where it goes. */
        thumbnail(at) {
            const slide = this.slides[at];
            const button = document.createElement('button');

            button.type = 'button';
            button.className = 'relative h-full shrink-0 overflow-hidden rounded-md border-2 border-transparent bg-white';
            button.style.aspectRatio = this.aspectRatio;
            button.addEventListener('click', () => this.go(at));

            if (slide) { button.appendChild(slide.svg.cloneNode(true)); }

            const badge = document.createElement('span');

            badge.className = 'absolute bottom-0 right-0 rounded-tl bg-black/60 px-1 text-[10px] leading-4 text-white';
            badge.textContent = String(at + 1);
            button.appendChild(badge);

            this._thumbs.push({ at, button });

            return button;
        },

        /**
         * The strip after a move: the window re-cut if the service has walked
         * near its edge, and scrolled so that what is *next* stands at the left.
         */
        syncStrip() {
            const strip = this.$refs.strip;

            if (!strip) { return; }

            const past = this.index < this._stripFrom;
            const near = this.index + STRIP_MARGIN > this._stripTo && this._stripTo < this.slides.length - 1;

            if (past || near) { this.buildStrip(); }

            for (const { at, button } of this._thumbs) {
                button.classList.toggle('border-zinc-900', at === this.index);
                button.classList.toggle('dark:border-white', at === this.index);
                button.classList.toggle('border-transparent', at !== this.index);
                button.classList.toggle('opacity-50', at < this.index);
            }

            const found = this._thumbs.find((thumb) => thumb.at === this.index + 1)
                ?? this._thumbs.find((thumb) => thumb.at === this.index);

            if (!found) { return; }

            strip.scrollBy({
                left: found.button.getBoundingClientRect().left - strip.getBoundingClientRect().left - 8,
                behavior: 'smooth',
            });
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
                slot: (entry.slotName ?? entry.slot ?? '').trim(),
                reference: (entry.reference ?? '').trim(),
                current: Boolean(on) && on.entryId === entry.id,
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
         * The music's own name first, which the payload carries for this list
         * alone: the heading printed on the slide is silent wherever the deck's
         * author asked for silence, and a row nobody can name is exactly what
         * the person looking for the Communion hymn cannot use. Only where
         * there is no music — a screen of words — does it fall back to the
         * words themselves, cut short, because on a phone the list has to be
         * read at a glance while something else is being played.
         */
        headingOf(entry) {
            if (typeof entry.label === 'string' && entry.label.trim() !== '') {
                return entry.label.trim();
            }

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
            this.askFullscreen();

            if (this.total === 0) { return; }

            this.index = Math.min(Math.max(index, 0), this.total - 1);
            this.show();
            this.push();
        },

        /**
         * A press of one of the three controls under the thumb, obeyed once.
         *
         * A press within the lock of the last one is dropped where it is made
         * rather than sent — the wall never hears it, so there is nothing to
         * correct afterwards and nothing for the screen to flicker through. The
         * lock is per control: the press that undoes an accidental Next is
         * Previous, and it must never be the press that is swallowed.
         */
        press(name, run) {
            const now = Date.now();

            if (now - (this._pressedAt[name] ?? 0) < PRESS_LOCK_MS) { return; }

            this._pressedAt[name] = now;
            this.pressed = name;

            clearTimeout(this._pressTimer);
            this._pressTimer = setTimeout(() => { this.pressed = null; }, PRESS_LOCK_MS);

            run();
        },

        next() {
            this.press('next', () => this.go(this.index + 1));
        },

        previous() {
            this.press('previous', () => this.go(this.index - 1));
        },

        toggleBlank() {
            this.press('blank', () => {
                this.askFullscreen();
                this.blanked = !this.blanked;
                this.push();
            });
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
         * The phone itself.
         * ---------------------------------------------------------------
         */

        /** Whether this browser will give the page the whole screen at all. */
        get fullscreenAvailable() {
            return document.fullscreenEnabled === true;
        },

        syncFullscreen() {
            this.fullscreen = Boolean(document.fullscreenElement);
        },

        toggleFullscreen() {
            this._askedFullscreen = true;

            if (document.fullscreenElement) {
                document.exitFullscreen?.().catch(() => {});

                return;
            }

            Promise.resolve(this.$refs.stage?.requestFullscreen?.()).catch(() => {});
        },

        /**
         * The whole screen, taken at the first press rather than asked for.
         *
         * As automatic as a browser permits: full screen needs a gesture, so the
         * gesture is the first thing the cantor does anyway. Asked once — a
         * cantor who left full screen meant to leave it, and a page that drags
         * them back every tap is a page they would put down. Where the browser
         * refuses outright, iPhones included, the three bands still fill the
         * window and nothing else here depends on it.
         */
        askFullscreen() {
            if (this._askedFullscreen || !this.fullscreenAvailable || document.fullscreenElement) { return; }

            this._askedFullscreen = true;

            Promise.resolve(this.$refs.stage?.requestFullscreen?.()).catch(() => {});
        },

        openList() {
            this.listOpen = true;
        },

        closeList() {
            this.listOpen = false;
        },

        /**
         * A thumb dragged across the page: left for the plan, right to put it
         * away.
         *
         * Measured rather than bound to a library, and deliberately blind to
         * anything that began inside the strip or the plan itself — both scroll,
         * and a scroll that opened a drawer would make the strip unusable one
         * handed.
         */
        onTouchStart(event) {
            const touch = event.changedTouches?.[0];

            this._touch = touch
                ? { x: touch.clientX, y: touch.clientY, scroller: Boolean(event.target?.closest?.('[data-scrolls]')) }
                : null;
        },

        onTouchEnd(event) {
            const start = this._touch;
            const touch = event.changedTouches?.[0];

            this._touch = null;

            if (!start || !touch || start.scroller) { return; }

            const across = touch.clientX - start.x;

            if (Math.abs(touch.clientY - start.y) > SWIPE_DRIFT) { return; }

            if (across <= -SWIPE_DISTANCE) { this.openList(); }
            if (across >= SWIPE_DISTANCE) { this.closeList(); }
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
