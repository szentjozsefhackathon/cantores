/**
 * The shape of a projector slide, and the frame every engraving is put into.
 *
 * Format-blind on purpose. The three engines disagree about how large a slide
 * is — and they are all correct, because each author tuned their sizes against
 * the canvas their own editor drew — but they cannot be allowed to disagree
 * about its *shape*, or a score would come out a different slide from the one
 * beside it. So the canvases live here, in one table that can be read down and
 * checked, and the framing that turns an engraving into a slide lives here with
 * them.
 */

const SVG_NS = 'http://www.w3.org/2000/svg';

/**
 * The canvas ABC engraves a slide onto, and the one anything without a projector
 * canvas of its own is laid into: a constant 1080 height, the width varying with
 * the ratio.
 */
const SLIDE_CANVAS = {
    '16/9': { width: 1920, height: 1080 },
    '4/3': { width: 1440, height: 1080 },
    '1/1': { width: 1080, height: 1080 },
};

/** Aretino works at half that scale throughout, which is why 45 pt is large there. */
const ARETINO_CANVAS = {
    '16/9': { width: 960, height: 540 },
    '4/3': { width: 720, height: 540 },
    '1/1': { width: 540, height: 540 },
};

/** GABC arrives at the same shapes from the other side: a constant 1920 width. */
const GABC_CANVAS = {
    '16/9': { width: 1920, height: 1080 },
    '4/3': { width: 1920, height: 1440 },
    '1/1': { width: 1920, height: 1920 },
};

/**
 * The rounding slack allowed before an engraving counts as not having fitted.
 * Every engine reports its extent in fractional units; two of them is noise.
 */
export const SLIDE_FIT_TOLERANCE = 2;

/** Whether a ratio makes slides at all — paper and responsive do not. */
export function isSlideRatio(ratio) {
    return Object.prototype.hasOwnProperty.call(SLIDE_CANVAS, ratio);
}

export function slideRatios() {
    return Object.keys(SLIDE_CANVAS);
}

/**
 * The canvas one format engraves one ratio onto, or null where the ratio is not
 * a slide.
 *
 * @param {string} format gabc | abc | aretino | chordpro | file
 * @param {string} ratio
 * @return {{width: number, height: number}|null}
 */
export function slideCanvas(format, ratio) {
    if (!isSlideRatio(ratio)) { return null; }

    if (format === 'aretino') { return { ...ARETINO_CANVAS[ratio] }; }
    if (format === 'gabc') { return { ...GABC_CANVAS[ratio] }; }

    // ChordPro and an uploaded page are not engraved to a projector by any
    // editor, so they have no canvas of their own and are fitted into the
    // slide's own box.
    return { ...SLIDE_CANVAS[ratio] };
}

/**
 * How much something must shrink to sit inside a box, on both axes.
 *
 * The booklet asks this of the width alone, because a page that runs long is
 * answered by breaking it rather than by shrinking it. A slide cannot break — it
 * is the size it is — so this is the one piece of arithmetic a projection needs
 * that nothing else in the codebase had.
 *
 * Never enlarges: a fragment smaller than the slide keeps its size, so a short
 * refrain is not blown up to fill a screen nobody measured it for.
 */
export function fitIntoBox(content, box) {
    const width = Number(content?.width) || 0;
    const height = Number(content?.height) || 0;

    if (!(width > 0) || !(height > 0)) { return 1; }

    return Math.min(1, box.width / width, box.height / height);
}

/**
 * Fill the box you are dropped into, whatever coordinates you count in.
 *
 * `xMidYMin meet` rather than a centred fit, so music shorter than the slide
 * stands at the top where it was engraved instead of drifting to the middle —
 * two consecutive slides of one hymn must not have their first staff in
 * different places.
 */
export function fitSlide(svg) {
    svg.setAttribute('width', '100%');
    svg.setAttribute('height', '100%');
    svg.setAttribute('preserveAspectRatio', 'xMidYMin meet');
    svg.style.display = 'block';
    svg.style.width = '100%';
    svg.style.height = '100%';
    svg.style.maxWidth = 'none';
    svg.style.overflow = 'hidden';

    return svg;
}

/**
 * The same, for an engine that reports the height its music came to rather than
 * the height it was given: the canvas is restated as the viewBox, so music
 * taller than the slide is cut off at the bottom instead of shrinking the staff
 * above it. ABC and GABC both work this way.
 */
export function frameSlide(svg, canvas) {
    svg.setAttribute('viewBox', `0 0 ${canvas.width} ${canvas.height}`);

    return fitSlide(svg);
}

/** A slide that engraved to nothing — a blank page rather than a broken one. */
export function emptySlide(canvas) {
    return frameSlide(document.createElementNS(SVG_NS, 'svg'), canvas);
}

export function parseSvg(markup) {
    if (!markup) { return null; }

    return new DOMParser().parseFromString(markup, 'image/svg+xml').querySelector('svg');
}

/** A viewBox as a box, or nulls where the element declares none. */
export function viewBoxOf(svg) {
    const box = (svg.getAttribute('viewBox') || '').split(/\s+/).map(Number);

    if (box.length !== 4 || !box.every(Number.isFinite)) {
        return { width: 0, height: 0 };
    }

    return { width: box[2], height: box[3] };
}
