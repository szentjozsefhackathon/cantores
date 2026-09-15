/**
 * The two engraving engines that are globals rather than modules, and the wait
 * every drawing owes them.
 *
 * exsurge and abc2svg are loaded by plain `<script src>` tags the drawing pages
 * carry in their body, and that is the whole of what this file exists for. On a
 * full page load the parser guarantees the order: the tags run while the
 * document is being read, long before Alpine starts anything. On a wire:navigate
 * visit nothing guarantees it — Livewire re-creates those tags itself, and a
 * script created that way loads *asynchronously*. It waits for the page bundles
 * in the head before it initialises Alpine on the swapped-in body, and for
 * nothing in the body at all.
 *
 * So a deck drawn at init raced the engines and usually won. Every row the
 * missing engine would have drawn threw, the drawing caught it, logged it and
 * dropped that slide — and the deck came up with holes in it, or, where every
 * row was a score, empty. Reloading put the parser back in charge of the order
 * and everything drew, which is what "press Ctrl-R and it is fine" meant.
 *
 * Waiting here rather than teaching each page to load the engines differently,
 * because the ordering is a fact about the drawing and not about any one page:
 * whoever is about to ask exsurge for a chant has to know it has arrived,
 * however the page that holds it was reached.
 */

/** How often the wait looks again, and how long it waits before giving up. */
const LOOK_MS = 25;
const GIVE_UP_MS = 15000;

/**
 * The engines, as the global each one defines and the script each is loaded by.
 *
 * abc2svg is asked for `Abc` and not merely for itself: the page creates the
 * `window.abc2svg` object first, to hang the off-screen span the library
 * measures text with on it, so the object exists a moment before the library
 * behind it does.
 *
 * @type {Array<{loaded: () => boolean, src: string}>}
 */
const ENGINES = [
    { loaded: () => typeof window.exsurge !== 'undefined' && Boolean(window.exsurge), src: 'exsurge' },
    { loaded: () => Boolean(window.abc2svg?.Abc), src: 'abc2svg' },
];

/**
 * Whether this page loads that engine at all.
 *
 * Only an engine the page carries is worth waiting for. A page that names
 * neither draws neither, and must not be made to wait fifteen seconds to find
 * that out; a deck of nothing but words is drawn by this bundle alone and is
 * ready the moment it is asked for.
 */
function carried(engine) {
    return Array.from(document.scripts ?? [])
        .some((script) => (script.getAttribute('src') ?? '').includes(engine.src));
}

/** The engines this page is loading and has not finished loading. */
function outstanding() {
    return ENGINES.filter((engine) => !engine.loaded() && carried(engine));
}

/**
 * Wait until the engines this page carries are there to be called.
 *
 * Polled rather than hung on the tags' own `load` events, because by the time
 * anything here asks, a tag may have loaded already and an event that has fired
 * never fires again. It is a handful of looks in the common case — everything is
 * usually there at the first — and the deadline is the answer to an engine that
 * is never coming: a CDN that is blocked or down must cost the room the chants
 * it draws, not the whole deck.
 *
 * @param {number} [giveUpMs] how long to wait before drawing without them
 * @return {Promise<void>}
 */
export function enginesReady(giveUpMs = GIVE_UP_MS) {
    if (typeof document === 'undefined' || typeof window === 'undefined') { return Promise.resolve(); }

    if (outstanding().length === 0) { return Promise.resolve(); }

    return new Promise((resolve) => {
        const deadline = Date.now() + giveUpMs;

        const look = () => {
            const waiting = outstanding();

            if (waiting.length === 0) {
                resolve();

                return;
            }

            if (Date.now() >= deadline) {
                console.error('[engines] gave up waiting for', waiting.map((engine) => engine.src).join(', '));
                resolve();

                return;
            }

            setTimeout(look, LOOK_MS);
        };

        setTimeout(look, LOOK_MS);
    });
}
