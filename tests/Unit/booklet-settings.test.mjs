import assert from 'node:assert/strict';
import test from 'node:test';

import {
    fileSettings,
    layoutWidthFor,
    paperBucket,
    resolveSettings,
    unifiedSettings,
} from '../../resources/js/booklet-settings.js';
import { pageGeometry } from '../../resources/js/booklet-geometry.js';

const geometry = pageGeometry({
    pageWidthMm: 148,
    pageHeightMm: 210,
    marginMm: 12,
    contentWidthMm: 124,
    contentHeightMm: 186,
    lyricSizePt: 11,
    staffHeightMm: 7,
    abcStaffSep: 25,
});

test('unification owns the width, the sizes and the stacking', () => {
    const unified = unifiedSettings('abc', geometry);

    assert.deepEqual(Object.keys(unified).sort(), [
        'abcLyricSize', 'abcPageRatio', 'abcPageScale', 'abcPageWidth',
        'abcStaffSep', 'abcVocalSpace', 'abcZoom',
    ]);
    assert.equal(unified.abcPageWidth, 468);
    assert.equal(unified.abcPageRatio, 'paper');
});

// The line that keeps a booklet from being a bulldozer: it decides how big
// things are and how tightly they stack, and leaves every other decision with
// whoever engraved the score.
test('unification leaves the author\'s own choices alone', () => {
    const authored = {
        abcLyricFont: "'Lora'",
        abcNoteSpacing: 1.8,
        abcTranspose: 3,
        abcNoClef: true,
    };

    const resolved = resolveSettings('abc', {}, { abc: { paper: authored } }, geometry, null);

    assert.equal(resolved.abcLyricFont, "'Lora'");
    assert.equal(resolved.abcNoteSpacing, 1.8);
    assert.equal(resolved.abcTranspose, 3);
    assert.equal(resolved.abcNoClef, true);
});

// Vertical air a score can afford on its own sheet is what costs a booklet a
// page, and in ABC the space above the first staff is the same setting as the
// space between two — so it is also the gap under a heading.
test('the booklet says how tightly ABC stacks, whatever the score says', () => {
    const authored = { abcStaffSep: 60, abcVocalSpace: 10 };

    const resolved = resolveSettings('abc', {}, { abc: { paper: authored } }, geometry, null);

    assert.equal(resolved.abcStaffSep, 25);
    assert.equal(resolved.abcVocalSpace, 0);

    // And it is the booklet's own number, not a constant.
    const roomy = resolveSettings('abc', {}, {}, { ...geometry, abcStaffSep: 40 }, null);
    assert.equal(roomy.abcStaffSep, 40);
});

test('unification overrules the score\'s own width and sizes', () => {
    const authored = { abcPageWidth: 1700, abcPageScale: 2.3, abcLyricSize: 12 };

    const resolved = resolveSettings('abc', {}, { abc: { paper: authored } }, geometry, null);

    assert.equal(resolved.abcPageWidth, 468);
    assert.notEqual(resolved.abcPageScale, 2.3);
    assert.notEqual(resolved.abcLyricSize, 12);
});

test('a per-score override beats the booklet', () => {
    const resolved = resolveSettings('abc', {}, {}, geometry, { abcPageWidth: 900, abcStaffSep: 20 });

    assert.equal(resolved.abcPageWidth, 900);
    assert.equal(resolved.abcStaffSep, 20);
});

test('format defaults show through where nothing else speaks', () => {
    const resolved = resolveSettings('abc', { abcNoteSpacing: 1.4 }, {}, geometry, null);

    assert.equal(resolved.abcNoteSpacing, 1.4);
});

test('a legacy auto bucket is read like a paper one', () => {
    assert.deepEqual(paperBucket({ gabc: { auto: { lyricSize: 9 } } }, 'gabc'), { lyricSize: 9 });
    // A score carrying both keeps the newer values on top.
    assert.deepEqual(
        paperBucket({ gabc: { auto: { lyricSize: 9, zoom: 80 }, paper: { lyricSize: 11 } } }, 'gabc'),
        { lyricSize: 11, zoom: 80 },
    );
});

test('every format is unified to the same lyric point size', () => {
    assert.equal(unifiedSettings('aretino', geometry).aretinoLyricSize, 11);
    assert.ok(unifiedSettings('chordpro', geometry).chordproFontSize > 0);
    assert.ok(unifiedSettings('gabc', geometry).lyricSize > 0);
    assert.ok(unifiedSettings('abc', geometry).abcLyricSize > 0);
});

test('ChordPro is forced to a single column so the booklet does the packing', () => {
    assert.equal(unifiedSettings('chordpro', geometry).chordproColumns, 1);
});

// An uploaded score has nothing to unify: it is a picture, and the page already
// prints it at full width. The one thing that can be said about it is "smaller".
test('an uploaded score is printed at full size unless taken down by hand', () => {
    assert.deepEqual(fileSettings(null), { fileZoom: 1 });
    assert.deepEqual(fileSettings({}), { fileZoom: 1 });
    assert.deepEqual(fileSettings({ fileZoom: 0.6 }), { fileZoom: 0.6 });
});

test('an unknown format contributes nothing', () => {
    assert.deepEqual(unifiedSettings('musicxml', geometry), {});
});

test('a score laid out at the content width is not scaled', () => {
    const resolved = resolveSettings('abc', {}, {}, geometry, null);

    assert.deepEqual(layoutWidthFor('abc', resolved, geometry), {
        layoutWidthPx: 468,
        scale: 1,
    });
});

// Widening one score is how a bad line break gets fixed: it is laid out on a
// wider page and the result shrinks to fit, so the page still holds.
test('a widened score is laid out wide and scaled back to fit', () => {
    const resolved = resolveSettings('abc', {}, {}, geometry, { abcPageWidth: 700 });
    const { layoutWidthPx, scale } = layoutWidthFor('abc', resolved, geometry);

    assert.equal(layoutWidthPx, 700);
    assert.ok(scale < 1);
    assert.ok(Math.abs(layoutWidthPx * scale - geometry.contentWidthPx) < 1e-6);
});

test('a narrowed score keeps its width and is not scaled up', () => {
    const resolved = resolveSettings('abc', {}, {}, geometry, { abcPageWidth: 300 });

    assert.deepEqual(layoutWidthFor('abc', resolved, geometry), {
        layoutWidthPx: 300,
        scale: 1,
    });
});

test('the width knob is read per format', () => {
    const gabc = resolveSettings('gabc', {}, {}, geometry, { gabcLayoutWidth: 800 });
    assert.equal(layoutWidthFor('gabc', gabc, geometry).layoutWidthPx, 800);

    // Aretino states its width in millimetres, so it converts before comparing.
    const aretino = resolveSettings('aretino', {}, {}, geometry, { aretinoStaffWidth: 248 });
    const { layoutWidthPx, scale } = layoutWidthFor('aretino', aretino, geometry);
    assert.ok(Math.abs(layoutWidthPx - 248 / (25.4 / 96)) < 1e-6);
    assert.ok(Math.abs(scale - 0.5) < 1e-6);
});

test('ChordPro has no width knob and always fills the content box', () => {
    const resolved = resolveSettings('chordpro', {}, {}, geometry, null);

    assert.deepEqual(layoutWidthFor('chordpro', resolved, geometry), {
        layoutWidthPx: geometry.contentWidthPx,
        scale: 1,
    });
});
