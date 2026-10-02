import assert from 'node:assert/strict';
import test from 'node:test';

/*
 * abc2svg measures every word through a span's clientWidth and clientHeight:
 * whole pixels at the size the word is set in, rounded by each browser its own
 * way. While a slide is engraved the span is stood in for by one asked at the
 * reference size, so every screen cuts the deck at the same places.
 */

const SIZE = 26.4;
const CHAR = 0.43;
const LINE = 1.25;

/** A span measuring like a browser: proportional to its font size, client sizes rounded. */
function browserSpan() {
    return {
        className: '',
        innerHTML: '',
        parentElement: {},
        style: { fontSize: '', lineHeight: '' },
        size() { return this.style.fontSize ? parseFloat(this.style.fontSize) : SIZE; },
        lineHeight() { return this.style.lineHeight ? Number(this.style.lineHeight) * this.size() : SIZE * LINE; },
        getBoundingClientRect() { return { width: this.innerHTML.length * this.size() * CHAR, height: this.lineHeight() }; },
        get clientWidth() { return Math.round(this.innerHTML.length * this.size() * CHAR); },
        get clientHeight() { return Math.round(this.lineHeight()); },
    };
}

globalThis.document = {};
// The page sets its line height in pixels, which must not stay put while the
// letters grow.
globalThis.getComputedStyle = (span) => ({ fontSize: `${span.size()}px`, lineHeight: `${span.lineHeight()}px` });

const { withReferenceMeasuring } = await import('../../resources/js/measuring-room.js');

test('a word is measured fractionally at the reference size while a slide is engraved', () => {
    const span = browserSpan();
    globalThis.abc2svg = { el: span };

    const [width, height] = withReferenceMeasuring(() => {
        const el = abc2svg.el;

        assert.notEqual(el, span, 'abc2svg was handed its own span');

        el.className = 'f1';
        el.innerHTML = 'Kyrie';

        return [el.clientWidth, el.clientHeight];
    });

    assert.ok(Math.abs(width - 5 * SIZE * CHAR) < 1e-9, `${width}`);
    assert.ok(Math.abs(height - SIZE * LINE) < 1e-9, `${height}`);
    assert.equal(abc2svg.el, span, 'the span was not given back');
    assert.deepEqual(span.style, { fontSize: '', lineHeight: '' }, 'the span was left at the reference size');
});

test('markup, which may carry sizes of its own, is measured as abc2svg always did', () => {
    const span = browserSpan();
    globalThis.abc2svg = { el: span };

    const width = withReferenceMeasuring(() => {
        abc2svg.el.innerHTML = '<b>Ky</b>';

        return abc2svg.el.clientWidth;
    });

    assert.equal(width, Math.round('<b>Ky</b>'.length * SIZE * CHAR));
});

test('the span is given back even when the engraving throws', () => {
    const span = browserSpan();
    globalThis.abc2svg = { el: span };

    assert.throws(() => withReferenceMeasuring(() => { throw new Error('bad tune'); }));
    assert.equal(abc2svg.el, span);
});

/* The fork hangs its first lyric line from each syllable's ink, asked of a
   canvas of its own making; while a slide is engraved that canvas answers from
   the font files. */
test('a canvas the engraving makes answers ink heights from the font files', async () => {
    const { cssFontParts } = await import('../../resources/js/measuring-room.js');
    let font = '';
    const fakeCanvas = {
        getContext: () => ({
            get font() { return font; },
            set font(value) { font = value; },
            measureText: () => ({ width: 10, actualBoundingBoxAscent: 999 }),
        }),
    };
    const span = browserSpan();

    globalThis.abc2svg = { el: span };
    globalThis.document = { createElement(tag) { return tag === 'canvas' ? { ...fakeCanvas } : {}; } };

    try {
        const [ascent, unknown] = withReferenceMeasuring(() => {
            const context = document.createElement('canvas').getContext('2d');

            context.font = '60.0px "Barlow Condensed"';
            const known = context.measureText('an').actualBoundingBoxAscent;

            context.font = '60.0px serif';

            return [known, context.measureText('an').actualBoundingBoxAscent];
        });

        assert.equal(ascent, 31.02);
        assert.equal(unknown, 999 * 60 / 1000, 'a face the table lacks is measured by the browser');
        assert.equal(document.createElement('canvas').getContext('2d').measureText('x').actualBoundingBoxAscent, 999, 'the page\'s own canvases were left answering from the table');
    } finally {
        globalThis.document = {};
    }

    assert.deepEqual(cssFontParts('bold italic 26.4px "Barlow Condensed"'), { bold: true, italic: true, size: 26.4, family: '"Barlow Condensed"' });
    assert.deepEqual(cssFontParts('700 20.0px Alegreya'), { bold: true, italic: false, size: 20, family: 'Alegreya' });
    assert.equal(cssFontParts('12pt serif'), null);
});
