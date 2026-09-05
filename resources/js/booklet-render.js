import { renderAretino, splitRowSVGs } from '@aretino-chant/core';

import { canvasMeasurer, chordproRows } from './booklet-chordpro.js';
import { packPages } from './booklet-flow.js';
import { mmToPx, pageGeometry, pxToMm } from './booklet-geometry.js';
import { layoutWidthFor, resolveSettings } from './booklet-settings.js';
import { textRowSvg } from './booklet-text.js';
import { abcMixin } from './score-editor-abc.js';
import { aretinoMixin } from './score-editor-aretino.js';
import { chordproMixin } from './score-editor-chordpro.js';
import { gabcMixin } from './score-editor-gabc.js';
import { injectWebFontsIntoSvg } from './svg-fonts.js';
import { stackSvgs } from './svg-stack.js';

/**
 * Turning a list of scores into pages.
 *
 * Every score is re-engraved here, at the booklet's width and size, rather than
 * reused from whatever the score editor last drew — which is the only way a
 * booklet can promise that a Gregorian antiphon and a guitar sheet come out the
 * same size on the same sheet.
 *
 * The three engraved formats can all be persuaded to emit their music one staff
 * line at a time, and ChordPro is drawn a row at a time by booklet-chordpro.js.
 * That is what makes flowing possible: a page is filled with blocks, not with
 * whole scores.
 */

const SVG_NS = 'http://www.w3.org/2000/svg';

/** Space above a score that is not the first thing on its page. */
const SCORE_GAP_MM = 6;

/** Space between a score's title and its first staff. */
const TITLE_GAP_MM = 1.5;

const TITLE_SIZE_FACTOR = 1.15;
const CREDIT_SIZE_FACTOR = 0.62;

const UI_FONT = "'Inter'";

/**
 * @typedef {object} BookletEntry
 * @property {number} id
 * @property {string} title
 * @property {string} format
 * @property {string} content
 * @property {object} settings the score's own settings column
 * @property {object|null} override booklet_scores.settings_override
 * @property {boolean} startOnNewPage
 * @property {string|null} credit attribution line, for a published score
 */

/**
 * Render a whole booklet.
 *
 * @param {BookletEntry[]} entries
 * @param {object} rawGeometry Booklet::geometry() as it arrives from PHP
 * @param {HTMLElement} host an off-screen but laid-out element, for measuring
 * @returns {Promise<{pages: SVGElement[], fonts: string[]}>}
 */
export async function renderBooklet(entries, rawGeometry, host) {
    const geometry = pageGeometry(rawGeometry);
    const blocks = [];
    const fonts = new Set([UI_FONT]);

    for (const entry of entries) {
        const built = await buildScoreBlocks(entry, geometry, host);

        built.fonts.forEach((font) => fonts.add(font));
        built.blocks.forEach((block) => blocks.push(block));
    }

    const pages = packPages(blocks, geometry.contentHeightPx)
        .map((page, index, all) => composePage(page, geometry, index + 1, all.length));

    return { pages, fonts: Array.from(fonts) };
}

/**
 * Everything one score contributes: its title, its music, and its credit.
 *
 * Exported for testing: everything up to page composition is free of the DOM,
 * so the block-building half can be checked against the real renderers.
 */
export async function buildScoreBlocks(entry, geometry, host) {
    const format = entry.format;
    const defaults = formatDefaults(format);
    const resolved = resolveSettings(format, defaults, entry.settings ?? {}, geometry, entry.override);
    const { layoutWidthPx, scale } = layoutWidthFor(format, resolved, geometry);

    const fonts = [fontOf(format, resolved)];
    const blocks = [];

    if (geometry.showTitles && entry.title) {
        const title = textRowSvg({
            content: entry.title,
            fontSize: geometry.lyricSizePx * TITLE_SIZE_FACTOR,
            fontFamily: UI_FONT,
            width: geometry.contentWidthPx,
            bold: true,
        });

        blocks.push({
            height: title.height,
            svg: title.svg,
            scale: 1,
            spaceBefore: mmToPx(SCORE_GAP_MM),
            // The title moves with the music it names, whatever else happens.
            keepWithNext: true,
            startsScore: true,
            breakBefore: !!entry.startOnNewPage,
        });
    }

    const music = await musicBlocks(format, entry, resolved, layoutWidthPx, geometry, host);

    music.forEach((block, i) => {
        // Scaled on what the renderer actually produced, not on what it was
        // asked for. A renderer may overshoot — Aretino grows its viewBox when a
        // long word runs past the page edge — and a block wider than the content
        // box would print into the margin.
        const blockScale = fitScale(block.svg, scale, geometry.contentWidthPx);

        blocks.push({
            height: block.height * blockScale,
            svg: block.svg,
            scale: blockScale,
            spaceBefore: (block.spaceBefore ?? 0) * blockScale
                + (i === 0 && blocks.length === 0 ? mmToPx(SCORE_GAP_MM) : 0)
                + (i === 0 && blocks.length > 0 ? mmToPx(TITLE_GAP_MM) : 0),
            keepWithNext: block.keepWithNext ?? false,
            startsScore: i === 0 && blocks.length === 0,
            breakBefore: i === 0 && blocks.length === 0 ? !!entry.startOnNewPage : false,
        });
    });

    if (entry.credit && blocks.length > 0) {
        const credit = textRowSvg({
            content: entry.credit,
            fontSize: geometry.lyricSizePx * CREDIT_SIZE_FACTOR,
            fontFamily: UI_FONT,
            width: geometry.contentWidthPx,
            italic: true,
            fill: '#666666',
        });

        // A licence that asks for attribution gets it beside the work it covers,
        // not stamped across every page of a booklet full of other people's music.
        blocks.push({ height: credit.height, svg: credit.svg, scale: 1, spaceBefore: 0 });
    }

    return { blocks, fonts };
}

/**
 * The music itself, one staff line — or one lyric row — per block.
 *
 * @returns {Promise<Array<{height: number, svg: string, spaceBefore?: number, keepWithNext?: boolean}>>}
 */
async function musicBlocks(format, entry, resolved, layoutWidthPx, geometry, host) {
    const content = entry.content ?? '';

    if (content.trim() === '') {
        return [];
    }

    try {
        if (format === 'aretino') {
            return aretinoBlocks(content, resolved, layoutWidthPx);
        }

        if (format === 'abc') {
            return abcBlocks(content, resolved, layoutWidthPx);
        }

        if (format === 'gabc') {
            return await gabcBlocks(content, resolved, layoutWidthPx, host);
        }

        if (format === 'chordpro') {
            return await chordproBlocks(content, resolved, layoutWidthPx, geometry);
        }
    } catch (e) {
        console.error('[booklet] could not render score', entry.id, e);
    }

    return [];
}

/**
 * Aretino splits itself: splitRowSVGs is part of the renderer and returns one
 * standalone document per staff row.
 */
function aretinoBlocks(content, resolved, layoutWidthPx) {
    const svg = renderAretino(content, {
        widthMm: pxToMm(layoutWidthPx),
        zoom: 1,
        staffSpaceMm: Number(resolved.aretinoStaffSize) / 4,
        lyricSize: Number(resolved.aretinoLyricSize),
        textFont: resolved.aretinoTextFont,
        staffGap: Number(resolved.aretinoStaffGap),
        hideRepeatClef: !!resolved.aretinoHideRepeatClef,
        sourceMap: false,
    });

    const rows = splitRowSVGs(svg) ?? [svg];

    return rows.map((row) => ({ height: svgHeight(row), svg: row }));
}

/**
 * abc2svg emits one <svg> per music line unless told otherwise, so the booklet
 * simply does not ask for %%fullsvg — the fragments it produces by default are
 * exactly the blocks the page wants.
 */
function abcBlocks(content, resolved, layoutWidthPx) {
    if (typeof abc2svg === 'undefined' || !abc2svg.Abc) {
        console.error('[booklet] abc2svg not loaded');

        return [];
    }

    let source = content;
    if (!/^X:/m.test(source)) {
        source = 'X:1\n' + source;
    }
    if (resolved.abcNoClef) {
        source = source.replace(/\|[|:\]]?/, '$&[K:clef=none]');
    }

    const pageScale = Number(resolved.abcPageScale) > 0 ? Number(resolved.abcPageScale) : 1;
    const lyricSize = Number(resolved.abcLyricSize) > 0 ? Number(resolved.abcLyricSize) : 12;
    const font = safeAbcFont(resolved.abcLyricFont);
    const vocalfont = ['%%vocalfont', font, resolved.abcLyricBold ? 'bold' : null,
        Number((lyricSize / pageScale * 3).toFixed(3))].filter(Boolean).join(' ');
    const transpose = Number(resolved.abcTranspose) || 0;

    const preamble = `%%pagewidth ${Math.round(layoutWidthPx)}px\n`
        + '%%leftmargin 0px\n%%rightmargin 0px\n'
        + `%%pagescale ${pageScale}\n${vocalfont}\n`
        + `%%notespacingfactor ${resolved.abcNoteSpacing}\n`
        + '%%musicspace 0\n%%topspace 0\n'
        + `%%staffsep ${resolved.abcStaffSep}\n`
        + `%%vocalspace ${resolved.abcVocalSpace}\n`
        + (transpose !== 0 ? `%%transpose ${transpose}\n` : '');

    const chunks = [];
    const abc = new abc2svg.Abc({
        img_out: (str) => chunks.push(str),
        errmsg: (msg, line) => console.warn(`[booklet] abc2svg: ${msg} (line ${line})`),
        read_file: () => null,
    });
    abc.tosvg('booklet', preamble + source);

    const strokes = `<style>.sW{stroke-width:${resolved.abcStemWidth}}.slW{stroke-width:${resolved.abcStaffLineWidth}}</style>`;

    return chunks
        .filter((chunk) => chunk.trim().startsWith('<svg'))
        .map((chunk) => ({
            height: svgHeight(chunk),
            svg: chunk.replace(/^(<svg[^>]*>)/, `$1${strokes}`),
        }));
}

/**
 * exsurge draws the whole chant as one document, but marks each staff line with
 * class="chantLine", so the lines can be lifted out of it.
 */
async function gabcBlocks(content, resolved, layoutWidthPx, host) {
    if (!window.exsurge) {
        console.error('[booklet] exsurge not loaded');

        return [];
    }

    const svg = await new Promise((resolve, reject) => {
        try {
            const ctxt = new exsurge.ChantContext();
            const z = 100 / 30;
            ctxt.setFont(resolved.lyricFont, Number(resolved.lyricSize) * z * 1.3);
            ctxt.setGlyphScaling((Number(resolved.staffSize) / 100) * z / 16);
            if (Number(resolved.minLyricWordSpacing) > 0) {
                ctxt.minLyricWordSpacing = Number(resolved.minLyricWordSpacing) * z;
            }
            if (Number(resolved.hyphenWidth) > 0) {
                ctxt.hyphenWidth = Number(resolved.hyphenWidth) * z;
            }
            ctxt.condensingTolerance = Number(resolved.condensingTolerance);
            ctxt.spaceBetweenSystems = Number(resolved.spaceBetweenSystems);
            ctxt.minSpaceBelowStaff = Number(resolved.minSpaceBelowStaff);

            const mappings = exsurge.Gabc.createMappingsFromSource(ctxt, content);
            const score = new exsurge.ChantScore(ctxt, mappings, !!resolved.dropCaps);
            score.performLayoutAsync(ctxt, () => {
                score.layoutChantLines(ctxt, Math.round(layoutWidthPx), () => {
                    resolve(score.createSvg(ctxt));
                });
            });
        } catch (e) {
            reject(e);
        }
    });

    return sliceRenderedSvg(svg, '.chantLine', host);
}

async function chordproBlocks(content, resolved, layoutWidthPx, geometry) {
    const ChordSheetJS = (await import('chordsheetjs')).default;

    let source = content;
    if (resolved.chordproGermanNotation) {
        source = germanChordsToEnglish(source);
    }

    let song = new ChordSheetJS.ChordProParser().parse(source);
    const transpose = Number(resolved.chordproTranspose) || 0;
    if (transpose !== 0) {
        song = song.transpose(transpose);
    }

    const fontFamily = resolved.chordproFontFamily;
    const fontSize = Number(resolved.chordproFontSize);
    const paragraphs = song.bodyParagraphs ?? song.paragraphs ?? [];

    const rows = chordproRows(paragraphs, {
        fontSize,
        fontFamily,
        layoutWidth: layoutWidthPx,
        contentHeight: geometry.contentHeightPx,
        measure: canvasMeasurer(fontFamily, fontSize),
    });

    return resolved.chordproGermanNotation
        ? rows.map((row) => ({ ...row, svg: englishChordsToGermanSvg(row.svg) }))
        : rows;
}

/**
 * Cut a rendered document into one standalone SVG per matching line.
 *
 * Measured through getBoundingClientRect rather than getBBox, so nested
 * transforms need no unpicking: the ratio between the root's box on screen and
 * its viewBox converts a line's screen position straight back into user units.
 * The host must therefore be laid out — off-screen is fine, `display: none` is
 * not.
 */
function sliceRenderedSvg(svgMarkup, selector, host) {
    host.innerHTML = svgMarkup;
    const root = host.querySelector('svg');

    if (!root) {
        return [];
    }

    const viewBox = (root.getAttribute('viewBox') ?? '').trim().split(/[\s,]+/).map(Number);
    const lines = Array.from(root.querySelectorAll(selector));
    const rootRect = root.getBoundingClientRect();

    if (lines.length === 0 || viewBox.length !== 4 || rootRect.height === 0) {
        host.innerHTML = '';

        return [{ height: svgHeight(svgMarkup), svg: svgMarkup }];
    }

    const [, viewTop, viewWidth, viewHeight] = viewBox;
    const unitsPerPixel = viewHeight / rootRect.height;
    const preamble = Array.from(root.childNodes)
        .filter((node) => ['defs', 'style'].includes(node.nodeName.toLowerCase()))
        .map((node) => node.outerHTML)
        .join('');

    const blocks = lines.map((line) => {
        const rect = line.getBoundingClientRect();
        const top = viewTop + (rect.top - rootRect.top) * unitsPerPixel;
        const height = Math.max(rect.height * unitsPerPixel, 1);

        return {
            height,
            svg: `<svg xmlns="${SVG_NS}" viewBox="0 ${top} ${viewWidth} ${height}" `
                + `width="${viewWidth}" height="${height}">${preamble}${line.outerHTML}</svg>`,
        };
    });

    host.innerHTML = '';

    return blocks;
}

/**
 * Lay one page's blocks onto a sheet of the booklet's actual paper size.
 *
 * The viewBox states the whole sheet, which is what makes the export come out as
 * real A4 or A5: SvgToPdfConverter restates a viewBox in millimetres, so a page
 * that says it is 559 x 794 units prints at 148 x 210 mm.
 */
function composePage(page, geometry, pageNumber, pageCount) {
    const fragments = [];
    const placements = [];

    page.items.forEach(({ block, y }) => {
        fragments.push(parseSvg(block.svg));
        placements.push({
            x: geometry.marginPx,
            y: geometry.marginPx + y,
            scale: block.scale ?? 1,
        });
    });

    if (pageCount > 1) {
        const number = textRowSvg({
            content: String(pageNumber),
            fontSize: geometry.lyricSizePx * CREDIT_SIZE_FACTOR,
            fontFamily: UI_FONT,
            width: geometry.contentWidthPx,
            fill: '#666666',
        });

        fragments.push(parseSvg(number.svg));
        placements.push({
            x: geometry.pageWidthPx / 2,
            y: geometry.pageHeightPx - geometry.marginPx * 0.6,
            scale: 1,
        });
    }

    const { svg } = stackSvgs(fragments, {
        placements,
        viewBox: { x: 0, y: 0, w: geometry.pageWidthPx, h: geometry.pageHeightPx },
    });

    svg.setAttribute('width', String(geometry.pageWidthPx));
    svg.setAttribute('height', String(geometry.pageHeightPx));

    // Paper, so the page is white rather than transparent wherever it is shown.
    const background = document.createElementNS(SVG_NS, 'rect');
    background.setAttribute('x', '0');
    background.setAttribute('y', '0');
    background.setAttribute('width', String(geometry.pageWidthPx));
    background.setAttribute('height', String(geometry.pageHeightPx));
    background.setAttribute('fill', '#ffffff');
    svg.insertBefore(background, svg.firstChild);

    return svg;
}

/**
 * Serialize the pages for the PDF endpoint, with the fonts embedded — rsvg has
 * no network, so a face that is not in the document is a face that is not
 * printed.
 */
export async function serializeBookletPages(pages, fonts) {
    const serializer = new XMLSerializer();
    const out = [];

    for (const page of pages) {
        const clone = page.cloneNode(true);
        await injectWebFontsIntoSvg(clone, fonts);
        out.push(serializer.serializeToString(clone));
    }

    return out;
}

function formatDefaults(format) {
    if (format === 'gabc') { return gabcMixin(); }
    if (format === 'abc') { return abcMixin(); }
    if (format === 'chordpro') { return chordproMixin(); }
    if (format === 'aretino') { return aretinoMixin(); }

    return {};
}

function fontOf(format, resolved) {
    if (format === 'gabc') { return resolved.lyricFont; }
    if (format === 'abc') { return resolved.abcLyricFont; }
    if (format === 'chordpro') { return resolved.chordproFontFamily; }
    if (format === 'aretino') { return resolved.aretinoTextFont; }

    return UI_FONT;
}

function parseSvg(markup) {
    const doc = new DOMParser().parseFromString(markup, 'image/svg+xml');

    return doc.documentElement;
}

/**
 * How much a block must shrink to sit inside the content box.
 *
 * Never enlarges: a block narrower than the page keeps the scale it was given.
 */
function fitScale(markup, requestedScale, contentWidthPx) {
    const width = svgWidth(markup);

    if (!(width > 0)) {
        return requestedScale;
    }

    return Math.min(requestedScale, contentWidthPx / width);
}

/**
 * The width an SVG fragment declares, in its own user units.
 */
function svgWidth(markup) {
    const viewBox = markup.match(/viewBox="\s*(-?[\d.]+)[\s,]+(-?[\d.]+)[\s,]+([\d.]+)[\s,]+([\d.]+)/);
    if (viewBox) {
        return parseFloat(viewBox[3]);
    }

    const width = markup.match(/\bwidth="([\d.]+)"/);

    return width ? parseFloat(width[1]) : 0;
}

/**
 * The height an SVG fragment declares, in its own user units.
 */
function svgHeight(markup) {
    const viewBox = markup.match(/viewBox="\s*(-?[\d.]+)[\s,]+(-?[\d.]+)[\s,]+([\d.]+)[\s,]+([\d.]+)/);
    if (viewBox) {
        return parseFloat(viewBox[4]);
    }

    const height = markup.match(/\bheight="([\d.]+)"/);

    return height ? parseFloat(height[1]) : 0;
}

function safeAbcFont(value) {
    const raw = (value ?? '').trim().replace(/['"]/g, '');
    const safe = /^[a-zA-Z0-9 .\-'&]+$/.test(raw) ? raw : 'EB Garamond';

    return /[ .\-'&]/.test(safe) ? `"${safe}"` : safe;
}

function germanChordsToEnglish(content) {
    const convert = (note) => (note === 'H' ? 'B' : note === 'B' ? 'Bb' : note);

    return content
        .replace(/\[([^\]]+)\]/g, (_, chord) => '['
            + chord.replace(/^(H|Bb?)/, convert).replace(/\/(H|Bb?)/, (__, n) => '/' + convert(n))
            + ']')
        .replace(/\{key:\s*(H|Bb?)\s*\}/gi, (_, key) => `{key: ${convert(key)}}`);
}

/**
 * Put the German note names back after chordsheetjs has done its English-only
 * transposition, matching what the score editor shows on screen.
 */
function englishChordsToGermanSvg(svg) {
    return svg.replace(/(fill="#1d4ed8"[^>]*>)([^<]*)(<\/text>)/g, (_, open, chord, close) =>
        open + chord.replace(/B(?!b)/g, 'H') + close);
}
