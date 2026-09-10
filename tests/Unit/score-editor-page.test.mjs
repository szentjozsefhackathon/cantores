import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

import {
    DEFAULT_LYRIC_SIZE_PT,
    DEFAULT_PAGE_WIDTH_MM,
    DEFAULT_STAFF_HEIGHT_MM,
    DEFAULT_TEXT_FONT,
    mmToPx,
    opticalLyricSizePt,
    ptForAbcLyricSize,
    ptForChordproFontSize,
    ptForGabcLyricSize,
    staffHeightMmForAbcPageScale,
    staffHeightMmForGabcStaffSize,
} from '../../resources/js/booklet-geometry.js';
import { abcMixin } from '../../resources/js/score-editor-abc.js';
import { aretinoMixin } from '../../resources/js/score-editor-aretino.js';
import { chordproMixin } from '../../resources/js/score-editor-chordpro.js';
import { gabcMixin, normalizeGabcLayoutWidth } from '../../resources/js/score-editor-gabc.js';

const close = (actual, expected, tolerance = 0.01) => assert.ok(
    Math.abs(actual - expected) <= tolerance,
    `expected ${actual} to be within ${tolerance} of ${expected}`,
);

/*
 * The four editors used to engrave on four different pages — Aretino on 170 mm
 * of real paper, GABC on a nominal 1920 units and ABC on 1700, which come to
 * 508 mm and 450 mm once a score's user unit is read as the CSS pixel every
 * exporter reads it as. So a staff size in one editor was no particular height
 * in another, and only a booklet, which converts all of them, could put two
 * scores side by side at the same size.
 *
 * They now open on one page at one size. This is the test that says so.
 */

test('every editor opens on the same page', () => {
    // To the hundredth of a unit, so the toolbar's millimetres come back whole.
    const pageWidthPx = Math.round(mmToPx(DEFAULT_PAGE_WIDTH_MM) * 100) / 100;

    assert.equal(aretinoMixin().aretinoStaffWidth, DEFAULT_PAGE_WIDTH_MM);
    assert.equal(abcMixin().abcPageWidth, pageWidthPx);
    assert.equal(gabcMixin().gabcLayoutWidth, pageWidthPx);

    const editor = readFileSync(new URL('../../resources/js/score-editor.js', import.meta.url), 'utf8');
    assert.match(editor, /const SCORE_PAGE_WIDTH_PX = Math\.round\(mmToPx\(DEFAULT_PAGE_WIDTH_MM\)\);/);
});

// Every one of them states that page in millimetres, and a width typed in whole
// millimetres has to come back out of the toolbar whole rather than as 170.13.
test('each width knob round-trips a whole number of millimetres', () => {
    assert.equal(normalizeGabcLayoutWidth(mmToPx(DEFAULT_PAGE_WIDTH_MM)), gabcMixin().gabcLayoutWidth);

    for (const blade of ['score-editor', 'score-view', 'public-score-view']) {
        const toolbar = readFileSync(
            new URL(`../../resources/views/livewire/pages/${blade}.blade.php`, import.meta.url),
            'utf8',
        );
        assert.match(toolbar, /x-model="gabcLayoutWidthMm"/, blade);
        assert.match(toolbar, /x-model="abcPageWidthMm"/, blade);
    }

    // The knob sets the page the chant's lines are broken at, so the layout the
    // preview asks exsurge for is that width and not a fixed canvas.
    const editor = readFileSync(new URL('../../resources/js/score-editor.js', import.meta.url), 'utf8');
    assert.match(editor, /return \{ width: normalizeGabcLayoutWidth\(this\.gabcLayoutWidth\), height: null \};/);
});

test('every engraved editor opens with a six millimetre staff', () => {
    close(staffHeightMmForGabcStaffSize(gabcMixin().staffSize), DEFAULT_STAFF_HEIGHT_MM, 0.005);
    close(staffHeightMmForAbcPageScale(abcMixin().abcPageScale), DEFAULT_STAFF_HEIGHT_MM, 0.005);
    assert.equal(aretinoMixin().aretinoStaffSize, DEFAULT_STAFF_HEIGHT_MM);
});

test('every editor opens with lyrics that read the same height', () => {
    close(ptForGabcLyricSize(gabcMixin().lyricSize), DEFAULT_LYRIC_SIZE_PT, 0.005);
    close(ptForAbcLyricSize(abcMixin().abcLyricSize), DEFAULT_LYRIC_SIZE_PT, 0.005);
    assert.equal(aretinoMixin().aretinoLyricSize, DEFAULT_LYRIC_SIZE_PT);

    // ChordPro is the exception that proves the rule. It is set smaller because
    // it is set in a larger-looking face, and comes out the same height of
    // letter as the other three.
    const chordpro = chordproMixin();
    close(
        ptForChordproFontSize(chordpro.chordproFontSize),
        opticalLyricSizePt(DEFAULT_LYRIC_SIZE_PT, chordpro.chordproFontFamily),
        0.005,
    );
    close(ptForChordproFontSize(chordpro.chordproFontSize), 9, 0.05);
});

test('the engraved formats open in the default face, the chord sheet in the screen one', () => {
    assert.equal(gabcMixin().lyricFont, `'${DEFAULT_TEXT_FONT}'`);
    assert.equal(abcMixin().abcLyricFont, DEFAULT_TEXT_FONT);
    assert.equal(aretinoMixin().aretinoTextFont, `'${DEFAULT_TEXT_FONT}'`);
    assert.equal(chordproMixin().chordproFontFamily, "'Merriweather'");
});

// The preview is the printed page, so the zoom is a magnifying glass over it
// rather than the size the score is engraved at — and every editor opens with
// the same magnification, because life size on a monitor is too small to work in.
test('every paper preview opens at the same magnification', () => {
    assert.equal(gabcMixin().zoom, 120);
    assert.equal(abcMixin().abcZoom, 120);
    assert.equal(aretinoMixin().aretinoZoom, 120);
});
