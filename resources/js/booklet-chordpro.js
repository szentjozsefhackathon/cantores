/**
 * ChordPro, drawn as SVG.
 *
 * The score editor renders ChordPro to HTML, which is right for the screen and
 * useless here: a booklet page becomes one SVG document handed to rsvg-convert,
 * and rsvg has no HTML in it. Rather than bolt a second, browser-driven PDF path
 * onto the export for one format, ChordPro is engraved the way the other three
 * already are — as text at measured positions.
 *
 * The bonus is that a lyrics-only sheet then flows like everything else: a row
 * of chords over lyrics is a block with a height, so a short hymn shares a page
 * with a Gregorian antiphon without either knowing about the other.
 *
 * Nothing here touches the DOM. Widths arrive through an injected `measure`, so
 * the layout can be tested against a known metric instead of a real font.
 */

import { escapeXml, round, textRowSvg } from './booklet-text.js';

const CHORD_COLOR = '#1d4ed8';

/** Multiples of the font size. Chords sit tighter than lyrics by convention. */
const LYRIC_LINE = 1.35;
const CHORD_LINE = 1.25;
const LABEL_LINE = 1.5;
const PARAGRAPH_GAP = 0.9;

/** Breathing room after a chord, so neighbouring chords never touch. */
const CHORD_GAP = 0.4;

/**
 * Lay a parsed ChordPro song out into flowable rows.
 *
 * @param {Array<{lines: Array<{items: Array}>, label?: string|null}>} paragraphs
 *        chordsheetjs Paragraphs, or anything shaped like them.
 * @param {object} options
 * @param {number} options.fontSize in px
 * @param {string} options.fontFamily
 * @param {number} options.layoutWidth in px
 * @param {(text: string, opts?: {bold?: boolean}) => number} options.measure
 * @param {number} [options.contentHeight] page height, to decide whether a
 *        paragraph is short enough to be kept whole
 * @returns {Array<{height: number, spaceBefore: number, keepWithNext: boolean, svg: string}>}
 */
export function chordproRows(paragraphs, options) {
    const { fontSize, layoutWidth, contentHeight = Infinity } = options;
    const blocks = [];

    paragraphs.forEach((paragraph, paragraphIndex) => {
        const rows = [];

        const label = paragraph.label ?? null;
        if (label) {
            rows.push(labelRow(label, options));
        }

        (paragraph.lines ?? []).forEach((line) => {
            const comment = commentOf(line);
            if (comment !== null) {
                rows.push(labelRow(comment, options));

                return;
            }

            const columns = columnsOf(line, options);
            if (columns.length === 0) {
                return;
            }

            wrapColumns(columns, layoutWidth).forEach((rowColumns) => {
                rows.push(chordLyricRow(rowColumns, options));
            });
        });

        if (rows.length === 0) {
            return;
        }

        const height = rows.reduce((total, row) => total + row.height, 0);

        // A verse that fits on a page is kept whole; one that cannot is left
        // free to break, because gluing it would only push it onto a page it
        // still overflows.
        const keepWhole = height <= contentHeight;

        rows.forEach((row, i) => {
            blocks.push({
                ...row,
                spaceBefore: i === 0 && paragraphIndex > 0 ? fontSize * PARAGRAPH_GAP : 0,
                keepWithNext: keepWhole && i < rows.length - 1,
            });
        });
    });

    return blocks;
}

/**
 * The renderable chord/lyric columns of one line.
 */
function columnsOf(line, options) {
    const { measure, fontSize } = options;

    return (line.items ?? [])
        .filter((item) => typeof item?.chords === 'string' || typeof item?.lyrics === 'string')
        .map((item) => {
            const chord = (item.chords ?? '').trim();
            const lyric = item.lyrics ?? '';
            const chordWidth = chord === '' ? 0 : measure(chord, { bold: true }) + fontSize * CHORD_GAP;

            return { chord, lyric, width: Math.max(measure(lyric), chordWidth) };
        })
        .filter((column) => column.chord !== '' || column.lyric !== '');
}

/**
 * Break a line's columns into rows no wider than the page.
 *
 * A column is never split: it is one chord and the syllables sung under it, and
 * moving half of it to the next line would put the chord over the wrong word.
 */
function wrapColumns(columns, layoutWidth) {
    const rows = [];
    let row = [];
    let width = 0;

    columns.forEach((column) => {
        if (row.length > 0 && width + column.width > layoutWidth) {
            rows.push(row);
            row = [];
            width = 0;
        }

        row.push(column);
        width += column.width;
    });

    if (row.length > 0) {
        rows.push(row);
    }

    return rows;
}

function chordLyricRow(columns, options) {
    const { fontSize, fontFamily } = options;

    const hasChords = columns.some((column) => column.chord !== '');
    const chordHeight = hasChords ? fontSize * CHORD_LINE : 0;
    const lyricHeight = fontSize * LYRIC_LINE;
    const height = chordHeight + lyricHeight;
    const width = columns.reduce((total, column) => total + column.width, 0);

    const parts = [];
    let x = 0;

    columns.forEach((column) => {
        if (column.chord !== '') {
            parts.push(text(column.chord, x, chordHeight * 0.8, {
                fontFamily,
                fontSize,
                fill: CHORD_COLOR,
                bold: true,
            }));
        }

        if (column.lyric !== '') {
            parts.push(text(column.lyric, x, chordHeight + lyricHeight * 0.78, {
                fontFamily,
                fontSize,
            }));
        }

        x += column.width;
    });

    return { height, svg: svgDocument(parts.join(''), Math.max(width, 1), height) };
}

function labelRow(label, options) {
    return textRowSvg({
        content: label,
        fontSize: options.fontSize,
        fontFamily: options.fontFamily,
        width: options.layoutWidth,
        bold: true,
        italic: true,
        fill: '#555555',
        lineHeight: LABEL_LINE,
    });
}

/**
 * A `{comment}` line, which ChordPro uses for performance notes.
 */
function commentOf(line) {
    const tag = (line.items ?? []).find((item) => item?.name === 'comment' && item?.value);

    return tag ? String(tag.value) : null;
}

function text(content, x, y, { fontFamily, fontSize, fill = '#000000', bold = false, italic = false }) {
    const weight = bold ? ' font-weight="bold"' : '';
    const style = italic ? ' font-style="italic"' : '';

    return `<text x="${round(x)}" y="${round(y)}" font-family="${escapeXml(fontFamily)}" `
        + `font-size="${round(fontSize)}" fill="${fill}"${weight}${style} `
        + `xml:space="preserve">${escapeXml(content)}</text>`;
}

function svgDocument(body, width, height) {
    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${round(width)} ${round(height)}" `
        + `width="${round(width)}" height="${round(height)}">${body}</svg>`;
}

/**
 * A text measurer backed by a canvas, for the browser.
 *
 * The same font string the SVG will carry, so what is measured is what is drawn.
 */
export function canvasMeasurer(fontFamily, fontSize) {
    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d');

    return (content, { bold = false } = {}) => {
        context.font = `${bold ? 'bold ' : ''}${fontSize}px ${fontFamily}`;

        return context.measureText(content ?? '').width;
    };
}
