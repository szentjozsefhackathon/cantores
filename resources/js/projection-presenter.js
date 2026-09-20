import { onAlpineInit } from './alpine-init.js';
import { isExcluded, renderDeck } from './projection-deck.js';
import { onPaper } from './slide-frame.js';
import { HEARTBEAT_MS, POLL_MS, PUSHED_POLL_MS, addressAt, commandClient, fitFrom, fitTransform, indexOfAddress, isTypingTarget, ownFit, poller, replayPendingState, showClient, showStream, shownExclusions, stateClient } from './projection-follow.js';

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
 * In a window the controls fade out when the room goes quiet and come back on any
 * movement of the mouse. In full screen they are not there at all: that picture
 * is the one the congregation is looking at, and a projector showing a toolbar is
 * showing the wrong thing.
 *
 * It is also one of the two clients of a Presentation — the row on which this
 * screen and a phone agree about where the service has got to. That obeys a
 * second rule, which outranks everything: **the wall never loses its picture**. A
 * keystroke is applied here first and reported afterwards, and every read and
 * every write swallows its failure. Losing the network costs the remote and
 * nothing else; the keyboard, and so the service, carries on.
 *
 * And it is a *screen* before it is any particular deck: it shows its owner's
 * show, whatever that is. The page can be opened on a deck, which puts that deck
 * up, or bare, which is the parish laptop put in front of the room at the start
 * of Mass and not touched again: then it waits, and the deck arrives when a
 * phone puts one up. Changing deck is
 * the one thing here that is allowed to be slow — a deck has to be engraved
 * before it can be shown — and the screen goes black while it happens, because a
 * room watching the last hymn linger while the next is prepared is worse than a
 * room watching nothing for two seconds.
 */

/**
 * The three pictures a service opens with. @see Presentation::SPLASH_ORDER
 */
const SPLASH_CARD = 'card';
const SPLASH_DARK = 'dark';
const SPLASH_OFF = 'off';

/** How long the bar stays up after the last sign of life. */
const IDLE_MS = 2500;

/**
 * How long the picture takes to go out when the cantor blanks the screen.
 *
 * Long enough to read as the light going down rather than something breaking,
 * short enough that nobody is left watching a hymn fade while the preacher is
 * already speaking.
 */
const BLANK_FADE_MS = 500;

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

        /**
         * The verses brought back for this service alone, keyed by row.
         *
         * Today's deviation rather than an edit: it lives on the presentation
         * and dies with it, and the projection stays as its author arranged it.
         */
        reveals: {},

        /**
         * The whole deck as engraved — every slide, the walked-past ones
         * included — and the subset the room is actually shown.
         *
         * Both are kept, because bringing a verse back today must not cost a
         * re-engraving: the slide was drawn at load like every other, and
         * revealing it is a filter run again over what is already in hand.
         */
        drawn: [],
        slides: [],
        index: 0,
        total: 0,

        busy: true,

        /**
         * Whether the cantor has put the wall out.
         *
         * Taken from the show the page was opened on, so that a reloaded wall
         * starts black rather than showing the slide it was hiding for the
         * second before the first read arrives.
         */
        blanked: Boolean(config.state?.blanked),
        idle: false,

        /**
         * What the room is looking at, as the show says — and whether this
         * page is still engraving it.
         *
         * `preparing` is not `blanked`. Blanking is an instruction the cantor
         * gave and only the cantor takes back; this is a condition that clears
         * itself when the drawing is done, and the phone is told which of the two
         * the black on the wall means.
         */
        presentationId: config.presentationId ?? null,
        title: config.title ?? '',
        preparing: false,

        /**
         * How far into its opening the service is: `card`, then `dark`, then
         * `off`.
         *
         * Three pictures rather than two, because the beginning of a Mass has
         * three. The card is up while the window is dragged onto the beamer and
         * the projector is lined up against it; then the room fills, and what
         * belongs on the wall is nothing at all, since a title card held for
         * twenty minutes in front of a seated congregation is an advertisement
         * and not a welcome; then the first hymn is announced and the deck
         * begins. Each press walks it on one.
         *
         * It lives on the presentation and not here, because the press that
         * walks it comes as often from the phone at the organ as from this
         * keyboard. Held here only as this screen's copy, reported like
         * everything else and only ever walked forwards.
         */
        splash: config.splash ?? SPLASH_OFF,

        /**
         * The deck this screen has actually finished engraving, and the deck the
         * server now has. While they differ the wall is behind an edit, and the
         * phone is told as much — which is the honest answer to "is the room
         * seeing my correction yet".
         */
        drawnRevision: config.revision ?? '',
        serverRevision: config.revision ?? '',

        /**
         * The newest state this screen has acted on. Anything not newer is
         * ignored, because without that a read answered just before the cantor
         * pressed space arrives just after it and sends the room back a slide.
         */
        appliedVersion: 0,
        canonicalState: null,
        pendingCommands: [],

        /**
         * Whether this page is the one the beamer is throwing.
         *
         * Kept as state of its own rather than read off the document at render
         * time, because only an event tells Alpine that the browser left or
         * entered full screen — the user can leave it with Esc, which nothing
         * here is told about otherwise.
         */
        fullscreen: false,

        /**
         * Where on this wall the picture lands.
         *
         * Read only here, and belonging to the screen rather than to the deck:
         * it is the shape of the room — a square screen, a beamer that cannot
         * be moved — and it is lined up from the phone, because the person who
         * can see the wall is never the person at the laptop.
         */
        fit: fitFrom(config.fit),

        _idleTimer: null,
        _poll: null,
        _heartbeat: null,
        _client: null,
        _commands: null,
        _show: null,
        _refreshToken: 0,

        /**
         * Whether the bar and the key hints are out of sight.
         *
         * Full screen is the mode in which the congregation, not the cantor, is
         * looking at this screen, so nothing that is a control appears on it at
         * all — not even for the moment after a mouse is moved. In a window the
         * bar still behaves as it always has, and comes back on any sign of life.
         *
         * Except while the screen is waiting or holding its title card, when
         * there is no picture to protect: the rule exists so that a projector is
         * never caught showing a toolbar over a hymn, and neither of those is a
         * hymn. Both are also the half hour before Mass, which is exactly when a
         * full-screen page with one centred line on black and no visible way out
         * is what a browser's scam heuristics are looking for — Edge blocks the
         * page outright, minutes in, and the wall is lost before the service has
         * begun. Leaving the bar up costs nothing anybody is reading, and while
         * the card is up it is what the cantor reaches for: the window has just
         * been dragged onto the beamer and full screen is the next thing asked
         * for.
         */
        get controlsHidden() {
            if (this.waiting || this.showingSplash) { return false; }

            return this.fullscreen || this.idle;
        },

        /** Whether there is no show for this screen to show at all. */
        get waiting() {
            return this.presentationId === null;
        },

        /**
         * Whether the title card is the picture right now.
         *
         * Not while a deck is being engraved and not on a screen with no deck at
         * all: both of those are the wall saying it has nothing, and a card over
         * them would claim a service was about to start when none is.
         */
        get showingSplash() {
            return this.splash === SPLASH_CARD && !this.waiting && !this.preparing;
        },

        /** Whether the service is in the quiet dark between the card and the deck. */
        get openingDark() {
            return this.splash === SPLASH_DARK && !this.waiting;
        },

        /** Whether the opening still has a picture of its own to walk through. */
        get opening() {
            return this.splash !== SPLASH_OFF;
        },

        /** Whether the room should be looking at black, and for any of three reasons. */
        get dark() {
            return this.blanked || this.preparing || this.openingDark;
        },

        /**
         * How long the black takes to arrive.
         *
         * Blanking is the sermon beginning, and a wall that snaps to black pulls
         * every eye in the room to it at the moment they were meant to go to the
         * pulpit; a picture that dims away is not an event. Coming back is the
         * opposite errand — a verse that has to be sung now — so it is instant,
         * and so is the black of a deck being swapped, which is not the cantor
         * asking for anything but the wall admitting it has nothing to show.
         */
        get darkFadeMs() {
            return this.dark && !this.preparing ? BLANK_FADE_MS : 0;
        },

        /**
         * What the person at this keyboard is told about the opening.
         *
         * In the bar and never over the picture: in full screen the bar is gone
         * and the room is looking at the card or at black, and a line of
         * instructions projected across a church is the one thing this page
         * exists to prevent.
         */
        get openingHint() {
            if (this.showingSplash) { return config.cardHint ?? ''; }

            return this.openingDark ? config.darkHint ?? '' : '';
        },

        get aspectRatio() {
            return this.geometry.aspectRatio ?? '16/9';
        },

        /** The fit, as the one line of CSS it comes to. */
        get fitTransform() {
            return fitTransform(this.fit);
        },

        init() {
            this._show = showClient(config);

            // A deck named in the URL was engraved by the server into this page,
            // so it goes up before anything is polled. A bare screen has nothing
            // to draw yet and simply starts listening.
            if (this.presentationId !== null) {
                this._client = stateClient(config);
                this.configureCommands(config.stateUrl);
                this.draw().then(() => this.follow());
            } else {
                this.busy = false;
                this.follow();
            }

            this.wake();
        },

        destroy() {
            clearTimeout(this._idleTimer);
            this._poll?.stop();
            this._stream?.stop();
            this._heartbeat?.stop();
            this._commands?.stop();
        },

        /** What the browser has just done with full screen, however it was asked. */
        syncFullscreen() {
            this.fullscreen = Boolean(document.fullscreenElement);
        },

        async draw() {
            this.busy = true;

            try {
                this.land(await renderDeck(this.entries, this.geometry));
            } catch (e) {
                console.error('[projection] could not draw the deck', e);
            } finally {
                this.busy = false;
            }
        },

        /**
         * The deck this page opened on, put up where the service already is.
         *
         * Read off the state baked into the page, not off the first poll: the
         * heartbeat starts in the same moment as the poll, and a wall that drew
         * the beginning while it waited would report the beginning as where the
         * service is — and the phone, and sometimes the wall itself, would
         * follow it there.
         */
        land(drawn) {
            if (!config.state) { return this.paint(drawn, this.address()); }

            this.drawn = drawn;
            this.adopt(config.state);
        },

        /**
         * A freshly engraved deck put on the wall in place of the old one.
         *
         * The slide being shown is kept across the swap: an edit made during the
         * rehearsal must not send the room back to the beginning, and an address
         * survives a row that was reordered, shortened or deleted where an array
         * offset would not.
         */
        paint(drawn, address) {
            this.drawn = drawn;
            this.repaint(address);
        },

        /**
         * The same engraved deck, filtered again — what a verse brought back for
         * today costs, which is nothing.
         */
        repaint(address) {
            const shown = shownExclusions(this.excluded, this.reveals);

            this.slides = this.drawn.filter((slide) => !isExcluded(slide, shown));
            this.total = this.slides.length;
            this.index = indexOfAddress(this.slides, this.entries, address);
            this.show();
        },

        /**
         * The address of the slide on the screen — a row of the deck and a place
         * within it, never a position in the filtered array.
         */
        address() {
            return addressAt(this.slides, this.index);
        },

        /**
         * A correction made in the editor, fetched without leaving the
         * projector — by hand, from the reload button.
         */
        applyUpdate(detail = {}) {
            // Read for the show as it stood on the server, which a phone may
            // since have moved on: a deck this page is no longer showing is not
            // painted over the one it is.
            if (detail.presentationId !== undefined && detail.presentationId !== this.presentationId) { return; }

            if (detail.payload) { this.entries = detail.payload; }
            if (detail.geometry) { this.geometry = detail.geometry; }
            if (detail.excluded !== undefined) { this.excluded = detail.excluded ?? {}; }
            if (detail.revision) { this.serverRevision = detail.revision; }

            const address = this.address();

            renderDeck(this.entries, this.geometry)
                .then((drawn) => {
                    this.paint(drawn, address);
                    this.drawnRevision = this.serverRevision;
                    this.report();
                })
                .catch((e) => console.error('[projection] could not draw the deck', e));
        },

        show() {
            const box = this.$refs.stageBox;
            if (!box) { return; }

            const slide = this.slides[this.index];

            box.replaceChildren(slide ? onPaper(slide.svg) : document.createComment('empty'));
        },

        go(index) {
            if (this.total === 0) { return; }

            // A slide asked for by name is the deck starting, whatever the
            // opening had left to show: somebody has reached past it.
            this.splash = SPLASH_OFF;
            this.index = Math.min(Math.max(index, 0), this.total - 1);
            this.show();
            this.report();
        },

        /**
         * One step of the opening: the card gives way to the dark, and the dark
         * to the deck.
         *
         * The deck is not moved by either step. That is what makes the press
         * which ends the dark land on the *first* slide rather than the second —
         * the opening is a pair of pictures over the deck and not a pair of
         * slides before it.
         *
         * Ending the dark hands the deck to the cantor still blanked, the same
         * way B does it there: Next only ever advances, and it is B alone that
         * puts a picture in front of the room. Without this, the press that ends
         * the opening would be the one press of Next in the whole service that
         * shows a slide rather than merely selecting one.
         */
        walkOpening() {
            if (this.splash === SPLASH_CARD) {
                this.splash = SPLASH_DARK;
            } else {
                this.splash = SPLASH_OFF;
                this.blanked = true;
            }

            this.show();
            this.report({ blank: true });
        },

        // A blanked screen goes on being moved through behind the black: the
        // cantor lines the next hymn up while the sermon is being preached, and
        // presses B once when it is time for the room to see it. Only B brings
        // the picture back, so nothing can un-blank the wall by accident.
        next() {
            if (this.opening) { return this.walkOpening(); }

            this.go(this.index + 1);
        },

        // Backwards out of the opening is forwards too. There is nothing behind
        // the beginning of a service to go back to, and the alternative — an
        // opening that can be rewound — is an opening a stale heartbeat could
        // rewind for you, over a hymn.
        previous() {
            if (this.opening) { return this.walkOpening(); }

            this.go(this.index - 1);
        },

        /**
         * B during the opening.
         *
         * Over the card it is the same press as Next, because what it asks for —
         * black — is exactly what comes next. Over the dark it ends the opening
         * and shows the first slide in the same press: B is the button that
         * shows the slides, so a press of it is never answered with more black.
         */
        toggleBlank() {
            if (this.showingSplash) { return this.walkOpening(); }

            if (this.openingDark) {
                this.splash = SPLASH_OFF;
                this.blanked = false;
                this.show();
                this.report({ blank: true });

                return;
            }

            this.blanked = !this.blanked;
            this.report({ blank: true });
        },

        onKey(event) {
            if (isTypingTarget(event.target)) { return; }

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
                b: () => this.toggleBlank(),
                B: () => this.toggleBlank(),
                f: () => this.toggleFullscreen(),
                F: () => this.toggleFullscreen(),
            };

            const handler = keys[event.key];
            if (!handler) { return; }

            event.preventDefault();
            handler();
        },

        /*
         * ---------------------------------------------------------------
         * The half of this page that talks to the phone.
         * ---------------------------------------------------------------
         */

        /**
         * Start following the show: which deck is up, and where in it the
         * service has got to.
         *
         * Both come back in one read, so a wall polls once a second and not
         * twice. Taking up whatever it says is the point — a reloaded tab
         * mid-service must land where the service is, not at the beginning, and
         * a screen that was away while a deck went up must find it.
         */
        follow() {
            // Once a second until the hub is listening, and then only as a
            // safety net: the hub says when to ask.
            this._poll = poller(() => this.pull(), {
                interval: () => (this._stream?.open ? PUSHED_POLL_MS : POLL_MS),
            });
            this._stream = showStream(config, {
                change: () => this._poll.poke(),
                // Either way round, ask now: a stream just opened may have missed
                // the move that happened while it connected, and one just lost
                // must not leave the page waiting out a fifteen second beat.
                open: () => this._poll.poke(),
            });
            // The same shape, three times as slow, and allowed to drift three
            // times as far — which is still well inside the five minutes a
            // presentation is counted live for.
            this._heartbeat = poller(() => this.acknowledgeRendered(), {
                interval: HEARTBEAT_MS,
                maxInterval: HEARTBEAT_MS * 3,
            });

            this._poll.start();
            this._stream.start();
            this._heartbeat.start();
        },

        /**
         * One read of the show.
         *
         * Three different things can have moved, answered by three different
         * fields. `presentationId` moves when a phone puts another deck up, and
         * the wall goes black and engraves it. `version` moves when
         * someone presses space, and the screen swaps a slide. `revision` moves
         * when someone saves an edit, and the screen reads the same deck again.
         */
        async pull() {
            const answer = await this._show.read();

            // A failed read is not an event. Nothing is drawn over the deck and
            // nothing moves; the next beat tries again, a little later than this
            // one did — which is the whole of what saying `false` here means.
            if (answer === null) { return false; }

            this.title = answer.title ?? '';

            // Taken up whatever else the answer says, because it is a fact
            // about this screen and not about the deck: a picture lined up
            // while the wall was waiting is still lined up when a deck arrives
            // on it, and one nudged mid-hymn moves under the hymn.
            this.fit = ownFit(answer) ?? this.fit;

            if ((answer.presentationId ?? null) !== this.presentationId) {
                await this.showDeck(answer);

                return;
            }

            const state = answer.state;

            if (state === null || state === undefined) { return; }

            this.serverRevision = state.revision ?? this.serverRevision;

            if (state.version >= Number(this.canonicalState?.version ?? 0)) { this.acceptCanonical(state); }

            if (this.serverRevision !== this.drawnRevision) {
                this.refresh();
            }
        },

        /**
         * Another deck put up from somewhere else.
         *
         * The one slow thing this page is allowed to do, and the only place it
         * takes the picture down on purpose. Everything of the deck that was on
         * the wall is dropped — its client, its slides, the place the service had
         * reached in it — because none of it means anything about the new one;
         * and the room looks at black until the new deck is engraved, which is
         * `preparing` and not `blanked`.
         *
         * A show taken down leaves the screen waiting: that is how a service
         * ends when the person who ends it is holding a phone at the organ.
         */
        async showDeck(answer) {
            const token = ++this._refreshToken;

            // As the server has it: the new show carries over the blank of the
            // one it replaced, and the next deck put up during the sermon stays
            // behind the black until somebody presses B.
            this.blanked = Boolean(answer.state?.blanked);
            this.presentationId = answer.presentationId ?? null;
            this.appliedVersion = 0;
            this.canonicalState = answer.state ?? null;
            this.pendingCommands = [];
            this.reveals = {};
            // Taken up before the engraving rather than after it, because
            // engraving ends in a report: a card read off the answer a moment
            // later would be reported away before this screen had drawn it.
            this.splash = answer.state?.splash ?? SPLASH_OFF;
            this.drawnRevision = '';
            this.serverRevision = '';

            if (this.presentationId === null) {
                this._commands?.stop();
                this._commands = null;
                this._client = null;
                this.preparing = false;
                this.paint([], { entryId: null, slideIndex: 0 });

                return;
            }

            this._client = stateClient({ ...config, stateUrl: answer.stateUrl, payloadUrl: answer.payloadUrl });
            this._commands?.stop();
            this.configureCommands(answer.stateUrl);
            this.preparing = true;

            try {
                await this.reengrave(token, true);

                // Where the service already is in the deck just put up — a
                // wall opened mid-hymn lands on the hymn, not at its first slide.
                if (token === this._refreshToken && answer.state) { this.adopt(answer.state); }
            } finally {
                // Only if nothing else has been put up since: a cantor who taps
                // twice must not be shown a deck that is on its way out by a
                // swap that started first.
                if (token === this._refreshToken) { this.preparing = false; }
            }
        },

        /**
         * The opening as the server has it, which is the only place it is decided.
         *
         * A latch there: a picture reported behind the one the service has
         * already reached is refused rather than argued with, and a refusal
         * moves no version — so the poll that corrects everything else never
         * fires for it. This screen reports every ten seconds whether anything
         * happened, and without taking the refusal back a heartbeat sent a
         * moment before the phone's press would leave the wall holding a card
         * over the hymn the room had just been given.
         */
        takeOpening(splash) {
            if (!splash || splash === this.splash) { return; }

            this.splash = splash;
            this.show();
        },

        /** Where somebody else has put the service. */
        adopt(state) {
            this.acceptCanonical(state);
        },

        configureCommands(stateUrl) {
            this._commands?.stop();
            this._commands = commandClient(
                { ...config, stateUrl },
                {
                    change: (pending) => { this.pendingCommands = pending; },
                    accepted: (command, state) => this.commandAccepted(state),
                },
            );
        },

        acceptCanonical(state) {
            if (!state || Number(state.version) < Number(this.canonicalState?.version ?? 0)) { return; }

            this.canonicalState = state;
            this.appliedVersion = state.version;
            this.reconcileState();
        },

        reconcileState() {
            const desired = replayPendingState(this.canonicalState, this.pendingCommands);

            if (!desired || desired.entryId === undefined) { return; }

            this.reveals = desired.reveals ?? {};
            this.blanked = Boolean(desired.blanked);
            this.splash = desired.splash ?? SPLASH_OFF;

            this.repaint({ entryId: desired.entryId, slideIndex: desired.slideIndex });
            this.acknowledgeRendered();
        },

        commandAccepted(state) {
            this.acceptCanonical(state);
        },

        /**
         * Send the explicit local keyboard command after applying it on-screen.
         *
         * The blank travels only with the press that changed it. Every other
         * report — a slide, a heartbeat, a deck finished drawing — can be sent
         * a moment before this screen has heard of a B pressed on the phone,
         * and carrying the blank along would take that press back.
         *
         * Deliberately not awaited by anything that moves the picture. The
         * ordered client keeps a failed command at its head and retries it.
         */
        report({ blank = false } = {}) {
            // A screen waiting for a show has nothing to say about where a
            // service has got to. Not a failure: there is simply
            // nothing to report, and the next beat is due at the usual time.
            if (this._client === null) { return; }

            const address = this.address();
            const changes = { ...(!blank ? address : {}), ...(blank ? { blanked: this.blanked } : {}), splash: this.splash };

            if (this._commands !== null) {
                return this._commands.write(changes).then(() => true);
            }

            // Compatibility for an already-open old bundle and the narrow unit
            // harnesses that replace the state client directly.
            return this._client
                .write(changes)
                .then((state) => {
                    if (state === null) { return false; }

                    this.appliedVersion = Math.max(this.appliedVersion, state.version);
                    this.takeOpening(state.splash);

                    return true;
                })
                .catch(() => false);
        },

        /** A heartbeat is evidence of rendered state, never desired state. */
        async acknowledgeRendered() {
            if (this.presentationId === null || !config.ackUrl || this.pendingCommands.length > 0 || this.serverRevision !== this.drawnRevision) {
                return;
            }

            await new Promise((resolve) => (globalThis.requestAnimationFrame ?? ((done) => setTimeout(done, 0)))(resolve));

            const acknowledgement = await this._show.acknowledge(config.ackUrl, {
                presentationId: this.presentationId,
                appliedVersion: this.appliedVersion,
                drawnRevision: this.drawnRevision || null,
            });

            return acknowledgement === null ? false : true;
        },

        /**
         * Read the deck again because it moved underneath, and engrave it again
         * without ever taking the picture down.
         *
         * An edit saved during the rehearsal, not a different deck: the slide on
         * the wall is kept across the swap, and the room sees the correction
         * appear under the hymn it is already singing.
         */
        async refresh() {
            return this.reengrave(++this._refreshToken, false);
        },

        /**
         * Draw the deck the client points at, and put it up when it is finished.
         *
         * The new deck is drawn into an array of its own and swapped in whole. If
         * the read or the engraving fails, the old deck simply stays and this
         * screen keeps its old revision, so the next change tries again — and
         * nothing is drawn over the deck to say so.
         *
         * `fresh` is the difference between an edit and a different deck: an
         * edit keeps the place the service had reached, and a different deck has
         * no place to keep.
         */
        async reengrave(token, fresh) {
            const payload = await this._client?.payload();

            if (!payload || token !== this._refreshToken) { return; }

            const address = fresh ? { entryId: null, slideIndex: 0 } : this.address();

            let drawn;

            try {
                drawn = await renderDeck(payload.entries ?? [], payload.geometry ?? {});
            } catch (e) {
                console.error('[projection] could not re-draw the deck', e);

                return;
            }

            if (token !== this._refreshToken) { return; }

            this.entries = payload.entries ?? [];
            this.geometry = payload.geometry ?? {};
            this.excluded = payload.excluded ?? {};

            this.paint(drawn, address);

            this.drawnRevision = payload.revision ?? this.drawnRevision;
            this.serverRevision = this.drawnRevision;
            this.acknowledgeRendered();
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

        /**
         * Step out of full screen before anything asks the cantor to type.
         *
         * A dialog in the middle of a full-screen page waiting for text is the
         * exact shape of a tech-support scam, and a browser that recognises the
         * shape does not warn about the dialog — Edge takes the whole site down,
         * minutes in, on the laptop the service is about to depend on. Naming a
         * screen is not worth that. Out of full screen the same dialog is an
         * ordinary dialog in an ordinary window, and the cantor takes the screen
         * back when they are done, which is the gesture they already know.
         */
        leaveFullscreenToType() {
            if (! document.fullscreenElement) { return; }

            document.exitFullscreen?.().catch(() => {});
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
