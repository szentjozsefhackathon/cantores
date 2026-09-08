/**
 * When the preview does its work, and when it admits to doing it.
 *
 * Both halves of one problem. Laying a booklet out is a single blocking stretch
 * of the browser's attention — every score engraved again, the lot reflowed onto
 * fresh pages — so it must not be started while someone is still turning the knob
 * that asked for it, and it must be visible when it does happen.
 */

/**
 * The shortest quiet gap before a change is acted on.
 *
 * Long enough that a second click on a stepper arrow lands before the layout run
 * does — a quarter of a second is under the pace of a deliberate repeated click,
 * which is how a knob nudged from 7 to 12 came to cost five full layouts of the
 * booklet, each one freezing the browser under the finger trying to make the
 * next.
 */
export const RENDER_DELAY_MIN_MS = 400;

/** ...and the longest, so a heavy booklet still answers within reason. */
export const RENDER_DELAY_MAX_MS = 1200;

/**
 * How long to wait, after a change, before laying the booklet out again.
 *
 * Taken from what the last layout actually cost, because that is what the wait
 * is really for. Cheap booklets — a few sheets of text — redraw almost at the
 * touch, while thirty engraved scores lock the browser for the best part of a
 * second, and a gap that does not grow with them is a gap that swallows clicks.
 *
 * @param {number} lastRenderMs what the previous layout run took, 0 if none yet
 */
export function renderDelayFor(lastRenderMs) {
    const measured = Number(lastRenderMs);

    if (!Number.isFinite(measured) || measured <= RENDER_DELAY_MIN_MS) {
        return RENDER_DELAY_MIN_MS;
    }

    return Math.min(RENDER_DELAY_MAX_MS, Math.round(measured));
}

/**
 * Whether the pages on screen are still the ones the booklet describes.
 *
 * Typesetting a booklet is quick, and that is the problem: a page size changed,
 * the whole thing was laid out again, and the only evidence is a preview that
 * looks much as it did — so it is impossible to tell a finished re-render from
 * one that never happened. This is the flag that says it out loud.
 *
 * Two things make it trustworthy. It goes up when a change is *made*, not when
 * the layout run finally starts, so the round trip to the server and the render
 * debounce are inside it rather than a silent gap before it. And it stays up for
 * a while after every change, because an indicator that appears for nine
 * milliseconds is the same as no indicator at all.
 */

/**
 * How long the flag stays up after a change.
 *
 * Long enough to be seen and believed; short enough not to feel like a wait —
 * and longer than the gap between two arrow-key steps, so a knob being walked
 * to a value reads as one stretch of work rather than a stutter.
 */
export const BUSY_MIN_VISIBLE_MS = 450;

/**
 * When to stop believing in work that was announced.
 *
 * The flag is raised by the change, but only a finished render lowers it, and
 * the two are not the same event: a request that fails, or a payload that comes
 * back with nothing to redraw, would otherwise leave the preview dimmed for
 * good. Generous, because a booklet of engraved scores is slow to lay out.
 */
export const BUSY_WATCHDOG_MS = 15000;

/**
 * @param {object} options
 * @param {(busy: boolean) => void} options.onChange told whenever the flag flips
 * @param {number} [options.minVisibleMs]
 * @param {number} [options.watchdogMs]
 * @param {() => number} [options.now]
 * @param {(fn: Function, ms: number) => *} [options.setTimer]
 * @param {(handle: *) => void} [options.clearTimer]
 */
export function createBusyFlag(options = {}) {
    const {
        onChange = () => {},
        minVisibleMs = BUSY_MIN_VISIBLE_MS,
        watchdogMs = BUSY_WATCHDOG_MS,
        now = () => Date.now(),
        setTimer = (fn, ms) => setTimeout(fn, ms),
        clearTimer = (handle) => clearTimeout(handle),
    } = options;

    let busy = false;
    let raisedAt = 0;
    let settleTimer = null;
    let watchdogTimer = null;

    function clearTimers() {
        clearTimer(settleTimer);
        clearTimer(watchdogTimer);
        settleTimer = null;
        watchdogTimer = null;
    }

    function set(value) {
        if (busy === value) { return; }

        busy = value;
        onChange(busy);
    }

    return {
        get busy() { return busy; },

        /**
         * Something changed: the pages on screen are now behind the booklet, and
         * stay so for at least the minimum from this moment — not from whenever
         * the flag first went up. A staff size walked from 7 to 12 with the
         * arrow keys is five changes and five layout runs, and a flag measured
         * from the first of them would blink out somewhere in the middle.
         */
        start() {
            clearTimers();

            raisedAt = now();
            set(true);

            watchdogTimer = setTimer(() => {
                clearTimers();
                set(false);
            }, watchdogMs);
        },

        /** The pages on screen are current again — as soon as they have been seen to change. */
        settle() {
            clearTimers();

            if (!busy) { return; }

            const remaining = Math.max(0, minVisibleMs - (now() - raisedAt));

            if (remaining === 0) {
                set(false);

                return;
            }

            settleTimer = setTimer(() => {
                settleTimer = null;
                set(false);
            }, remaining);
        },

        /** The editor is going away; no timer may outlive it. */
        stop() {
            clearTimers();
            set(false);
        },
    };
}
