import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

/**
 * A booklet is measured before it is drawn — canvas.measureText for text and
 * ChordPro, the engines' own metrics for the music — and every number lands in
 * an SVG that is never measured again. Measure while the browser is still on the
 * fallback face and the whole booklet keeps the wrong widths until something
 * else forces a fresh render, which is the "right only on the second try" the
 * score editor already had fixed for Aretino.
 */

const loaded = [];

globalThis.document = {
    fonts: {
        load(spec) {
            loaded.push(spec);

            return Promise.resolve([]);
        },
    },
};

const { ensureFontsLoaded } = await import('../../resources/js/svg-fonts.js');

test('every style of a family is loaded before anything is measured in it', async () => {
    loaded.length = 0;

    await ensureFontsLoaded(["'Lora', serif"], 22);

    assert.deepEqual(loaded, [
        '22px "Lora"',
        'italic 22px "Lora"',
        'bold 22px "Lora"',
        'italic bold 22px "Lora"',
    ]);
});

test('a family already waited for is not fetched twice', async () => {
    await ensureFontsLoaded(["'Inter'"], 12);
    loaded.length = 0;

    await ensureFontsLoaded(['Inter', "'Inter', sans-serif"], 12);

    assert.deepEqual(loaded, [], 'the same face was asked for again');
});

test('a family with no name asks for nothing', async () => {
    loaded.length = 0;

    await ensureFontsLoaded(['', null, undefined], 12);

    assert.deepEqual(loaded, []);
});

const render = await readFile(new URL('../../resources/js/booklet-render.js', import.meta.url), 'utf8');

// The fonts a render reports are the ones it has already drawn with, so they
// arrive too late to be waited for. Both booklet renderers therefore work the
// families out from the entries first, and wait, before a block is built.
test('both booklet renderers wait for their faces before building a block', () => {
    for (const fn of ['renderBooklet', 'renderBookletFlow']) {
        const body = render.slice(render.indexOf(`export async function ${fn}(`));
        const wait = body.indexOf('await ensureFontsLoaded(bookletFonts(entries, geometry)');
        const build = body.indexOf('buildEntryBlocks(');

        assert.ok(wait !== -1, `${fn} does not wait for its fonts`);
        assert.ok(wait < build, `${fn} builds blocks before the fonts are in`);
    }
});

const { bookletFonts } = await import('../../resources/js/booklet-render.js');
const { pageGeometry } = await import('../../resources/js/booklet-geometry.js');

const geometry = pageGeometry({
    pageWidthMm: 148,
    pageHeightMm: 210,
    marginMm: 12,
    contentWidthMm: 124,
    contentHeightMm: 186,
    lyricSizePt: 11,
    staffHeightMm: 7,
    textFont: 'Inter',
});

const entry = (over = {}) => ({
    id: 1, kind: 'score', format: 'aretino', content: '', settings: {}, override: null, ...over,
});

// The booklet leaves the font to whoever engraved the score, so the families to
// wait for are the scores' own — the booklet's text font alone is not enough.
test('the families waited for are the ones each score is actually set in', () => {
    const fonts = bookletFonts([
        entry({ settings: { aretino: { paper: { aretinoTextFont: "'Lora'" } } } }),
        entry({ id: 2, format: 'chordpro', settings: { chordpro: { paper: { chordproFontFamily: "'Barlow Condensed', sans-serif" } } } }),
        entry({ id: 3, format: 'abc', settings: { abc: { paper: { abcLyricFont: 'EB Garamond' } } } }),
        entry({ id: 4, kind: 'text', text: 'Rubrika' }),
    ], geometry);

    assert.ok(fonts.includes("'Inter'"), 'the booklet\'s own text font is missing');
    assert.ok(fonts.includes("'Lora'"));
    assert.ok(fonts.includes("'Barlow Condensed', sans-serif"));
    assert.ok(fonts.includes('EB Garamond'));
});

// A per-score override is what someone changed by hand, and it wins over the
// score's own settings here exactly as it does in the render.
test('a font overridden for this booklet is the one waited for', () => {
    const fonts = bookletFonts([
        entry({ settings: { aretino: { paper: { aretinoTextFont: "'Lora'" } } }, override: { aretinoTextFont: "'EB Garamond'" } }),
    ], geometry);

    assert.ok(fonts.includes("'EB Garamond'"));
    assert.ok(! fonts.includes("'Lora'"), 'the overridden font was waited for instead');
});
