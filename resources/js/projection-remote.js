import { onAlpineInit } from './alpine-init.js';
import { RESTORE_ICON, SKIP_ICON, isExcluded, renderDeck } from './projection-deck.js';
import { FIT_MOVE_STEP, FIT_NEUTRAL, FIT_ZOOM_STEP, addressAt, fitFrom, fitTransform, indexOfAddress, isTypingTarget, movedFit, poller, sameFit, screenClient, shownExclusions, stateClient, zoomedFit } from './projection-follow.js';

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
 * On a phone it is three bands that never move: the slide the room is reading
 * across the top half, a scrollable strip of what is coming under it, and the
 * two controls pressed a hundred times a service across the bottom quarter.
 * Nothing about a service is worth a thumb hunting for the Next button, so
 * nothing here scrolls except the strip and the plan behind the swipe.
 *
 * On a laptop it is three columns: the deck read as the service it came from
 * down the left, the service itself in the middle — what the room is reading,
 * the controls, and the next slide the same size beneath them — and every slide
 * of the deck down the right, each with the control that takes one out of today
 * or puts it back. The strip is the phone's alone up there; two scrollable
 * sheets of the same pictures are enough.
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

/**
 * The width at which the remote stops being a phone.
 *
 * Tailwind's `lg`, and the same breakpoint the markup switches on: below it the
 * page is three bands under one thumb, above it the two panes of a laptop
 * standing beside the projector — the whole deck down the left, the service down
 * the right. One number, read by both halves, so the pictures the browser builds
 * cannot disagree with the layout Tailwind put them in.
 */
const WIDE_QUERY = '(min-width: 1024px)';

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

/**
 * How long the phone trusts its own hand over the screen's answer, after it has
 * moved the picture.
 *
 * The nudge is optimistic like everything else here, and the poll that arrives a
 * moment later was answered before the write landed. Without a pause the arrow
 * the cantor pressed appears to bounce back, and lining a projector up by
 * pressing an arrow that undoes itself is not something anybody can do from
 * across a building.
 */
const FIT_SETTLE_MS = 2000;

/** The three pictures a service opens with. @see Presentation::SPLASH_ORDER */
const SPLASH_CARD = 'card';
const SPLASH_DARK = 'dark';
const SPLASH_OFF = 'off';

onAlpineInit(() => {
    Alpine.data('projectionRemote', (config = {}) => ({
        geometry: config.geometry ?? {},
        entries: config.entries ?? [],
        excluded: config.excluded ?? {},

        /** Where today disagrees with the deck, keyed by row. */
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

        /**
         * Where the picture lands on the wall, and the panel that moves it.
         *
         * A fact about the room rather than about the deck — one of the
         * churches throws a 4:3 beamer at a square screen hung high, so a deck
         * built 1:1 for it still lands above the heads it was meant for. It
         * belongs to the screen, so it is lined up once and is still true next
         * Sunday, and it is done from here because the person who can see the
         * wall is never the person at the laptop.
         */
        fit: fitFrom(config.fit),
        fitOpen: false,

        /**
         * Whether this is a laptop beside the projector rather than a phone.
         *
         * Watched rather than measured once: a window dragged onto the other
         * display crosses the breakpoint without reloading, and the two sheets
         * of pictures the browser builds by hand have to be cut the other way
         * when it does.
         */
        wide: false,

        /** Where the deck itself is edited, for the pane that offers it. */
        editUrl: config.editUrl ?? null,

        skipText: config.skipText ?? '',
        unskipText: config.unskipText ?? '',
        skippedText: config.skippedText ?? '',

        /**
         * What is asked before the screen is cleared.
         *
         * Carried in the configuration rather than written into the button,
         * because the button is a Blade component and Blade leaves `@js` inside
         * a component's attribute uncompiled — the browser is then handed an `@`
         * where it expects an expression, and nothing on the page below it is
         * ever wired up.
         */
        clearText: config.clearText ?? '',

        fullscreen: false,

        _stripFrom: 0,
        _stripTo: -1,
        _thumbs: [],
        _deckItems: [],
        /** Which slide the deck pane was last scrolled to follow. */
        _deckAt: null,
        _wide: null,
        _onWide: null,
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
         * Whether the service has been closed on the screen itself.
         *
         * Declared here and not merely written by the first poll: a field the
         * template reads has to exist before the first answer comes back, or
         * every expression drawn from it throws while the page is still
         * building — and an Alpine directive that throws takes the directives
         * queued behind it down with it, which on this page is every control
         * under the thumb.
         */
        ended: false,

        /**
         * How far into its opening the service is: `card`, then `dark`, then
         * `off`.
         *
         * Drawn here as well as on the wall, because the card is what the beamer
         * is lined up against and the person doing the lining up is holding this
         * — the picture under the thumb and the picture across the room have to
         * be the same one or the fit panel is guesswork.
         *
         * And named here, in the strip under the preview, because with two
         * states it was not clear how a cantor was ever meant to get *out* of
         * the card. Now each press walks the opening on one, and the phone says
         * which press that is.
         */
        splash: config.splash ?? SPLASH_OFF,

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
        _poll: null,
        _fitAt: 0,

        /** Whether the screen has anything on it at all. */
        /** Whether the title card is the picture on the wall — and so in the preview. */
        get showingSplash() {
            return this.splash === SPLASH_CARD && !this.waiting && !this.preparing;
        },

        /** Whether the wall is in the quiet dark between the card and the deck. */
        get openingDark() {
            return this.splash === SPLASH_DARK && !this.waiting;
        },

        /** Whether the opening still has a picture of its own to walk through. */
        get opening() {
            return this.splash !== SPLASH_OFF;
        },

        /**
         * The line under the preview that says what the room is looking at and
         * what the next press will do about it.
         */
        get openingHint() {
            if (this.showingSplash) { return config.cardHint ?? ''; }

            return this.openingDark ? config.darkHint ?? '' : '';
        },

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

        /**
         * The fit as CSS, for this phone's own preview.
         *
         * The preview is the whole of how this is used: the cantor is at the
         * organ looking at the wall, and the picture in the hand moves with it,
         * so a nudge is confirmed twice over — once across the room and once
         * under the thumb.
         */
        get fitTransform() {
            return fitTransform(this.fit);
        },

        /** The scale as the panel says it: a percentage, not a multiplier. */
        get fitPercent() {
            return Math.round(this.fit.scale * 100);
        },

        /** Whether the picture is where the application would have put it anyway. */
        get fitIsNeutral() {
            return sameFit(this.fit, FIT_NEUTRAL);
        },

        init() {
            this._screen = screenClient(config);

            // The page is a fixed three-band panel, and a body that still
            // scrolls behind it is a body that bounces under the thumb.
            this._bodyOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            this.syncFullscreen();
            this.watchWidth();

            const listen = () => {
                this._poll = poller(() => this.pull());
                this._poll.start();
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
            this._poll?.stop();
            clearTimeout(this._pressTimer);
            this._wide?.removeEventListener?.('change', this._onWide);
            document.body.style.overflow = this._bodyOverflow ?? '';
        },

        /**
         * Which of the two layouts this window is in, now and whenever it
         * changes.
         *
         * A change re-cuts what the browser builds by hand: the deck pane and
         * the next slide are either worth building or are not worth cloning
         * engraved slides into, and nothing below the breakpoint can see them.
         */
        watchWidth() {
            this._wide = window.matchMedia?.(WIDE_QUERY) ?? null;
            this.wide = this._wide?.matches === true;

            this._onWide = () => {
                this.wide = this._wide.matches;
                this.buildDeck();
                this.buildStrip();
                this.show();
            };

            this._wide?.addEventListener?.('change', this._onWide);
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
            this.buildDeck();
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
            // The next slide full size is the laptop's; the phone has the
            // strip, and a clone nobody can see is a clone not worth making.
            draw(this.$refs.nextBox, this.wide ? this.slides[this.index + 1] : null);
            this.syncStrip();
            this.highlightDeck();
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
            strip.scrollTop = 0;
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

            // What is *next* is brought to the near edge rather than merely
            // into view: a thumbnail half off the end is a thumbnail nobody taps.
            const thumb = found.button.getBoundingClientRect();
            const box = strip.getBoundingClientRect();

            strip.scrollBy({ left: thumb.left - box.left - 8, behavior: 'smooth' });
        },

        /*
         * ---------------------------------------------------------------
         * The deck itself, down the side of the laptop.
         * ---------------------------------------------------------------
         */

        /**
         * The whole deck as a contact sheet, every slide in it, the ones this
         * service walks past included.
         *
         * The laptop's half of the remote, and the one thing the phone has no
         * room for: a cantor at a keyboard beside the projector is reading ahead
         * rather than pressing Next, and what they want in front of them is the
         * deck as it was arranged — with a control on each slide to take one out
         * of today's service or put one back.
         *
         * Built by hand and only when the window is wide, for the reason the
         * strip is: a picture here is a second clone of an engraved slide, and a
         * Sunday deck is sixty of them. It is re-cut when what is *shown*
         * changes, which is a few times a service — never on a move, where only
         * the highlight travels.
         */
        buildDeck() {
            const host = this.$refs.deck;

            if (!host) { return; }

            this._deckItems = [];
            // The sheet is new, so wherever it was scrolled to is not where the
            // service is: the next highlight scrolls rather than deciding it
            // already has.
            this._deckAt = null;

            if (!this.wide) {
                host.replaceChildren();

                return;
            }

            // The number a slide carries is its number in the room, as in the
            // editor: the ones being walked past do not take one, or the sheet
            // and the projector would be counting differently.
            let shown = 0;

            const items = this.drawn.map((slide) => {
                const hidden = this.isHiddenToday(slide.entryId, slide.index);

                if (!hidden) { shown += 1; }

                return this.deckItem(slide, hidden, shown);
            });

            host.replaceChildren(...items);
            this.highlightDeck();
        },

        /** One slide of the sheet: the picture, its number, and the one control. */
        deckItem(slide, hidden, number) {
            const figure = document.createElement('figure');

            figure.className = hidden ? 'projection-slide projection-slide-skipped' : 'projection-slide';

            const box = document.createElement('button');

            box.type = 'button';
            box.className = 'projection-slide-box block w-full cursor-pointer p-0';
            box.style.aspectRatio = this.aspectRatio;
            box.appendChild(slide.svg.cloneNode(true));
            box.addEventListener('click', () => this.goToSlide(slide.entryId, slide.index));
            figure.appendChild(box);

            const caption = document.createElement('figcaption');

            caption.className = 'projection-slide-number';

            const label = document.createElement('span');

            label.textContent = hidden ? this.skippedText : String(number);
            caption.appendChild(label);
            caption.appendChild(this.deckToggle(slide, hidden));

            figure.appendChild(caption);

            this._deckItems.push({ entryId: slide.entryId, index: slide.index, figure });

            return figure;
        },

        /**
         * Take a slide out of today's service, or put one back.
         *
         * The editor's control, with the editor's icons, doing what the remote
         * may do rather than what the editor does: this is today's deviation and
         * not an edit, so the projection is left exactly as its author arranged
         * it and the deviation travels on the presentation, where the wall reads
         * it too. Whoever wants the deck itself changed has the button at the top
         * of this pane.
         */
        deckToggle(slide, hidden) {
            const button = document.createElement('button');

            button.type = 'button';
            button.className = 'projection-slide-skip';
            button.title = hidden ? this.unskipText : this.skipText;
            button.setAttribute('aria-label', button.title);
            button.setAttribute('aria-pressed', hidden ? 'true' : 'false');
            button.innerHTML = hidden ? RESTORE_ICON : SKIP_ICON;
            button.addEventListener('click', () => this.toggleReveal(slide.entryId, slide.index));

            return button;
        },

        /** The slide the room is on, marked in the sheet beside it. */
        highlightDeck() {
            const on = this.slides[this.index];
            let current = null;

            for (const item of this._deckItems) {
                const here = Boolean(on) && on.entryId === item.entryId && on.index === item.index;

                item.figure.classList.toggle('projection-slide-hovered', here);

                if (here) { current = item; }
            }

            this.scrollDeckTo(current, on);
        },

        /**
         * The sheet scrolled to keep up with the service.
         *
         * A sixty-slide deck is several screens of pictures, and the mark on the
         * slide the room is reading is worth nothing on the screenful nobody is
         * looking at — so the pane follows the service rather than waiting to be
         * scrolled. Brought into view only when it has left it, and centred when
         * it has: a jump of twenty slides lands in the middle of the sheet, with
         * what came before and what is coming either side of it, while an
         * ordinary Next moves nothing until the next slide reaches the edge.
         *
         * Only on a move, never on a redraw of the same slide: the poll answers
         * twice a second, and a pane that re-scrolled on every answer could not
         * be scrolled by hand at all.
         */
        scrollDeckTo(item, on) {
            const host = this.$refs.deck;

            if (!host || !item || !on) { return; }

            const at = `${on.entryId}:${on.index}`;

            if (at === this._deckAt) { return; }

            this._deckAt = at;

            const box = host.getBoundingClientRect();
            const slide = item.figure.getBoundingClientRect();

            if (slide.top >= box.top && slide.bottom <= box.bottom) { return; }

            host.scrollBy({
                top: slide.top - box.top - (box.height - slide.height) / 2,
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

            return this.entries.map((entry) => {
                const slides = this.drawn
                    .filter((slide) => slide.entryId === entry.id)
                    .map((slide) => ({
                        index: slide.index,
                        shown: !this.isHiddenToday(entry.id, slide.index),
                        deviates: this.isRevealed(entry.id, slide.index),
                        current: Boolean(on) && on.entryId === entry.id && on.index === slide.index,
                    }));

                return {
                    id: entry.id,
                    heading: this.headingOf(entry),
                    slot: (entry.slotName ?? entry.slot ?? '').trim(),
                    // What the row is filed under rather than what it prints:
                    // the outline groups by these two, and a deck whose author
                    // switched every heading off must still group.
                    music: (entry.label ?? '').trim(),
                    variation: (entry.variation ?? '').trim(),
                    reference: (entry.reference ?? '').trim(),
                    // And what the editor's own row says of it. A slot's music
                    // is often sung from one of several engravings of it, all
                    // sharing a title, so the score, the file chosen out of it
                    // and the variation are what actually tell two rows apart —
                    // and the opening notes tell them apart faster than any of
                    // the three. Read off the score rather than off the
                    // headings, so a deck that prints none of it is still legible
                    // to the person holding the remote.
                    isText: entry.kind === 'text',
                    words: this.firstLineOf(entry),
                    score: (entry.scoreName ?? '').trim(),
                    file: (entry.fileName ?? '').trim(),
                    variationName: (entry.variationName ?? '').trim(),
                    incipit: entry.incipitUrl ?? null,
                    current: Boolean(on) && on.entryId === entry.id,
                    slideCount: slides.length,
                    shownCount: slides.filter((slide) => slide.shown).length,
                    slides,
                };
            });
        },

        /**
         * The deck read as the service it was made from: slots, the music under
         * each, and what the deck took from that music.
         *
         * The laptop's left pane, and the editor's plan pane with everything
         * that edits it taken away — because the question it answers during a
         * service is not "what is on slide 41" but "is the Communion hymn in,
         * and which verses of it". Grouped by the row's own filing rather than
         * by what it prints: a deck whose author switched every heading off is
         * silent on the screen and must still be legible here.
         *
         * Consecutive rather than gathered: the deck's order *is* the service's
         * order, so a slot that comes round twice is two bands, which is what
         * the person reading it is looking at.
         */
        get outline() {
            const groups = [];

            for (const row of this.rows) {
                let slot = groups[groups.length - 1];

                if (slot === undefined || slot.name !== row.slot) {
                    slot = { key: `slot-${groups.length}`, name: row.slot, musics: [] };
                    groups.push(slot);
                }

                let music = slot.musics[slot.musics.length - 1];

                if (music === undefined || music.name !== row.music) {
                    music = { key: `${slot.key}-music-${slot.musics.length}`, name: row.music, rows: [] };
                    slot.musics.push(music);
                }

                music.rows.push(row);
            }

            return groups;
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

        /**
         * The one line that names a row of the plan.
         *
         * The editor's row, said in a pane a quarter of its width: a screen of
         * words is its opening words, and an engraving is the score it is, with
         * the file chosen out of it where the score holds several. The music's
         * own title is not repeated here — the band above the row already says
         * it — and the heading is the last resort, for a row whose score has
         * gone since the deck was made.
         */
        rowName(row) {
            if (row.isText) { return row.words || row.heading; }

            if (row.score === '') { return row.heading; }

            return row.file === '' ? row.score : `${row.score} · ${row.file}`;
        },

        /**
         * The opening words of a screen of words, as the editor's row shows them.
         *
         * A paragraph has no title, so the first line is its name — the rubric
         * that stands before the Gloria, the greeting before the first hymn — and
         * it is what the cantor scanning the plan is reading for. Cut to a line,
         * with the Markdown that sets it left out of the name.
         */
        firstLineOf(entry) {
            if (entry.kind !== 'text') { return ''; }

            const line = (entry.text ?? '')
                .split('\n')
                .map((part) => part.replace(/^[#>\-*\s]+/, '').replace(/[*_]/g, '').trim())
                .find((part) => part !== '') ?? '';

            return line.length > 40 ? `${line.slice(0, 40)}…` : line;
        },

        /*
         * ---------------------------------------------------------------
         * Driving the service.
         * ---------------------------------------------------------------
         */

        go(index) {
            this.askFullscreen();

            if (this.total === 0) { return; }

            // A slide asked for by name is the deck starting, whatever the
            // opening had left to show: somebody has reached past it.
            this.splash = SPLASH_OFF;
            this.index = Math.min(Math.max(index, 0), this.total - 1);
            this.show();
            this.push();
        },

        /**
         * One step of the opening: the card gives way to the dark, and the dark
         * to the deck.
         *
         * The deck is not moved by either step, which is what makes the press
         * that ends the dark land on the *first* slide and not the second.
         * Backwards out of the opening is forwards too — there is nothing behind
         * the beginning of a service to go back to.
         */
        walkOpening() {
            this.askFullscreen();
            this.splash = this.splash === SPLASH_CARD ? SPLASH_DARK : SPLASH_OFF;
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
            this.flash(name);

            run();
        },

        /** The control lit blue for as long as the lock it stands for lasts. */
        flash(name) {
            this.pressed = name;

            clearTimeout(this._pressTimer);
            this._pressTimer = setTimeout(() => { this.pressed = null; }, PRESS_LOCK_MS);
        },

        next() {
            this.press('next', () => (this.opening ? this.walkOpening() : this.go(this.index + 1)));
        },

        previous() {
            this.press('previous', () => (this.opening ? this.walkOpening() : this.go(this.index - 1)));
        },

        /**
         * Blank during the opening: over the card the same press as Next, since
         * black is what comes next anyway; over the dark, the handover — the
         * wall does not change, but the opening is over and the next press of
         * this button reveals the first slide.
         */
        toggleBlank() {
            this.press('blank', () => this.blankNow());
        },

        /**
         * The blank itself, wherever it was asked for.
         *
         * Over the card it is the same press as Next, because black is what
         * comes next anyway. Over the dark it is the handover: the wall does not
         * change, but the opening is over and the next press of this button
         * reveals the first slide, as it would anywhere else in the deck.
         */
        blankNow() {
            if (this.showingSplash) { return this.walkOpening(); }

            this.askFullscreen();

            if (this.openingDark) {
                this.splash = SPLASH_OFF;
                this.blanked = true;
            } else {
                this.blanked = !this.blanked;
            }

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
         * The same remote, driven from a keyboard.
         * ---------------------------------------------------------------
         */

        /**
         * A key pressed on the laptop driving the screen beside it.
         *
         * The desktop end of this page. A cantor at the organ has a thumb and
         * nothing else, but a cantor at a laptop already has the gesture every
         * display program taught them — space for the next slide — and the
         * three bands are useless to them if reaching a slide means aiming a
         * mouse at it. The same keys as the wall's own, so that whichever of
         * the two windows has the focus, the service moves the same way.
         *
         * Nothing here goes through the press lock. That lock is for a thumb
         * bouncing on glass without looking; a second press of a key is a
         * second press, and the wall itself has never locked one.
         */
        onKey(event) {
            // A browser shortcut is not a slide: nothing here is worth costing
            // somebody the tab they meant to switch to.
            if (event.ctrlKey || event.metaKey || event.altKey) { return; }

            // While the picture is being lined up, the arrows are aimed at the
            // picture. Two things a key could mean is one too many, and nobody
            // opens that panel in order to change slide.
            //
            // Asked before the typing question rather than after it, because
            // that one hands every key inside a dialog to the dialog — which is
            // right where a dialog holds a field and wrong here, where pressing
            // an arrow with the mouse would leave the arrows on the keyboard
            // doing nothing for the rest of the session.
            if (this.fitOpen) {
                this.onFitKey(event);

                return;
            }

            if (isTypingTarget(event.target)) { return; }

            const keys = {
                ArrowRight: () => this.moved('next', this.index + 1),
                ArrowDown: () => this.moved('next', this.index + 1),
                PageDown: () => this.moved('next', this.index + 1),
                ' ': () => this.moved('next', this.index + 1),
                Enter: () => this.moved('next', this.index + 1),
                ArrowLeft: () => this.moved('previous', this.index - 1),
                ArrowUp: () => this.moved('previous', this.index - 1),
                PageUp: () => this.moved('previous', this.index - 1),
                Backspace: () => this.moved('previous', this.index - 1),
                Home: () => this.moved('previous', 0),
                End: () => this.moved('next', this.total - 1),
                b: () => this.blank(),
                B: () => this.blank(),
                f: () => this.toggleFullscreen(),
                F: () => this.toggleFullscreen(),
                l: () => this.toggleList(),
                L: () => this.toggleList(),
                Escape: () => this.closeList(),
            };

            // Escape belongs to the browser wherever the plan is already shut —
            // it is how a full-screen window is left.
            if (event.key === 'Escape' && !this.listOpen) { return; }

            const handler = keys[event.key];

            if (!handler) { return; }

            event.preventDefault();
            handler();
        },

        /** A keyed move: obeyed at once, and lighting the control it stands for. */
        moved(name, index) {
            // A press during the opening walks the opening and moves nothing:
            // what the room has not been shown yet cannot be advanced past.
            if (this.opening) {
                this.flash(name);
                this.walkOpening();

                return;
            }

            this.flash(name);
            this.go(index);
        },

        /** The blank, without the thumb's lock in front of it. */
        blank() {
            this.flash('blank');
            this.blankNow();
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

            // Only where the remote is the whole device. On a laptop this page
            // is a window beside the one the room is reading — often literally
            // beside it, on the other display — and a window that swallows the
            // screen at the first press is a window that hides the deck it was
            // opened to drive. The button is still there for whoever wants it.
            if (window.matchMedia?.('(pointer: coarse)')?.matches !== true) { return; }

            this._askedFullscreen = true;

            Promise.resolve(this.$refs.stage?.requestFullscreen?.()).catch(() => {});
        },

        openList() {
            this.listOpen = true;
        },

        closeList() {
            this.listOpen = false;
        },

        toggleList() {
            this.listOpen = !this.listOpen;
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

            // The panel that lines the picture up owns the whole screen while
            // it is open: a thumb dragged across an arrow there is aiming at
            // the arrow, not asking for the plan.
            if (!start || !touch || start.scroller || this.fitOpen) { return; }

            const across = touch.clientX - start.x;

            if (Math.abs(touch.clientY - start.y) > SWIPE_DRIFT) { return; }

            if (across <= -SWIPE_DISTANCE) { this.openList(); }
            if (across >= SWIPE_DISTANCE) { this.closeList(); }
        },

        /*
         * ---------------------------------------------------------------
         * Where the picture lands on the wall.
         * ---------------------------------------------------------------
         *
         * The presenter fits the deck into the projector and centres it, which
         * is right in every room but the ones it is not: a square screen, a
         * beamer bolted where it is, a deck built for the glass and still
         * landing high and clipped. What is wrong there is not the deck — next
         * Sunday's lands identically — so it is the screen that is nudged, and
         * from here, because the laptop is across the building and the person
         * who can see the wall is holding this.
         */

        openFit() {
            this.fitOpen = true;
        },

        closeFit() {
            this.fitOpen = false;
        },

        /** One press of an arrow: -1, 0 or 1 in each direction. */
        moveFit(across, down) {
            this.putFit(movedFit(this.fit, across * FIT_MOVE_STEP, down * FIT_MOVE_STEP));
        },

        /** One press of a zoom, larger or smaller. */
        zoomFit(by) {
            this.putFit(zoomedFit(this.fit, by * FIT_ZOOM_STEP));
        },

        /** Back to centred and as large as fits — the fit nobody has to think about. */
        resetFit() {
            this.putFit({ ...FIT_NEUTRAL });
        },

        /**
         * A nudge: shown here at once and told to the screen afterwards.
         *
         * Optimistic like every other control on this page, and for a reason of
         * its own — lining a projector up is a dozen presses in a row, and a
         * panel that waited for each of them would be lined up by guesswork. A
         * failed write is not an event: the picture on the wall stays where it
         * was, and the next press says the whole fit again rather than a
         * difference, so nothing accumulates a press that never landed.
         */
        putFit(fit) {
            this.fit = fit;
            this._fitAt = Date.now();

            this._screen.adjust(fit).catch(() => {});
        },

        /**
         * The keyboard while the panel is open: the laptop's end of the same
         * four arrows.
         *
         * Nothing here is locked or flashed. The lock is for a thumb bouncing on
         * glass without looking, and this is a hand at a keyboard watching a wall
         * move.
         */
        onFitKey(event) {
            const keys = {
                ArrowLeft: () => this.moveFit(-1, 0),
                ArrowRight: () => this.moveFit(1, 0),
                ArrowUp: () => this.moveFit(0, -1),
                ArrowDown: () => this.moveFit(0, 1),
                '+': () => this.zoomFit(1),
                '=': () => this.zoomFit(1),
                '-': () => this.zoomFit(-1),
                _: () => this.zoomFit(-1),
                0: () => this.resetFit(),
                Escape: () => this.closeFit(),
            };

            const handler = keys[event.key];

            if (!handler) { return; }

            event.preventDefault();
            handler();
        },

        /*
         * ---------------------------------------------------------------
         * Today's deviation from the deck.
         * ---------------------------------------------------------------
         */

        /**
         * Whether a slide is one today disagrees with the deck about — brought
         * back, or taken out.
         */
        isRevealed(entryId, slideIndex) {
            return (this.reveals[entryId] ?? this.reveals[String(entryId)] ?? []).includes(Number(slideIndex));
        },

        /** Whether the deck itself leaves this slide out. */
        isSkipped(entryId, slideIndex) {
            return isExcluded({ entryId: Number(entryId), index: Number(slideIndex) }, this.excluded);
        },

        /**
         * And whether the room will actually be shown it today — the deck's
         * arrangement, disagreed with where today disagrees.
         */
        isHiddenToday(entryId, slideIndex) {
            return this.isSkipped(entryId, slideIndex) !== this.isRevealed(entryId, slideIndex);
        },

        /**
         * Disagree with the deck about one slide for this service, or stop
         * disagreeing.
         *
         * Symmetric: the verse the deck leaves out comes back, and the verse it
         * shows is taken out, because the same Sunday wants both — the long
         * procession and the short one. Either way it costs nothing at render
         * time, since every slide was engraved at load, the walked-past ones
         * included, which is the feature that makes this a remote rather than a
         * clicker. And it is today's deviation, not an edit: the projection is
         * left as its author arranged it.
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

            // A slide brought into today's service is where the service should
            // now be looking; one taken out of it is not, and repaint will hand
            // the service on to the nearest slide that is still shown.
            const address = next.includes(index) === this.isSkipped(id, index)
                ? { entryId: id, slideIndex: index }
                : addressAt(this.slides, this.index);

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
                .write({ ...addressAt(this.slides, this.index), blanked: this.blanked, splash: this.splash, reveals: this.reveals })
                .then((state) => {
                    if (state === null) { return; }

                    this.appliedVersion = Math.max(this.appliedVersion, state.version);
                    this.takeOpening(state.splash);
                })
                .catch(() => {});
        },

        /**
         * The opening as the server has it, which is the only place it is decided.
         *
         * A latch there: a picture reported behind the one the service has
         * already reached is refused rather than argued with, and a refusal
         * moves no version — so the poll that corrects everything else never
         * fires for it. Without taking the refusal back here, a phone that
         * pressed Next in the same second as the laptop goes on holding a card
         * the room stopped looking at, and goes on walking an opening that is
         * over instead of driving the deck.
         */
        takeOpening(splash) {
            if (!splash || splash === this.splash) { return; }

            this.splash = splash;
            this.show();
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

            // A failed read is not an event here either: nothing is drawn to say
            // so, and saying `false` only asks the next beat to wait a little
            // longer than this one did.
            if (answer === null) { return false; }

            this.title = answer.title ?? '';

            // The screen's own answer about where its picture lands — unless
            // this phone has just moved it, in which case the answer in hand
            // was written before the press and saying so would undo it.
            if (Date.now() - this._fitAt > FIT_SETTLE_MS) {
                this.fit = fitFrom(answer.fit);
            }

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
                this.splash = state.splash ?? SPLASH_OFF;
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
            this.editUrl = answer.editUrl ?? null;
            this.appliedVersion = 0;
            this.reveals = {};
            this.blanked = false;
            this.splash = answer.state?.splash ?? SPLASH_OFF;
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
