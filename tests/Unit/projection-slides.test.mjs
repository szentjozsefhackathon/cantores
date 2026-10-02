import assert from 'node:assert/strict';
import test from 'node:test';

import { deckLayout, isExcluded, layoutSignature, textPalette } from '../../resources/js/projection-deck.js';
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

/* A chord sheet is words too — it is laid out here rather than engraved by any
   engine — so every theme has to answer for the two colours only it uses. */
test('every theme says what a chord and a section label are set in', () => {
    for (const theme of ['dark', 'light']) {
        const palette = textPalette({ textTheme: theme });

        assert.ok(palette.chord, `${theme} has no chord colour`);
        assert.ok(palette.label, `${theme} has no label colour`);
        assert.notEqual(palette.chord, palette.background);
        assert.notEqual(palette.label, palette.background);
    }
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

/* What the wall sends with its acknowledgement: for every row, one list per page
   of the score, holding where each of its slides starts. */
test('a drawn deck writes down where it cut each row', () => {
    const layout = deckLayout([
        { entryId: 3, index: 0, page: 0, start: 0 },
        { entryId: 3, index: 1, page: 0, start: 4 },
        { entryId: 3, index: 2, page: 1, start: 0 },
        { entryId: 8, index: 0, page: 0, start: 0 },
    ]);

    assert.deepEqual(layout, { 3: [[0, 4], [0]], 8: [[0]] });
    assert.deepEqual(deckLayout([]), {});
});

/* A layout read back out of a JSON column may have its keys in another order,
   and must still be recognised as the one that was sent. */
test('a layout is the same layout whatever order its rows arrive in', () => {
    assert.equal(
        layoutSignature({ 10: [[0]], 2: [[0, 3]] }),
        layoutSignature({ 2: [[0, 3]], 10: [[0]] }),
    );
    assert.notEqual(layoutSignature({ 2: [[0, 3]] }), layoutSignature({ 2: [[0]] }));
    assert.equal(layoutSignature(null), '');
    assert.equal(layoutSignature([]), '');
});
