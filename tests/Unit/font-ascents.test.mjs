import assert from 'node:assert/strict';
import test from 'node:test';

import { servedFace, tableAscent } from '../../resources/js/font-ascents.js';

/*
 * A lyric line hangs from the ink height of its syllables, and a browser rounds
 * that height to 1/64 of the size, each system its own way — Windows measured
 * Barlow Condensed's lower-case a step shorter than Android. So the height is
 * read from the served fonts' own outlines, which say the same everywhere.
 */

test('a word reaches as high as its tallest letter, at the size it is set in', () => {
    assert.equal(tableAscent('an', 60, "'Barlow Condensed'"), 31.02);
    assert.equal(tableAscent('Áy', 60, "'Barlow Condensed'"), 53.46);
    assert.equal(tableAscent('Ferenc', 120, "'Barlow Condensed'"), 84);
});

/* Between what Windows (30.9375) and Android (31.875) report for the same 'a'
   at 60 px — the outline itself, not either rounding of it. */
test('the height is the outline\'s, not a browser\'s rounding of it', () => {
    const ascent = tableAscent('a', 60, "'Barlow Condensed'");

    assert.ok(ascent > 30.9375 && ascent < 31.875, String(ascent));
});

test('a face is chosen the way CSS chooses one', () => {
    assert.equal(servedFace("'Barlow Condensed'"), servedFace("'Barlow Condensed'", false, true), 'Barlow has no italic, and draws upright');
    assert.notEqual(servedFace("'Barlow Condensed'"), servedFace("'Barlow Condensed'", true), 'its bold is a face of its own');
    assert.notEqual(servedFace("'EB Garamond', serif"), servedFace("'EB Garamond', serif", false, true));
    assert.equal(servedFace('serif'), null);
});

test('a face or a letter the table does not have is left to the browser', () => {
    assert.equal(tableAscent('a', 60, 'serif'), null);
    assert.equal(tableAscent('a☃', 60, "'Barlow Condensed'"), null);
});

test('a word with no ink reaches the face\'s ascender', () => {
    assert.equal(tableAscent(' ', 60, "'Barlow Condensed'"), 60);
});
