import assert from 'node:assert/strict';
import test from 'node:test';

import ChordSheetJS from 'chordsheetjs';

import { chordproRows } from '../../resources/js/booklet-chordpro.js';
import {
    chordStringsOf,
    spellFlatB,
    spellFlatBInHtml,
    spellFlatBInText,
} from '../../resources/js/chordpro-notation.js';
import { parseChordproSong } from '../../resources/js/score-editor-chordpro.js';

/**
 * German notation writes B flat as `B`; this app writes it `Bb`, so that it can
 * never be read as the `H` next to it. chordsheetjs has no setting for that, so
 * the spelling is applied to what it has rendered — after the transposition, so
 * it can only respell a chord and never move it.
 */

test('a German B becomes Bb', () => {
    assert.equal(spellFlatB('B'), 'Bb');
    assert.equal(spellFlatB('Bm7'), 'Bbm7');
    assert.equal(spellFlatB('F#/B'), 'F#/Bb');
});

test('H is left alone, being B natural already', () => {
    assert.equal(spellFlatB('H'), 'H');
    assert.equal(spellFlatB('Hm'), 'Hm');
    assert.equal(spellFlatB('G/H'), 'G/H');
});

test('an already flat B is not flattened twice', () => {
    assert.equal(spellFlatB('Bb'), 'Bb');
    assert.equal(spellFlatB('Bbmaj7'), 'Bbmaj7');
});

test('B# is left alone, because Bb# would be nonsense', () => {
    // German mode does emit B# for some enharmonic spellings.
    assert.equal(spellFlatB('B#m'), 'B#m');
});

test('every chord German mode renders survives respelling', async () => {
    const source = '[A]a [Am7]b [G/H]c [Csus4]d [Fmaj7]e [Ddim]f [Eaug]g [F#m]h\n';
    const song = await parseChordproSong(source, { german: true, transpose: 0 });

    for (let steps = 0; steps < 12; steps += 1) {
        chordStringsOf(song.transpose(steps)).forEach((chord) => {
            assert.ok(
                !/Bb[b#]/.test(spellFlatB(chord)),
                `respelling ${chord} produced ${spellFlatB(chord)}`,
            );
        });
    }
});

test('only the chord cells of a rendering are respelled', () => {
    const html = '<div class="chord">B</div><div class="lyrics">Boldog</div>';

    assert.equal(
        spellFlatBInHtml(html),
        '<div class="chord">Bb</div><div class="lyrics">Boldog</div>',
    );
});

test('the table formatter cells are respelled too', () => {
    assert.equal(
        spellFlatBInHtml('<td class="chord">B</td><td class="lyrics">B</td>'),
        '<td class="chord">Bb</td><td class="lyrics">B</td>',
    );
});

test('in plain text, only rows made entirely of this song chords are respelled', () => {
    const text = 'B      Am\nBoldog assszony\n';

    assert.equal(spellFlatBInText(text, ['B', 'Am']), 'Bb      Am\nBoldog assszony\n');
});

test('a lyric row is left alone even when it opens with a chord name', () => {
    // The substitution this replaced turned "Boldog" into "Holdog".
    const text = 'B\nBoldog vagy B\n';

    assert.equal(spellFlatBInText(text, ['B']), 'Bb\nBoldog vagy B\n');
});

test('an empty row is not mistaken for a chord row', () => {
    assert.equal(spellFlatBInText('\n   \n', ['B']), '\n   \n');
});

test('plain text with German notation off is untouched by the caller', async () => {
    const song = await parseChordproSong('[A]a\n', { german: false, transpose: 1 });

    assert.deepEqual(chordStringsOf(song), ['A#']);
});

test('the booklet draws the respelled chord, at its respelled width', () => {
    const measure = (text) => (text ?? '').length * 5;
    const paragraphs = [{ lines: [{ items: [{ chords: 'B', lyrics: 'a' }] }] }];
    const options = { fontSize: 10, fontFamily: "'Merriweather'", layoutWidth: 200, measure };

    const plain = chordproRows(paragraphs, options);
    const spelled = chordproRows(paragraphs, { ...options, spell: spellFlatB });

    assert.match(plain[0].svg, />B<\/text>/);
    assert.match(spelled[0].svg, />Bb<\/text>/);

    // The respelled chord is the one that gets measured, so its column is a
    // character wider — the layout must not be planned around the shorter name.
    const widthOf = (svg) => Number(svg.match(/width="([\d.]+)"/)[1]);

    assert.equal(widthOf(spelled[0].svg) - widthOf(plain[0].svg), 5);
});

test('the booklet leaves chords alone when no speller is given', () => {
    const rows = chordproRows(
        [{ lines: [{ items: [{ chords: 'B', lyrics: 'Ave' }] }] }],
        { fontSize: 10, fontFamily: "'Merriweather'", layoutWidth: 200, measure: (t) => (t ?? '').length * 5 },
    );

    assert.match(rows[0].svg, />B<\/text>/);
});

test('end to end: a sheet transposed into B flat reads Bb everywhere', async () => {
    const song = await parseChordproSong('[A]Ave [E]Maria\n', { german: true, transpose: 1 });

    const html = spellFlatBInHtml(new ChordSheetJS.HtmlDivFormatter().format(song));
    const text = spellFlatBInText(new ChordSheetJS.TextFormatter().format(song), chordStringsOf(song));
    const svg = chordproRows(
        song.bodyParagraphs,
        { fontSize: 10, fontFamily: "'Merriweather'", layoutWidth: 400, measure: (t) => (t ?? '').length * 5, spell: spellFlatB },
    ).map((row) => row.svg).join('');

    assert.match(html, /<div class="chord">Bb<\/div>/);
    assert.match(text, /^Bb\b/);
    assert.match(svg, />Bb<\/text>/);
});

for (const [source, expected] of [
    ['Fmaj7', 'F△'],
    ['Fma7/A', 'F△/A'],
    ['Dm7', 'Dm⁷'],
    ['G13', 'G¹³'],
    ['Dm7/G', 'Dm⁷/G'],
    ['Fmaj9', 'Fmaj⁹'],
]) {
    test(`displays ${source} as ${expected}`, async () => {
        const { displayChord, displayChordsInHtml, displayChordsInText } = await import('../../resources/js/chordpro-notation.js');

        assert.equal(displayChord(source), expected);
        const expectedHtml = expected.replace(/[⁰¹²³⁴⁵⁶⁷⁸⁹]+/g, (digits) =>
            `<sup style="font-size:70%;line-height:0;vertical-align:baseline;position:relative;top:-0.5em">${[...digits].map((digit) => '⁰¹²³⁴⁵⁶⁷⁸⁹'.indexOf(digit)).join('')}</sup>`);
        for (const tag of ['div', 'td']) {
            assert.equal(displayChordsInHtml(`<${tag} class="chord">${source}</${tag}><div class="lyrics">Dm7</div>`),
                `<${tag} class="chord">${expectedHtml}</${tag}><div class="lyrics">Dm7</div>`);
        }
        assert.equal(displayChordsInText(`${source}\nVerse 7`, [source]), `${expected}\nVerse 7`);
    });
}
