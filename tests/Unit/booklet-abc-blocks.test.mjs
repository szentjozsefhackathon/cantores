import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { fileURLToPath } from 'node:url';
import vm from 'node:vm';

import { pageGeometry } from '../../resources/js/booklet-geometry.js';
import { buildScoreBlocks } from '../../resources/js/booklet-render.js';

/**
 * The ABC half of the block pipeline, run against the real abc2svg.
 *
 * The engraver wants a global and no DOM, so it is evaluated once into a sandbox
 * and handed to the module under test the way the browser hands it over.
 */
const sandbox = { abc2svg: {}, console };
vm.createContext(sandbox);
vm.runInContext(
    readFileSync(fileURLToPath(new URL('../../public/js/abc2svg-1.js', import.meta.url)), 'utf8'),
    sandbox,
);
globalThis.abc2svg = sandbox.abc2svg;

const geometry = pageGeometry({
    pageWidthMm: 148,
    pageHeightMm: 210,
    marginMm: 12,
    contentWidthMm: 124,
    contentHeightMm: 186,
    lyricSizePt: 11,
    staffHeightMm: 7,
});

const HYMN = 'L:1/4\nK:C\nCDEF GABc|cBAG FEDC|CDEF GABc|cBAG FEDC|\nw: la la la la la la la la la la la la la la la la\n';

const entry = (over = {}) => ({
    id: 1,
    kind: 'score',
    slot: 'Kezdőének',
    music: null,
    variation: null,
    format: 'abc',
    content: HYMN,
    settings: {},
    override: null,
    startOnNewPage: false,
    credit: null,
    ...over,
});

const idsIn = (blocks) => blocks.flatMap((block) => [...block.svg.matchAll(/id="([^"]+)"/g)].map((m) => m[1]));
const refsIn = (blocks) => blocks.flatMap((block) => [...block.svg.matchAll(/href="#([^"]+)"/g)].map((m) => m[1]));

// Two scores at different staff scales draw their staff lines at different
// lengths — each is the page's width in its own units — but abc2svg names that
// path `stdef` in both, and a booklet page keeps the first of any name. So the
// second score's staff lines came out at the first score's width: short of the
// page, or past its edge. Every engraving therefore names its glyphs for itself.
test('two scores on a page never name a glyph alike', async () => {
    const { blocks: first } = await buildScoreBlocks(entry({ id: 1 }), geometry, null);
    const { blocks: second } = await buildScoreBlocks(entry({ id: 2, override: { abcPageScale: 0.6 } }), geometry, null);

    const shared = idsIn(first).filter((id) => idsIn(second).includes(id));
    assert.deepEqual(shared, [], 'the two engravings define the same name');

    // And each fragment points at a definition of its own rather than at nothing.
    for (const blocks of [first, second]) {
        for (const ref of refsIn(blocks)) {
            assert.ok(idsIn(blocks).includes(ref), `#${ref} is referenced but never defined`);
        }
    }

    assert.ok(idsIn(first).some((id) => id.startsWith('stdef')), 'expected the staff lines to be defined once and used');
});

// The staff lines really are drawn at the width each score was laid out at, so a
// score at half the scale is engraved on a staff twice as long in its own units.
test('a score at another staff scale draws its own staff lines', async () => {
    const staffWidth = (blocks) => {
        const path = blocks.map((block) => block.svg.match(/id="stdef[^"]*" class="slW" d="m0 0h([\d.]+)/)).find(Boolean);

        assert.ok(path, 'no staff-line definition in the engraving');

        return Number(path[1]);
    };

    const { blocks: plain } = await buildScoreBlocks(entry(), geometry, null);
    const { blocks: small } = await buildScoreBlocks(entry({ override: { abcPageScale: 0.5 } }), geometry, null);

    assert.ok(staffWidth(small) > staffWidth(plain) * 1.9, 'a halved scale should double the staff length in the score’s own units');
});
