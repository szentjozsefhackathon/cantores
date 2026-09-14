import assert from 'node:assert/strict';
import test from 'node:test';

import { isExcluded, textPalette } from '../../resources/js/projection-deck.js';
import { textSlideSettings } from '../../resources/js/projection-settings.js';

/*
 * The two questions a deck answers about a slide before anything is drawn: what
 * colour its words are set in, and whether the service shows it at all.
 */

test('a screen of words is white on black until the deck says otherwise', () => {
    const palette = textPalette({});

    assert.equal(palette.background, '#000000');
    assert.equal(palette.text, '#ffffff');
});

test('the light theme is the paper one, red rubric and all', () => {
    const palette = textPalette({ textTheme: 'light' });

    assert.equal(palette.background, '#ffffff');
    assert.equal(palette.text, '#000000');
    assert.equal(palette.accent, '#cc0000');
});

/* Missal red on black is nearly unreadable, so the dark theme answers with its
   complement rather than with the same ink. */
test('the dark theme keeps a rubric warning without keeping its ink', () => {
    assert.notEqual(textPalette({ textTheme: 'dark' }).accent, '#cc0000');
});

/* The server states the whole palette; the name is only the fall-back for a
   payload that predates it. */
test('the servers palette wins over the name it came with', () => {
    const palette = textPalette({ textTheme: 'dark', textPalette: { text: '#eeeeee' } });

    assert.equal(palette.text, '#eeeeee');
    assert.equal(palette.background, '#000000');
});

test('a deck naming a scheme nobody has heard of still gets one', () => {
    assert.equal(textPalette({ textTheme: 'chartreuse' }).background, '#000000');
});

test('a slide is walked past only where its own row says that position is', () => {
    const excluded = { 7: [1, 3] };

    assert.equal(isExcluded({ entryId: 7, index: 1 }, excluded), true);
    assert.equal(isExcluded({ entryId: 7, index: 2 }, excluded), false);
    assert.equal(isExcluded({ entryId: 8, index: 1 }, excluded), false);
});

/* The map arrives from JSON, where every key is a string, and is read against
   row ids that are numbers. */
test('a row is found whether its id arrived as a number or as a string', () => {
    assert.equal(isExcluded({ entryId: 7, index: 0 }, { '7': [0] }), true);
    assert.equal(isExcluded({ entryId: '7', index: 0 }, { 7: [0] }), true);
});

test('a deck with nothing left out leaves nothing out', () => {
    assert.equal(isExcluded({ entryId: 7, index: 0 }, {}), false);
    assert.equal(isExcluded({ entryId: 7, index: 0 }, undefined), false);
});

/*
 * And how large those words are set, with the leading they are stacked at. Two
 * layers only: a screen of words has no engine and no author behind it.
 */

test('a screen of words takes the decks size and leading', () => {
    const resolved = textSlideSettings(null, { textSizeScale: 1.5, textLineHeight: 1.2 });

    assert.equal(resolved.textSizeScale, 1.5);
    assert.equal(resolved.textLineHeight, 1.2);
});

test('a deck that says nothing leaves its words as they were drawn', () => {
    const resolved = textSlideSettings(null, {});

    assert.equal(resolved.textSizeScale, 1);
    assert.equal(resolved.textLineHeight, 1.45);
});

test('a row wins over the deck, one key at a time', () => {
    const resolved = textSlideSettings({ textLineHeight: 2.2 }, { textSizeScale: 1.5, textLineHeight: 1.2 });

    assert.equal(resolved.textSizeScale, 1.5);
    assert.equal(resolved.textLineHeight, 2.2);
});
