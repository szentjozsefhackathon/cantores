/**
 * The seam between a screen showing a deck and the row that says where the
 * service has got to.
 *
 * Shared by the wall and the phone, because they are the same client: both
 * engrave the same payload, both read the same state, both write it. What
 * differs is only which of them is allowed to feel slow — the phone may wait for
 * an answer, the wall may not.
 *
 * Everything here swallows its failures and says so by answering `null`. That is
 * the one rule of this feature that may not be traded for anything: losing the
 * network costs the remote and nothing else. No error is drawn over the deck, no
 * screen is blanked, nothing jumps to the beginning — the keyboard, and so the
 * service, carries on.
 */

/**
 * Whether a key was aimed at something being typed in rather than at the deck.
 *
 * Every key the wall and the remote bind is also a key a text field needs: the
 * arrows and Backspace move a caret, Space and Enter are ordinary characters,
 * and so are the b and f that blank the screen and take it full. Both pages
 * carry fields — the wall is where the laptop is named, the remote where a deck
 * is searched for — so a keystroke inside a field, or anywhere inside an open
 * dialog, belongs to the field and not to the service.
 */
export const isTypingTarget = (target) => {
    if (! target || typeof target.tagName !== 'string') { return false; }

    if (target.isContentEditable) { return true; }

    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName)) { return true; }

    return typeof target.closest === 'function'
        && target.closest('dialog, [role="dialog"]') !== null;
};

/** A slide index no deck reaches: "as far as this row goes". @see Presentation::LAST_SLIDE */
export const LAST_SLIDE = 2147483647;

/** How often both ends ask where the service is. Slides are not frames. */
export const POLL_MS = 1000;

/** How often the wall says it is still there, when nothing has moved. */
export const HEARTBEAT_MS = 10000;

/**
 * How far apart the asking is allowed to drift when nobody is answering.
 *
 * Small, because what is being backed away from is a Mass. Five seconds is long
 * enough to take most of the load off a server that is struggling — a hundred
 * parishes asking twice a second become a hundred parishes asking a fifth as
 * often — and short enough that a blink of bad wifi costs the remote one beat
 * and not a hymn.
 */
export const POLL_BACKOFF_MAX_MS = 5000;

/**
 * The asking itself: one beat at a time, and never two at once.
 *
 * `setInterval` is the wrong shape for this and was the one real hazard in the
 * feature. It fires on a clock rather than on an answer, so a read that takes
 * three seconds has three more behind it before it lands — which means every
 * client answers a slow server by asking it more often, and a hundred of them
 * doing that together is how a server that was merely slow stops answering at
 * all. A beat scheduled *after* the previous one finishes cannot do that: one
 * request per client is in flight, whatever the server is doing.
 *
 * The backing off is the other half of the same thought. A tick that says it
 * failed doubles the wait, up to `maxInterval`, and the first tick that
 * succeeds puts it straight back — so an outage thins the asking out instead of
 * thickening it, and recovery costs one beat. The wait is jittered so that a
 * hundred clients that lost the server at the same moment do not come back to
 * it at the same moment either.
 *
 * A tick says it failed by answering exactly `false`, or by throwing. Anything
 * else — including the `undefined` of a tick that simply did its work — is a
 * success, so that the rule of this file still holds: a failure is not an
 * event, and nothing is ever drawn over a deck to announce one.
 *
 * @param {() => (boolean|void|Promise<boolean|void>)} tick
 * @param {{interval?: number, maxInterval?: number, random?: () => number}} options
 */
export function poller(tick, options = {}) {
    const interval = options.interval ?? POLL_MS;
    // Never below the interval, whatever was asked for: the ceiling is there to
    // make a struggling server asked *less* often, and a beat that is already
    // slower than the ceiling must not be sped up by failing.
    const maxInterval = Math.max(interval, options.maxInterval ?? POLL_BACKOFF_MAX_MS);
    const random = options.random ?? Math.random;

    let timer = null;
    let wait = interval;
    let running = false;

    /** Give or take a quarter, so that a crowd does not return as a crowd. */
    const jittered = (ms) => Math.round(ms * (0.75 + (random() * 0.5)));

    async function beat() {
        let ok = true;

        try {
            ok = await tick() !== false;
        } catch {
            ok = false;
        }

        wait = ok ? interval : Math.min(maxInterval, wait * 2);

        if (running) { timer = setTimeout(beat, ok ? wait : jittered(wait)); }
    }

    return {
        /** Ask now, and keep asking. Asking twice is asking once. */
        start() {
            if (running) { return; }

            running = true;
            beat();
        },

        /** Stop, whatever is in flight. Nothing lands after this. */
        stop() {
            running = false;
            clearTimeout(timer);
            timer = null;
        },

        /** How long the next wait would be — the backing off, made visible. */
        get wait() {
            return wait;
        },
    };
}

/**
 * Every request this feature makes, and the rule all of them obey.
 *
 * A failure is not an event: it answers `null` and is over. That is the one rule
 * here that may not be traded for anything — losing the network costs the remote
 * and nothing else, and no error is ever drawn over a deck a congregation is
 * reading.
 *
 * @param {string} csrfToken
 */
function jsonRequests(csrfToken) {
    /*
     * `X-Requested-With` is not decoration. Laravel records the current URL as
     * the session's previous one on every plain GET, and `fetch` is a plain GET
     * as far as that check is concerned — so without this header a wall spends a
     * Mass telling its own session that the last page it was on was
     * `/screens/5/state`, once a second. Two things follow from that, and both
     * are wrong: the session is dirtied by every poll, which is what would stop
     * it ever being written conditionally; and anything that sends this person
     * back where they came from — a login, a form — sends them to a JSON
     * endpoint. Saying what this request actually is fixes both.
     */
    const asked = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    const headers = {
        ...asked,
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken ?? '',
    };

    return {
        get: (url) => request(url, { headers: asked }),
        post: (url, body) => request(url, { method: 'POST', headers, body: JSON.stringify(body) }),
    };

    async function request(url, init) {
        try {
            const response = await fetch(url, { credentials: 'same-origin', ...init });

            if (!response.ok) { return null; }

            return await response.json();
        } catch {
            return null;
        }
    }
}

/**
 * A client for one presentation's state.
 *
 * Made rather than configured, because a screen outlives the decks put on it:
 * when a phone points the wall at something else, the URLs change under a page
 * that is not reloading, and the old client must be dropped whole rather than
 * edited. Its two URLs come from the screen's own answer, so nothing here has to
 * know how a route is spelled.
 *
 * @param {{stateUrl: string, payloadUrl: string, csrfToken: string}} config
 */
export function stateClient(config) {
    const http = jsonRequests(config.csrfToken);

    return {
        /** Where the service is, or null if we could not find out. */
        read: () => http.get(config.stateUrl),

        /** Where this device has just put the service. Null on any failure. */
        write: (state) => http.post(config.stateUrl, state),

        /** The deck itself, re-read because the state said it had moved. */
        payload: () => http.get(config.payloadUrl),
    };
}

/**
 * The address of the slide at a position in the shown deck.
 *
 * An address and not the position itself, because the position is an offset into
 * a *filtered* array and the filter is the verses left out today: bring one back
 * mid-service and every offset after it moves. A row and a place within that row
 * do not.
 *
 * @param {Array<{entryId: number, index: number}>} slides the shown deck
 * @param {number} at
 * @return {{entryId: number|null, slideIndex: number}}
 */
export function addressAt(slides, at) {
    const slide = slides[at];

    return slide ? { entryId: slide.entryId, slideIndex: slide.index } : { entryId: null, slideIndex: 0 };
}

/**
 * Where an address lands in this deck — forgivingly.
 *
 * The server has already resolved *which row*, because only it knows what the
 * deck still contains. What is left is the part only a browser can answer, since
 * how many slides a row comes to is read off the score every time it is drawn:
 * the place within the row is clamped to what the row now is, and a row whose
 * slides are all walked past today hands the service on to the next one that is
 * shown.
 *
 * @param {Array<{entryId: number, index: number}>} slides the shown deck
 * @param {Array<{id: number}>} entries the deck's rows, in order
 * @param {{entryId: number|null, slideIndex: number}} address
 * @return {number} a position in `slides`
 */
export function indexOfAddress(slides, entries, address) {
    if (slides.length === 0) { return 0; }
    if (!address || address.entryId === null || address.entryId === undefined) { return 0; }

    const entryId = Number(address.entryId);
    const slideIndex = Number(address.slideIndex ?? 0);

    const exact = slides.findIndex((slide) => slide.entryId === entryId && slide.index === slideIndex);
    if (exact !== -1) { return exact; }

    // The row is still shown but has lost the slide that was on the screen — a
    // stanza deleted, a page break moved. The nearest one still before it, or
    // its first if the row now starts later than where we were.
    const within = slides
        .map((slide, at) => ({ slide, at }))
        .filter(({ slide }) => slide.entryId === entryId);

    if (within.length > 0) {
        const before = within.filter(({ slide }) => slide.index <= slideIndex);

        return (before.length > 0 ? before[before.length - 1] : within[0]).at;
    }

    // Nothing of that row is shown: it was deleted, or every slide it comes to
    // is one this service walks past. The service lands on the first slide of
    // the next row that is, and on the end of the deck if there is none.
    const position = entries.findIndex((entry) => Number(entry.id) === entryId);

    if (position !== -1) {
        const after = entries.slice(position + 1).map((entry) => Number(entry.id));
        const next = slides.findIndex((slide) => after.includes(slide.entryId));

        if (next !== -1) { return next; }
    }

    return slides.length - 1;
}

/**
 * Which slides this service walks past, once today's deviations are applied.
 *
 * A deck's exclusions are a deliberate arrangement — "the verses left out today,
 * kept in the deck for the Sunday that wants them" — and a cantor reacting to a
 * long procession is not rewriting it. So the deviation lives on the
 * presentation and is applied here, leaving the projection as its author
 * arranged it.
 *
 * The deviation is a toggle rather than a reveal: a slide named in it is shown
 * where the deck leaves it out, and left out where the deck shows it. Both
 * directions are needed by the same Sunday — the extra verse the procession
 * wants, and the one the short one does not — and one symmetric difference says
 * both without a second map on the row.
 *
 * @param {Object<string|number, Array<number>>} excluded from the payload
 * @param {Object<string|number, Array<number>>} reveals from the state
 */
export function shownExclusions(excluded, reveals) {
    const effective = {};
    const rows = new Set([
        ...Object.keys(excluded ?? {}),
        ...Object.keys(reveals ?? {}),
    ]);

    for (const entryId of rows) {
        const deck = slidesOfRow(excluded, entryId);
        const today = slidesOfRow(reveals, entryId);

        const left = [
            ...deck.filter((index) => !today.includes(index)),
            ...today.filter((index) => !deck.includes(index)),
        ].sort((a, b) => a - b);

        if (left.length > 0) { effective[entryId] = left; }
    }

    return effective;
}

/**
 * One row's slide list out of a map keyed by row.
 *
 * Both spellings of the key, because JSON gives it back as a string and a tap
 * gives it as a number.
 *
 * @param {Object<string|number, Array<number>>} map
 * @param {string|number} entryId
 * @return {Array<number>}
 */
function slidesOfRow(map, entryId) {
    const list = (map ?? {})[entryId] ?? (map ?? {})[String(entryId)] ?? (map ?? {})[Number(entryId)];

    return Array.isArray(list) ? list.map(Number) : [];
}

/*
 * ---------------------------------------------------------------
 * Where the picture lands on the wall.
 * ---------------------------------------------------------------
 *
 * The presenter fits the deck's own shape into the projector's and centres it,
 * which is right everywhere except the rooms where it is not: a square screen
 * hung high, a beamer that cannot be moved, a deck built 1:1 for it and still
 * landing half a foot above the heads it was meant for. So the fitted picture
 * is nudged and scaled afterwards, and the three numbers that say how live on
 * the screen — the room, not the deck.
 *
 * All three are relative to the fitted picture rather than to pixels, so a
 * window resized, a projector swapped and a deck in another shape all keep
 * whatever was lined up. Both ends share this so that the phone's preview and
 * the wall cannot drift apart.
 */

/** The picture as the application has always drawn it: centred, and as large as fits. */
export const FIT_NEUTRAL = Object.freeze({ scale: 1, x: 0, y: 0 });

/** @see Screen::FIT_MIN_SCALE */
const FIT_MIN_SCALE = 0.25;
const FIT_MAX_SCALE = 2;
const FIT_MAX_OFFSET = 1;

/**
 * One press of an arrow, and one press of a zoom.
 *
 * Small enough that a picture can be put where it belongs rather than near it,
 * and large enough that getting it there is a handful of presses and not a
 * minute of tapping while a congregation waits.
 */
export const FIT_MOVE_STEP = 0.02;
export const FIT_ZOOM_STEP = 0.025;

const clamp = (value, low, high) => Math.min(high, Math.max(low, value));

/**
 * A fit as it came off the wire, made safe to draw with.
 *
 * Anything missing or unreadable is the neutral fit, because the one thing this
 * must never do is leave a room looking at a picture pushed off its screen by a
 * field that arrived as undefined.
 *
 * @param {{scale?: number, x?: number, y?: number}|null|undefined} raw
 * @return {{scale: number, x: number, y: number}}
 */
export function fitFrom(raw) {
    const number = (value, fallback) => (Number.isFinite(Number(value)) ? Number(value) : fallback);

    return {
        scale: clamp(number(raw?.scale, 1), FIT_MIN_SCALE, FIT_MAX_SCALE),
        x: clamp(number(raw?.x, 0), -FIT_MAX_OFFSET, FIT_MAX_OFFSET),
        y: clamp(number(raw?.y, 0), -FIT_MAX_OFFSET, FIT_MAX_OFFSET),
    };
}

/**
 * The same fit, moved by one press.
 *
 * @param {{scale: number, x: number, y: number}} fit
 */
export function movedFit(fit, across, down) {
    return fitFrom({ ...fit, x: fit.x + across, y: fit.y + down });
}

/** And the same fit, one press larger or smaller. */
export function zoomedFit(fit, by) {
    return fitFrom({ ...fit, scale: fit.scale + by });
}

/**
 * What it comes to in CSS.
 *
 * The move is written in per cent of the picture's own size, which is what
 * makes the numbers mean the same thing on a phone's preview and on a wall: the
 * translate comes before the scale, so an arrow moves the picture by the same
 * fraction of itself whether it has been scaled down or not.
 */
export function fitTransform(fit) {
    const { scale, x, y } = fitFrom(fit);

    const per = (fraction) => Number((fraction * 100).toFixed(3));

    return `translate(${per(x)}%, ${per(y)}%) scale(${Number(scale.toFixed(4))})`;
}

/**
 * Whether two fits are the same picture.
 *
 * Read off a float column and sent back as a float, so they are compared as
 * near enough rather than as equal.
 */
export function sameFit(one, other) {
    const a = fitFrom(one);
    const b = fitFrom(other);

    return Math.abs(a.scale - b.scale) < 0.0005
        && Math.abs(a.x - b.x) < 0.0005
        && Math.abs(a.y - b.y) < 0.0005;
}

/**
 * A client for a screen — what the room is looking at, rather than where in it
 * the service has got to.
 *
 * One read answers both: the screen's answer carries the presentation's state
 * nested inside it, so a wall polling once a second is polling once a second and
 * not twice. `presentationId` is the field everything turns on, and it changing
 * is the one event that makes a screen go black and engrave something else.
 *
 * @param {{screenUrl: string, csrfToken: string}} config
 */
export function screenClient(config) {
    const http = jsonRequests(config.csrfToken);

    return {
        /** What the screen is showing, or null if we could not find out. */
        read: () => http.get(config.screenUrl),

        /**
         * Put a deck on the screen, or — with null — take what is on it off and
         * end the service. A screen is pointed twice a service; nothing about
         * this call is on the hot path.
         */
        point: (projectionId) => http.post(config.screenUrl, { projectionId }),

        /**
         * Move the picture on the wall, without saying anything about what is
         * on it. Pressed a dozen times while a beamer is lined up and never
         * again during the service.
         */
        adjust: (fit) => http.post(config.screenUrl, { fit }),
    };
}
