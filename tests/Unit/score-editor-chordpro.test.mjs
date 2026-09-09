import assert from 'node:assert/strict';
import test from 'node:test';

import { chordproIncipitRows, parseChordproSong } from '../../resources/js/score-editor-chordpro.js';

// Half the font size per character, as in booklet-chordpro's own tests: real
// font metrics would make the arithmetic unreadable without testing anything
// extra.
const measure = (text) => (text ?? '').length * 20;

const options = {
    fontSize: 40,
    fontFamily: "'Lora'",
    layoutWidth: 900,
    measure,
};

const pair = (chords, lyrics) => ({ chords, lyrics });
const line = (...items) => ({ items });

test('draws the opening lines, chords over lyrics', () => {
    const rows = chordproIncipitRows(
        [{ lines: [line(pair('C', 'Ave '), pair('G', 'Maria'))] }],
        options,
    );

    assert.equal(rows.length, 1);
    const texts = [...rows[0].svg.matchAll(/<text[^>]*>([^<]*)</g)].map(([, content]) => content);
    assert.deepEqual(texts, ['C', 'Ave ', 'G', 'Maria']);
});

test('stops after the first few lines', () => {
    const rows = chordproIncipitRows(
        [{ lines: [1, 2, 3, 4, 5].map((n) => line(pair('C', `line ${n}`))) }],
        options,
    );

    assert.equal(rows.length, 3);
    assert.ok(rows.every((row) => !row.svg.includes('line 4')));
});

test('leaves out section labels and comments, which a thumbnail has no room for', () => {
    const rows = chordproIncipitRows(
        [{
            label: 'Verse 1',
            lines: [
                line({ name: 'start_of_verse', value: '' }),
                line({ name: 'comment', value: 'slowly' }),
                line(pair('', 'Ave Maria')),
            ],
        }],
        options,
    );

    assert.equal(rows.length, 1);
    assert.ok(rows[0].svg.includes('Ave Maria'));
    assert.ok(!rows[0].svg.includes('Verse 1'));
    assert.ok(!rows[0].svg.includes('slowly'));
});

test('has nothing to draw for a sheet with no sung text', () => {
    assert.deepEqual(chordproIncipitRows([{ lines: [line({ name: 'comment', value: 'x' }), line()] }], options), []);
    assert.deepEqual(chordproIncipitRows([], options), []);
});

test('reads the paragraphs a parsed sheet actually hands over', async () => {
    const song = await parseChordproSong(
        '{title: Ave Maria}\n{subtitle: Trad}\n\n{start_of_verse}\n[C]Ave Ma[G]ria\n{end_of_verse}\n',
        { german: true, transpose: 0 },
    );

    const rows = chordproIncipitRows(song.bodyParagraphs, options);

    assert.equal(rows.length, 1);

    const texts = [...rows[0].svg.matchAll(/<text[^>]*>([^<]*)</g)].map(([, content]) => content);

    // The sung line, syllable by syllable, with its chords — and not the title,
    // which is the page's heading rather than part of the music. The other
    // formats crop their headings off the incipit too.
    assert.deepEqual(texts, ['C', 'Ave ', 'Ma', 'G', 'ria']);
});
