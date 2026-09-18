import { canvasMeasurer } from './booklet-chordpro.js';
import { markdownRows } from './booklet-markdown.js';
import { textRowSvg } from './booklet-text.js';
import { enginesReady } from './music-engines.js';
import { renderRatioPages } from './projection-render.js';
import { fileSlideSettings, resolveSlideSettings, textSlideSettings } from './projection-settings.js';
import { slidePalette } from './slide-palette.js';
import { packSoftPages } from './soft-pages.js';
import { fitIntoBox, frameSlide, isSlideRatio, paintSlide, parseSvg, slideCanvas } from './slide-frame.js';
import { stackSvgs } from './svg-stack.js';

/**
 * A projection's rows turned into the slides that are actually projected.
 *
 * The one place the deck's own shape is imposed on anything. Each score is cut
 * into the screens its author's page breaks ask for, engraved at that author's
 * own layout for this ratio, and then — and only then — fitted into the deck's
 * box with whatever heading the row still carries.
 *
 * A row is not a slide. One score is however many screens it breaks into, and a
 * screen of words is however many it has to be cut into to stay readable; the
 * count is never stored either way. It is read back off the source here, every
 * time, which is what makes a `%pagebreak` moved on Thursday a different screen
 * on Sunday.
 */

/** The canvas every non-engraved slide — words, a scan — is composed on. */
const TEXT_CANVAS = { '16/9': { width: 1920, height: 1080 }, '4/3': { width: 1440, height: 1080 }, '1/1': { width: 1080, height: 1080 } };

/** The margin around a slide's own words, as a share of its width. */
const TEXT_MARGIN = 0.08;

/** How large a screen of words is set, as a share of the slide's height. */
const TEXT_HEIGHT = 0.075;

/**
 * How large a heading is set beside the music it names, as a share of the
 * slide's height. Small: a congregation came to sing, not to read the name of
 * what it is singing, and the heading is there to be glanced at once.
 */
const HEADING_HEIGHT = 0.075;

const HEADING_FONT = "'Barlow Condensed'";

/**
 * The colours this deck sets its words in.
 *
 * The table itself lives in slide-palette.js, where the chord-sheet engraver can
 * reach it too; this is the name the deck has always called it by.
 */
export { slidePalette as textPalette } from './slide-palette.js';

/**
 * Every slide this deck comes to, in order.
 *
 * @param {Array<object>} entries the render payload's rows
 * @param {object} geometry from the payload — carries the ratio
 * @return {Promise<Array<{entryId: number, svg: SVGElement, overflows: boolean}>>}
 */
export async function renderDeck(entries, geometry) {
    const ratio = geometry?.ratio;

    if (!isSlideRatio(ratio)) { return []; }

    // Before the first row, and not per row: a score drawn by an engine that
    // has not arrived throws, and the throw costs that slide silently. See
    // music-engines.js for why a page reached by navigation cannot assume they
    // are there.
    await enginesReady();

    const slides = [];
    const palette = slidePalette(geometry);

    for (const entry of entries ?? []) {
        try {
            const made = await slidesOf(entry, ratio, palette, geometry);

            // The position within the row, which is what a slide left out of the
            // service is remembered by: the row is one thing chosen from the
            // plan, and its slides are however many its page breaks cut it into.
            made.forEach((slide, index) => slides.push({ entryId: entry.id, index, ...slide }));
        } catch (e) {
            console.error('[projection] could not draw a row', entry?.id, e);
        }
    }

    return slides;
}

/**
 * Whether one slide is one the service walks past.
 *
 * Asked of the deck's exclusion map rather than of the slide, because the two
 * answer different questions and change at different times: every slide is drawn
 * whatever happens, and which of them are shown is settled afterwards, without
 * engraving anything again.
 *
 * @param {{entryId: number, index: number}} slide
 * @param {Object<string|number, Array<number>>} excluded keyed by row
 */
export function isExcluded(slide, excluded) {
    const list = (excluded ?? {})[slide.entryId] ?? (excluded ?? {})[String(slide.entryId)];

    return Array.isArray(list) && list.includes(slide.index);
}

/**
 * A circle with a stroke through it, and the same circle with a cross in it.
 *
 * Drawn from two primitives rather than pulled from the icon set, because both
 * sheets that carry them — the editor's and the remote's deck pane — are built
 * in JavaScript and have no Blade behind them, and a path copied out of an icon
 * library by hand is a path nobody can check.
 */
export const SKIP_ICON = '<svg viewBox="0 0 16 16" aria-hidden="true" focusable="false">'
    + '<circle cx="8" cy="8" r="6" fill="none" stroke="currentColor" stroke-width="1.5"/>'
    + '<line x1="3.9" y1="12.1" x2="12.1" y2="3.9" stroke="currentColor" stroke-width="1.5"/></svg>';

export const RESTORE_ICON = '<svg viewBox="0 0 16 16" aria-hidden="true" focusable="false">'
    + '<circle cx="8" cy="8" r="6" fill="none" stroke="currentColor" stroke-width="1.5"/>'
    + '<line x1="8" y1="4.6" x2="8" y2="11.4" stroke="currentColor" stroke-width="1.5"/>'
    + '<line x1="4.6" y1="8" x2="11.4" y2="8" stroke="currentColor" stroke-width="1.5"/></svg>';

/** How many slides each row came to, keyed by row — what the editor labels with. */
export function slideCounts(slides) {
    const counts = {};

    for (const slide of slides) {
        counts[slide.entryId] = (counts[slide.entryId] ?? 0) + 1;
    }

    return counts;
}

async function slidesOf(entry, ratio, palette, geometry) {
    if (entry.kind === 'text') { return textSlides(entry, ratio, palette, geometry); }
    if (entry.kind === 'file') { return await fileSlides(entry, ratio); }

    return await scoreSlides(entry, ratio, palette);
}

/**
 * A score, cut where its author said to cut it — and, for a chord sheet,
 * wherever it has to be cut besides, since words flow and an engraving does not.
 *
 * The deck's ink is handed down with it, for the same reason: a chord sheet is
 * words, and comes out white on black beside the screens of words it is sung
 * from. The three engines ignore it and engrave their own black on white.
 *
 * The heading rides on the first screen only. A hymn broken across three slides
 * is one hymn, and repeating its name on every screen would say three times what
 * the congregation read once.
 */
async function scoreSlides(entry, ratio, palette) {
    const settings = resolveSlideSettings(entry.format, entry.settings ?? {}, ratio, entry.override);
    const pages = await renderRatioPages(entry.format, entry.content ?? '', settings, ratio, palette, entry.sections ?? null);
    const canvas = slideCanvas(entry.format, ratio);
    const heading = headingOf(entry);

    return pages.map(({ svg, overflows }, index) => ({
        svg: index === 0 && heading !== null ? withHeading(svg, canvas, heading) : svg,
        overflows,
    }));
}

/**
 * An uploaded score: one whole page per slide, letterboxed into the screen.
 *
 * Fitted on both axes rather than stretched to the width, because a portrait
 * scan on a widescreen is exactly the case this has to get right — filling the
 * width would run most of the page off the bottom.
 */
async function fileSlides(entry, ratio) {
    const canvas = { ...TEXT_CANVAS[ratio] };
    const zoom = Number(fileSlideSettings(entry.override).fileZoom) || 1;

    const drawn = await Promise.all((entry.pages ?? []).map(async (page) => {
        try {
            return { ...page, markup: await pageMarkup(page.url) };
        } catch (e) {
            console.error('[projection] could not fetch a page', page.url, e);

            return null;
        }
    }));

    return drawn.filter(Boolean).map((page) => {
        const fragment = parseSvg(page.markup);

        if (fragment === null) { return { svg: blankSlide(canvas), overflows: false }; }

        const box = intrinsicBox(fragment, canvas);
        const scale = fitIntoBox(box, canvas) * zoom;

        const { svg } = stackSvgs([fragment], {
            placements: [{
                x: (canvas.width - box.width * scale) / 2,
                y: (canvas.height - box.height * scale) / 2,
                scale,
            }],
            viewBox: { x: 0, y: 0, w: canvas.width, h: canvas.height },
        });

        return { svg: frameSlide(svg, canvas), overflows: false };
    });
}

/**
 * The screens one row of words comes to.
 *
 * Set large and centred on the slide's own margin, using the same Markdown the
 * booklet's paragraphs are written in — so a rubric written for the handout can
 * be pasted onto a screen and read the same way.
 *
 * How large is a share of the slide's own height, times whatever the deck says
 * and whatever this row says on top of that. A share rather than a size in
 * points because the canvas is the screen: the same words have to read the same
 * way at all three shapes.
 *
 * The whole row is laid out once, at that size, and then cut into as many
 * screens as it needs — see packSoftPages, which spends the author's own
 * `%pagebreak` lines before it spends anything of its own. Setting the words
 * smaller is what is left when even a single paragraph will not hold, and a
 * screen that had to do it says so.
 */
function textSlides(entry, ratio, palette, geometry) {
    const canvas = { ...TEXT_CANVAS[ratio] };
    const width = canvas.width * (1 - 2 * TEXT_MARGIN);
    const { textSizeScale, textLineHeight } = textSlideSettings(entry.override, geometry);
    const fontSize = canvas.height * TEXT_HEIGHT * textSizeScale;
    const box = canvas.height * (1 - 2 * TEXT_MARGIN);

    const rows = markdownRows(entry.text ?? '', {
        layoutWidth: width,
        fontSize,
        fontFamily: HEADING_FONT,
        lineHeight: textLineHeight,
        measure: canvasMeasurer(HEADING_FONT, fontSize),
        palette,
        ratio,
    });

    const pages = packSoftPages(rows, box);

    if (pages.length === 0) { return [{ svg: blankSlide(canvas, palette.background), overflows: false }]; }

    return pages.map((page) => textSlide(page, canvas, palette, box));
}

/** One of those screens, stacked and centred on the canvas. */
function textSlide(page, canvas, palette, box) {
    const scale = Math.min(1, box / page.height);
    const total = page.height * scale;

    const fragments = [];
    const placements = [];
    let y = (canvas.height - total) / 2;

    page.rows.forEach((row, i) => {
        y += (i === 0 ? 0 : (row.spaceBefore ?? 0)) * scale;
        fragments.push(parseSvg(row.svg));
        placements.push({ x: canvas.width * TEXT_MARGIN, y, scale });
        y += row.height * scale;
    });

    const { svg } = stackSvgs(fragments, {
        placements,
        viewBox: { x: 0, y: 0, w: canvas.width, h: canvas.height },
    });

    return {
        svg: paintSlide(frameSlide(svg, canvas), canvas, palette.background),
        overflows: scale < 1,
    };
}

/**
 * The one line a slide says about what is on it.
 *
 * A booklet prints the slot, the music, where it can be looked up and the
 * variation, each on a line of its own — a page is read at arm's length and has
 * room. A screen has neither, so whatever the row still carries is joined into
 * one line and set small above the music.
 */
function headingOf(entry) {
    const line = [entry.slot, entry.music, entry.variation]
        .map((part) => (typeof part === 'string' ? part.trim() : ''))
        .filter((part) => part !== '')
        .join(' – ');

    const reference = typeof entry.reference === 'string' ? entry.reference.trim() : '';

    if (line === '' && reference === '') { return null; }

    return { line, reference };
}

/**
 * The music with its name above it.
 *
 * The engraving is scaled into what is left rather than cropped, so a slide that
 * gains a heading loses a little size instead of losing its last staff.
 */
function withHeading(music, canvas, heading) {
    const headingHeight = canvas.height * HEADING_HEIGHT;
    const fontSize = headingHeight * 0.62;

    const row = textRowSvg({
        content: heading.line,
        suffix: heading.reference || null,
        fontSize,
        fontFamily: HEADING_FONT,
        width: canvas.width * (1 - 2 * TEXT_MARGIN),
        bold: true,
        fill: '#333333',
    });

    const box = { width: canvas.width, height: canvas.height - headingHeight };
    const musicFragment = parseSvg(new XMLSerializer().serializeToString(music));
    const musicBox = intrinsicBox(musicFragment, canvas);
    const scale = fitIntoBox(musicBox, box);

    const { svg } = stackSvgs([parseSvg(row.svg), musicFragment], {
        placements: [
            { x: canvas.width * TEXT_MARGIN, y: headingHeight * 0.2, scale: 1 },
            { x: (canvas.width - musicBox.width * scale) / 2, y: headingHeight, scale },
        ],
        viewBox: { x: 0, y: 0, w: canvas.width, h: canvas.height },
    });

    return frameSlide(svg, canvas);
}

/**
 * What a fragment is actually the size of, in its own units — its viewBox where
 * it declares one, and the canvas where it does not.
 */
function intrinsicBox(svg, fallback) {
    const box = (svg.getAttribute('viewBox') || '').split(/\s+/).map(Number);

    if (box.length === 4 && box.every(Number.isFinite) && box[2] > 0 && box[3] > 0) {
        return { width: box[2], height: box[3] };
    }

    return { ...fallback };
}

function blankSlide(canvas, background = null) {
    const { svg } = stackSvgs([], { viewBox: { x: 0, y: 0, w: canvas.width, h: canvas.height } });
    const framed = frameSlide(svg, canvas);

    return paintSlide(framed, canvas, background);
}

/** One uploaded page, fetched once however many slides ask for it. */
const pageCache = new Map();

function pageMarkup(url) {
    if (!pageCache.has(url)) {
        pageCache.set(url, fetch(url, { credentials: 'same-origin' }).then(async (response) => {
            if (!response.ok) { throw new Error(`page fetch failed with ${response.status}`); }

            const type = response.headers.get('content-type') ?? '';

            if (type.includes('svg')) { return await response.text(); }

            // A file rendered before the vector pipeline existed comes back as a
            // picture; it is wrapped so the rest of this can treat every page the
            // same way.
            const blob = await response.blob();
            const dataUri = await new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = () => resolve(reader.result);
                reader.onerror = reject;
                reader.readAsDataURL(blob);
            });
            const size = await imageSize(dataUri);

            return `<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" `
                + `viewBox="0 0 ${size.width} ${size.height}" width="${size.width}" height="${size.height}">`
                + `<image href="${dataUri}" xlink:href="${dataUri}" width="${size.width}" height="${size.height}"/></svg>`;
        }));
    }

    return pageCache.get(url);
}

function imageSize(dataUri) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => resolve({ width: img.naturalWidth, height: img.naturalHeight });
        img.onerror = reject;
        img.src = dataUri;
    });
}
