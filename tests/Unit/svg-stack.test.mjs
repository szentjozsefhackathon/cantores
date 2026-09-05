import assert from 'node:assert/strict';
import test from 'node:test';

import {
    placementTransform,
    stackPlacements,
    stackedExtent,
    viewBoxOf,
} from '../../resources/js/svg-stack.js';

function fakeSvg(viewBox) {
    return {
        getAttribute: (name) => (name === 'viewBox' ? viewBox : null),
    };
}

test('viewBoxOf reads a viewBox as a box', () => {
    assert.deepEqual(viewBoxOf(fakeSvg('0 0 640 120')), { x: 0, y: 0, w: 640, h: 120 });
});

test('viewBoxOf keeps a non-zero origin', () => {
    assert.deepEqual(viewBoxOf(fakeSvg('-4 -12.5 640 120')), { x: -4, y: -12.5, w: 640, h: 120 });
});

test('viewBoxOf accepts a comma-separated viewBox', () => {
    assert.deepEqual(viewBoxOf(fakeSvg('0,0,640,120')), { x: 0, y: 0, w: 640, h: 120 });
});

test('viewBoxOf falls back for a fragment that declares none', () => {
    assert.deepEqual(viewBoxOf(fakeSvg(null)), { x: 0, y: 0, w: 1920, h: 200 });
    assert.deepEqual(viewBoxOf(fakeSvg('0 0 nonsense')), { x: 0, y: 0, w: 1920, h: 200 });
});

// The stacking arithmetic the editor's buildMergedSvg and mergeAbcSvgsToElement
// both did inline: each fragment sits directly below the previous one, the
// combined width is the widest fragment and the height is their sum.
test('stackPlacements puts each fragment under the last', () => {
    const boxes = [{ w: 640, h: 100 }, { w: 640, h: 80 }, { w: 700, h: 50 }];

    assert.deepEqual(stackPlacements(boxes), [
        { x: 0, y: 0, scale: 1 },
        { x: 0, y: 100, scale: 1 },
        { x: 0, y: 180, scale: 1 },
    ]);
});

test('stackedExtent is the widest fragment by the total height', () => {
    const boxes = [{ w: 640, h: 100 }, { w: 640, h: 80 }, { w: 700, h: 50 }];

    assert.deepEqual(stackedExtent(boxes, stackPlacements(boxes)), { width: 700, height: 230 });
});

test('stackedExtent accounts for a scaled fragment', () => {
    const boxes = [{ w: 800, h: 100 }];
    const placements = [{ x: 0, y: 20, scale: 0.5 }];

    assert.deepEqual(stackedExtent(boxes, placements), { width: 400, height: 70 });
});

// Byte-for-byte the transform the editor emitted — `translate(-x yOffset-y)`,
// with no scale term — so the extraction cannot silently move existing output.
test('placementTransform matches the unscaled transform the editor emitted', () => {
    assert.equal(
        placementTransform({ x: 0, y: 0, w: 640, h: 100 }, { x: 0, y: 180, scale: 1 }),
        'translate(0 180)',
    );
    assert.equal(
        placementTransform({ x: -4, y: -12, w: 640, h: 100 }, { x: 0, y: 100, scale: 1 }),
        'translate(4 112)',
    );
});

test('placementTransform adds a scale only when the fragment is scaled', () => {
    assert.equal(
        placementTransform({ x: 0, y: 0, w: 800, h: 100 }, { x: 10, y: 20, scale: 0.5 }),
        'translate(10 20) scale(0.5)',
    );
    assert.equal(
        placementTransform({ x: 6, y: 4, w: 800, h: 100 }, { x: 0, y: 0, scale: 0.5 }),
        'translate(-3 -2) scale(0.5)',
    );
});
