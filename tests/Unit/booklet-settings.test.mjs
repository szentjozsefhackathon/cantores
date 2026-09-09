import assert from 'node:assert/strict';
import test from 'node:test';

import { movesSetting, steppedValue } from '../../resources/js/booklet-settings.js';

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
    assert.equal(movesSetting("'Lora'", 'Lora'), false);
    assert.equal(movesSetting("'Lora'", "'Inter'"), true);
    assert.equal(movesSetting("'Lora'", undefined), true);
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
