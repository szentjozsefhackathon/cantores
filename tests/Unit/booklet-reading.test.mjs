import assert from 'node:assert/strict';
import test from 'node:test';

import { packPages } from '../../resources/js/booklet-flow.js';
import { mmToPx, pageGeometry } from '../../resources/js/booklet-geometry.js';
import {
    clampZoom,
    READER_MARGIN_MM,
    readerGeometry,
    readReaderSettings,
    styleForFont,
    writeReaderSettings,
    ZOOM_MAX,
    ZOOM_MIN,
} from '../../resources/js/booklet-reading.js';
import { travellingOverride } from '../../resources/js/booklet-settings.js';

/** The cantor's A5 booklet, as Booklet::geometry() sends it. */
const booklet = {
    pageWidthMm: 148,
    pageHeightMm: 210,
    marginMm: 12,
    contentWidthMm: 124,
    contentHeightMm: 186,
    lyricSizePt: 11,
    staffHeightMm: 7,
    textFont: 'EB Garamond',
    headingScale: 0.9,
    abcStaffSep: 25,
    abcLyricFirstSkip: 1.4,
    abcLyricSkip: 0.8,
};

/** BookletStyles::typographies(), as the server sends it to the reader. */
const styles = {
    hymnal: { textFont: 'Alegreya', abcLyricFirstSkip: 1.4, abcLyricSkip: 0.9 },
    modern: { textFont: 'Merriweather', abcLyricFirstSkip: 1.5, abcLyricSkip: 1 },
    graduale: { textFont: 'EB Garamond', abcLyricFirstSkip: 1.4, abcLyricSkip: 0.8 },
};

/** A phone in portrait: 390 CSS px of column. */
const PHONE_PX = 390;

test('the screen decides the width, and the reader keeps the margin small', () => {
    const geometry = readerGeometry(booklet, PHONE_PX);

    assert.equal(geometry.marginMm, READER_MARGIN_MM);
    // The page is exactly the column, so one user unit comes out one CSS pixel.
    assert.ok(Math.abs(mmToPx(geometry.pageWidthMm) - PHONE_PX) < 0.001);
    assert.ok(Math.abs(geometry.contentWidthMm - (geometry.pageWidthMm - 2 * READER_MARGIN_MM)) < 0.001);
});

test('at one, the phone shows the booklet\'s proportions rather than its point sizes', () => {
    const geometry = readerGeometry(booklet, PHONE_PX);
    const proportion = geometry.contentWidthMm / booklet.contentWidthMm;

    assert.ok(Math.abs(geometry.lyricSizePt - booklet.lyricSizePt * proportion) < 0.001);
    assert.ok(Math.abs(geometry.staffHeightMm - booklet.staffHeightMm * proportion) < 0.001);

    // Narrower than A5, so everything comes out smaller — but in step, which is
    // what makes it the same booklet.
    assert.ok(proportion < 1);
    assert.ok(Math.abs(
        geometry.lyricSizePt / geometry.staffHeightMm - booklet.lyricSizePt / booklet.staffHeightMm,
    ) < 0.001);
});

test('the reader\'s size moves the type and the staves together', () => {
    const plain = readerGeometry(booklet, PHONE_PX);
    const bigger = readerGeometry(booklet, PHONE_PX, { zoom: 2 });

    assert.ok(Math.abs(bigger.lyricSizePt - plain.lyricSizePt * 2) < 0.001);
    assert.ok(Math.abs(bigger.staffHeightMm - plain.staffHeightMm * 2) < 0.001);

    // The width is the screen's, not the reader's: making the type bigger makes
    // the lines break sooner, it does not make the page wider.
    assert.equal(bigger.pageWidthMm, plain.pageWidthMm);
});

// A face and the gaps that face needs are one decision, so the reader is
// offered the style rather than the face. Anything else puts Merriweather's
// lyrics over Alegreya's gaps on the phone — the fault styles exist to prevent.
test('the cantor\'s typography is inherited, and only the style is the reader\'s', () => {
    const inherited = readerGeometry(booklet, PHONE_PX, {}, styles);

    assert.equal(inherited.textFont, 'EB Garamond');
    assert.equal(inherited.abcLyricFirstSkip, 1.4);
    assert.equal(inherited.abcLyricSkip, 0.8);
    assert.equal(inherited.headingScale, booklet.headingScale);
    assert.equal(inherited.abcStaffSep, booklet.abcStaffSep);
});

test('a reader\'s style swaps the face and both spacings together', () => {
    const modern = readerGeometry(booklet, PHONE_PX, { style: 'modern' }, styles);

    assert.equal(modern.textFont, 'Merriweather');
    assert.equal(modern.abcLyricFirstSkip, 1.5);
    assert.equal(modern.abcLyricSkip, 1);

    // A style nobody has heard of leaves the booklet's own typography standing.
    const unknown = readerGeometry(booklet, PHONE_PX, { style: 'gothic' }, styles);

    assert.equal(unknown.textFont, 'EB Garamond');
    assert.equal(unknown.abcLyricSkip, 0.8);
});

test('a size out of range, or no size at all, still yields a booklet', () => {
    assert.equal(clampZoom(99), ZOOM_MAX);
    assert.equal(clampZoom(0), ZOOM_MIN);
    assert.equal(clampZoom('nonsense'), 1);

    // A column that has not been laid out yet must not produce a zero-wide page.
    assert.ok(readerGeometry(booklet, 0).contentWidthMm > 0);
});

test('the cantor\'s musical decisions travel to the phone', () => {
    const geometry = pageGeometry(readerGeometry(booklet, PHONE_PX));

    const travelling = travellingOverride('abc', {
        abcTranspose: -2,
        abcNoClef: true,
        abcLyricFont: "'Merriweather'",
    }, geometry);

    assert.deepEqual(travelling, {
        abcTranspose: -2,
        abcNoClef: true,
        abcLyricFont: "'Merriweather'",
    });
});

test('the cantor\'s page-fitting nudges do not', () => {
    const geometry = pageGeometry(readerGeometry(booklet, PHONE_PX));

    // Widened to stop a line breaking on A5, and shrunk to stop a page breaking:
    // both are answers to a sheet of paper the phone is not.
    assert.deepEqual(
        travellingOverride('abc', { abcPageWidth: 900, abcPageScale: 0.5, abcTranspose: 3 }, geometry),
        { abcTranspose: 3 },
    );

    assert.deepEqual(
        travellingOverride('gabc', { gabcLayoutWidth: 900, staffSize: 12, dropCaps: false }, geometry),
        { dropCaps: false },
    );

    assert.deepEqual(
        travellingOverride('aretino', { aretinoStaffWidth: 200, aretinoLyricSize: 8, aretinoHideRepeatClef: true }, geometry),
        { aretinoHideRepeatClef: true },
    );

    assert.deepEqual(
        travellingOverride('chordpro', { chordproFontSize: 8, chordproColumns: 2, chordproTranspose: 1 }, geometry),
        { chordproTranspose: 1 },
    );

    // A scan taken down because it shouted on A5 fills a phone perfectly well.
    assert.deepEqual(travellingOverride('file', { fileZoom: 0.4 }, geometry), {});
});

test('travelling an entry that was never adjusted asks nothing of anyone', () => {
    const geometry = pageGeometry(readerGeometry(booklet, PHONE_PX));

    assert.deepEqual(travellingOverride('abc', null, geometry), {});
    assert.deepEqual(travellingOverride('abc', undefined, geometry), {});
});

test('a screen never runs out, so nothing is packed onto a second surface', () => {
    const blocks = [
        { height: 400, keepWithNext: true },
        { height: 9000 },
        { height: 4000, spaceBefore: 20 },
    ];

    const packed = packPages(blocks, Number.POSITIVE_INFINITY);

    assert.equal(packed.length, 1);
    assert.equal(packed[0].items.length, 3);
    // The first block opens the surface, so its leading is dropped; the last
    // keeps its own.
    assert.equal(packed[0].height, 400 + 9000 + 20 + 4000);
});

test('what a reader sets is remembered per link, and a broken store is simply forgotten', () => {
    const store = new Map();
    const storage = {
        getItem: (key) => (store.has(key) ? store.get(key) : null),
        setItem: (key, value) => store.set(key, value),
    };

    writeReaderSettings(storage, 'abc123', { zoom: 1.5, style: 'modern', overrides: { 7: { abcTranspose: 2 } } });

    assert.deepEqual(readReaderSettings(storage, 'abc123', styles), {
        zoom: 1.5,
        style: 'modern',
        overrides: { 7: { abcTranspose: 2 } },
    });

    // Another link is another booklet, and knows nothing of this one.
    assert.deepEqual(readReaderSettings(storage, 'other', styles), { zoom: 1, style: null, overrides: {} });

    store.set('booklet-reader:abc123', 'not json at all');
    assert.deepEqual(readReaderSettings(storage, 'abc123', styles), { zoom: 1, style: null, overrides: {} });

    // A phone with storage switched off still reads the booklet.
    const dead = { getItem: () => { throw new Error('denied'); }, setItem: () => { throw new Error('denied'); } };
    assert.deepEqual(readReaderSettings(dead, 'abc123', styles), { zoom: 1, style: null, overrides: {} });
    assert.doesNotThrow(() => writeReaderSettings(dead, 'abc123', { zoom: 1, style: null, overrides: {} }));
});

// The control was a face and is now a style, and a phone that remembers a face
// must not forget what its reader set because of that.
test('a face remembered from before the styles is read back as its style', () => {
    const store = new Map();
    const storage = {
        getItem: (key) => (store.has(key) ? store.get(key) : null),
        setItem: (key, value) => store.set(key, value),
    };

    store.set('booklet-reader:abc123', JSON.stringify({ zoom: 1.2, textFont: 'EB Garamond', overrides: {} }));

    assert.deepEqual(readReaderSettings(storage, 'abc123', styles), {
        zoom: 1.2,
        style: 'graduale',
        overrides: {},
    });

    // A projector face no style claims is forgotten rather than half-honoured:
    // that reader is put back on the booklet's own style.
    store.set('booklet-reader:abc123', JSON.stringify({ zoom: 1, textFont: 'Inter', overrides: {} }));

    assert.equal(readReaderSettings(storage, 'abc123', styles).style, null);
    assert.equal(styleForFont(styles, "'Merriweather'"), 'modern');
});
