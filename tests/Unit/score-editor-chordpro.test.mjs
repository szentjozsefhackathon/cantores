import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

import { ptToPx, pxToPt } from '../../resources/js/booklet-geometry.js';
import { chordproIncipitRows, chordproMixin, parseChordproSong } from '../../resources/js/score-editor-chordpro.js';

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

test('the editor never writes a title directive into the sheet', () => {
    // A score's title is the row's, not the sheet's: syncing it into the
    // ChordPro overwrote whatever the sheet already said it was called — the
    // worked example among them, which came out titled "Untitled score".
    const editor = readFileSync(new URL('../../resources/js/score-editor.js', import.meta.url), 'utf8');
    const chordpro = readFileSync(new URL('../../resources/js/score-editor-chordpro.js', import.meta.url), 'utf8');

    assert.ok(!/syncChordproTitle/.test(editor + chordpro), 'nothing may sync the title into the content');
    assert.ok(!/chordpro: '\{title/.test(editor), 'the starting sheet must carry no title directive');
});

test('a chord sheet starts at 12 pt, in the px its container is styled with', () => {
    assert.equal(chordproMixin().chordproFontSize, ptToPx(12));
});

test('every chord sheet toolbar sets the size in points over a setting kept in px', () => {
    const editor = readFileSync(new URL('../../resources/js/score-editor.js', import.meta.url), 'utf8');

    // Every toolbar that sets a chord sheet's size speaks points; everything
    // downstream — the preview, the score views, the HTML export — is still
    // handed px, so the pair of accessors is the only place the two units meet.
    for (const page of ['score-editor', 'score-view', 'public-score-view']) {
        const toolbar = readFileSync(new URL(`../../resources/views/livewire/pages/${page}.blade.php`, import.meta.url), 'utf8');

        assert.match(toolbar, /x-model="chordproFontSizePt"/, `${page} should set the size in points`);
        assert.ok(!/x-model="chordproFontSize"/.test(toolbar), `${page} should not set the size in px`);
    }

    assert.match(editor, /get chordproFontSizePt\(\)/);
    assert.match(editor, /set chordproFontSizePt\(value\)/);

    // And the factory default reads back as the round number it was chosen as.
    assert.equal(pxToPt(chordproMixin().chordproFontSize), 12);
});
