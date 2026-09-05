import assert from 'node:assert/strict';
import test from 'node:test';

import { chordproRows } from '../../resources/js/booklet-chordpro.js';

// A predictable metric: every character is half the font size wide, and bold
// costs nothing. Real font metrics would make the arithmetic unreadable without
// testing anything extra.
const measure = (text) => (text ?? '').length * 5;

const options = {
    fontSize: 10,
    fontFamily: "'Lora'",
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

test('markup in the lyrics is escaped rather than emitted', () => {
    const rows = chordproRows([{ lines: [{ items: [pair('', '<b>& "x"')] }] }], options);

    assert.match(rows[0].svg, /&lt;b&gt;&amp; &quot;x&quot;/);
    assert.doesNotMatch(rows[0].svg, /<b>/);
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
