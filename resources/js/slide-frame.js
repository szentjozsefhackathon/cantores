/**
 * The shape of a projector slide, and the frame every engraving is put into.
 *
 * Format-blind on purpose. Every engine engraves a slide onto the same canvas,
 * so a score cannot come out a different shape of slide from the one beside it,
 * nor a point of its lyric a different height. The canvas lives here, in one
 * table that can be read down and checked, and the framing that turns an
 * engraving into a slide lives here with it.
 */

const SVG_NS = 'http://www.w3.org/2000/svg';

/**
 * The canvas every format engraves a slide onto: a constant 1080 height, the
 * width varying with the ratio.
 *
 * Aretino used to work at half this scale, and GABC at a constant 1920 width
 * that grew taller instead of narrower, so a point of either came out a
 * different height from a point of ABC's on the same screen, and 80 typed into
 * one was a different size from 80 typed into another.
 */
const SLIDE_CANVAS = {
    '16/9': { width: 1920, height: 1080 },
    '4/3': { width: 1440, height: 1080 },
    '1/1': { width: 1080, height: 1080 },
};

/**
 * The border kept clear on every side of a slide, in canvas units: 4% of its
 * height. Screens crop, bezels hide and a badly aligned projector throws its
 * edge onto the wall, and a margin also absorbs the last fraction of a unit in
 * which two rendering engines would otherwise cut a deck at different places.
 */
export const SLIDE_MARGIN = 43;

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
 * a slide: the room inside the margin, which is all an engraving may use. The
 * margin is carried with it as `margin`, for frameSlide and paintSlide to put
 * back around whatever was drawn.
 *
 * @param {string} format gabc | abc | aretino | chordpro | file
 * @param {string} ratio
 * @return {{width: number, height: number}|null}
 */
export function slideCanvas(format, ratio) {
    if (!isSlideRatio(ratio)) { return null; }


    // ChordPro and an uploaded page are not engraved to a projector by any
    // editor, so they have no canvas of their own and are fitted into the
    // slide's own box.
    const { width, height } = SLIDE_CANVAS[ratio];

    return { width: width - 2 * SLIDE_MARGIN, height: height - 2 * SLIDE_MARGIN, margin: SLIDE_MARGIN };
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
export function fitSlide(svg, margin = 0) {
    if (margin > 0) { addMargin(svg, margin); }

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

    return fitSlide(svg, canvas.margin ?? 0);
}

/** The viewBox grown by the margin on every side, so the drawing keeps clear of the edges. */
function addMargin(svg, margin) {
    const { x, y, width, height } = viewBoxOf(svg);

    svg.setAttribute('viewBox', `${x - margin} ${y - margin} ${width + 2 * margin} ${height + 2 * margin}`);
}

/**
 * The slide's ground, painted into the document rather than behind it.
 *
 * It has to be part of the SVG: the same slide is shown in the editor's contact
 * sheet, thrown by the presenter and — one day — exported, and only a rectangle
 * inside the drawing reaches all three. Laid underneath everything already
 * there, so nothing has to be drawn in a particular order to survive it.
 */
export function paintSlide(svg, canvas, background) {
    const margin = canvas.margin ?? 0;

    return paintBox(svg, { x: -margin, y: -margin, width: canvas.width + 2 * margin, height: canvas.height + 2 * margin }, background);
}

function paintBox(svg, box, background) {
    if (!background) { return svg; }

    const rect = document.createElementNS(SVG_NS, 'rect');

    rect.setAttribute('x', String(box.x));
    rect.setAttribute('y', String(box.y));
    rect.setAttribute('width', String(box.width));
    rect.setAttribute('height', String(box.height));
    rect.setAttribute('fill', background);

    svg.insertBefore(rect, svg.firstChild);

    return svg;
}

/**
 * A slide as the wall shows it: on the paper the engines assume.
 *
 * The three engines draw black on nothing and leave the white to whatever is
 * behind them. On the wall that cannot be the box the slide sits in — the box is
 * sized in fractions of a pixel and the drawing in whole ones, so a box painted
 * white shows a hairline of it beside a slide of words. The white is laid inside
 * the drawing instead, under any ground of its own, so what shows through the
 * rounding is the black stage.
 *
 * Only under a slide with no ground of its own. Two grounds on one fractional
 * edge are each smoothed into the pixel they half cover, and the white under a
 * black slide of words came through that pixel as a grey line. Crisp at the
 * edge for the same reason: a score's white ends on a whole pixel, not a grey
 * one.
 */
export function onPaper(svg) {
    const slide = svg.cloneNode(true);
    const box = viewBoxOf(slide);

    if (!(box.width > 0) || !(box.height > 0) || hasGround(slide, box)) { return slide; }

    paintBox(slide, box, 'white');
    slide.firstChild.setAttribute('shape-rendering', 'crispEdges');

    return slide;
}

/** Whether a slide's first mark is a ground laid across the whole of it by paintSlide. */
function hasGround(svg, { x, y, width, height }) {
    const first = svg.firstChild;

    return first?.nodeName?.toLowerCase() === 'rect'
        && first.getAttribute('fill') !== null
        && Number(first.getAttribute('x')) === x
        && Number(first.getAttribute('y')) === y
        && Number(first.getAttribute('width')) === width
        && Number(first.getAttribute('height')) === height;
}

/**
 * A slide that engraved to nothing — a blank page rather than a broken one.
 * Still painted, where the slide has a ground: a verse that came to nothing is
 * a black screen among black screens, not a white flash.
 */
export function emptySlide(canvas, background = null) {
    return paintSlide(frameSlide(document.createElementNS(SVG_NS, 'svg'), canvas), canvas, background);
}

export function parseSvg(markup) {
    if (!markup) { return null; }

    return new DOMParser().parseFromString(markup, 'image/svg+xml').querySelector('svg');
}

/** A viewBox as a box, or zeros where the element declares none. */
export function viewBoxOf(svg) {
    const box = (svg.getAttribute('viewBox') || '').trim().split(/[\s,]+/).map(Number);

    if (box.length !== 4 || !box.every(Number.isFinite)) {
        return { x: 0, y: 0, width: 0, height: 0 };
    }

    return { x: box[0], y: box[1], width: box[2], height: box[3] };
}
