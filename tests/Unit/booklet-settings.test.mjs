import assert from 'node:assert/strict';
import test from 'node:test';

import {
    abcLyricSizeForPt,
    aretinoLyricSizeForPt,
    chordproFontSizeForPt,
    gabcLyricSizeForPt,
    pageGeometry,
} from '../../resources/js/booklet-geometry.js';
import { movesSetting, READER_SIZE_STEP_PT, readerStep, resolveSettings, steppedValue } from '../../resources/js/booklet-settings.js';

const geometry = pageGeometry({
    pageWidthMm: 148,
    pageHeightMm: 210,
    marginMm: 12,
    contentWidthMm: 124,
    contentHeightMm: 186,
    lyricSizePt: 10.5,
    staffHeightMm: 5,
    textFont: 'Merriweather',
});

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
