import assert from 'node:assert/strict';
import test from 'node:test';

import {
    BUSY_MIN_VISIBLE_MS,
    BUSY_WATCHDOG_MS,
    createBusyFlag,
    layoutSignature,
    RENDER_DELAY_MAX_MS,
    RENDER_DELAY_MIN_MS,
    renderDelayFor,
} from '../../resources/js/booklet-pacing.js';

// A stepper arrow clicked deliberately, over and over, comes round about this
// often. A layout run started inside that gap is a layout run started under the
// finger reaching for the next click.
const REPEAT_CLICK_MS = 350;

test('a change is left to settle for longer than the gap between two clicks', () => {
    assert.ok(
        RENDER_DELAY_MIN_MS > REPEAT_CLICK_MS,
        'the second of two clicks would land after the booklet had already begun to redraw',
    );
});

test('a booklet that redraws in no time is redrawn at the shortest wait', () => {
    assert.equal(renderDelayFor(0), RENDER_DELAY_MIN_MS);
    assert.equal(renderDelayFor(12), RENDER_DELAY_MIN_MS);
    assert.equal(renderDelayFor(RENDER_DELAY_MIN_MS), RENDER_DELAY_MIN_MS);
});

test('a booklet that is slow to lay out earns a wait as long as it takes', () => {
    assert.equal(renderDelayFor(RENDER_DELAY_MIN_MS + 200), RENDER_DELAY_MIN_MS + 200);
    assert.equal(renderDelayFor(742.6), 743);
});

test('however slow it is, the preview still answers within reason', () => {
    assert.equal(renderDelayFor(9000), RENDER_DELAY_MAX_MS);
});

test('a layout that was never timed does not stall the next one', () => {
    assert.equal(renderDelayFor(undefined), RENDER_DELAY_MIN_MS);
    assert.equal(renderDelayFor(Number.NaN), RENDER_DELAY_MIN_MS);
    assert.equal(renderDelayFor(-50), RENDER_DELAY_MIN_MS);
});

/** A clock the test winds by hand, with the timers hanging off it. */
function fakeClock() {
    let time = 0;
    let nextHandle = 1;
    const timers = new Map();

    return {
        now: () => time,
        setTimer(fn, ms) {
            const handle = nextHandle++;
            timers.set(handle, { at: time + ms, fn });

            return handle;
        },
        clearTimer(handle) { timers.delete(handle); },
        pending: () => timers.size,
        advance(ms) {
            time += ms;

            [...timers.entries()]
                .filter(([, timer]) => timer.at <= time)
                .sort((a, b) => a[1].at - b[1].at)
                .forEach(([handle, timer]) => {
                    timers.delete(handle);
                    timer.fn();
                });
        },
    };
}

function flagOn(clock, options = {}) {
    const changes = [];
    const flag = createBusyFlag({
        onChange: (busy) => changes.push(busy),
        now: clock.now,
        setTimer: clock.setTimer,
        clearTimer: clock.clearTimer,
        ...options,
    });

    return { flag, changes };
}

test('the change raises the flag at once, before anything is laid out', () => {
    const clock = fakeClock();
    const { flag, changes } = flagOn(clock);

    flag.start();

    assert.equal(flag.busy, true);
    assert.deepEqual(changes, [true]);
});

test('a render that finishes instantly still leaves the flag up long enough to read', () => {
    const clock = fakeClock();
    const { flag } = flagOn(clock);

    flag.start();
    clock.advance(20);
    flag.settle();

    assert.equal(flag.busy, true, 'a twenty millisecond flicker is no indicator at all');

    clock.advance(BUSY_MIN_VISIBLE_MS - 20 - 1);
    assert.equal(flag.busy, true);

    clock.advance(1);
    assert.equal(flag.busy, false);
});

test('a render longer than the minimum lowers the flag the moment it is done', () => {
    const clock = fakeClock();
    const { flag, changes } = flagOn(clock);

    flag.start();
    clock.advance(BUSY_MIN_VISIBLE_MS + 500);
    flag.settle();

    assert.equal(flag.busy, false);
    assert.deepEqual(changes, [true, false]);
});

test('a knob nudged over and over is one stretch of work, not a flicker per step', () => {
    const clock = fakeClock();
    const { flag, changes } = flagOn(clock);

    flag.start();

    // One arrow-key step: the change, the render that follows it, a pause too
    // short to reach for the key again — over and over, well past the minimum.
    for (let step = 0; step < 5; step++) {
        clock.advance(120);
        flag.settle();
        clock.advance(180);
        flag.start();
    }

    assert.equal(flag.busy, true);
    assert.deepEqual(changes, [true], 'the flag never went down between the steps');

    clock.advance(600);
    flag.settle();

    assert.equal(flag.busy, false);
});

test('the minimum runs from the latest change, so a long session of them never blinks', () => {
    const clock = fakeClock();
    const { flag, changes } = flagOn(clock);

    flag.start();
    clock.advance(BUSY_MIN_VISIBLE_MS * 3);
    flag.start();
    flag.settle();

    assert.equal(flag.busy, true, 'this change is only just made, however old the last one is');
    assert.deepEqual(changes, [true]);

    clock.advance(BUSY_MIN_VISIBLE_MS);
    assert.equal(flag.busy, false);
});

test('work that is announced but never finishes is given up on', () => {
    const clock = fakeClock();
    const { flag } = flagOn(clock);

    flag.start();
    clock.advance(BUSY_WATCHDOG_MS - 1);

    assert.equal(flag.busy, true);

    clock.advance(1);
    assert.equal(flag.busy, false);
    assert.equal(clock.pending(), 0, 'nothing is left ticking');
});

test('settling something that was never started changes nothing', () => {
    const clock = fakeClock();
    const { flag, changes } = flagOn(clock);

    flag.settle();

    assert.equal(flag.busy, false);
    assert.deepEqual(changes, []);
    assert.equal(clock.pending(), 0);
});

test('an editor that goes away takes its timers with it', () => {
    const clock = fakeClock();
    const { flag, changes } = flagOn(clock);

    flag.start();
    flag.stop();

    assert.equal(flag.busy, false);
    assert.deepEqual(changes, [true, false]);
    assert.equal(clock.pending(), 0);
});

const GEOMETRY = { pageSize: 'a5', orientation: 'portrait', marginMm: 12, lyricSizePt: 11 };

function booklet(overrides = {}) {
    return [
        { id: 1, kind: 'score', format: 'gabc', content: '(c4) Ky(f)ri(g)e(h)', settings: {}, override: {} },
        { id: 2, kind: 'text', text: 'Kezdésre', startOnNewPage: false },
        { id: 3, kind: 'score', format: 'chordpro', content: '[C]Áldjuk', settings: {}, override: { ...overrides } },
    ];
}

/** What the server hands back: the same booklet, through JSON and home again. */
function roundTrip(entries) {
    return JSON.parse(JSON.stringify(entries));
}

test('a booklet handed back unchanged is the one already on screen', () => {
    const entries = booklet({ staffHeight: 6 });

    assert.equal(
        layoutSignature(roundTrip(entries), { ...GEOMETRY }),
        layoutSignature(entries, GEOMETRY),
    );
});

test('an override written down in another order is still the same booklet', () => {
    // The browser adds each knob as it is touched; the server lists them the
    // way the panel does, and the two orders need not agree.
    const turned = booklet({ staffHeight: 6, width: 120 });
    const saved = booklet({ width: 120, staffHeight: 6 });

    assert.equal(layoutSignature(saved, GEOMETRY), layoutSignature(turned, GEOMETRY));
});

test('a knob that actually moved makes a booklet that must be drawn again', () => {
    assert.notEqual(
        layoutSignature(booklet({ staffHeight: 7 }), GEOMETRY),
        layoutSignature(booklet({ staffHeight: 6 }), GEOMETRY),
    );
});

test('a knob put back to the booklet default is a change like any other', () => {
    assert.notEqual(
        layoutSignature(booklet(), GEOMETRY),
        layoutSignature(booklet({ staffHeight: 6 }), GEOMETRY),
    );
});

test('a page the booklet is laid out onto is part of what was drawn', () => {
    assert.notEqual(
        layoutSignature(booklet(), { ...GEOMETRY, lyricSizePt: 12 }),
        layoutSignature(booklet(), GEOMETRY),
    );
});

test('a score moved to another place in the booklet is drawn again', () => {
    const entries = booklet();
    const moved = [entries[1], entries[0], entries[2]];

    assert.notEqual(layoutSignature(moved, GEOMETRY), layoutSignature(entries, GEOMETRY));
});

test('a score added or taken out is drawn again', () => {
    assert.notEqual(
        layoutSignature(booklet().slice(1), GEOMETRY),
        layoutSignature(booklet(), GEOMETRY),
    );
});
