import assert from 'node:assert/strict';
import test from 'node:test';

import { buildScoreBlocks, buildTextBlocks, scopeLiftedMarkup } from '../../resources/js/booklet-render.js';
import { packPages } from '../../resources/js/booklet-flow.js';
import { pageGeometry, pxToMm } from '../../resources/js/booklet-geometry.js';

/**
 * The block half of the pipeline, run against the real Aretino renderer.
 *
 * Everything up to page composition is free of the DOM, so this is a genuine
 * integration check rather than a mock: the engraver really runs, really splits
 * itself into staff rows, and the result really has to fit the page.
 *
 * ABC and GABC need their globals (abc2svg, exsurge) and ChordPro needs a canvas
 * to measure with, so only Aretino can be reached from here.
 */

const geometry = pageGeometry({
    pageWidthMm: 148,
    pageHeightMm: 210,
    marginMm: 12,
    contentWidthMm: 124,
    contentHeightMm: 186,
    lyricSizePt: 11,
    staffHeightMm: 7,
    showTitles: true,
});

const CHANT = 'c: f\nn: fg h g f g h\nw: Ky-ri-e e-lei-son Chri-ste e-lei-son\n';

const entry = (over = {}) => ({
    id: 1,
    kind: 'score',
    slot: 'Kyrie',
    music: null,
    variation: null,
    format: 'aretino',
    content: CHANT,
    settings: {},
    override: null,
    startOnNewPage: false,
    credit: null,
    ...over,
});

const widthOf = (svg) => parseFloat(svg.match(/viewBox="\s*[-\d.]+[\s,]+[-\d.]+[\s,]+([\d.]+)/)[1]);

test('a chant becomes a heading block and one block per staff row', async () => {
    const { blocks } = await buildScoreBlocks(entry(), geometry, null);

    assert.ok(blocks.length >= 2, 'expected a heading and at least one staff row');
    assert.equal(blocks[0].keepWithNext, true, 'the heading must not be orphaned');
    assert.match(blocks[0].svg, /Kyrie/);
});

// What is said above a score comes from the plan: the moment in the service,
// the music where a slot holds several, and the variation only when asked for.
test('every heading line the entry carries is set above the music', async () => {
    const { blocks } = await buildScoreBlocks(
        entry({ slot: 'Áldozás', music: 'Ének egy', variation: 'orgonakíséret' }),
        geometry,
        null,
    );

    assert.match(blocks[0].svg, /Áldozás/);
    assert.match(blocks[1].svg, /Ének egy/);
    assert.match(blocks[2].svg, /orgonakíséret/);
    assert.match(blocks[2].svg, /font-style="italic"/);

    // Set at the lyric size, told apart by weight alone.
    assert.match(blocks[0].svg, new RegExp(`font-size="${geometry.lyricSizePx.toFixed(3).replace(/\.?0+$/, '')}`));
    assert.deepEqual(blocks.slice(0, 3).map(block => block.keepWithNext), [true, true, true]);
    assert.deepEqual(blocks.slice(1, 3).map(block => block.spaceBefore), [0, 0]);
});

test('a heading the entry does not carry is not printed', async () => {
    const withSlot = await buildScoreBlocks(entry(), geometry, null);
    const without = await buildScoreBlocks(entry({ slot: null }), geometry, null);

    assert.equal(without.blocks.length, withSlot.blocks.length - 1);
    assert.equal(without.blocks[0].startsScore, true);
    assert.ok(without.blocks[0].spaceBefore > 0, 'the music takes over the gap the heading had');
});

// The guarantee the page depends on: nothing is ever wider than the content box,
// whatever the renderer decided to do. Aretino grows its own viewBox when a long
// word runs past the page edge, so this is not hypothetical.
test('no block is ever wider than the content box once scaled', async () => {
    const long = 'c: f\nn: fg h g f g h g f g h g f\nw: Su-per-ca-li-fra-gi-lis-ti-cus ex-pi-a-li-do-ci-us om-ni-po-ten-tis-si-mus\n';
    const { blocks } = await buildScoreBlocks(entry({ content: long }), geometry, null);

    blocks.forEach((block) => {
        const scaled = widthOf(block.svg) * block.scale;

        assert.ok(
            scaled <= geometry.contentWidthPx + 0.01,
            `block is ${scaled.toFixed(1)}px wide, content box is ${geometry.contentWidthPx.toFixed(1)}px`,
        );
    });
});

test('a block that had to shrink has its height shrunk to match', async () => {
    const long = 'c: f\nn: fg h g f g h g f g h g f\nw: Su-per-ca-li-fra-gi-lis-ti-cus ex-pi-a-li-do-ci-us om-ni-po-ten-tis-si-mus\n';
    const { blocks } = await buildScoreBlocks(entry({ content: long }), geometry, null);

    blocks.filter(block => block.scale < 1).forEach((block) => {
        const natural = parseFloat(block.svg.match(/viewBox="[^"]*?([\d.]+)"/)[1]);

        assert.ok(block.height < natural + 0.01, 'a scaled block must not keep its natural height');
    });
});

test('the staff is engraved at the height the booklet asked for', async () => {
    const { blocks } = await buildScoreBlocks(entry(), geometry, null);
    // Aretino draws its staff as <line> elements; the title block has none.
    const staff = blocks.find(block => block.svg.includes('<line'));

    assert.ok(staff, 'expected a drawn staff row');
    // A four-line staff at 7mm, plus lyrics beneath, on a 186mm page: the row has
    // to be a sane fraction of the page rather than an unscaled nominal canvas.
    assert.ok(pxToMm(staff.height) > 5, 'staff row is implausibly short');
    assert.ok(pxToMm(staff.height) < 60, 'staff row is implausibly tall');
});

test('a chant flows onto pages that each fit', async () => {
    const { blocks } = await buildScoreBlocks(entry(), geometry, null);
    const pages = packPages(blocks, geometry.contentHeightPx);

    assert.ok(pages.length >= 1);
    pages.forEach((page) => {
        assert.ok(
            page.height <= geometry.contentHeightPx || page.items.length === 1,
            'a page may only overflow when a single block is taller than the page',
        );
    });
});

test('an empty score contributes nothing', async () => {
    const { blocks } = await buildScoreBlocks(entry({ content: '   ' }), geometry, null);

    // The title is dropped too: a heading with no music under it is not a score.
    assert.equal(blocks.filter(block => !block.keepWithNext).length, 0);
});

test('titles can be turned off', async () => {
    const { blocks } = await buildScoreBlocks(
        entry(),
        { ...geometry, showTitles: false },
        null,
    );

    assert.doesNotMatch(blocks[0].svg, /Kyrie/);
});

test('a paragraph of instructions flows as blocks like everything else', () => {
    const { blocks } = buildTextBlocks(
        { id: 2, kind: 'text', text: '# Rubrika\n\nÁlljunk **fel**.', startOnNewPage: true },
        geometry,
        (text, { fontSize = geometry.lyricSizePx } = {}) => text.length * fontSize * 0.5,
    );

    assert.ok(blocks.length >= 2);
    assert.equal(blocks[0].startsScore, true);
    assert.equal(blocks[0].breakBefore, true, 'a text entry can ask for a fresh page too');
    assert.ok(blocks[0].spaceBefore > 0);
    assert.match(blocks[0].svg, /Rubrika/);
    assert.match(blocks[blocks.length - 1].svg, /font-weight="bold"[^>]*>fel/);

    const pages = packPages(blocks, geometry.contentHeightPx);
    assert.equal(pages.length, 1);
});

/**
 * exsurge names things for the one document it drew, and a booklet page is made
 * of lines cut out of several such documents laid side by side.
 */
test('a lifted chant line takes its stylesheet and its glyphs with it, under its own name', () => {
    const markup = '<svg class="Exsurge ChantScore exs-4">'
        + '<style>svg.Exsurge .lyric{font-family:\'EB Garamond\';font-size:15px}'
        + 'svg.Exsurge .dropCap{font-size:66px}</style>'
        + '<defs><g id="PunctumQuadratum" class="glyph" transform="scale(0.03)"><path d="M0 0"/></g></defs>'
        + '<g class="chantLine"><use xlink:href="#PunctumQuadratum" id="note-1"/></g>'
        + '</svg>';

    const scoped = scopeLiftedMarkup(markup, 'exs-4', ['PunctumQuadratum']);

    assert.doesNotMatch(scoped, /svg\.Exsurge/, 'no rule may depend on a root the fragment has lost');
    assert.match(scoped, /\.exs-4 \.lyric\{/);
    assert.match(scoped, /\.exs-4 \.dropCap\{/);
    assert.match(scoped, /id="exs-4-PunctumQuadratum"/);
    assert.match(scoped, /xlink:href="#exs-4-PunctumQuadratum"/);
    assert.match(scoped, /id="note-1"/, 'only what the document defines is renamed');
});

test('two chants on one page keep their own glyphs', () => {
    const chant = (id) => `<defs><g id="${id}" transform="scale(0.03)"/></defs>`
        + `<use xlink:href="#${id}"/>`;

    const first = scopeLiftedMarkup(chant('PunctumQuadratum'), 'exs-1', ['PunctumQuadratum']);
    const second = scopeLiftedMarkup(chant('PunctumQuadratum'), 'exs-2', ['PunctumQuadratum']);

    assert.match(first, /id="exs-1-PunctumQuadratum"/);
    assert.match(second, /id="exs-2-PunctumQuadratum"/);
    assert.doesNotMatch(second, /"#exs-1-/, 'the second chant must not draw with the first one\'s scaling');
});
