import assert from 'node:assert/strict';
import test from 'node:test';

import { packSoftPages, stackHeight } from '../../resources/js/soft-pages.js';

/**
 * A laid-out row, as markdownRows() hands one over: a height, the gap it wants
 * above it, and whatever break stood over the block it opens.
 */
const row = (height, extra = {}) => ({ height, spaceBefore: 0, keepWithNext: false, breakBefore: null, ...extra });

const heights = (pages) => pages.map((page) => page.rows.map((r) => r.height));

test('a row of words that fits is one screen', () => {
    const pages = packSoftPages([row(100), row(100), row(100)], 400);

    assert.deepEqual(heights(pages), [[100, 100, 100]]);
});

test('an empty row comes to no screens at all', () => {
    assert.deepEqual(packSoftPages([], 400), []);
});

test('an instruction cuts however well the words fit', () => {
    const pages = packSoftPages([row(100), row(100, { breakBefore: 'hard' })], 4000);

    assert.deepEqual(heights(pages), [[100], [100]]);
});

test('a suggestion goes unused where the words already fit', () => {
    const pages = packSoftPages([row(100), row(100, { breakBefore: 'soft' })], 400);

    assert.deepEqual(heights(pages), [[100, 100]]);
});

test('a suggestion is taken once the words overflow', () => {
    const pages = packSoftPages([row(100), row(100, { breakBefore: 'soft' })], 150);

    assert.deepEqual(heights(pages), [[100], [100]]);
});

test('only as many suggestions are taken as the screen needs', () => {
    const rows = [
        row(100),
        row(100, { breakBefore: 'soft' }),
        row(100, { breakBefore: 'soft' }),
    ];

    // Two hundred fits, three hundred does not: the second suggestion is spent
    // and the first is not, rather than every mark in the row being obeyed.
    assert.deepEqual(heights(packSoftPages(rows, 250)), [[100, 100], [100]]);
});

test('a suggestion inside an instruction is weighed against its own chunk', () => {
    const rows = [
        row(100),
        row(100, { breakBefore: 'soft' }),
        row(50, { breakBefore: 'hard' }),
        row(50, { breakBefore: 'soft' }),
    ];

    // At 150 the first chunk overflows and spends its suggestion while the
    // second, which fits, keeps its own; at 250 neither spends anything.
    assert.deepEqual(heights(packSoftPages(rows, 150)), [[100], [100], [50, 50]]);
    assert.deepEqual(heights(packSoftPages(rows, 250)), [[100, 100], [50, 50]]);
});

test('a chunk with nothing to spend is cut at its own blocks', () => {
    const pages = packSoftPages([row(100), row(100), row(100)], 250);

    assert.deepEqual(heights(pages), [[100, 100], [100]]);
});

test('a heading is never left at the foot of a screen without its text', () => {
    const rows = [row(100), row(100, { keepWithNext: true }), row(100)];

    assert.deepEqual(heights(packSoftPages(rows, 250)), [[100], [100, 100]]);
});

test('a paragraph taller than the screen is handed back whole to be set smaller', () => {
    const pages = packSoftPages([row(500)], 250);

    assert.deepEqual(heights(pages), [[500]]);
    assert.equal(pages[0].height, 500);
});

test('a segment that overflows on its own falls through to its blocks', () => {
    const rows = [
        row(100),
        row(100, { breakBefore: 'soft' }),
        row(100),
        row(100),
    ];

    // The first screen holds a suggestion, so it is cut there; the 300 left is
    // cut again, at its own block boundaries, because there is nothing else left
    // to spend.
    assert.deepEqual(heights(packSoftPages(rows, 250)), [[100], [100, 100], [100]]);
});

test('the tail of an overflowing segment shares a screen with the next one', () => {
    const rows = [
        row(100),
        row(100),
        row(100),
        row(100, { breakBefore: 'soft' }),
    ];

    // The suggestion lies past the first screen, so it is not reached for: the
    // screen is filled, and what is left flows on with the piece after it.
    assert.deepEqual(heights(packSoftPages(rows, 250)), [[100, 100], [100, 100]]);
});

test('a suggestion on the screen is the cut taken, however much more would fit', () => {
    const rows = [
        row(100),
        row(100, { breakBefore: 'soft' }),
        row(100),
        row(100),
    ];

    const pages = packSoftPages(rows, 300);

    assert.deepEqual(heights(pages), [[100], [100, 100, 100]]);
    assert.equal(pages[1].rows[0].breakBefore, 'soft');
});

test('of several suggestions on one screen, the last is taken', () => {
    const rows = [
        row(100),
        row(100, { breakBefore: 'soft' }),
        row(100, { breakBefore: 'soft' }),
        row(100),
        row(100),
    ];

    assert.deepEqual(heights(packSoftPages(rows, 350)), [[100, 100], [100, 100, 100]]);
});

test('the space above a screens first row belongs to what it was cut from', () => {
    const rows = [row(100), row(100, { spaceBefore: 40, breakBefore: 'hard' })];
    const pages = packSoftPages(rows, 400);

    assert.deepEqual(pages.map((page) => page.height), [100, 100]);
});

test('rows sharing a screen keep the space between them', () => {
    assert.equal(stackHeight([row(100), row(100, { spaceBefore: 40 })]), 240);
    assert.equal(stackHeight([row(100, { spaceBefore: 40 })]), 100);
});

/*
 * What a chord sheet adds to a row of words: a row can say where a cut would
 * fall inside the group keepWithNext is holding together. Rows that say nothing
 * — every row markdownRows writes — never reach these two tiers, which is what
 * keeps a heading on the screen it introduces.
 */
test('a group is cut where its rows allow it rather than costing a screen', () => {
    const held = (height, splitBefore) => row(height, { keepWithNext: true, splitBefore });
    const pages = packSoftPages(
        [held(100, true), held(100, false), held(100, true), row(100, { splitBefore: false })],
        250,
    );

    assert.deepEqual(heights(pages), [[100, 100], [100, 100]]);
});

test('a group that says nothing about its own cuts is moved whole', () => {
    const pages = packSoftPages(
        [row(100), row(100, { keepWithNext: true }), row(100, { keepWithNext: true }), row(100)],
        250,
    );

    assert.deepEqual(heights(pages), [[100], [100, 100, 100]]);
});

/**
 * A chord sheet's row: a line of a paragraph, which knows whether it opens one.
 */
const line = (height, startsParagraph = false, extra = {}) => row(height, { splitBefore: true, startsParagraph, ...extra });

test('a screen ends between two paragraphs rather than inside one', () => {
    const pages = packSoftPages([line(100, true), line(100), line(100, true), line(100)], 300);

    assert.deepEqual(heights(pages), [[100, 100], [100, 100]]);
});

test('a paragraph taller than a screen is cut at its lines and fills the screen', () => {
    const pages = packSoftPages([line(100, true), line(100, true), line(100), line(100), line(100)], 300);

    assert.deepEqual(heights(pages), [[100, 100, 100], [100, 100]]);
});

test('a suggestion still beats a paragraph boundary further down', () => {
    const pages = packSoftPages(
        [line(100, true), line(100, false, { breakBefore: 'soft' }), line(100, true), line(100)],
        350,
    );

    assert.deepEqual(heights(pages), [[100], [100, 100, 100]]);
});

test('a wrapped line is not cut while a paragraph boundary fits', () => {
    const pages = packSoftPages(
        [line(100, true), line(100, true), row(100, { splitBefore: false }), line(100)],
        300,
    );

    assert.deepEqual(heights(pages), [[100], [100, 100, 100]]);
});

test('rows that say nothing of paragraphs are filled as before', () => {
    const pages = packSoftPages([line(100), line(100), line(100), line(100)], 300);

    assert.deepEqual(heights(pages), [[100, 100, 100], [100]]);
});
