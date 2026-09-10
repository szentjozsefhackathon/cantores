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
            { text: 'álljunk ', bold: false, italic: false, color: null, fontSize: 1 },
            { text: 'fel', bold: true, italic: false, color: null, fontSize: 1 },
            { text: ', majd ', bold: false, italic: false, color: null, fontSize: 1 },
            { text: 'üljünk', bold: false, italic: true, color: null, fontSize: 1 },
            { text: ' le', bold: false, italic: false, color: null, fontSize: 1 },
        ],
    );

    assert.deepEqual(
        inlineSegments('***mind a kettő***'),
        [{ text: 'mind a kettő', bold: true, italic: true, color: null, fontSize: 1 }],
    );
});

// A rubric is likelier to contain a stray asterisk than a mistake.
test('a marker with no partner stays the character it was', () => {
    assert.deepEqual(
        inlineSegments('2 * 3'),
        [{ text: '2 * 3', bold: false, italic: false, color: null, fontSize: 1 }],
    );
});

test('a block carries its own emphasis into every segment', () => {
    assert.deepEqual(
        inlineSegments('idézet **benne**', { italic: true }),
        [
            { text: 'idézet ', bold: false, italic: true, color: null, fontSize: 1 },
            { text: 'benne', bold: true, italic: true, color: null, fontSize: 1 },
        ],
    );
});

// A rubric printed in red is the church's oldest way of saying "this is an
// instruction, not a word to sing"; <small> is for an aside that should not
// crowd the line it sits on.
test('a <red> tag colours the words it wraps and closes back to black', () => {
    assert.deepEqual(
        inlineSegments('most <red>térdelünk</red> tovább'),
        [
            { text: 'most ', bold: false, italic: false, color: null, fontSize: 1 },
            { text: 'térdelünk', bold: false, italic: false, color: '#cc0000', fontSize: 1 },
            { text: ' tovább', bold: false, italic: false, color: null, fontSize: 1 },
        ],
    );
});

test('a <small> tag shrinks the words it wraps and closes back to full size', () => {
    assert.deepEqual(
        inlineSegments('Kyrie <small>(görögül)</small> eleison'),
        [
            { text: 'Kyrie ', bold: false, italic: false, color: null, fontSize: 1 },
            { text: '(görögül)', bold: false, italic: false, color: null, fontSize: 0.8 },
            { text: ' eleison', bold: false, italic: false, color: null, fontSize: 1 },
        ],
    );
});

test('the tags nest inside emphasis and each other', () => {
    assert.deepEqual(
        inlineSegments('**<red><small>rúbrika</small></red>**'),
        [{ text: 'rúbrika', bold: true, italic: false, color: '#cc0000', fontSize: 0.8 }],
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

// Modest on purpose: these headings sit between staves on a page the size of a
// hand, where a document's proportions read as a shout.
test('the heading levels are only a little larger than the text', () => {
    const size = (source) => parseFloat(
        markdownRows(source, options)[0].svg.match(/font-size="([\d.]+)"/)[1],
    );

    assert.equal(size('# Egy'), 12);
    assert.equal(size('## Kettő'), 11);
    assert.equal(size('### Három'), 10);
    assert.equal(size('Szöveg.'), 10);
});

test('the booklet\'s heading scale multiplies every level', () => {
    const rows = markdownRows('# Cím\n\n## Alcím\n\nSzöveg.', { ...options, headingScale: 0.5 });
    const size = (row) => parseFloat(row.svg.match(/font-size="([\d.]+)"/)[1]);

    assert.equal(size(rows[0]), 6);
    assert.equal(size(rows[1]), 5.5);
    // The body is not a heading and is not touched by it.
    assert.equal(size(rows[2]), 10);
});

test('paragraphs are spaced apart but may break across pages', () => {
    const rows = markdownRows('Első.\n\nMásodik.', options);

    assert.equal(rows[0].spaceBefore, 0);
    assert.ok(rows[1].spaceBefore > 0);
    // Less than a line of it: the leading already separates them.
    assert.ok(rows[1].spaceBefore < options.fontSize);
    assert.equal(rows[0].keepWithNext, false);
});

// The gap is part of how loudly a heading speaks, so it answers to the same
// knob the type does — a heading halved and left in a full-sized gap reads as a
// paragraph someone bolded by accident.
test('the heading scale moves the air around a heading with it', () => {
    const source = 'Előtte.\n\n# Cím\n\nUtána.';
    const full = markdownRows(source, options);
    const half = markdownRows(source, { ...options, headingScale: 0.5 });

    // Above the heading, and between the heading and the text it introduces.
    assert.ok(Math.abs(half[1].spaceBefore - full[1].spaceBefore / 2) < 1e-9);
    assert.ok(Math.abs(half[2].spaceBefore - full[2].spaceBefore / 2) < 1e-9);

    // Two paragraphs are the body's business, and are left alone by it.
    const plain = (scale) => markdownRows('Első.\n\nMásodik.', { ...options, headingScale: scale });
    assert.equal(plain(0.5)[1].spaceBefore, plain(1)[1].spaceBefore);
});

test('a heading is given more air above it than between it and its text', () => {
    const rows = markdownRows('Előtte.\n\n# Cím\n\nUtána.', options);
    const paragraphGap = markdownRows('Első.\n\nMásodik.', options)[1].spaceBefore;

    assert.ok(rows[1].spaceBefore > paragraphGap, 'a heading opens something and is given room to');
    assert.ok(rows[2].spaceBefore < rows[1].spaceBefore, 'a heading sits closer to its text than to what precedes it');
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

test('a <red> run reaches the page with a red fill and a <small> run with a smaller size', () => {
    const [red] = markdownRows('lásd <red>piros</red>', options);
    assert.match(red.svg, /<text[^>]*fill="#cc0000"[^>]*>piros<\/text>/);

    const [small] = markdownRows('lásd <small>kicsi</small>', options);
    assert.match(small.svg, /<text[^>]*font-size="8"[^>]*>kicsi<\/text>/);
});

// The measure has to be told the run is small, or the words after it are placed
// as though it were full size and drift right of where they are drawn.
test('a <small> run is measured at its own size, so what follows it sits tight', () => {
    // measure is length * fontSize * 0.5: 'ab' is 10 wide, the small 'cd' 8, a
    // full space 5, a small space 4 — so 'ef' lands at 10 + 4 + 8 + 5 = 27.
    const [row] = markdownRows('ab <small>cd</small> ef', options);

    assert.match(row.svg, /<text x="27"[^>]*>ef<\/text>/);
});

test('the width of a run stays inside a tag that also carries colour', () => {
    // 'piros' small-and-red is 5 * 8 * 0.5 = 20 wide; 'Valami' then sits at
    // 20 + 5 (a full space) = 25.
    const [row] = markdownRows('<red><small>piros</small></red> Valami', options);

    assert.match(row.svg, /<text[^>]*font-size="8"[^>]*fill="#cc0000"[^>]*>piros<\/text>/);
    assert.match(row.svg, /<text x="25"[^>]*>Valami<\/text>/);
});

test('markup characters reach the page escaped', () => {
    const [row] = markdownRows('a < b & c', options);

    assert.match(row.svg, /a &lt; b &amp; c/);
});

// A rubric set in a face with a large x-height is set at a smaller em, and every
// vertical measure taken in that em would pull the rubric up with it. The
// leading is taken in the nominal em instead, so a paragraph occupies the same
// depth of page whichever face the booklet is set in.
test('the leading follows the size the booklet quoted, not the em it is set at', () => {
    const source = '# Rubrika\n\nÁlljunk fel.\n\n---\n\nÜljünk le.';

    const depth = (rows) => rows.reduce((total, row) => total + row.height + (row.spaceBefore ?? 0), 0);

    const reference = markdownRows(source, options);
    // A face set at four fifths of the em, given back the leading of the whole.
    const compensated = markdownRows(source, { ...options, fontSize: 8, leadingScale: 10 / 8 });

    assert.ok(depth(reference) > 0);
    assert.ok(Math.abs(depth(compensated) - depth(reference)) < 1e-9, 'the rubric changed depth with the face');
    // Only the leading was restated: the letters are still set at the em asked for.
    assert.match(compensated[0].svg, /font-size="9.6"/);
});
