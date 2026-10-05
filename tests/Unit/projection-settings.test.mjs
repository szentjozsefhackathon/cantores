import assert from 'node:assert/strict';
import test from 'node:test';

import { movesSetting } from '../../resources/js/booklet-settings.js';
import {
    FROM_STYLE,
    divergingKeys,
    inheritedSlideSetting,
    resolveSlideSettings,
    styleMinScale,
    styledDefaults,
} from '../../resources/js/projection-settings.js';
import { formatDefaults } from '../../resources/js/score-editor-settings.js';

/**
 * The four layers a slide is set from: factory defaults, the deck's style, the
 * score's own layout for the ratio, and what was changed by hand in this deck.
 */

const STYLE = {
    id: 1,
    minScale: 0.8,
    settings: {
        abc: { abcLyricSize: 30, abcPageScale: 2, abcLyricFont: 'Inter' },
        chordpro: { chordproFontSize: 70 },
    },
};

const TUNED = { abc: { '16/9': { abcLyricSize: 25, abcNoteSpacing: 1.5, abcTranspose: 2 } } };

test('a deck in no style is set exactly as before: defaults, score, override', () => {
    const settings = resolveSlideSettings('abc', TUNED, '16/9', { abcStaffSep: 40 });

    assert.equal(settings.abcLyricSize, 25);
    assert.equal(settings.abcStaffSep, 40);
    assert.equal(settings.abcPageScale, formatDefaults('abc', '16/9').defaults.abcPageScale);
    assert.equal(settings.slideMinScale, 1, 'nothing is ever shrunk without a style');
});

test('a score never laid out for the ratio is set in the style, face included', () => {
    const settings = resolveSlideSettings('abc', {}, '16/9', {}, STYLE);

    assert.equal(settings.abcLyricSize, 30);
    assert.equal(settings.abcPageScale, 2);
    assert.equal(settings.abcLyricFont, 'Inter');
    assert.equal(settings.slideMinScale, 0.8);
});

test('the score author’s own layout for the ratio wins over the style', () => {
    const settings = resolveSlideSettings('abc', TUNED, '16/9', {}, STYLE);

    assert.equal(settings.abcLyricSize, 25, 'the author chose this size for this screen');
    assert.equal(settings.abcPageScale, 2, 'and said nothing about the staff, which the style sets');
});

test('a slide told to follow the style sets the score’s own layout aside', () => {
    const settings = resolveSlideSettings('abc', TUNED, '16/9', { [FROM_STYLE]: true }, STYLE);

    assert.equal(settings.abcLyricSize, 30);
    assert.equal(settings.abcNoteSpacing, formatDefaults('abc', '16/9').defaults.abcNoteSpacing);
    assert.ok(!(FROM_STYLE in settings), 'the flag is not a setting');
});

test('a change made in the deck wins over everything', () => {
    const settings = resolveSlideSettings('abc', TUNED, '16/9', { [FROM_STYLE]: true, abcLyricSize: 12 }, STYLE);

    assert.equal(settings.abcLyricSize, 12);
});

test('the style is read at its own format only', () => {
    const settings = resolveSlideSettings('gabc', {}, '16/9', {}, STYLE);

    assert.deepEqual(
        { lyricSize: settings.lyricSize, staffSize: settings.staffSize },
        { lyricSize: formatDefaults('gabc', '16/9').defaults.lyricSize, staffSize: formatDefaults('gabc', '16/9').defaults.staffSize },
    );
    assert.equal(styledDefaults('chordpro', '16/9', STYLE).chordproFontSize, 70);
});

test('a knob changed by hand is measured against the style and the score together', () => {
    const inherited = inheritedSlideSetting('abc', {}, '16/9', { abcLyricSize: 12 }, 'abcLyricSize', STYLE);

    assert.equal(inherited, 30);
});

test('the keys a score’s layout disagrees with the style on, and only the style’s keys', () => {
    const keys = divergingKeys('abc', TUNED, '16/9', STYLE, ['abcLyricSize', 'abcNoteSpacing', 'abcPageScale'], movesSetting);

    assert.deepEqual(keys, ['abcLyricSize', 'abcNoteSpacing']);
    assert.deepEqual(divergingKeys('abc', {}, '16/9', STYLE, ['abcLyricSize'], movesSetting), [], 'an untuned score agrees');
});

test('a score’s layout that matches the style is no disagreement', () => {
    const matching = { abc: { '16/9': { abcLyricSize: 30 } } };

    assert.deepEqual(divergingKeys('abc', matching, '16/9', STYLE, ['abcLyricSize'], movesSetting), []);
});

test('the shrink allowance is kept to a sane range', () => {
    assert.equal(styleMinScale(null), 1);
    assert.equal(styleMinScale({ minScale: 0.85 }), 0.85);
    assert.equal(styleMinScale({ minScale: 0.1 }), 0.5);
    assert.equal(styleMinScale({ minScale: 3 }), 1);
});
