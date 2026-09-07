import assert from 'node:assert/strict';
import test from 'node:test';

import { pageGeometry, mmToPx } from '../../resources/js/booklet-geometry.js';
import { stripPlacements } from '../../resources/js/booklet-render.js';

const geometry = pageGeometry({
    pageWidthMm: 148,
    pageHeightMm: 210,
    marginMm: 12,
    contentWidthMm: 124,
    contentHeightMm: 186,
    lyricSizePt: 11,
    staffHeightMm: 7,
    showTitles: true,
});

/** Three systems cut from one page: same width, differing heights. */
const strips = [
    { width: 2032, height: 350 },
    { width: 2032, height: 160 },
    { width: 2032, height: 420 },
];

test('every system of a file is scaled by the same factor', () => {
    const placements = stripPlacements(strips, geometry);

    assert.equal(placements.length, 3);
    assert.deepEqual(
        [...new Set(placements.map((placement) => placement.scale))],
        [geometry.contentWidthPx / 2032],
    );
});

// The point of cutting the pages up: a run of systems has to be free to break
// across a page turn, or the booklet is back to placing whole pages.
test('systems are not glued to one another', () => {
    for (const placement of stripPlacements(strips, geometry)) {
        assert.equal(placement.keepWithNext, false);
    }
});

test('a system is as tall as its share of the page width makes it', () => {
    const [first] = stripPlacements(strips, geometry);
    const scale = geometry.contentWidthPx / 2032;

    assert.ok(Math.abs(first.height - 350 * scale) < 1e-9);
});

test('the widest system sets the scale, so a short one is not blown up', () => {
    const ragged = [{ width: 2032, height: 300 }, { width: 900, height: 120 }];
    const placements = stripPlacements(ragged, geometry);

    assert.equal(placements[0].scale, geometry.contentWidthPx / 2032);
    assert.equal(placements[1].scale, placements[0].scale);
    assert.ok(placements[1].height < placements[0].height);
});

test('the first system carries the score gap, the rest a system gap', () => {
    const placements = stripPlacements(strips, geometry);

    assert.ok(Math.abs(placements[0].spaceBefore - mmToPx(6)) < 1e-9);
    assert.ok(Math.abs(placements[1].spaceBefore - mmToPx(3)) < 1e-9);
    assert.ok(Math.abs(placements[2].spaceBefore - mmToPx(3)) < 1e-9);
});

test('a heading above the music takes the gap over from the first system', () => {
    const [first] = stripPlacements(strips, geometry, { afterHeading: true });

    assert.ok(Math.abs(first.spaceBefore - mmToPx(1.5)) < 1e-9);
    assert.equal(first.startsScore, false);
    assert.equal(first.breakBefore, false);
});

test('a score asked to start a page says so on its first system', () => {
    const placements = stripPlacements(strips, geometry, { startOnNewPage: true });

    assert.equal(placements[0].startsScore, true);
    assert.equal(placements[0].breakBefore, true);
    assert.equal(placements[1].breakBefore, false);
});

// The heading already carries the break in that case, exactly as it does for
// the four engraved formats.
test('a score with a heading leaves the page break to the heading', () => {
    const placements = stripPlacements(strips, geometry, {
        afterHeading: true,
        startOnNewPage: true,
    });

    assert.equal(placements[0].breakBefore, false);
});

test('no systems, no blocks', () => {
    assert.deepEqual(stripPlacements([], geometry), []);
});
