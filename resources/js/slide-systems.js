import { SLIDE_FIT_TOLERANCE, fitSlide, parseSvg } from './slide-frame.js';
import { packSoftPages, startsAtAutomaticCut } from './soft-pages.js';
import { stackSvgs } from './svg-stack.js';

/**
 * An engraved page that does not fit, cut between its staff systems into the
 * slides it needs.
 *
 * The three engines disagree about how they report running out of room, which
 * is why an engraving used to be cut only where its author said. They agree
 * about something more useful: every one of them can hand its music over one
 * staff system at a time — abc2svg emits an <svg> per music line, Aretino has
 * splitRowSVGs, exsurge marks each chant line so it can be lifted out — which is
 * how the booklet already flows a score across pages. A list of systems with
 * heights is exactly what packSoftPages takes, so the same tiers a chord sheet
 * is cut by apply unchanged:
 *
 *   1. `%pagebreak` and the break numbered for this ratio cut, before anything
 *      here is asked (splitPages).
 *   2. A page that fits is one slide, drawn exactly as it always was — the
 *      engine's own renderer answers it, not this.
 *   3. A page that does not fit is filled a slide at a time, and each slide is
 *      cut at the last `%pagebreak?` on it — or, where it holds none, between
 *      systems, as low as it can be.
 *
 * Below all three a single system taller than the screen is left as it is and
 * `overflows` says so, which is the one case the author still has to answer —
 * with a smaller staff. Nothing is ever shrunk to fit.
 *
 * A slide that begins at a cut between systems says so with `autoSplit`. The
 * cut was the packer's choice rather than the author's, and one of them
 * usually lands somewhere a `%pagebreak169` would have put better; the editors
 * show it so the author can see where.
 *
 * @typedef {object} SlideSystem
 * @property {SVGElement|string} svg one staff system, as the engine drew it
 * @property {number} height in the slide canvas's own units
 * @property {boolean} [keepWithNext] a title, which must not end a slide alone
 *
 * @typedef {object} SystemPage
 * @property {SlideSystem[]} systems
 * @property {number} height what the systems come to, stacked
 * @property {boolean} autoSplit whether it begins at a cut nobody wrote
 * @property {number} start the index of its first system, counted across every segment
 */

/**
 * The slides a run of systems comes to, as systems and heights — the whole of
 * the decision, free of the DOM.
 *
 * @param {SlideSystem[][]} segments one list per `%pagebreak?` piece, in order
 * @param {number} boxHeight the room a slide has
 * @param {number[]|null} [starts] cuts made by another browser, made here instead
 * @return {SystemPage[]} never empty unless every segment is
 */
export function packSystems(segments, boxHeight, starts = null) {
    const rows = [];

    segments.forEach((systems, segmentIndex) => {
        systems.forEach((system, i) => {
            const previous = i === 0 ? null : systems[i - 1];

            rows.push({
                height: system.height,
                spaceBefore: 0,
                keepWithNext: !!system.keepWithNext,
                // Every system boundary may be cut except the one under a title:
                // soft-pages.js reads a `false` here as two rows that are one.
                splitBefore: !previous?.keepWithNext,
                breakBefore: i === 0 && segmentIndex > 0 ? 'soft' : false,
                system,
            });
        });
    });

    return packSoftPages(rows, boxHeight, starts).map((page, i) => ({
        systems: page.rows.map((row) => row.system),
        height: page.height,
        autoSplit: startsAtAutomaticCut(page, i),
        start: page.start,
    }));
}

/**
 * One packed page drawn as a slide: its systems stacked down from the top, each
 * at the size it was engraved.
 *
 * Top-aligned for the reason fitSlide gives: two consecutive slides of one hymn
 * must not start their first staff at different heights. A system wider than
 * the canvas — Aretino widens its own when a long word runs past the edge —
 * widens the slide with it, keeping its shape, so the slide is letterboxed the
 * way the engine's own renderer letterboxes it, rather than cut at the side.
 *
 * @param {SystemPage} page
 * @param {{width: number, height: number}} canvas
 * @return {{svg: SVGElement, overflows: boolean, autoSplit: boolean, start: number}}
 */
export function systemsSlide(page, canvas) {
    const fragments = page.systems.map((system) => (typeof system.svg === 'string' ? parseSvg(system.svg) : system.svg));
    const widths = fragments.map((fragment) => boxWidthOf(fragment));
    const width = Math.max(canvas.width, ...widths);
    const placements = [];
    let y = 0;

    page.systems.forEach((system) => {
        placements.push({ x: 0, y, scale: 1 });
        y += system.height;
    });

    const { svg } = stackSvgs(fragments, {
        placements,
        viewBox: { x: 0, y: 0, w: width, h: canvas.height * (width / canvas.width) },
    });

    return {
        svg: fitSlide(svg),
        overflows: page.height > canvas.height + SLIDE_FIT_TOLERANCE || width > canvas.width + SLIDE_FIT_TOLERANCE,
        autoSplit: page.autoSplit,
        start: page.start ?? 0,
    };
}

/**
 * Whether a page is drawn as the one slide its engine engraves whole.
 *
 * This browser's own measurement decides, unless another browser's cuts were
 * handed down: then the page is one slide exactly where the wall made it one,
 * even if it measures a pixel too tall here, and is cut wherever the wall cut
 * it, even if it would have fitted here.
 *
 * @param {number[]|null} starts see packSystems
 * @param {boolean} overflows whether the whole page ran over in this browser
 */
export function staysWhole(starts, overflows) {
    return Array.isArray(starts) && starts.length > 0 ? starts.length === 1 : !overflows;
}

/**
 * Every slide one overflowing page comes to.
 *
 * @param {SlideSystem[][]} segments see packSystems
 * @param {{width: number, height: number}} canvas
 * @param {(svg: SVGElement) => SVGElement} [finish] whatever the format does to
 *        a finished slide — ABC writes its ink and stroke widths on
 * @param {number[]|null} [starts] see packSystems
 * @return {Array<{svg: SVGElement, overflows: boolean, autoSplit: boolean, start: number}>}
 */
export function systemSlides(segments, canvas, finish = (svg) => svg, starts = null) {
    return packSystems(segments, canvas.height, starts).map((page) => {
        const slide = systemsSlide(page, canvas);

        return { ...slide, svg: finish(slide.svg) };
    });
}

function boxWidthOf(svg) {
    const box = (svg?.getAttribute?.('viewBox') || '').trim().split(/[\s,]+/).map(Number);

    return box.length === 4 && Number.isFinite(box[2]) ? box[2] : 0;
}
