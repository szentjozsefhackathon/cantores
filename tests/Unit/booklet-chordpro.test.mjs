import assert from 'node:assert/strict';
import test from 'node:test';

import { chordproRows } from '../../resources/js/booklet-chordpro.js';

// A predictable metric: every character is half the font size wide, and bold
// costs nothing. Real font metrics would make the arithmetic unreadable without
// testing anything extra.
const measure = (text) => (text ?? '').length * 5;

const options = {
    fontSize: 10,
    fontFamily: "'Merriweather'",
    layoutWidth: 200,
    measure,
};

const pair = (chords, lyrics) => ({ chords, lyrics });

test('a chord is drawn above its own lyric fragment', () => {
    const rows = chordproRows(
        [{ lines: [{ items: [pair('C', 'Ave '), pair('G', 'Maria')] }] }],
        options,
    );

    assert.equal(rows.length, 1);

    // Two chords, two lyric fragments.
    const texts = [...rows[0].svg.matchAll(/<text[^>]*x="([\d.]+)"[^>]*y="([\d.]+)"[^>]*>([^<]*)</g)]
        .map(([, x, y, content]) => ({ x: Number(x), y: Number(y), content }));

    assert.deepEqual(texts.map(t => t.content), ['C', 'Ave ', 'G', 'Maria']);

    // The chord and the lyric it belongs to start at the same x...
    assert.equal(texts[0].x, texts[1].x);
    assert.equal(texts[2].x, texts[3].x);
    // ...and the chord sits above the lyric.
    assert.ok(texts[0].y < texts[1].y);
    // The second column begins after the first, which is as wide as 'Ave '.
    assert.equal(texts[2].x, 20);
});

test('a row with no chords is only as tall as its lyrics', () => {
    const withChords = chordproRows([{ lines: [{ items: [pair('C', 'Ave')] }] }], options);
    const without = chordproRows([{ lines: [{ items: [pair('', 'Ave')] }] }], options);

    assert.equal(without[0].height, 13.5);
    assert.ok(withChords[0].height > without[0].height);
});

test('a column is as wide as the wider of its chord and its lyric', () => {
    // 'Gmaj7' is 25 wide plus a 4px gap; the lyric 'ah' is only 10, so the chord
    // decides — otherwise the next chord would collide with this one.
    const rows = chordproRows(
        [{ lines: [{ items: [pair('Gmaj7', 'ah'), pair('C', 'men')] }] }],
        options,
    );

    const xs = [...rows[0].svg.matchAll(/<text[^>]*x="([\d.]+)"/g)].map(m => Number(m[1]));

    assert.equal(xs[2], 29);
});

test('a line wider than the page wraps, and never splits a column', () => {
    const items = Array.from({ length: 10 }, () => pair('C', 'sing '));
    const rows = chordproRows([{ lines: [{ items }] }], options);

    // Each column is 25 wide, so eight fit in 200px and two go to a second row.
    assert.equal(rows.length, 2);
    assert.equal((rows[0].svg.match(/<text/g) ?? []).length, 16);
    assert.equal((rows[1].svg.match(/<text/g) ?? []).length, 4);
});

test('a chordless run too wide for the page is broken between its words', () => {
    // chordsheetjs hands a line back as one column per chord, so everything
    // after the last chord arrives as a single item — here 40 characters, 200
    // wide, on a 200 wide page it shares with the chord's own column.
    const rows = chordproRows(
        [{ lines: [{ items: [pair('C', 'Ave '), pair('', 'gratia plena dominus tecum benedicta')] }] }],
        options,
    );

    const lyrics = rows.map((row) => (
        [...row.svg.matchAll(/<text[^>]*>([^<]*)</g)].map(([, content]) => content)
    ));

    // Nothing is lost, and the line still reads as it is sung.
    assert.equal(lyrics.flat().filter((text) => text !== 'C').join(''), 'Ave gratia plena dominus tecum benedicta');

    // The chord stays over the syllable it was written above.
    assert.deepEqual(lyrics[0].slice(0, 2), ['C', 'Ave ']);

    // Every row now fits the page, which is the whole point: a row wider than
    // the content box is scaled down by the renderer, and a booklet set at one
    // size printed those lines at another.
    rows.forEach((row) => {
        assert.ok(Number(row.svg.match(/viewBox="0 0 ([\d.]+)/)[1]) <= options.layoutWidth);
    });
});

test('a word wider than the page is left to overflow rather than cut', () => {
    const rows = chordproRows(
        [{ lines: [{ items: [pair('', 'Aaaaaabbbbbbccccccddddddeeeeeeffffff')] }] }],
        options,
    );

    assert.equal(rows.length, 1);
    assert.match(rows[0].svg, /Aaaaaabbbbbbccccccddddddeeeeeeffffff/);
});

test('a column that fits a row of its own is moved whole, not split', () => {
    // 'sing ' columns are 25 wide; the last of nine has to move, and moving it
    // whole keeps its chord over its own word.
    const items = Array.from({ length: 9 }, () => pair('C', 'sing here '));
    const rows = chordproRows([{ lines: [{ items }] }], options);

    rows.forEach((row) => {
        [...row.svg.matchAll(/<text[^>]*>([^<]*)</g)]
            .map(([, content]) => content)
            .filter((content) => content !== 'C')
            .forEach((lyric) => assert.equal(lyric, 'sing here '));
    });
});

test('paragraphs are separated, but not at the top of a page', () => {
    const rows = chordproRows(
        [
            { lines: [{ items: [pair('C', 'one')] }] },
            { lines: [{ items: [pair('G', 'two')] }] },
        ],
        options,
    );

    assert.equal(rows[0].spaceBefore, 0);
    assert.equal(rows[1].spaceBefore, 9);
});

test('a verse that fits on a page is kept whole', () => {
    const lines = Array.from({ length: 3 }, () => ({ items: [pair('C', 'sing')] }));
    const rows = chordproRows([{ lines }], { ...options, contentHeight: 500 });

    assert.deepEqual(rows.map(row => row.keepWithNext), [true, true, false]);
});

test('a verse too tall for any page is left free to break', () => {
    const lines = Array.from({ length: 3 }, () => ({ items: [pair('C', 'sing')] }));
    const rows = chordproRows([{ lines }], { ...options, contentHeight: 20 });

    assert.deepEqual(rows.map(row => row.keepWithNext), [false, false, false]);
});

test('a section label is rendered and stays with its verse', () => {
    const rows = chordproRows(
        [{ label: 'Refrén', lines: [{ items: [pair('C', 'Ave')] }] }],
        { ...options, contentHeight: 500 },
    );

    assert.match(rows[0].svg, /Refrén/);
    assert.match(rows[0].svg, /font-style="italic"/);
    assert.equal(rows[0].keepWithNext, true);
});

test('a comment directive becomes its own row', () => {
    const rows = chordproRows(
        [{ lines: [{ items: [{ name: 'comment', value: 'lassan' }] }] }],
        options,
    );

    assert.equal(rows.length, 1);
    assert.match(rows[0].svg, /lassan/);
});

test('markup ChordPro does not define is escaped rather than emitted', () => {
    const rows = chordproRows([{ lines: [{ items: [pair('', '<q>& "x"')] }] }], options);

    assert.match(rows[0].svg, /&lt;q&gt;&amp; &quot;x&quot;/);
    assert.doesNotMatch(rows[0].svg, /<q>/);
});

test('italic, bold and underline markup is drawn, not printed', () => {
    const rows = chordproRows(
        [{ lines: [{ items: [pair('', 'plain <i>so</i> <b>very</b> <u>true</u>')] }] }],
        options,
    );

    const runs = [...rows[0].svg.matchAll(/<text([^>]*)>([^<]*)</g)]
        .map(([, attrs, content]) => ({ attrs, content }));

    // The tags themselves are gone, and the words read on unbroken.
    assert.equal(runs.map((run) => run.content).join(''), 'plain so very true');

    assert.doesNotMatch(runs[0].attrs, /font-style|font-weight|text-decoration/);
    assert.match(runs.find((run) => run.content === 'so').attrs, /font-style="italic"/);
    assert.match(runs.find((run) => run.content === 'very').attrs, /font-weight="bold"/);
    assert.match(runs.find((run) => run.content === 'true').attrs, /text-decoration="underline"/);
});

test('a styled run is placed after the one before it, in its own metric', () => {
    // A bold measurer, so a run set in it is demonstrably measured as bold: the
    // five characters of 'plain' are 5 wide each, and the italic 'so' 20 each.
    const styled = (text, { italic = false } = {}) => (text ?? '').length * (italic ? 20 : 5);

    const rows = chordproRows(
        [{ lines: [{ items: [pair('C', 'plain <i>so</i>!')] }] }],
        { ...options, measure: styled, layoutWidth: 1000 },
    );

    const xs = [...rows[0].svg.matchAll(/<text[^>]*x="([\d.]+)"/g)].map((m) => Number(m[1]));

    // The chord, then 'plain ' at 0, 'so' after its 30, and '!' after 40 more.
    assert.deepEqual(xs, [0, 0, 30, 70]);
});

test('markup written across a chord carries on into the next column', () => {
    const rows = chordproRows(
        [{ lines: [{ items: [pair('C', '<i>Ave '), pair('G', 'Maria</i> now')] }] }],
        options,
    );

    const runs = [...rows[0].svg.matchAll(/<text([^>]*)>([^<]*)</g)]
        .map(([, attrs, content]) => ({ italic: /font-style="italic"/.test(attrs), content }));

    assert.deepEqual(
        runs.filter((run) => run.italic).map((run) => run.content),
        ['Ave ', 'Maria'],
    );
    assert.deepEqual(
        runs.filter((run) => !run.italic).map((run) => run.content),
        ['C', 'G', ' now'],
    );
});

test('a styled run survives being broken between two rows', () => {
    const rows = chordproRows(
        [{ lines: [{ items: [pair('C', 'Ave '), pair('', '<i>gratia plena dominus tecum benedicta in mulieribus</i>')] }] }],
        options,
    );

    const runs = rows.flatMap((row) => (
        [...row.svg.matchAll(/<text([^>]*)>([^<]*)</g)]
            .map(([, attrs, content]) => ({ italic: /font-style="italic"/.test(attrs), content }))
    ));

    assert.equal(
        runs.map((run) => run.content).join(''),
        'CAve gratia plena dominus tecum benedicta in mulieribus',
    );
    assert.ok(rows.length > 1);
    runs.filter((run) => run.content !== 'C' && run.content !== 'Ave ')
        .forEach((run) => assert.ok(run.italic, `${run.content} lost its italic`));
});

test('markup in a section label and a comment is honoured too', () => {
    const rows = chordproRows(
        [
            { label: 'Refrén <u>2x</u>', lines: [{ items: [pair('C', 'Ave')] }] },
            { lines: [{ items: [{ name: 'comment', value: 'lassan <u>és</u> halkan' }] }] },
        ],
        options,
    );

    const label = [...rows[0].svg.matchAll(/<text([^>]*)>([^<]*)</g)]
        .map(([, attrs, content]) => ({ attrs, content }));

    assert.deepEqual(label.map((run) => run.content), ['Refrén ', '2x']);
    // The label is bold italic throughout; only the underline is the markup's.
    label.forEach((run) => assert.match(run.attrs, /font-style="italic"/));
    assert.doesNotMatch(label[0].attrs, /text-decoration/);
    assert.match(label[1].attrs, /text-decoration="underline"/);

    const comment = [...rows[2].svg.matchAll(/<text([^>]*)>([^<]*)</g)]
        .map(([, attrs, content]) => ({ attrs, content }));

    assert.deepEqual(comment.map((run) => run.content), ['lassan ', 'és', ' halkan']);
    assert.match(comment[1].attrs, /text-decoration="underline"/);
});

test('empty lines and empty paragraphs produce nothing', () => {
    assert.deepEqual(chordproRows([{ lines: [{ items: [] }] }], options), []);
    assert.deepEqual(chordproRows([], options), []);
});

test('each row is a standalone SVG sized to its own content', () => {
    const rows = chordproRows([{ lines: [{ items: [pair('C', 'Ave')] }] }], options);

    assert.match(rows[0].svg, /^<svg xmlns="http:\/\/www\.w3\.org\/2000\/svg" viewBox="0 0 15 26"/);
    assert.match(rows[0].svg, /<\/svg>$/);
});
