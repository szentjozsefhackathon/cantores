import assert from 'node:assert/strict';
import test from 'node:test';

import { opticalLyricSizePt, ptToPx } from '../../resources/js/booklet-geometry.js';
import { CHORDPRO_RATIO_DEFAULTS, chordproMixin } from '../../resources/js/score-editor-chordpro.js';
import { applyConditionalBlocks, splitPages } from '../../resources/js/score-editor-pages.js';
import { formatDefaults } from '../../resources/js/score-editor-settings.js';
import { fitIntoBox, isSlideRatio, ratioPageSources, renderRatioPage, slideCanvas, slideRatios } from '../../resources/js/projection-render.js';

/*
 * Engraving a slide needs a browser — abc2svg and exsurge are globals a page
 * carries, and every engine hands its work back as markup somebody has to parse.
 * What is testable here is everything upstream of that: the shape of the canvas
 * each format engraves onto, the arithmetic that fits a thing into a box, and
 * the cutting of a source into the pages one ratio asks for.
 *
 * That is also where the bugs would be silent. A canvas whose aspect is wrong by
 * a percent still renders, still looks like a slide, and puts one score on the
 * screen at a different size from the one before it.
 */

const FORMATS = ['abc', 'gabc', 'aretino', 'chordpro', 'file'];

const ASPECT = { '16/9': 16 / 9, '4/3': 4 / 3, '1/1': 1 };

/*
 * The one invariant the three canvas tables exist to keep. They disagree about
 * size on purpose — ABC holds a 1080 height, GABC a 1920 width, Aretino works at
 * half ABC's scale — because each author tuned their sizes against the canvas
 * their own editor drew them on. They may not disagree about shape.
 */
test('every format engraves every ratio onto a canvas of exactly that ratio', () => {
    for (const ratio of slideRatios()) {
        for (const format of FORMATS) {
            const canvas = slideCanvas(format, ratio);

            assert.ok(canvas, `${format} has no canvas for ${ratio}`);
            assert.equal(
                canvas.width / canvas.height,
                ASPECT[ratio],
                `${format} at ${ratio} is ${canvas.width}x${canvas.height}`,
            );
        }
    }
});

// Paper and responsive are the editor's business. A projection asking for one
// must be told no rather than handed a box of some arbitrary shape.
test('only the three projector ratios are slides', () => {
    assert.deepEqual(slideRatios(), ['16/9', '4/3', '1/1']);

    for (const ratio of ['paper', 'responsive', 'auto', '', null, undefined, '3/2']) {
        assert.equal(isSlideRatio(ratio), false, String(ratio));
        assert.equal(slideCanvas('abc', ratio), null, String(ratio));
    }
});

// A format with no projector canvas of its own is laid into the slide's own box
// rather than falling through to whichever table happens to be first.
test('a format without a canvas of its own is given the slide box', () => {
    for (const format of ['chordpro', 'file', 'something-new']) {
        assert.deepEqual(slideCanvas(format, '16/9'), { width: 1920, height: 1080 });
    }

    assert.deepEqual(slideCanvas('aretino', '16/9'), { width: 960, height: 540 });
    assert.deepEqual(slideCanvas('gabc', '4/3'), { width: 1920, height: 1440 });
    assert.deepEqual(slideCanvas('abc', '4/3'), { width: 1440, height: 1080 });
});

/*
 * Shrink-to-fit on both axes, which is the one piece of arithmetic a slide needs
 * that a booklet never did: a page that runs long is answered by breaking it,
 * and a slide cannot break.
 */
test('fitting into a box shrinks on whichever axis is tighter, and never enlarges', () => {
    const box = { width: 1920, height: 1080 };

    assert.equal(fitIntoBox({ width: 3840, height: 1080 }, box), 0.5, 'too wide');
    assert.equal(fitIntoBox({ width: 1920, height: 2160 }, box), 0.5, 'too tall');
    assert.equal(fitIntoBox({ width: 960, height: 540 }, box), 1, 'smaller is left alone');
    assert.equal(fitIntoBox({ width: 1920, height: 1080 }, box), 1, 'exact');

    // A portrait page on a landscape screen fits to its height and pillarboxes.
    assert.equal(fitIntoBox({ width: 2160, height: 2160 }, box), 0.5);

    // Nothing measurable: leave it alone rather than divide by zero.
    for (const nothing of [{ width: 0, height: 0 }, {}, null, undefined]) {
        assert.equal(fitIntoBox(nothing, box), 1);
    }
});

const ABC = 'X:1\nT:Teszt\nM:4/4\nL:1/4\nK:F\nF G A B|\nw: el-ső\n%pagebreak169\nc d c B|\nw: má-so-dik\n';

test('a numbered page break cuts only the ratio it names', () => {
    assert.equal(splitPages(ABC, 'abc', '16/9').length, 2);
    assert.equal(splitPages(ABC, 'abc', '4/3').length, 1);
    assert.equal(splitPages(ABC, 'abc', '1/1').length, 1);
});

// Paper has no pages at all: every break is stripped and the score comes back
// whole, which is what 'auto' always meant.
test('paper and responsive come back as one page with the breaks taken out', () => {
    for (const ratio of ['paper', 'responsive']) {
        const pages = splitPages(ABC, 'abc', ratio);

        assert.equal(pages.length, 1);
        assert.ok(!pages[0].includes('%pagebreak'), ratio);
    }
});

/*
 * A break belonging to another ratio is dropped rather than left behind. It
 * reads as a comment in all three engines, so leaving it would be harmless in
 * ABC and visible in a format whose comment character is not '%'.
 */
test('a break for another ratio is removed rather than left in the source', () => {
    const [page] = splitPages(ABC, 'abc', '4/3');

    assert.ok(!page.includes('%pagebreak169'));
});

// The header is what makes a page a score rather than a fragment: every engine
// needs the key back, so each page carries it.
test('every page carries the header again', () => {
    const pages = splitPages(ABC, 'abc', '16/9');

    for (const page of pages) {
        assert.match(page, /^X:1\nT:Teszt\nM:4\/4\nL:1\/4\nK:F\n/);
    }

    assert.match(pages[0], /el-ső/);
    assert.match(pages[1], /má-so-dik/);
    assert.ok(!pages[1].includes('el-ső'), 'the second page is not the whole score again');
});

// GABC and Aretino close their header with a bare %% instead.
test('the chant formats take their header from the %% line', () => {
    const chant = 'name: Teszt;\n%%\n(c4) Ky(f)ri(g)e(h)\n%pagebreak\ne(g)le(f)i(e)son(d)\n';
    const pages = splitPages(chant, 'gabc', '16/9');

    assert.equal(pages.length, 2);
    assert.match(pages[0], /^name: Teszt;\n%%\n/);
    assert.match(pages[1], /^name: Teszt;\n%%\n/);
    assert.match(pages[1], /son/);
});

/*
 * ChordPro has no header of its own — its directives travel with the words they
 * belong to — so a page is given exactly what stood between its breaks.
 */
test('a chord sheet keeps no header and still splits', () => {
    const sheet = '{title: Teszt}\n[C]Első [G]sor\n%pagebreak\n[Am]Má-so-dik [F]sor\n';
    const pages = splitPages(sheet, 'chordpro', '16/9');

    assert.equal(pages.length, 2);
    assert.match(pages[0], /\{title: Teszt\}/);
    assert.ok(!pages[1].includes('{title: Teszt}'));
});

/*
 * One page, one slide, is true of an engraving and not of a chord sheet: words
 * flow, so a page of them that will not fit comes to several. The dispatcher
 * keeps the two apart rather than letting a caller take the first slide of a
 * ChordPro page for the whole of it.
 */
test('an engraved page is one slide and a chord sheet page is not asked to be', async () => {
    await assert.rejects(
        () => renderRatioPage('chordpro', '[C]Egy\n', {}, '16/9'),
        /cannot be engraved to a slide/,
    );
});

/*
 * A suggestion cuts nothing here, because whether it is taken is not known until
 * the page has been laid out. ChordPro is the only format laid out in this
 * repository, so it is the only one given the chance to decide: its pages keep
 * the marker, spelled one way whatever suffix it was written with, and the three
 * engraved formats have it stripped as before.
 */
test('a suggested break is left in a chord sheet page and taken out of everything else', () => {
    const sheet = '[C]Első sor\n%pagebreak?\n[Am]Má-so-dik sor\n';
    const [page] = splitPages(sheet, 'chordpro', '16/9');

    assert.equal(splitPages(sheet, 'chordpro', '16/9').length, 1, 'a suggestion is not a page break');
    assert.match(page, /^\[C\]Első sor\n%pagebreak\?\n\[Am\]/);

    assert.ok(!splitPages(sheet, 'abc', '16/9')[0].includes('pagebreak'), 'ABC strips one');
    assert.ok(!splitPages(sheet, 'gabc', '16/9')[0].includes('pagebreak'), 'GABC strips one');
    assert.ok(!splitPages(sheet, 'chordpro', 'paper')[0].includes('pagebreak'), 'paper has no pages');
});

test('a suggestion numbered for one shape is spelled plainly there and dropped elsewhere', () => {
    const sheet = '[C]Első sor\n%pagebreak169?\n[Am]Má-so-dik sor\n';

    assert.match(splitPages(sheet, 'chordpro', '16/9')[0], /\n%pagebreak\?\n/);
    assert.ok(!splitPages(sheet, 'chordpro', '4/3')[0].includes('pagebreak'));
    assert.ok(!splitPages(sheet, 'chordpro', '1/1')[0].includes('pagebreak'));
});

test('a chord sheet still cuts hard, and carries its suggestions into the right page', () => {
    const sheet = '[C]Egy\n%pagebreak?\n[G]Kettő\n%pagebreak\n[Am]Három\n%pagebreak?\n[F]Négy\n';
    const pages = splitPages(sheet, 'chordpro', '16/9');

    assert.equal(pages.length, 2);
    assert.match(pages[0], /Egy\n%pagebreak\?\n\[G\]Kettő/);
    assert.match(pages[1], /Három\n%pagebreak\?\n\[F\]Négy/);
});

/*
 * ABC is rewritten before it is cut, never after: an X: line inserted afterwards
 * would land on the first page and leave every later one unparseable.
 */
test('ABC gets its X line before the split, so every page has one', () => {
    const headerless = 'T:Teszt\nK:F\nF G|\n%pagebreak169\nA B|\n';
    const pages = ratioPageSources('abc', headerless, {}, '16/9');

    assert.equal(pages.length, 2);
    for (const page of pages) {
        assert.match(page, /^X:1\n/);
    }
});

// See plans/score-sections.md: a row's chosen sections are strung together
// with a %pagebreak between each before anything else happens to the source,
// so a row choosing the same section twice gets it twice, as its own page.
test('a row choosing sections gives one page per chosen section, repeats included', () => {
    const sectioned = 'X:1\nK:G\n%section 1\nA B|\n%section 2\nc d|\n';

    assert.equal(ratioPageSources('abc', sectioned, {}, '16/9', [2, 1, 2]).length, 3);
    assert.equal(ratioPageSources('abc', sectioned, {}, '16/9', null).length, 1);
});

test('suppressing the clef is a source edit, and the chords are read as Hungarian', () => {
    const [withClef] = ratioPageSources('abc', 'X:1\nK:F\n"H"F G|\n', {}, '16/9');
    const [noClef] = ratioPageSources('abc', 'X:1\nK:F\n"H"F G|\n', { abcNoClef: true }, '16/9');

    assert.ok(!withClef.includes('clef=none'));
    assert.match(noClef, /\[K:clef=none\]/);

    // Hungarian H is B natural; abc2svg would otherwise leave it untransposed.
    assert.match(withClef, /"B"/);
});

// Only ABC is rewritten. A chant source handed back changed would no longer
// match the offsets the Aretino editor maps its preview clicks through.
test('the other formats are handed back exactly as they were written', () => {
    const chant = 'name: Teszt;\n%%\n(c4) Ky(f)ri(g)e(h)\n';

    assert.equal(ratioPageSources('gabc', chant, {}, '16/9').join(''), chant);
    assert.equal(ratioPageSources('aretino', chant, {}, '16/9').join(''), chant);
});

/*
 * ChordPro is the format that had no projector layout at all: the editor drew it
 * as HTML in a box and reported its ratio as 'auto' whatever was chosen, so a
 * guitar sheet in a service could not be put on the screen beside the hymn
 * before it. These are the parts of giving it one.
 */

test('a chord sheet has a layout of its own for every ratio', () => {
    for (const ratio of slideRatios()) {
        const defaults = CHORDPRO_RATIO_DEFAULTS[ratio];

        assert.ok(defaults, ratio);
        assert.equal(defaults.chordproFontFamily, "'Barlow Condensed'");
        // One column: a second column on a projector is a second thing to find.
        assert.equal(defaults.chordproColumns, 1);
        // The zoom magnifies a preview; a slide is the size of the screen.
        assert.equal(defaults.chordproZoom, 100);
    }
});

// Sized against ABC's own per-ratio lyric sizes, so a chord sheet projected
// after a hymn reads the same height rather than the same nominal size.
test('the projected chord sheet is sized off the same points ABC uses', () => {
    for (const [ratio, pt] of [['16/9', 70], ['4/3', 58.5], ['1/1', 52]]) {
        assert.equal(
            CHORDPRO_RATIO_DEFAULTS[ratio].chordproFontSize,
            Math.round(ptToPx(opticalLyricSizePt(pt, 'Barlow Condensed')) * 10000) / 10000,
            ratio,
        );
    }

    // Bigger screen, bigger type: the ratios step down in order.
    assert.ok(CHORDPRO_RATIO_DEFAULTS['16/9'].chordproFontSize > CHORDPRO_RATIO_DEFAULTS['4/3'].chordproFontSize);
    assert.ok(CHORDPRO_RATIO_DEFAULTS['4/3'].chordproFontSize > CHORDPRO_RATIO_DEFAULTS['1/1'].chordproFontSize);
});

// The ratio select is a control, not a setting: it says which bucket to read,
// so it must never be written into one.
test('a chord sheet opens on paper and keeps its ratio out of the saved settings', () => {
    const mixin = chordproMixin();

    assert.equal(mixin.chordproPageRatio, 'paper');
    assert.ok(!mixin.chordproFields.includes('chordproPageRatio'));
});

// Choosing a ratio must hand back that ratio's layout, not the paper one.
test('asking for a chord sheet ratio gives the screen defaults', () => {
    const paper = formatDefaults('chordpro', 'paper').defaults;
    const screen = formatDefaults('chordpro', '16/9').defaults;

    assert.equal(paper.chordproFontFamily, "'Merriweather'");
    assert.equal(screen.chordproFontFamily, "'Barlow Condensed'");
    assert.ok(screen.chordproFontSize > paper.chordproFontSize);
});

/*
 * In the three engraved formats a conditional block written for another ratio is
 * already invisible, because '%' opens a comment. ChordPro comments with '#', so
 * there the block is a line of lyrics reading '%[43' — it has to go.
 */
test('a block for another ratio is deleted from a chord sheet and left alone elsewhere', () => {
    const source = 'A[C]lap\n%[43 csak negy harmadban %]\nB[G]lap\n';

    const chordpro = applyConditionalBlocks(source, '16/9', 'chordpro');
    assert.ok(!chordpro.includes('%[43'), 'left in a chord sheet');
    assert.ok(!chordpro.includes('csak negy harmadban'));
    assert.match(chordpro, /A\[C\]lap/);
    assert.match(chordpro, /B\[G\]lap/);

    // ABC keeps it: it is a comment there, and the source length is what the
    // Aretino editor maps preview clicks through.
    const abc = applyConditionalBlocks(source, '16/9', 'abc');
    assert.equal(abc, source);
    assert.equal(applyConditionalBlocks(source, '16/9'), source);
});

// The block written for the ratio being drawn goes live in every format.
test('the block for this ratio is activated in a chord sheet too', () => {
    const source = 'A[C]lap\n%[169 [G]csak tizenhat kilencben %]\nB[G]lap\n';
    const out = applyConditionalBlocks(source, '16/9', 'chordpro');

    assert.ok(!out.includes('%['));
    assert.ok(!out.includes('%]'));
    assert.match(out, /\[G\]csak tizenhat kilencben/);
});
