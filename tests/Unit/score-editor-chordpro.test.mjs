import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

import { DEFAULT_LYRIC_SIZE_PT, opticalLyricSizePt, ptToPx, pxToPt } from '../../resources/js/booklet-geometry.js';
import ChordSheetJS from 'chordsheetjs';

import { balanceMarkup, chordproIncipitRows, chordproMixin, parseChordproSong, stripMarkup } from '../../resources/js/score-editor-chordpro.js';

// Half the font size per character, as in booklet-chordpro's own tests: real
// font metrics would make the arithmetic unreadable without testing anything
// extra.
const measure = (text) => (text ?? '').length * 20;

const options = {
    fontSize: 40,
    fontFamily: "'Merriweather'",
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

test('a chord sheet starts at the size the engraved formats do, in the px its container is styled with', () => {
    // 9 pt of Merriweather, not 11: the two read as the same height of letter,
    // which is the whole point of quoting sizes in one reference face. A sheet
    // exported at the factory settings therefore has lyrics the same size as an
    // Aretino or a GABC score exported at its own.
    const size = pxToPt(chordproMixin().chordproFontSize);

    assert.ok(Math.abs(size - 9) < 0.05, `expected about 9 pt, got ${size}`);
    assert.equal(
        chordproMixin().chordproFontSize,
        Number(ptToPx(opticalLyricSizePt(DEFAULT_LYRIC_SIZE_PT, 'Merriweather')).toFixed(4)),
    );
    assert.equal(chordproMixin().chordproFontFamily, "'Merriweather'");
});

test('the preview opens magnified, because the sheet is set at printed size', () => {
    assert.equal(chordproMixin().chordproZoom, 120);
    assert.ok(chordproMixin().chordproFields.includes('chordproZoom'));

    const toolbar = readFileSync(new URL('../../resources/views/livewire/pages/score-editor.blade.php', import.meta.url), 'utf8');

    assert.match(toolbar, /x-model="chordproZoom"/);
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

    // And the factory default reads back as the round number it was chosen as,
    // to the two decimals the spinner shows.
    assert.equal(Math.round(pxToPt(chordproMixin().chordproFontSize) * 100) / 100, 8.96);
});

const parse = (content) => parseChordproSong(content, { german: false, transpose: 0 });

/** The lyric of each column of the formatted HTML, markup and all. */
const lyricsOf = (html) => [...html.matchAll(/<div class="lyrics">(.*?)<\/div>/g)].map(([, lyric]) => lyric);

test('markup that runs across a chord is closed and reopened at the column', async () => {
    // Each fragment becomes a `<div>` of its own, and a tag cannot straddle two
    // of them: left as written, only 'Ma' came out italic and the rest of the
    // word did not.
    const html = new ChordSheetJS.HtmlDivFormatter().format(
        balanceMarkup(await parse('[C]Ave <i>Ma[G]ri</i>a')),
    );

    assert.deepEqual(lyricsOf(html), ['Ave ', '<i>Ma</i>', '<i>ri</i>a']);
});

test('nested markup is reopened in full', async () => {
    const html = new ChordSheetJS.HtmlDivFormatter().format(
        balanceMarkup(await parse('<b><u>Ky[C]rie</u></b>')),
    );

    assert.deepEqual(lyricsOf(html), ['<b><u>Ky</u></b>', '<b><u>rie</u></b>']);
});

test('markup closed on its own line does not run on into the next', async () => {
    const html = new ChordSheetJS.HtmlDivFormatter().format(
        balanceMarkup(await parse('<i>one\ntwo')),
    );

    assert.deepEqual(lyricsOf(html), ['<i>one</i>', 'two']);
});

test('a lyric with no markup is left exactly as it was', async () => {
    const html = new ChordSheetJS.HtmlDivFormatter().format(
        balanceMarkup(await parse('[C]Ave Ma[G]ria')),
    );

    // chordsheetjs cuts a fragment at a space as well as at a chord; either
    // way, nothing gained a tag it did not have.
    assert.deepEqual(lyricsOf(html), ['Ave ', 'Ma', 'ria']);
});

test('everything but the three drawable tags stops being markup', async () => {
    // The sanitizing happens on the way into the parser, so a `<span>` reaches
    // the browser as text and only ChordPro's own three tags stay live.
    const html = new ChordSheetJS.HtmlDivFormatter().format(
        balanceMarkup(await parse('[C]<span style="x">a</span> <i>b</i>')),
    );

    assert.deepEqual(lyricsOf(html).join(''), '&lt;span style="x"&gt;a&lt;/span&gt; <i>b</i>');
});

test('plain text keeps the words and drops the markup', async () => {
    const text = new ChordSheetJS.TextFormatter().format(stripMarkup(await parse('[C]Ave <i>Ma[G]ri</i>a')));

    assert.match(text, /Ave Maria/);
    assert.doesNotMatch(text, /<i>|<\/i>/);
});
