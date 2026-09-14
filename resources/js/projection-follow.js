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

/** A slide index no deck reaches: "as far as this row goes". @see Presentation::LAST_SLIDE */
export const LAST_SLIDE = 2147483647;

/** How often both ends ask where the service is. Slides are not frames. */
export const POLL_MS = 1000;

/** How often the wall says it is still there, when nothing has moved. */
export const HEARTBEAT_MS = 10000;

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
    const headers = {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken ?? '',
        Accept: 'application/json',
    };

    return {
        get: (url) => request(url, { headers: { Accept: 'application/json' } }),
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
 * Which slides this service walks past, once today's reveals are taken off.
 *
 * A deck's exclusions are a deliberate arrangement — "the verses left out today,
 * kept in the deck for the Sunday that wants them" — and a cantor reacting to a
 * long procession is not rewriting it. So the reveal lives on the presentation
 * and is subtracted here, leaving the projection as its author arranged it.
 *
 * @param {Object<string|number, Array<number>>} excluded from the payload
 * @param {Object<string|number, Array<number>>} reveals from the state
 */
export function shownExclusions(excluded, reveals) {
    const effective = {};

    for (const [entryId, slides] of Object.entries(excluded ?? {})) {
        const revealed = (reveals ?? {})[entryId] ?? (reveals ?? {})[String(entryId)] ?? [];
        const left = (slides ?? []).filter((index) => !revealed.includes(index));

        if (left.length > 0) { effective[entryId] = left; }
    }

    return effective;
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
    };
}
