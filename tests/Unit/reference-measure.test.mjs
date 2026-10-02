import assert from 'node:assert/strict';
import test from 'node:test';

import { MEASURE_REFERENCE_PX, referenceContext, referenceFont } from '../../resources/js/reference-measure.js';

/*
 * Every screen engraving the same deck at the same ratio must cut it into the
 * same slides, and a browser rounds a word's ink to whole pixels at the size it
 * is asked about — differently on different systems. So words are measured at a
 * thousand pixels and scaled down, where that rounding no longer moves a page.
 */

/** A 2D context that measures like a browser: proportional to the size, the ink rounded the way `round` does. */
function browserContext(round) {
    let font = '10px sans-serif';
    const sizeOf = () => Number(/(\d+(?:\.\d+)?)px/.exec(font)[1]);

    return {
        drawnIn: [],
        get font() { return font; },
        set font(value) { font = value; },
        measureText(text) {
            const px = sizeOf();

            return {
                width: text.length * px * 0.4321,
                actualBoundingBoxAscent: round(px * 0.7333),
                fontBoundingBoxAscent: round(px * 1.0049),
            };
        },
        fillText(text) { this.drawnIn.push(font); return text; },
    };
}

test('a font is restated at the reference size, with the factor back', () => {
    assert.deepEqual(referenceFont("italic bold 66.5px 'Barlow Condensed'"), {
        font: `italic bold ${MEASURE_REFERENCE_PX}px 'Barlow Condensed'`,
        scale: 0.0665,
    });
    assert.deepEqual(referenceFont('12pt serif'), { font: '12pt serif', scale: 1 }, 'a size that is not pixels is left alone');
    assert.deepEqual(referenceFont(''), { font: '', scale: 1 });
});

test('a word is measured at the reference size and answered at the size asked', () => {
    const context = referenceContext(browserContext((value) => value));

    context.font = '66.667px serif';
    const metrics = context.measureText('Kyrie');

    assert.ok(Math.abs(metrics.width - 5 * 66.667 * 0.4321) < 1e-9);
    assert.ok(Math.abs(metrics.actualBoundingBoxAscent - 66.667 * 0.7333) < 1e-9);
    assert.equal(context.font, '66.667px serif', 'the size asked about is the size kept');
});

test('two systems that round the ink differently measure the same ascent to under a tenth of a pixel', () => {
    const up = referenceContext(browserContext(Math.ceil));
    const down = referenceContext(browserContext(Math.floor));
    const size = 66.667;

    up.font = `${size}px serif`;
    down.font = `${size}px serif`;

    // Asked at the lyric's own size, the two would be a whole pixel apart.
    assert.equal(Math.ceil(size * 0.7333) - Math.floor(size * 0.7333), 1);

    const apart = Math.abs(up.measureText('ri').actualBoundingBoxAscent - down.measureText('ri').actualBoundingBoxAscent);

    assert.ok(apart < 0.1, `${apart} px apart`);
});

/* exsurge draws with the same context it measures with; only the measuring
   steps up to the reference size. */
test('everything but measuring is the wrapped context\'s own', () => {
    const inner = browserContext((value) => value);
    const context = referenceContext(inner);

    context.font = '20px serif';
    context.measureText('x');
    context.fillText('x');

    assert.deepEqual(inner.drawnIn, ['20px serif']);
});

test('without a browser the engine keeps its own estimate', async () => {
    const { referenceMeasures } = await import('../../resources/js/score-editor-aretino.js');

    assert.deepEqual(referenceMeasures(), {});
});
