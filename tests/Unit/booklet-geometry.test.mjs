import assert from 'node:assert/strict';
import test from 'node:test';

import {
    abcLyricSizeForPt,
    abcPageScaleForStaffHeight,
    aretinoLyricSizeForPt,
    aretinoStaffSizeForStaffHeight,
    chordproFontSizeForPt,
    gabcLyricSizeForPt,
    gabcStaffSizeForStaffHeight,
    mmToPx,
    pageGeometry,
    ptToPx,
    pxToMm,
} from '../../resources/js/booklet-geometry.js';

const close = (actual, expected, tolerance = 1e-6) => assert.ok(
    Math.abs(actual - expected) <= tolerance,
    `expected ${actual} to be within ${tolerance} of ${expected}`,
);

test('millimetres and pixels convert at 96 dpi', () => {
    close(mmToPx(25.4), 96);
    close(pxToMm(96), 25.4);
    close(pxToMm(mmToPx(148)), 148);
});

test('points convert at 72 to the inch', () => {
    close(ptToPx(72), 96);
    close(ptToPx(11), 14.6667, 1e-4);
});

test('A5 portrait with a 12mm margin becomes the expected pixel box', () => {
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

    close(geometry.pageWidthPx, 559.37, 0.01);
    close(geometry.pageHeightPx, 793.7, 0.01);
    close(geometry.contentWidthPx, 468.66, 0.01);
    close(geometry.marginPx, 45.35, 0.01);
    close(geometry.lyricSizePx, 14.6667, 1e-4);
});

// Each conversion is the inverse of what the renderer does with the number, so
// the check is that a lyric of N points comes out the same rendered pixel height
// in all four engines. That equality is the whole point of the unifier.
test('every format renders one lyric point at the same pixel height', () => {
    const pt = 11;
    const expected = ptToPx(pt);

    close(abcLyricSizeForPt(pt) * 3, expected);
    close(gabcLyricSizeForPt(pt) * (100 / 30) * 1.3, expected);
    close(chordproFontSizeForPt(pt), expected);
    // Aretino is given points directly rather than pixels.
    close(ptToPx(aretinoLyricSizeForPt(pt)), expected);
});

test('every engraved format renders one staff at the same millimetre height', () => {
    const mm = 7;

    // abc2svg: topbar = 6*(5-1) = 24 units, multiplied by %%pagescale.
    close(pxToMm(abcPageScaleForStaffHeight(mm) * 24), mm);
    // exsurge: a four-line staff spans 600 * glyphScaling, and the editor passes
    // glyphScaling = staffSize/480, so the staff is 1.25 * staffSize units.
    close(pxToMm(gabcStaffSizeForStaffHeight(mm) * 1.25), mm);
    // Aretino works in millimetres already.
    close(aretinoStaffSizeForStaffHeight(mm), mm);
});

test('the calibration lands near the values the editor defaults imply', () => {
    // The current ABC default is pagescale 2.3 on a nominal 450mm page; printed
    // scaled-to-fit on A4 that is an effective 2.3 * 210/450 = 1.07. A booklet
    // asking for a 7mm staff should land in the same neighbourhood.
    close(abcPageScaleForStaffHeight(7), 1.102, 0.01);

    // The GABC default is staffSize 80 on a nominal 508mm canvas, which prints
    // as a 10.94mm staff once scaled onto A4. A booklet lays out at true size
    // with no such shrink, so the same staff needs 80 * 210/508 = 33.07.
    close(gabcStaffSizeForStaffHeight(10.94), 80 * 210 / 508, 0.05);
});
