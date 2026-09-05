import assert from 'node:assert/strict';
import test from 'node:test';

import { packPages } from '../../resources/js/booklet-flow.js';

const block = (height, extra = {}) => ({ height, ...extra });

function pageHeights(pages) {
    return pages.map(page => page.items.map(item => item.block.height));
}

test('blocks that fit share one page', () => {
    const pages = packPages([block(100), block(100), block(100)], 400);

    assert.equal(pages.length, 1);
    assert.deepEqual(pageHeights(pages), [[100, 100, 100]]);
});

test('a block that does not fit starts the next page', () => {
    const pages = packPages([block(100), block(100), block(100)], 250);

    assert.deepEqual(pageHeights(pages), [[100, 100], [100]]);
});

test('blocks are placed at running offsets down the page', () => {
    const pages = packPages([block(100), block(60)], 400);

    assert.deepEqual(pages[0].items.map(item => item.y), [0, 100]);
    assert.equal(pages[0].height, 160);
});

test('spaceBefore separates blocks but never opens a page', () => {
    const pages = packPages(
        [block(100), block(100, { spaceBefore: 20 }), block(100, { spaceBefore: 20 })],
        250,
    );

    // 100 + 20 + 100 = 220 fits; the third would make 340, so it moves over —
    // and lands at the very top of page two rather than 20 below it.
    assert.deepEqual(pageHeights(pages), [[100, 100], [100]]);
    assert.deepEqual(pages[0].items.map(item => item.y), [0, 120]);
    assert.deepEqual(pages[1].items.map(item => item.y), [0]);
});

test('a title is never left at the foot of a page without its music', () => {
    const pages = packPages(
        [
            block(200),
            block(20, { keepWithNext: true, startsScore: true }),
            block(120),
        ],
        300,
    );

    // The title alone would fit under the 200 block; its music would not, so
    // both move together.
    assert.deepEqual(pageHeights(pages), [[200], [20, 120]]);
});

test('a chain of keep-with-next blocks moves as one', () => {
    const pages = packPages(
        [
            block(200),
            block(20, { keepWithNext: true }),
            block(15, { keepWithNext: true }),
            block(100),
        ],
        300,
    );

    assert.deepEqual(pageHeights(pages), [[200], [20, 15, 100]]);
});

test('breakBefore forces a fresh page even when there is room', () => {
    const pages = packPages(
        [block(50), block(50, { breakBefore: true }), block(50)],
        400,
    );

    assert.deepEqual(pageHeights(pages), [[50], [50, 50]]);
});

test('breakBefore on the very first block does not leave an empty page', () => {
    const pages = packPages([block(50, { breakBefore: true }), block(50)], 400);

    assert.deepEqual(pageHeights(pages), [[50, 50]]);
});

test('a block taller than the page is placed rather than dropped', () => {
    const pages = packPages([block(100), block(500)], 300);

    assert.deepEqual(pageHeights(pages), [[100], [500]]);
});

test('no blocks makes no pages', () => {
    assert.deepEqual(packPages([], 400), []);
});
