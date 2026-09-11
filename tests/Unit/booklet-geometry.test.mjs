import assert from 'node:assert/strict';
import test from 'node:test';

import {
    DEFAULT_ABC_STAFF_SEP,
    abcLyricSizeForPt,
    abcPageScaleForStaffHeight,
    aretinoLyricSizeForPt,
    aretinoStaffSizeForStaffHeight,
    chordproFontSizeForPt,
    gabcLyricSizeForPt,
    gabcStaffSizeForStaffHeight,
    mmToPx,
    leadingScale,
    opticalLyricSizePt,
    opticalSizeFactor,
    REFERENCE_TEXT_FONT,
    pageGeometry,
    ptToPx,
    pxToMm,
    pxToPt,
    quoteFontFamily,
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

test('points convert back from pixels at the same rate', () => {
    close(pxToPt(96), 72);
    close(pxToPt(ptToPx(12)), 12);
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
    });

    close(geometry.pageWidthPx, 559.37, 0.01);
    close(geometry.pageHeightPx, 793.7, 0.01);
    close(geometry.contentWidthPx, 468.66, 0.01);
    close(geometry.marginPx, 45.35, 0.01);
    close(geometry.lyricSizePx, 14.6667, 1e-4);
});

// A family with a space in it, written unquoted into a font-family attribute,
// is two families neither of which exists — and the booklet's own face is the
// one every heading and every rubric is set in.
test('the booklet\'s text font reaches the page quoted', () => {
    assert.equal(quoteFontFamily('EB Garamond'), "'EB Garamond'");
    assert.equal(quoteFontFamily("'Merriweather'"), "'Merriweather'");
    assert.equal(quoteFontFamily(null), "'Alegreya'");
    assert.equal(quoteFontFamily('  '), "'Alegreya'");
});

test('the typography a booklet chose travels with its geometry', () => {
    const geometry = pageGeometry({
        pageWidthMm: 148,
        pageHeightMm: 210,
        marginMm: 12,
        contentWidthMm: 124,
        contentHeightMm: 186,
        lyricSizePt: 11,
        staffHeightMm: 7,
        textFont: 'Merriweather',
        headingScale: 0.8,
        abcStaffSep: 32,
    });

    assert.equal(geometry.textFont, "'Merriweather'");
    assert.equal(geometry.headingScale, 0.8);
    assert.equal(geometry.abcStaffSep, 32);
});

// Nothing is not the same as zero here: a booklet asking for no separation at
// all is asking for it, and must not be handed the default instead.
test('an ABC separation of zero is a decision, not a missing one', () => {
    const geometry = pageGeometry({
        pageWidthMm: 148, pageHeightMm: 210, marginMm: 12,
        contentWidthMm: 124, contentHeightMm: 186,
        lyricSizePt: 11, staffHeightMm: 7, abcStaffSep: 0,
    });

    assert.equal(geometry.abcStaffSep, 0);
});

// An older booklet, or a payload written before these existed, must still draw.
test('a geometry that says nothing about type falls back to the default face at full size', () => {
    const geometry = pageGeometry({
        pageWidthMm: 148, pageHeightMm: 210, marginMm: 12,
        contentWidthMm: 124, contentHeightMm: 186,
        lyricSizePt: 11, staffHeightMm: 7,
    });

    assert.equal(geometry.textFont, "'Alegreya'");
    assert.equal(geometry.headingScale, 1);
    assert.equal(geometry.abcStaffSep, DEFAULT_ABC_STAFF_SEP);
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

// A point measures the em square, which is not what anybody looks at. Two faces
// set at one size read as two sizes, so a booklet quoting one lyric size for a
// pile of scores has to restate it in whichever face the booklet is set in.
test('a size quoted in the reference face is restated in the face it is set in', () => {
    assert.equal(opticalSizeFactor(REFERENCE_TEXT_FONT), 1);
    assert.equal(opticalSizeFactor("'Alegreya'"), 1);

    // The pairings judged by eye against 11 pt of Alegreya.
    close(opticalLyricSizePt(11, 'Merriweather'), 9, 0.05);
    close(opticalLyricSizePt(11, "'EB Garamond'"), 11.5, 0.05);
    // And the two sans faces, which are set beside the serifs on a slide.
    close(opticalLyricSizePt(11, 'Inter'), 9.1, 0.05);
    close(opticalLyricSizePt(11, "'Barlow Condensed'"), 9.8, 0.05);
});

// A fallback stack, or a face nobody has measured, is likelier to be deliberate
// than a mistake, and guessing at a factor for it would be the wrong kind of
// helpful.
test('an unmeasured face is left at the size it was asked for', () => {
    assert.equal(opticalSizeFactor('Comic Sans MS, cursive'), 1);
    assert.equal(opticalLyricSizePt(11, undefined), 11);
});

test('the booklet hands its renderers the compensated size, and remembers the one it was given', () => {
    const geometry = (textFont) => pageGeometry({
        pageWidthMm: 148, pageHeightMm: 210, marginMm: 12,
        contentWidthMm: 124, contentHeightMm: 186,
        lyricSizePt: 11, staffHeightMm: 6, textFont,
    });

    close(geometry('Alegreya').lyricSizePt, 11);
    close(geometry('Merriweather').lyricSizePt, 9, 0.05);
    assert.equal(geometry('Merriweather').nominalLyricSizePt, 11);
    close(geometry('Merriweather').lyricSizePx, ptToPx(geometry('Merriweather').lyricSizePt));
});

// Holding the x-height constant hands each face a different em, and leading
// measured in that em follows the face instead of the eye: Alegreya's lines
// would stand a fifth further apart than Merriweather's for letters of the same
// apparent height. So the leading is measured in the nominal size, and a face
// set small gets the space a face set large would have taken.
test('the leading is measured in the size the booklet quoted, not the size it is set at', () => {
    assert.equal(leadingScale(REFERENCE_TEXT_FONT), 1);
    assert.equal(leadingScale('Comic Sans MS, cursive'), 1);

    const geometry = (textFont) => pageGeometry({
        pageWidthMm: 148, pageHeightMm: 210, marginMm: 12,
        contentWidthMm: 124, contentHeightMm: 186,
        lyricSizePt: 11, staffHeightMm: 6, textFont,
    });

    ['Alegreya', 'Merriweather', 'EB Garamond', 'Inter', 'Barlow Condensed'].forEach((font) => {
        close(geometry(font).lyricSizePx * geometry(font).leadingScale, ptToPx(11), 1e-9);
    });
});

test('preserves zero and sub-half staff to lyric clearance in booklet geometry', () => {
    for (const value of [0, '0', 0.1, 0.4]) {
        assert.equal(pageGeometry({ abcLyricFirstSkip: value }).abcLyricFirstSkip, Number(value));
    }
});
