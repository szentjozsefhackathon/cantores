import { renderAbcSlide, hungarianChordsToAbc } from './score-editor-abc.js';
import { renderAretinoSlide } from './score-editor-aretino.js';
import { renderChordproSlides } from './score-editor-chordpro.js';
import { renderGabcSlide } from './score-editor-gabc.js';
import { splitPages } from './score-editor-pages.js';
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

    return splitPages(source, format, ratio);
}

/**
 * Engrave one page of one score onto its slide.
 *
 * The three engraved formats only: ChordPro is not engraved by an engine and
 * does not answer one page with one slide, so it goes through
 * renderRatioPageSlides below.
 *
 * Comes back framed and ready to drop into a box of that ratio, and saying
 * whether the music fitted — each engine reports running out of room in its own
 * way, and each format's renderer knows which. Nothing here shrinks an
 * engraving to make it fit: that answer belongs to the author, who gives it
 * with a smaller size or another `%pagebreak`.
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

    if (format === 'abc') { return renderAbcSlide(pageSource, settings, canvas); }
    if (format === 'gabc') { return renderGabcSlide(pageSource, settings, canvas); }
    if (format === 'aretino') { return renderAretinoSlide(pageSource, settings, canvas, ratio); }

    throw new Error(`[projection] ${format} cannot be engraved to a slide`);
}

/**
 * The slides one page comes to — which is one of them, except for ChordPro.
 *
 * An engraved page is one slide by definition: it is the size its engine made
 * it, and running over is the author's business. A chord sheet is words, so a
 * page of it that will not fit is broken into as many slides as it needs rather
 * than cut off at the bottom edge — see renderChordproSlides.
 *
 * The palette goes the same way, and no further: a chord sheet is words and
 * takes the deck's ink, and the three engines draw their own black on their own
 * white whatever the deck says.
 *
 * @param {import('./slide-palette.js').SlidePalette} [palette]
 * @return {Promise<Array<{svg: SVGElement, overflows: boolean}>>} never empty
 */
export async function renderRatioPageSlides(format, pageSource, settings, ratio, palette) {
    if (format !== 'chordpro') {
        return [await renderRatioPage(format, pageSource, settings, ratio)];
    }

    const canvas = slideCanvas(format, ratio);

    if (canvas === null) {
        throw new Error(`[projection] ${ratio} is not a slide ratio`);
    }

    return renderChordproSlides(pageSource, settings, canvas, palette ?? slidePalette());
}

/**
 * Every slide one score comes to at one ratio, in order.
 *
 * @param {import('./slide-palette.js').SlidePalette} [palette] the deck's ink,
 *        for the one format that is words rather than an engraving
 * @param {number[]|null} [sections] the row's chosen section references
 * @return {Promise<Array<{svg: SVGElement, overflows: boolean}>>}
 */
export async function renderRatioPages(format, content, settings, ratio, palette, sections = null) {
    const pages = await Promise.all(
        ratioPageSources(format, content, settings, ratio, sections)
            .map((page) => renderRatioPageSlides(format, page, settings, ratio, palette)),
    );

    return pages.flat();
}
