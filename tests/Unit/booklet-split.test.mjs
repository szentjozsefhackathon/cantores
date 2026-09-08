import assert from 'node:assert/strict';
import test from 'node:test';

import { beginSplitDrag, clampSplitPercent, splitPercentAt } from '../../resources/js/booklet-split.js';

/** The little of an element the handle drag actually touches. */
function fakeHandle() {
    const listeners = new Map();

    return {
        captured: null,
        listeners,
        setPointerCapture(pointerId) { this.captured = pointerId; },
        addEventListener(type, handler) { listeners.set(type, handler); },
        removeEventListener(type, handler) {
            if (listeners.get(type) === handler) { listeners.delete(type); }
        },
        fire(type, event) { listeners.get(type)?.(event); },
    };
}

const rowOf = (left, width) => ({ getBoundingClientRect: () => ({ left, width }) });

test('the split stays between the two panes', () => {
    assert.equal(clampSplitPercent(45), 45);
    assert.equal(clampSplitPercent(-30), 20);
    assert.equal(clampSplitPercent(150), 80);
    assert.equal(clampSplitPercent(Number.NaN), 60);
    assert.equal(clampSplitPercent(undefined), 60);
});

test('a pointer position becomes the share of the row left of it', () => {
    const box = { left: 100, width: 1000 };

    assert.equal(splitPercentAt(500, box), 40);
    assert.equal(splitPercentAt(700, box), 60);
    assert.equal(splitPercentAt(0, box), 20);
    assert.equal(splitPercentAt(2000, box), 80);
});

test('a row with no width yet cannot answer where the handle is', () => {
    assert.equal(splitPercentAt(500, { left: 0, width: 0 }), null);
    assert.equal(splitPercentAt(500, null), null);
});

test('a drag captures the pointer and reports every move', () => {
    const handle = fakeHandle();
    const moves = [];
    let ended = false;

    beginSplitDrag(handle, rowOf(0, 1000), { pointerId: 7 }, {
        onMove: (percent) => moves.push(percent),
        onEnd: () => { ended = true; },
    });

    assert.equal(handle.captured, 7);

    handle.fire('pointermove', { clientX: 300 });
    handle.fire('pointermove', { clientX: 700 });

    assert.deepEqual(moves, [30, 70]);
    assert.equal(ended, false);

    handle.fire('pointerup', {});

    assert.equal(ended, true);
    assert.equal(handle.listeners.size, 0);
});

test('letting go stops the drag, and later moves are ignored', () => {
    const handle = fakeHandle();
    const moves = [];

    beginSplitDrag(handle, rowOf(0, 1000), { pointerId: 1 }, { onMove: (percent) => moves.push(percent) });

    handle.fire('pointercancel', {});
    handle.fire('pointermove', { clientX: 300 });

    assert.deepEqual(moves, []);
});
