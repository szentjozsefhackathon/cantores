import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

import { DEFAULT_LYRIC_SIZE_PT, opticalLyricSizePt, ptToPx, pxToPt } from '../../resources/js/booklet-geometry.js';
import ChordSheetJS from 'chordsheetjs';

import {
    CHORDPRO_PAGE_WIDTH_PX,
    balanceMarkup,
    chordproIncipitRows,
    chordproMixin,
    chordproPageLayout,
    chordproPageMetrics,
    chordproSlidePages,
    parseChordproSong,
    stripMarkup,
} from '../../resources/js/score-editor-chordpro.js';
import { slidePalette } from '../../resources/js/slide-palette.js';

// Half the font size per character, as in booklet-chordpro's own tests: real
// font metrics would make the arithmetic unreadable without testing anything
// extra.
const measure = (text) => (text ?? '').length * 20;

const options = {
    fontSize: 40,
    fontFamily: "'Merriweather'",
    layoutWidth: 900,
    measure,
};

const pair = (chords, lyrics) => ({ chords, lyrics });
const line = (...items) => ({ items });

test('draws the opening lines, chords over lyrics', () => {
    const rows = chordproIncipitRows(
        [{ lines: [line(pair('C', 'Ave '), pair('G', 'Maria'))] }],
        options,
    );

    assert.equal(rows.length, 1);
    const texts = [...rows[0].svg.matchAll(/<text[^>]*>([^<]*)</g)].map(([, content]) => content);
    assert.deepEqual(texts, ['C', 'Ave ', 'G', 'Maria']);
});

test('stops after the first few lines', () => {
    const rows = chordproIncipitRows(
        [{ lines: [1, 2, 3, 4, 5].map((n) => line(pair('C', `line ${n}`))) }],
        options,
    );

    assert.equal(rows.length, 3);
    assert.ok(rows.every((row) => !row.svg.includes('line 4')));
});

test('leaves out section labels and comments, which a thumbnail has no room for', () => {
    const rows = chordproIncipitRows(
        [{
            label: 'Verse 1',
            lines: [
                line({ name: 'start_of_verse', value: '' }),
                line({ name: 'comment', value: 'slowly' }),
                line(pair('', 'Ave Maria')),
            ],
        }],
        options,
    );

    assert.equal(rows.length, 1);
    assert.ok(rows[0].svg.includes('Ave Maria'));
    assert.ok(!rows[0].svg.includes('Verse 1'));
    assert.ok(!rows[0].svg.includes('slowly'));
});

test('has nothing to draw for a sheet with no sung text', () => {
    assert.deepEqual(chordproIncipitRows([{ lines: [line({ name: 'comment', value: 'x' }), line()] }], options), []);
    assert.deepEqual(chordproIncipitRows([], options), []);
});

test('reads the paragraphs a parsed sheet actually hands over', async () => {
    const song = await parseChordproSong(
        '{title: Ave Maria}\n{subtitle: Trad}\n\n{start_of_verse}\n[C]Ave Ma[G]ria\n{end_of_verse}\n',
        { german: true, transpose: 0 },
    );

    const rows = chordproIncipitRows(song.bodyParagraphs, options);

    assert.equal(rows.length, 1);

    const texts = [...rows[0].svg.matchAll(/<text[^>]*>([^<]*)</g)].map(([, content]) => content);

    // The sung line, syllable by syllable, with its chords — and not the title,
    // which is the page's heading rather than part of the music. The other
    // formats crop their headings off the incipit too.
    assert.deepEqual(texts, ['C', 'Ave ', 'Ma', 'G', 'ria']);
});

test('the editor never writes a title directive into the sheet', () => {
    // A score's title is the row's, not the sheet's: syncing it into the
    // ChordPro overwrote whatever the sheet already said it was called — the
    // worked example among them, which came out titled "Untitled score".
    const editor = readFileSync(new URL('../../resources/js/score-editor.js', import.meta.url), 'utf8');
    const chordpro = readFileSync(new URL('../../resources/js/score-editor-chordpro.js', import.meta.url), 'utf8');

    assert.ok(!/syncChordproTitle/.test(editor + chordpro), 'nothing may sync the title into the content');
    assert.ok(!/chordpro: '\{title/.test(editor), 'the starting sheet must carry no title directive');
});

test('a chord sheet starts at the size the engraved formats do, in the px its container is styled with', () => {
    // 9 pt of Merriweather, not 11: the two read as the same height of letter,
    // which is the whole point of quoting sizes in one reference face. A sheet
    // exported at the factory settings therefore has lyrics the same size as an
    // Aretino or a GABC score exported at its own.
    const size = pxToPt(chordproMixin().chordproFontSize);

    assert.ok(Math.abs(size - 9) < 0.05, `expected about 9 pt, got ${size}`);
    assert.equal(
        chordproMixin().chordproFontSize,
        Number(ptToPx(opticalLyricSizePt(DEFAULT_LYRIC_SIZE_PT, 'Merriweather')).toFixed(4)),
    );
    assert.equal(chordproMixin().chordproFontFamily, "'Merriweather'");
});

test('the preview opens magnified, because the sheet is set at printed size', () => {
    assert.equal(chordproMixin().chordproZoom, 120);
    assert.ok(chordproMixin().chordproFields.includes('chordproZoom'));

    const toolbar = readFileSync(new URL('../../resources/views/livewire/pages/score-editor.blade.php', import.meta.url), 'utf8');

    assert.match(toolbar, /x-model="chordproZoom"/);
});

/*
 * A projector is not a page. The ratio defaults set 62 pt at 16:9 — larger than
 * a paper toolbar's whole range — so the ceiling has to follow the ratio, or the
 * spinner argues with the size the editor itself chose. And a column is a page's
 * answer to a long sheet; a slide's answer is another slide, so the control that
 * sets them has nothing to do on a projector and is not shown there.
 */
test('a chord sheet toolbar lets a projector have the size a projector needs', () => {
    for (const page of ['score-editor', 'score-view', 'public-score-view']) {
        const toolbar = readFileSync(new URL(`../../resources/views/livewire/pages/${page}.blade.php`, import.meta.url), 'utf8');

        assert.match(
            toolbar,
            /x-model="chordproFontSizePt"[^>]*x-bind:max="isFixedRatio\(chordproPageRatio\) \? 144 : 24"/,
            `${page} should raise the size ceiling on a slide`,
        );
        assert.match(
            toolbar,
            /x-show="!isFixedRatio\(chordproPageRatio\)"[\s\S]{0,400}x-model="chordproColumns"/,
            `${page} should hide the column control on a slide`,
        );
    }
});

test('every chord sheet toolbar sets the size in points over a setting kept in px', () => {
    const editor = readFileSync(new URL('../../resources/js/score-editor.js', import.meta.url), 'utf8');

    // Every toolbar that sets a chord sheet's size speaks points; everything
    // downstream — the preview, the score views, the HTML export — is still
    // handed px, so the pair of accessors is the only place the two units meet.
    for (const page of ['score-editor', 'score-view', 'public-score-view']) {
        const toolbar = readFileSync(new URL(`../../resources/views/livewire/pages/${page}.blade.php`, import.meta.url), 'utf8');

        assert.match(toolbar, /x-model="chordproFontSizePt"/, `${page} should set the size in points`);
        assert.ok(!/x-model="chordproFontSize"/.test(toolbar), `${page} should not set the size in px`);
    }

    assert.match(editor, /get chordproFontSizePt\(\)/);
    assert.match(editor, /set chordproFontSizePt\(value\)/);

    // And the factory default reads back as the round number it was chosen as,
    // to the two decimals the spinner shows.
    assert.equal(Math.round(pxToPt(chordproMixin().chordproFontSize) * 100) / 100, 8.96);
});

const parse = (content) => parseChordproSong(content, { german: false, transpose: 0 });

/** The lyric of each column of the formatted HTML, markup and all. */
const lyricsOf = (html) => [...html.matchAll(/<div class="lyrics">(.*?)<\/div>/g)].map(([, lyric]) => lyric);

test('markup that runs across a chord is closed and reopened at the column', async () => {
    // Each fragment becomes a `<div>` of its own, and a tag cannot straddle two
    // of them: left as written, only 'Ma' came out italic and the rest of the
    // word did not.
    const html = new ChordSheetJS.HtmlDivFormatter().format(
        balanceMarkup(await parse('[C]Ave <i>Ma[G]ri</i>a')),
    );

    assert.deepEqual(lyricsOf(html), ['Ave ', '<i>Ma</i>', '<i>ri</i>a']);
});

test('nested markup is reopened in full', async () => {
    const html = new ChordSheetJS.HtmlDivFormatter().format(
        balanceMarkup(await parse('<b><u>Ky[C]rie</u></b>')),
    );

    assert.deepEqual(lyricsOf(html), ['<b><u>Ky</u></b>', '<b><u>rie</u></b>']);
});

test('markup closed on its own line does not run on into the next', async () => {
    const html = new ChordSheetJS.HtmlDivFormatter().format(
        balanceMarkup(await parse('<i>one\ntwo')),
    );

    assert.deepEqual(lyricsOf(html), ['<i>one</i>', 'two']);
});

test('a lyric with no markup is left exactly as it was', async () => {
    const html = new ChordSheetJS.HtmlDivFormatter().format(
        balanceMarkup(await parse('[C]Ave Ma[G]ria')),
    );

    // chordsheetjs cuts a fragment at a space as well as at a chord; either
    // way, nothing gained a tag it did not have.
    assert.deepEqual(lyricsOf(html), ['Ave ', 'Ma', 'ria']);
});

test('a script survives the sanitiser and becomes its own element', async () => {
    const html = new ChordSheetJS.HtmlDivFormatter().format(
        balanceMarkup(await parse('[C]H<sub>2</sub>O, 1<sup>st</sup>')),
    );

    assert.equal(lyricsOf(html).join(''), 'H<sub>2</sub>O, 1<sup>st</sup>');
});

test('a script written across a chord is reopened like any other markup', async () => {
    const html = new ChordSheetJS.HtmlDivFormatter().format(
        balanceMarkup(await parse('x<sup>a[C]b</sup>')),
    );

    assert.deepEqual(lyricsOf(html), ['x<sup>a</sup>', '<sup>b</sup>']);
});

test('everything but the drawable tags stops being markup', async () => {
    // The sanitizing happens on the way into the parser, so a `<span>` reaches
    // the browser as text and only ChordPro's own three tags stay live.
    const html = new ChordSheetJS.HtmlDivFormatter().format(
        balanceMarkup(await parse('[C]<span style="x">a</span> <i>b</i>')),
    );

    assert.deepEqual(lyricsOf(html).join(''), '&lt;span style="x"&gt;a&lt;/span&gt; <i>b</i>');
});

test('plain text keeps the words and drops the markup', async () => {
    const text = new ChordSheetJS.TextFormatter().format(stripMarkup(await parse('[C]Ave <i>Ma[G]ri</i>a')));

    assert.match(text, /Ave Maria/);
    assert.doesNotMatch(text, /<i>|<\/i>/);
});

const row = (height, extra = {}) => ({ height, svg: '<svg/>', ...extra });

test('a single column is the full text width of the page', () => {
    const metrics = chordproPageMetrics({ columns: 1, fontSize: 12 });

    assert.equal(metrics.count, 1);
    assert.equal(metrics.columnWidth, CHORDPRO_PAGE_WIDTH_PX);
    // 170 mm of A4, the width every other format here is engraved to.
    assert.equal(Math.round(CHORDPRO_PAGE_WIDTH_PX), 643);
});

test('two columns share the width, less the gutter between them', () => {
    const metrics = chordproPageMetrics({ columns: 2, fontSize: 10 });

    assert.equal(metrics.gap, 20);
    assert.equal(metrics.columnWidth * 2 + metrics.gap, CHORDPRO_PAGE_WIDTH_PX);
});

test('a column count that means nothing falls back to one', () => {
    assert.equal(chordproPageMetrics({ columns: '', fontSize: 10 }).count, 1);
    assert.equal(chordproPageMetrics({ columns: 0, fontSize: 10 }).count, 1);
    assert.equal(chordproPageMetrics({ columns: 99, fontSize: 10 }).count, 4);
});

test('rows run down the page, and the page is as tall as they are', () => {
    const metrics = chordproPageMetrics({ columns: 1, fontSize: 10, pageWidth: 100 });
    const { placements, width, height } = chordproPageLayout([row(20), row(30)], metrics);

    assert.equal(width, 100);
    assert.equal(height, 50);
    assert.deepEqual(placements.map(({ x, y }) => ({ x, y })), [{ x: 0, y: 0 }, { x: 0, y: 20 }]);
});

test('a second column starts a gutter beyond the first', () => {
    const metrics = chordproPageMetrics({ columns: 2, fontSize: 10, pageWidth: 220 });
    const { placements, height } = chordproPageLayout([row(20), row(20)], metrics);

    // 100 wide each with a 20 gutter, so the right column begins at 120.
    assert.deepEqual(placements.map(({ x, y }) => ({ x, y })), [{ x: 0, y: 0 }, { x: 120, y: 0 }]);
    // The page is as tall as its tallest column, not as tall as the song.
    assert.equal(height, 20);
});

test('every row keeps its own drawing', () => {
    const rows = [row(10, { svg: '<svg id="a"/>' }), row(10, { svg: '<svg id="b"/>' })];
    const { placements } = chordproPageLayout(rows, chordproPageMetrics({ columns: 1, fontSize: 10 }));

    assert.deepEqual(placements.map(({ row: placed }) => placed.svg), ['<svg id="a"/>', '<svg id="b"/>']);
});

test('every chord sheet toolbar offers the picture exports, not only the text ones', () => {
    // A chord sheet is engraved to SVG like the other three formats now, so the
    // editor and both score views hand it to the same export routes.
    const actions = ['copyChordproImage()', 'exportChordproPng()', 'exportChordproSvg()', 'exportChordproPdf()'];

    for (const page of ['score-editor', 'score-view', 'public-score-view']) {
        const view = readFileSync(new URL(`../../resources/views/livewire/pages/${page}.blade.php`, import.meta.url), 'utf8');

        actions.forEach((action) => {
            assert.ok(view.includes(action), `${page} should offer ${action}`);
        });
    }
});

test('the mixin carries every export action the toolbars call', () => {
    const mixin = chordproMixin();

    ['chordproPageElement', 'copyChordproImage', 'exportChordproPng', 'exportChordproSvg', 'exportChordproPdf']
        .forEach((action) => assert.equal(typeof mixin[action], 'function', `${action} is missing`));
});

for (const german of [false, true]) {
    for (const transpose of [0, 2]) {
        test(`clipboard displays major seventh triangles (German: ${german}, transpose: ${transpose})`, async () => {
            const originalNavigator = Object.getOwnPropertyDescriptor(globalThis, 'navigator');
            let copied;
            Object.defineProperty(globalThis, 'navigator', {
                configurable: true,
                value: { clipboard: { writeText: async (text) => { copied = text; } } },
            });

            try {
                const editor = {
                    ...chordproMixin(),
                    localContent: '[Fmaj7]Alleluia',
                    chordproGermanNotation: german,
                    chordproTranspose: transpose,
                    showCopyFeedback() {},
                };

                await editor.copyChordproPlainText();

                assert.match(copied, new RegExp(transpose === 0 ? 'F△' : 'G△'));
                assert.doesNotMatch(copied, /[FG]ma7/);
            } finally {
                if (originalNavigator) {
                    Object.defineProperty(globalThis, 'navigator', originalNavigator);
                } else {
                    delete globalThis.navigator;
                }
            }
        });
    }
}

/*
 * A chord sheet on a slide flows; it does not clip.
 *
 * The rows below are all the same height by construction — one chord line and
 * one lyric line, 1.25 and 1.35 of the 40 px size — so a screen's capacity can
 * be written as a number of rows and the tier order read straight off the
 * result. What is being tested is which cuts get spent, and in what order.
 */

/** One chord-and-lyric row, and the air a verse boundary asks for. */
const ROW = 40 * (1.25 + 1.35);
const GAP = 40 * 0.9;

const slidePages = (sheet, height, width = 1920) => chordproSlidePages(sheet, {
    german: true,
    transpose: 0,
    fontFamily: "'Merriweather'",
    fontSize: 40,
    canvas: { width, height },
    measure,
});

const rowCounts = (pages) => pages.map((page) => page.rows.length);

test('a sheet that fits is one slide, and its suggestion goes unused', async () => {
    const pages = await slidePages('[C]Egy\n%pagebreak?\n[G]Kettő\n', 1080);

    assert.deepEqual(rowCounts(pages), [2]);
    // Laid out apart and put back together, the two pieces keep the air a verse
    // boundary would have had between them.
    assert.equal(pages[0].rows[1].spaceBefore, GAP);
    assert.equal(pages[0].height, ROW * 2 + GAP);
});

test('the same sheet on a screen too short for it is cut at the suggestion', async () => {
    const pages = await slidePages('[C]Egy\n%pagebreak?\n[G]Kettő\n', ROW + 10);

    assert.deepEqual(rowCounts(pages), [1, 1]);
});

test('only as many suggestions are spent as the screen actually needs', async () => {
    const sheet = '[C]Egy\n%pagebreak?\n[G]Kettő\n%pagebreak?\n[Am]Három\n';

    // Room for two of the three pieces: two slides, not one per suggestion.
    assert.deepEqual(rowCounts(await slidePages(sheet, ROW * 2 + GAP + 10)), [2, 1]);
    assert.deepEqual(rowCounts(await slidePages(sheet, ROW + 10)), [1, 1, 1]);
});

/*
 * The tier below the author's own suggestions. A sheet that says nothing about
 * where it should break is broken at its verses, and a verse that fits the
 * screen is never split across two of them — which is what keepWithNext is for,
 * and what used to be answered by cutting the last verse off the bottom edge.
 */
test('a sheet with no markers is cut at a verse boundary rather than truncated', async () => {
    const sheet = '[C]Egy\n[G]Két\n\n[Am]Há\n[F]Négy\n';
    const pages = await slidePages(sheet, ROW * 3);

    assert.deepEqual(rowCounts(pages), [2, 2]);
});

/*
 * The last resort, and the only case `overflows` is still for: one line set so
 * large that no cut anywhere would make it fit. It comes back whole and too
 * tall, because a screen ending mid-word is worse than a screen that overruns.
 */
test('a single row taller than the screen comes back over-tall rather than cut', async () => {
    const pages = await slidePages('[C]Egy\n', ROW / 2);

    assert.deepEqual(rowCounts(pages), [1]);
    assert.ok(pages[0].height > ROW / 2);
});

/*
 * A verse is a preference, not a promise. Two verses of two lines on a screen
 * with room for three rows: keeping both verses whole costs two slides and half
 * an empty screen, so the second verse is cut at its own newline instead.
 */
test('a verse is cut at a line boundary when keeping it whole would cost a slide', async () => {
    const sheet = '[C]Egy\n[G]Két\n\n[Am]Há\n[F]Négy\n\n[C]Öt\n[G]Hat\n';
    const pages = await slidePages(sheet, ROW * 3 + GAP);

    assert.deepEqual(rowCounts(pages), [3, 3]);
    assert.match(pages[0].rows[2].svg, /Há</);
});

/*
 * The boundary is the newline the author wrote, not the wrap the screen forced:
 * a line too wide to fit keeps its pieces together, so a slide never opens on
 * the tail of a sentence whose head is on the slide before it.
 */
test('a wrapped line is not cut in the middle of itself to fill a slide', async () => {
    const sheet = '[C]Egy kettő három négy\n[G]Öt\n';
    const pages = await slidePages(sheet, ROW * 2 + 10, 200);

    assert.ok(pages.length > 1, 'the sheet was cut');
    assert.match(pages[pages.length - 1].rows[0].svg, /<text[^>]*>G</, 'the last slide opens on a line of its own');
    pages.forEach((page) => assert.ok(page.height <= ROW * 2 + 10, 'and nothing overran'));
});

/*
 * Below even that: a single written line taller than the whole screen. Cutting
 * it mid-sentence is bad; hiding the end of it is worse, so it is cut.
 */
test('a line taller than the screen is cut inside itself rather than hidden', async () => {
    const pages = await slidePages('[C]Egy kettő három négy öt hat\n', ROW * 2, 200);

    assert.ok(pages.length > 1);
    pages.forEach((page) => assert.ok(page.height <= ROW * 2));
});

test('a sheet with nothing sung in it comes to no slides at all', async () => {
    assert.deepEqual(await slidePages('', 1080), []);
    assert.deepEqual(await slidePages('{title: Teszt}\n', 1080), []);
});

/*
 * A long line wraps to the next row before anything thinks about pages, and the
 * chord stays over the syllable it was written on — the wrapping is
 * booklet-chordpro's, but this is the path a slide takes through it, at the
 * slide's own width rather than a page's.
 */
test('a line too wide for the screen wraps within the slide', async () => {
    const narrow = await slidePages('[C]Egy kettő három négy öt hat\n', 1080, 200);

    assert.ok(narrow[0].rows.length > 1, 'the line wrapped');
    assert.match(narrow[0].rows[0].svg, /<text[^>]*>C</, 'the chord stays on the first piece');
});

/*
 * The ink a chord sheet is set in on a screen. A sheet that flows and breaks
 * where it must is words, not an engraving, so it is set the way the screens of
 * words beside it in the same deck are — white on black, unless a deck whose
 * room is lit says otherwise.
 */
test('a slide is set white on black unless a palette says otherwise', async () => {
    const pages = await slidePages('{start_of_verse: Refrain}\n[C]Ave [G]Maria\n', 1080);
    const drawn = pages[0].rows.map((row) => row.svg).join('');

    assert.match(drawn, /fill="#ffffff"/, 'lyrics are white');
    assert.match(drawn, /fill="#7dd3fc"/, 'chords answer black with a sky');
    assert.doesNotMatch(drawn, /fill="#000000"/, 'nothing is left in printed black');
});

test('a lit room gets the same sheet in ink', async () => {
    const pages = await chordproSlidePages('[C]Ave [G]Maria\n', {
        german: true,
        transpose: 0,
        fontFamily: "'Merriweather'",
        fontSize: 40,
        canvas: { width: 1920, height: 1080 },
        measure,
        palette: slidePalette({ textTheme: 'light' }),
    });

    const drawn = pages[0].rows.map((row) => row.svg).join('');

    assert.match(drawn, /fill="#000000"/);
    assert.match(drawn, /fill="#1d4ed8"/);
});
