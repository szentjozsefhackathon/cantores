import assert from 'node:assert/strict';
import test from 'node:test';

import { pageGeometry, mmToPx } from '../../resources/js/booklet-geometry.js';
import { innerMarkupOf, scopePageIds, stripPlacements, windowedPageSvg } from '../../resources/js/booklet-render.js';

const geometry = pageGeometry({
    pageWidthMm: 148,
    pageHeightMm: 210,
    marginMm: 12,
    contentWidthMm: 124,
    contentHeightMm: 186,
    lyricSizePt: 11,
    staffHeightMm: 7,
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

// The one knob a picture has. It multiplies the fit-to-width scale rather than
// replacing it, so the file stays as wide as it is tall and every system of it
// still shrinks by the same factor.
test('a file taken down by hand shrinks every one of its systems alike', () => {
    const full = stripPlacements(strips, geometry);
    const smaller = stripPlacements(strips, geometry, { zoom: 0.6 });

    smaller.forEach((placement, i) => {
        assert.ok(Math.abs(placement.scale - full[i].scale * 0.6) < 1e-9);
        assert.ok(Math.abs(placement.height - full[i].height * 0.6) < 1e-9);
    });
});

test('a zoom that says nothing leaves the file at the width of the page', () => {
    const full = stripPlacements(strips, geometry)[0];

    for (const zoom of [undefined, null, 0, NaN, 1]) {
        assert.equal(stripPlacements(strips, geometry, { zoom })[0].scale, full.scale);
    }
});

test('the first system carries the score gap, the rest a system gap', () => {
    const placements = stripPlacements(strips, geometry);

    assert.ok(Math.abs(placements[0].spaceBefore - mmToPx(3)) < 1e-9);
    assert.ok(Math.abs(placements[1].spaceBefore - mmToPx(3)) < 1e-9);
    assert.ok(Math.abs(placements[2].spaceBefore - mmToPx(3)) < 1e-9);
});

test('a heading above the music takes the gap over from the first system', () => {
    const [first] = stripPlacements(strips, geometry, { afterHeading: true });

    assert.ok(Math.abs(first.spaceBefore - mmToPx(1.5)) < 1e-9);
    assert.equal(first.startsScore, false);
    assert.equal(first.breakBefore, false);
});

// The gap under a heading is part of the heading, so it answers to the booklet's
// heading scale exactly as the type does.
test('the gap under a heading shrinks with the heading', () => {
    const [first] = stripPlacements(strips, { ...geometry, headingScale: 0.6 }, { afterHeading: true });

    assert.ok(Math.abs(first.spaceBefore - mmToPx(1.5) * 0.6) < 1e-9);
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

// A vector file's strips carry point-valued width/height and a rect; the
// placement arithmetic only uses their ratios, so it is unchanged.
test('point-valued systems scale by the same factor as pixel ones', () => {
    const vector = [
        { width: 452.3, height: 61.1, rect: '40 55 452.3 61.1' },
        { width: 452.3, height: 44.7, rect: '40 130 452.3 44.7' },
    ];
    const placements = stripPlacements(vector, geometry);

    assert.deepEqual(
        [...new Set(placements.map((placement) => placement.scale))],
        [geometry.contentWidthPx / 452.3],
    );
    assert.ok(Math.abs(placements[0].height - 61.1 * placements[0].scale) < 1e-9);
});

// The system is a window onto the page: the wrapper's inner <svg> states the
// rectangle as its viewBox and clips to it, and carries the marker the export
// swaps for a placeholder.
test('the vector wrapper is the page clipped to the system rectangle', () => {
    const page = '<?xml version="1.0"?>\n<svg xmlns="http://www.w3.org/2000/svg" '
        + 'width="595pt" height="842pt" viewBox="0 0 595 842"><g id="p1"><path d="M0 0"/></g></svg>';

    const svg = windowedPageSvg(
        { pageSvg: page, width: 452.3, height: 61.1, rect: '40 55 452.3 61.1', page: 2 },
        { fileId: 7 },
    );

    assert.match(svg, /<svg viewBox="40 55 452.3 61.1"[^>]*overflow="hidden"/);
    assert.match(svg, /data-score-page="7"/);
    assert.match(svg, /data-page="2"/);
    assert.match(svg, /data-rect="40 55 452.3 61.1"/);
    // The page's ids are scoped to this file and page.
    assert.match(svg, /<g id="sp7_2-p1"><path d="M0 0"\/><\/g>/);
    assert.doesNotMatch(svg, /<\?xml/);
});

test('innerMarkupOf drops the svg root and keeps its children', () => {
    assert.equal(
        innerMarkupOf('<?xml version="1.0"?><svg xmlns="x" viewBox="0 0 1 1"><g/></svg>\n'),
        '<g/>',
    );
});

test('scopePageIds prefixes every id and every reference to one', () => {
    const scoped = scopePageIds(
        '<symbol id="glyph0-1"/><use xlink:href="#glyph0-1"/><g clip-path="url(#clip1)"/><clipPath id="clip1"/>',
        'sp3_1',
    );

    assert.match(scoped, /id="sp3_1-glyph0-1"/);
    assert.match(scoped, /href="#sp3_1-glyph0-1"/);
    assert.match(scoped, /url\(#sp3_1-clip1\)/);
    assert.match(scoped, /id="sp3_1-clip1"/);
});
