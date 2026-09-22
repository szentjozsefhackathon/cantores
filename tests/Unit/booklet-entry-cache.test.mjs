import assert from 'node:assert/strict';
import test from 'node:test';

import { createEntryCache } from '../../resources/js/booklet-render.js';

/**
 * The renderer keeps each entry's blocks between renders, so a knob turned on
 * one score sends that score alone back through its engraver. These check the
 * bookkeeping with a stand-in engraver that counts what it is asked to draw.
 */
function countingCache({ complete = () => true } = {}) {
    const engraved = [];
    const cache = createEntryCache(
        async (entry) => {
            engraved.push(entry.id);

            return { blocks: [{ svg: `<svg id="${entry.id}"/>` }], fonts: [], complete: complete(entry) };
        },
        (markup) => ({ parsed: markup }),
    );

    async function render(entries, geometry = { pageWidthPx: 500 }) {
        const pass = cache.begin();
        const built = [];

        for (const entry of entries) {
            built.push(await pass.blocksOf(entry, geometry, null));
        }

        pass.end();

        return built;
    }

    return { engraved, render };
}

const score = (id, override = {}) => ({ id, kind: 'score', format: 'abc', content: `X:${id}`, override });

test('a booklet drawn again unchanged engraves nothing', async () => {
    const { engraved, render } = countingCache();
    const entries = [score(1), score(2), score(3)];

    const first = await render(entries);
    const second = await render(entries);

    assert.deepEqual(engraved, [1, 2, 3]);
    assert.equal(second[1], first[1], 'expected the very same blocks back');
});

test('one score nudged is the only one engraved again', async () => {
    const { engraved, render } = countingCache();

    await render([score(1), score(2), score(3)]);
    await render([score(1), score(2, { abcPageScale: 0.8 }), score(3)]);

    assert.deepEqual(engraved, [1, 2, 3, 2]);
});

test('a new geometry engraves everything again', async () => {
    const { engraved, render } = countingCache();
    const entries = [score(1), score(2)];

    await render(entries, { pageWidthPx: 500 });
    await render(entries, { pageWidthPx: 420 });

    assert.deepEqual(engraved, [1, 2, 1, 2]);
});

test('only what the last render used is kept', async () => {
    const { engraved, render } = countingCache();

    await render([score(1, { abcPageScale: 0.8 })]);
    await render([score(1, { abcPageScale: 0.9 })]);
    await render([score(1, { abcPageScale: 0.8 })]);

    assert.deepEqual(engraved, [1, 1, 1]);
});

test('each block is parsed once, when it is engraved', async () => {
    const { render } = countingCache();

    const [built] = await render([score(1)]);

    assert.deepEqual(built.blocks[0].element, { parsed: '<svg id="1"/>' });
});

test('an uploaded score missing some of its systems is fetched again next time', async () => {
    const { engraved, render } = countingCache({ complete: (entry) => entry.id !== 2 });
    const entries = [score(1), score(2)];

    await render(entries);
    await render(entries);

    assert.deepEqual(engraved, [1, 2, 2]);
});
