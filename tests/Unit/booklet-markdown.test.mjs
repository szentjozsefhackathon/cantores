import assert from 'node:assert/strict';
import test from 'node:test';

import { inlineSegments, markdownRows, parseBlocks, wrapWords } from '../../resources/js/booklet-markdown.js';

/** A metric with no font in it: every character is half the size wide. */
const measure = (text, { fontSize = 10 } = {}) => (text ?? '').length * fontSize * 0.5;

const options = { fontSize: 10, fontFamily: 'Inter', layoutWidth: 100, measure };

test('blocks are told apart by their markers', () => {
    const blocks = parseBlocks([
        '# Rubrika',
        '',
        'Két sor,',
        'egy bekezdés.',
        '',
        '- első',
        '2. második',
        '> idézet',
        '---',
    ].join('\n'));

    assert.deepEqual(blocks.map((block) => block.type), [
        'heading', 'paragraph', 'list', 'list', 'quote', 'rule',
    ]);
    assert.equal(blocks[0].level, 1);
    assert.equal(blocks[1].text, 'Két sor, egy bekezdés.');
    assert.equal(blocks[2].marker, '•');
    assert.equal(blocks[3].marker, '2.');
});

test('emphasis splits a line and nests', () => {
    assert.deepEqual(
        inlineSegments('álljunk **fel**, majd *üljünk* le'),
        [
            { text: 'álljunk ', bold: false, italic: false },
            { text: 'fel', bold: true, italic: false },
            { text: ', majd ', bold: false, italic: false },
            { text: 'üljünk', bold: false, italic: true },
            { text: ' le', bold: false, italic: false },
        ],
    );

    assert.deepEqual(
        inlineSegments('***mind a kettő***'),
        [{ text: 'mind a kettő', bold: true, italic: true }],
    );
});

// A rubric is likelier to contain a stray asterisk than a mistake.
test('a marker with no partner stays the character it was', () => {
    assert.deepEqual(
        inlineSegments('2 * 3'),
        [{ text: '2 * 3', bold: false, italic: false }],
    );
});

test('a block carries its own emphasis into every segment', () => {
    assert.deepEqual(
        inlineSegments('idézet **benne**', { italic: true }),
        [
            { text: 'idézet ', bold: false, italic: true },
            { text: 'benne', bold: true, italic: true },
        ],
    );
});

test('words wrap at the given width and an oversized word gets its own line', () => {
    const words = ['aaa', 'bbb', 'cccccccccccccccc', 'd']
        .map((text) => ({ text, bold: false, italic: false }));

    const lines = wrapWords(words, 40, measure);

    assert.deepEqual(lines.map((line) => line.map((word) => word.text)), [
        ['aaa', 'bbb'],
        ['cccccccccccccccc'],
        ['d'],
    ]);
});

test('every row is a standalone svg with a height', () => {
    const rows = markdownRows('# Cím\n\nEgy rövid mondat.', options);

    assert.ok(rows.length >= 2);
    rows.forEach((row) => {
        assert.ok(row.height > 0);
        assert.match(row.svg, /^<svg xmlns=/);
        assert.match(row.svg, /viewBox="0 0 /);
    });
});

test('a heading is set larger and holds on to what follows it', () => {
    const rows = markdownRows('# Cím\n\nSzöveg.', options);

    assert.equal(rows[0].keepWithNext, true);
    assert.ok(rows[0].height > rows[1].height);
    assert.match(rows[0].svg, /font-weight="bold"/);
});

test('paragraphs are spaced apart but may break across pages', () => {
    const rows = markdownRows('Első.\n\nMásodik.', options);

    assert.equal(rows[0].spaceBefore, 0);
    assert.ok(rows[1].spaceBefore > 0);
    assert.equal(rows[0].keepWithNext, false);
});

test('a list item is marked and indented', () => {
    const [row] = markdownRows('- állva', options);

    assert.match(row.svg, />•</);
    assert.match(row.svg, /<text x="14"/);
});

test('a rule is drawn rather than written', () => {
    const [row] = markdownRows('---', options);

    assert.match(row.svg, /<line /);
    assert.doesNotMatch(row.svg, /<text /);
});

test('markup characters reach the page escaped', () => {
    const [row] = markdownRows('a < b & c', options);

    assert.match(row.svg, /a &lt; b &amp; c/);
});
