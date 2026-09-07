import assert from 'node:assert/strict';
import test from 'node:test';

import ChordSheetJS from 'chordsheetjs';

import { chordStringsOf, spellFlatBInHtml, spellFlatBInText } from '../../resources/js/chordpro-notation.js';
import { parseChordproSong } from '../../resources/js/score-editor-chordpro.js';

/**
 * German note names used to be a pair of text substitutions around chordsheetjs:
 * H and B were rewritten to English before parsing, and the rendered output was
 * rewritten back afterwards. chordsheetjs understands the notation itself since
 * 15.5, which matters most for transposition — a substitution on the output only
 * ever saw note names, never the interval that produced them.
 */

const chordsOf = (song) => song.bodyParagraphs
    .flatMap((paragraph) => paragraph.lines ?? [])
    .flatMap((line) => line.items ?? [])
    .map((item) => item.chords)
    .filter((chords) => typeof chords === 'string' && chords !== '');

const parse = (content, german, transpose = 0) => parseChordproSong(content, { german, transpose });

test('German note names are read as German', async () => {
    const song = await parse('[H]Uram [B]ir[Bb]galmazz\n', true);

    assert.deepEqual(chordsOf(song), ['H', 'B', 'Bb']);
});

test('transposing up a semitone from A gives B, not A#', async () => {
    // The old output substitution could not do this: it rewrote B to H, so an
    // A transposed to B flat stayed the English "A#" on the page.
    assert.deepEqual(chordsOf(await parse('[A]a\n', true, 1)), ['B']);
    assert.deepEqual(chordsOf(await parse('[A]a\n', true, 2)), ['H']);
});

test('a German B is B flat, so it transposes a semitone below the English one', async () => {
    assert.deepEqual(chordsOf(await parse('[B]a\n', true, 2)), ['C']);
    assert.deepEqual(chordsOf(await parse('[B]a\n', false, 2)), ['C#']);
});

test('the bass note of a slash chord follows the notation too', async () => {
    assert.deepEqual(chordsOf(await parse('[G/H]a\n', true, 2)), ['A/C#']);
});

test('English notation is left alone', async () => {
    assert.deepEqual(chordsOf(await parse('[A]a [Bb]b\n', false, 0)), ['A', 'Bb']);
    assert.deepEqual(chordsOf(await parse('[A]a\n', false, 1)), ['A#']);
});

test('a {key} directive in German notation is understood without rewriting', async () => {
    const song = await parse('{key: H}\n[H]a\n', true);

    assert.equal(song.metadata.get('key'), 'H');
    assert.deepEqual(chordsOf(song), ['H']);
});

test('the rendered HTML carries the German names', async () => {
    const html = new ChordSheetJS.HtmlDivFormatter().format(await parse('[A]a\n', true, 2));

    assert.match(html, /<div class="chord">H<\/div>/);
});

test('the rendered HTML gets the app spelling of the flat', async () => {
    const html = spellFlatBInHtml(
        new ChordSheetJS.HtmlDivFormatter().format(await parse('[A]a\n', true, 1)),
    );

    assert.match(html, /<div class="chord">Bb<\/div>/);
});

test('plain text export carries the German names, spelled the same way', async () => {
    const song = await parse('[A]a\n', true, 1);
    const text = spellFlatBInText(
        new ChordSheetJS.TextFormatter().format(song),
        chordStringsOf(song),
    );

    assert.match(text, /^Bb\b/);
});

test('markup in the lyrics is still neutralised before parsing', async () => {
    const song = await parse('[C]<script>alert(1)</script>\n', true);
    const lyrics = song.bodyParagraphs
        .flatMap((paragraph) => paragraph.lines ?? [])
        .flatMap((line) => line.items ?? [])
        .map((item) => item.lyrics)
        .join('');

    assert.ok(!lyrics.includes('<script>'), 'raw angle brackets must not survive');
    assert.match(lyrics, /&lt;script&gt;/);
});
