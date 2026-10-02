import assert from 'node:assert/strict';
import test from 'node:test';

/*
 * Every screen engraving the same deck at the same ratio must cut it into the
 * same slides, and a browser rounds a word's ink to whole pixels at the size it
 * is asked about — differently on different systems. So the engine is handed
 * measurers that ask at a thousand pixels and scale down, where that rounding
 * no longer moves a page.
 */

/** A canvas that measures like a browser: everything proportional to the size, the ink rounded the way `round` does. */
function browser(round) {
    let px = 0;

    return {
        createElement: () => ({
            getContext: () => ({
                set font(value) { px = Number(/(\d+(?:\.\d+)?)px/.exec(value)[1]); },
                measureText: (text) => ({
                    width: text.length * px * 0.4321,
                    actualBoundingBoxAscent: round(px * 0.7333),
                    fontBoundingBoxAscent: round(px * 1.0049),
                }),
            }),
        }),
    };
}

async function measuresIn(round) {
    globalThis.document = browser(round);

    try {
        const { referenceMeasures } = await import(`../../resources/js/score-editor-aretino.js?browser=${Math.random()}`);

        return referenceMeasures();
    } finally {
        delete globalThis.document;
    }
}

test('a word is measured at a thousand pixels and scaled to the size it is set in', async () => {
    const { measureText, measureAscent } = await measuresIn((value) => value);

    assert.ok(Math.abs(measureText('Kyrie', 66.667, "'Barlow Condensed'") - 5 * 66.667 * 0.4321) < 1e-9);
    assert.ok(Math.abs(measureAscent('Kyrie', 66.667, "'Barlow Condensed'") - 66.667 * 0.7333) < 1e-9);
    assert.equal(measureText('', 66.667, 'serif'), 0);
    assert.equal(measureAscent('', 66.667, 'serif'), 0);
});

test('two systems that round the ink differently measure the same ascent to under a tenth of a pixel', async () => {
    const up = await measuresIn(Math.ceil);
    const down = await measuresIn(Math.floor);
    const size = 66.667;

    // Asked at the lyric's own size, the two would be a whole pixel apart.
    assert.equal(Math.ceil(size * 0.7333) - Math.floor(size * 0.7333), 1);

    const apart = Math.abs(up.measureAscent('ri', size, 'serif') - down.measureAscent('ri', size, 'serif'));

    assert.ok(apart < 0.1, `${apart} px apart`);
});

test('without a browser the engine keeps its own estimate', async () => {
    const { referenceMeasures } = await import('../../resources/js/score-editor-aretino.js');

    assert.deepEqual(referenceMeasures(), {});
});
