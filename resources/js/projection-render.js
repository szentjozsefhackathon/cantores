import { renderAbcSlide, renderAbcSlides, hungarianChordsToAbc } from './score-editor-abc.js';
import { renderAretinoSlide, renderAretinoSlides } from './score-editor-aretino.js';
import { renderChordproSlides } from './score-editor-chordpro.js';
import { renderGabcSlide, renderGabcSlides } from './score-editor-gabc.js';
import { softSegmentSources, splitPages } from './score-editor-pages.js';
import { arrangeSections } from './score-sections.js';
import { slideCanvas } from './slide-frame.js';
import { slidePalette } from './slide-palette.js';

/**
 * Engraving one score onto the slides one projector ratio asks for.
 *
 * The three fixed ratios were built into the score editor and stayed there: an
 * author could tune a 16:9 layout and had nowhere to send it, because the code
 * that drew it read two dozen fields off the editor's own Alpine component.
 * Each format now engraves its own slide beside the rest of its own knowledge —
 * see renderAbcSlide, renderGabcSlide, renderAretinoSlide — and this is the
 * dispatcher over them, which is all a caller needs to know.
 *
 * There is deliberately one copy of it. The editor draws these slides and so
 * does a projection; docs/abc2svg-formatting.md and docs/vendor-patches.md
 * already keep lists of the renderers that must stay in step with one another,
 * and a third independent copy is a drift bug waiting for the next abc2svg
 * patch.
 */

export { fitIntoBox, isSlideRatio, slideCanvas, slideRatios } from './slide-frame.js';

/**
 * The sources one score comes to at one ratio — everything an engine needs,
 * page by page, with whatever each format insists on having done to it first.
 *
 * ABC is the only one that rewrites: it needs an `X:` header to parse at all,
 * suppressing the clef is a source edit rather than a directive, and its chord
 * symbols are written in Hungarian. All three happen before the split, because
 * a header inserted afterwards would land on the first page alone.
 *
 * A row that has chosen sections is arranged first, its chosen `%section`s
 * strung together with a `%pagebreak` between each, so the split below never
 * has to know sections exist — what it sees is a source with page breaks in
 * it, exactly as it always has.
 *
 * @param {number[]|null} [sections] the row's chosen section references
 */
export function ratioPageSources(format, content, settings, ratio, sections = null) {
    let source = arrangeSections(content ?? '', format, sections, { separator: '%pagebreak' }).source;

    if (format === 'abc') {
        if (!/^X:/m.test(source)) { source = 'X:1\n' + source; }
        if (settings?.abcNoClef) { source = source.replace(/\|[|:\]]?/, '$&[K:clef=none]'); }
        source = hungarianChordsToAbc(source);
    }

    // The suggestions are left in: each renderer below decides whether to spend
    // them, and takes them out of whatever it hands its engine.
    return splitPages(source, format, ratio, true);
}

/**
 * Engrave one page of one score onto exactly one slide.
 *
 * The three engraved formats only, and only where one slide is what is wanted
 * whatever happens: a projection goes through renderRatioPageSlides below,
 * which cuts a page that does not fit rather than clipping it.
 *
 * Comes back framed and ready to drop into a box of that ratio, and saying
 * whether the music fitted — each engine reports running out of room in its own
 * way, and each format's renderer knows which. Nothing here shrinks an
 * engraving to make it fit.
 *
 * @param {string} format gabc | abc | aretino
 * @param {string} pageSource one entry from ratioPageSources()
 * @param {object} settings the resolved per-ratio settings bucket
 * @param {string} ratio
 * @return {Promise<{svg: SVGElement, overflows: boolean}>}
 */
export async function renderRatioPage(format, pageSource, settings, ratio) {
    const canvas = slideCanvas(format, ratio);

    if (canvas === null) {
        throw new Error(`[projection] ${ratio} is not a slide ratio`);
    }

    const source = softSegmentSources(pageSource, format).whole;

    if (format === 'abc') { return renderAbcSlide(source, settings, canvas); }
    if (format === 'gabc') { return renderGabcSlide(source, settings, canvas); }
    if (format === 'aretino') { return renderAretinoSlide(source, settings, canvas, ratio); }

    throw new Error(`[projection] ${format} cannot be engraved to a slide`);
}

/**
 * The slides one page comes to.
 *
 * A page that fits is one slide, exactly as it has always been drawn. One that
 * does not is not cut off at the bottom edge: the author's own answer is spent
 * first — `%pagebreak`, then `%pagebreak?` — and whatever is still too tall is
 * cut between staff systems, or for a chord sheet between its rows, into as
 * many slides as it needs. See slide-systems.js and renderChordproSlides.
 *
 * A slide that begins at a cut nobody wrote carries `autoSplit`, so the editors
 * can point at it: a `%pagebreak169` placed by hand usually cuts better. What is
 * still `overflows` is the one case no cut can answer — a single system taller
 * than the screen — and nothing here shrinks anything to make it fit.
 *
 * The palette goes to a chord sheet and no further: a chord sheet is words and
 * takes the deck's ink, and the three engines draw their own black on their own
 * white whatever the deck says.
 *
 * @param {string} pageSource one entry from ratioPageSources(), suggestions left in
 * @param {import('./slide-palette.js').SlidePalette} [palette]
 * @return {Promise<Array<{svg: SVGElement, overflows: boolean, autoSplit: boolean}>>} never empty
 */
export async function renderRatioPageSlides(format, pageSource, settings, ratio, palette) {
    const canvas = slideCanvas(format, ratio);

    if (canvas === null) {
        throw new Error(`[projection] ${ratio} is not a slide ratio`);
    }

    if (format === 'chordpro') { return renderChordproSlides(pageSource, settings, canvas, palette ?? slidePalette()); }
    if (format === 'abc') { return renderAbcSlides(pageSource, settings, canvas); }
    if (format === 'gabc') { return renderGabcSlides(pageSource, settings, canvas); }
    if (format === 'aretino') { return renderAretinoSlides(pageSource, settings, canvas, ratio); }

    throw new Error(`[projection] ${format} cannot be engraved to a slide`);
}

/**
 * Every slide one score comes to at one ratio, in order.
 *
 * @param {import('./slide-palette.js').SlidePalette} [palette] the deck's ink,
 *        for the one format that is words rather than an engraving
 * @param {number[]|null} [sections] the row's chosen section references
 * @return {Promise<Array<{svg: SVGElement, overflows: boolean, autoSplit: boolean}>>}
 */
export async function renderRatioPages(format, content, settings, ratio, palette, sections = null) {
    const pages = await Promise.all(
        ratioPageSources(format, content, settings, ratio, sections)
            .map((page) => renderRatioPageSlides(format, page, settings, ratio, palette)),
    );

    return pages.flat();
}
