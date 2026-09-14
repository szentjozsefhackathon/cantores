import assert from 'node:assert/strict';
import test from 'node:test';

import {
    abcLyricSizeForPt,
    aretinoLyricSizeForPt,
    chordproFontSizeForPt,
    gabcLyricSizeForPt,
    pageGeometry,
} from '../../resources/js/booklet-geometry.js';
import { movesSetting, READER_SIZE_STEP_PT, readerStep, resolveSettings, steppedValue, textSettings, travellingOverride, unifiedSettings } from '../../resources/js/booklet-settings.js';

const rawGeometry = {
    pageWidthMm: 148,
    pageHeightMm: 210,
    marginMm: 12,
    contentWidthMm: 124,
    contentHeightMm: 186,
    lyricSizePt: 10.5,
    staffHeightMm: 5,
    textFont: 'Merriweather',
    abcLyricFirstSkip: 1.5,
    abcLyricSkip: 1,
};

const geometry = pageGeometry(rawGeometry);

/** Where each engine keeps the face it sets lyrics in. */
const fontKeys = {
    gabc: 'lyricFont',
    abc: 'abcLyricFont',
    chordpro: 'chordproFontFamily',
    aretino: 'aretinoTextFont',
};

test('every format is set in the booklet\'s face, whatever its author chose', () => {
    for (const [format, key] of Object.entries(fontKeys)) {
        const resolved = resolveSettings(
            format,
            { [key]: "'Barlow Condensed'" },
            { [format]: { paper: { [key]: "'Inter'" } } },
            geometry,
            null,
        );

        assert.equal(resolved[key], "'Merriweather'", `${format} kept a face of its own`);
    }
});

test('a face overridden for one score wins over the booklet\'s', () => {
    for (const [format, key] of Object.entries(fontKeys)) {
        const resolved = resolveSettings(format, {}, {}, geometry, { [key]: "'Inter'" });

        assert.equal(resolved[key], "'Inter'", `${format} ignored its override`);
    }
});

test('unifying the face leaves the author\'s other choices alone', () => {
    const resolved = resolveSettings(
        'abc',
        {},
        { abc: { paper: { abcLyricFont: "'Inter'", abcNoteSpacing: 1.4, abcTranspose: -2 } } },
        geometry,
        null,
    );

    assert.equal(resolved.abcNoteSpacing, 1.4);
    assert.equal(resolved.abcTranspose, -2);
});

test('a stored value equal to the inherited one has moved nothing', () => {
    assert.equal(movesSetting(0, 0), false);
    assert.equal(movesSetting(0, undefined), true);
    assert.equal(movesSetting(3, 0), true);
    assert.equal(movesSetting(-3, 0), true);
});

test('numbers are compared as numbers, and loosely', () => {
    assert.equal(movesSetting('12.5', 12.5), false);
    assert.equal(movesSetting(4.6667, 4.66670000001), false);
    assert.equal(movesSetting(4.6667, 4.7), true);
});

test('booleans are compared as booleans', () => {
    assert.equal(movesSetting(false, undefined), false);
    assert.equal(movesSetting(false, false), false);
    assert.equal(movesSetting(true, false), true);
    assert.equal(movesSetting(false, true), true);
});

test('faces are compared unquoted', () => {
    assert.equal(movesSetting("'Merriweather'", 'Merriweather'), false);
    assert.equal(movesSetting("'Merriweather'", "'Inter'"), true);
    assert.equal(movesSetting("'Merriweather'", undefined), true);
});

test('a missing value moves nothing', () => {
    assert.equal(movesSetting(undefined, 3), false);
    assert.equal(movesSetting(null, 3), false);
});

test('a knob a reader steps lands on the step\'s own grid, and stops at its ends', () => {
    const lyricSize = { min: 2, max: 60, step: 0.5 };

    // The size a screen's geometry computed is an arbitrary fraction; pressing
    // bigger tidies it up rather than carrying the fraction along.
    assert.equal(steppedValue(11.9067, lyricSize, 1), 12.5);
    assert.equal(steppedValue(11.9067, lyricSize, -1), 11.5);
    assert.equal(steppedValue(12.5, lyricSize, 1), 13);

    // Nothing to step from — a picture with no size of its own yet — still moves.
    assert.equal(steppedValue(undefined, { min: -11, max: 11, step: 1 }, 1), 1);

    const transpose = { min: -11, max: 11, step: 1 };
    assert.equal(steppedValue(11, transpose, 1), 11);
    assert.equal(steppedValue(-11, transpose, -1), -11);

    // A scan is drawn at the full width of the page and cannot be pushed past it.
    assert.equal(steppedValue(1, { min: 0.2, max: 1, step: 0.05 }, 1), 1);
    assert.equal(steppedValue(1, { min: 0.2, max: 1, step: 0.05 }, -1), 0.95);
});

/*
 * A slide's sizes are held in each engine's own units, where one step is a step
 * of the last decimal — a projection's panel asked for a tenth of what the knob
 * already reads instead, so bigger is visibly bigger whatever unit the size is
 * counted in.
 */
test('a knob stepped by a share of itself moves by that share, and never by less than a step', () => {
    const lyricSize = { min: 2, max: 120, step: 0.5, percent: 10 };

    assert.equal(steppedValue(40, lyricSize, 1), 44);
    assert.equal(steppedValue(44, lyricSize, -1), 40);

    // Snapped to the step, so a press lands on a number of the same shape a
    // typed one has.
    assert.equal(steppedValue(4.6667, lyricSize, 1), 5);

    // A tenth of a small value rounds to nothing, so the step is the floor.
    const staffScale = { min: 0.2, max: 12, step: 0.05, percent: 10 };
    assert.equal(steppedValue(0.3, staffScale, 1), 0.35);
    assert.equal(steppedValue(0.3, staffScale, -1), 0.25);

    // And the ends of the range still hold.
    assert.equal(steppedValue(118, lyricSize, 1), 120);
    assert.equal(steppedValue(2, lyricSize, -1), 2);

    // Nothing to take a share of: the step is what is left.
    assert.equal(steppedValue(0, lyricSize, 1), 2);
});

test('one press of a reader\'s size knob is half a point of type, whatever drew the score', () => {
    // Every format's knob, converted back into points through the same
    // conversions the booklet is laid out with, is the same rise in type.
    const sizes = {
        lyricSize: gabcLyricSizeForPt,
        abcLyricSize: abcLyricSizeForPt,
        aretinoLyricSize: aretinoLyricSizeForPt,
        chordproFontSize: chordproFontSizeForPt,
    };

    for (const [key, toKnobUnit] of Object.entries(sizes)) {
        const step = readerStep({ key, role: 'size', step: 0.5 });

        // Back to points: the conversions are linear, so a step in the knob's
        // unit is READER_SIZE_STEP_PT times the unit's own factor.
        assert.ok(Math.abs(step / toKnobUnit(1) - READER_SIZE_STEP_PT) < 1e-3, `${key} stepped by ${step}`);
    }
});

test('a knob that is not type keeps the step the panel gave it', () => {
    assert.equal(readerStep({ key: 'abcTranspose', role: 'transpose', step: 1 }), 1);
    assert.equal(readerStep({ key: 'fileZoom', role: 'size', step: 0.05 }), 0.05);
    assert.equal(readerStep({ key: 'somethingNew', role: 'size' }), 1);
});

test('a chant and a hymn move by the same amount of type', () => {
    // 10.5pt of lyrics in each format's own unit, stepped once: what the eye
    // sees grow is the same, which is the whole point of converting the step.
    const chant = { key: 'lyricSize', role: 'size', min: 1.5, max: 60, step: 0.5 };
    const hymn = { key: 'chordproFontSize', role: 'size', min: 6, max: 32, step: 0.5 };

    const chantPt = (value) => value / gabcLyricSizeForPt(1);
    const hymnPt = (value) => value / chordproFontSizeForPt(1);

    const chantGrew = chantPt(steppedValue(gabcLyricSizeForPt(10.5), { ...chant, step: readerStep(chant) }, 1)) - 10.5;
    const hymnGrew = hymnPt(steppedValue(chordproFontSizeForPt(10.5), { ...hymn, step: readerStep(hymn) }, 1)) - 10.5;

    assert.ok(Math.abs(chantGrew - hymnGrew) < 0.2, `${chantGrew} vs ${hymnGrew}`);
    assert.ok(Math.abs(chantGrew - READER_SIZE_STEP_PT) < 0.2, `${chantGrew}`);
});

// The gap between a staff and its lyrics, and the gap between two lyric lines,
// are the two numbers a face makes right or wrong — so the booklet's style owns
// them, exactly as it owns the face, and the author's own numbers are not
// consulted. See App\Support\BookletStyles.
test('the booklet\'s style says how far the lyrics stand off the staff', () => {
    const resolved = resolveSettings(
        'abc',
        { abcLyricFirstSkip: 1, abcLyricSkip: 1 },
        { abc: { paper: { abcLyricFirstSkip: 0.8, abcLyricSkip: 1.3 } } },
        geometry,
        null,
    );

    assert.equal(resolved.abcLyricFirstSkip, 1.5);
    assert.equal(resolved.abcLyricSkip, 1);
});

test('the same gap reaches the two engines that keep no setting for it', () => {
    assert.equal(unifiedSettings('gabc', geometry).minSpaceBelowStaff, geometry.minSpaceBelowStaff);
    assert.equal(unifiedSettings('aretino', geometry).aretinoLyricDistance, geometry.aretinoLyricDistance);
    assert.equal(unifiedSettings('aretino', geometry).aretinoLyricMinStaffDistance, geometry.aretinoLyricMinStaffDistance);
});

// Not an accident of travellingOverride's derivation, which is why it is pinned
// here: an override means different things depending on which layer it
// overrules. Now that the style owns the gap and has already got the face right,
// the only reason left to depart from it is the page — this hymn runs two lines
// over, tighten it — and a screen is not that page.
test('a spacing override is a page-fitting nudge, and a screen is not that page', () => {
    const travelling = travellingOverride('abc', {
        abcLyricFirstSkip: 1.1,
        abcLyricSkip: 0.7,
        abcTranspose: -2,
    }, geometry);

    assert.deepEqual(travelling, { abcTranspose: -2 });
});

/*
 * A paragraph the booklet says rather than sings. It has no engine and no
 * author, so the whole of what there is to resolve is the booklet's own two
 * numbers and whatever the row said instead.
 */

test('words take the booklets text size and leading', () => {
    const resolved = textSettings(null, pageGeometry({ ...rawGeometry, textSizeScale: 1.3, textLineHeight: 1.8 }));

    assert.equal(resolved.textSizeScale, 1.3);
    assert.equal(resolved.textLineHeight, 1.8);
});

test('a booklet that says nothing about its words leaves them as they were drawn', () => {
    const resolved = textSettings(null, geometry);

    assert.equal(resolved.textSizeScale, 1);
    assert.equal(resolved.textLineHeight, 1.45);
});

test('a row wins over the booklet, one key at a time', () => {
    const resolved = textSettings({ textSizeScale: 0.7 }, pageGeometry({ ...rawGeometry, textSizeScale: 1.3, textLineHeight: 1.8 }));

    assert.equal(resolved.textSizeScale, 0.7);
    assert.equal(resolved.textLineHeight, 1.8);
});

/* A spacing override is a page-fitting nudge and does not travel; how large a
   rubric is set beside the lyrics is typography, and does. */
test('a paragraphs own size reaches the reader with it', () => {
    const travelling = travellingOverride('text', { textSizeScale: 1.4, textLineHeight: 1.2 }, geometry);

    assert.deepEqual(travelling, { textSizeScale: 1.4, textLineHeight: 1.2 });
});
