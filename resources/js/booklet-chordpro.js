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
 * Inline markup — `<i>`, `<b>`, `<u>`, `<sup>`, `<sub>` — is honoured wherever
 * ChordPro allows it, by cutting a lyric into styled runs and drawing each one
 * at its own measured position and size; see chordpro-markup.js.
 *
 * Nothing here touches the DOM. Widths arrive through an injected `measure`, so
 * the layout can be tested against a known metric instead of a real font.
 */

import { packColumns, packPages } from './booklet-flow.js';
import { chordDisplayRuns } from './chordpro-notation.js';
import { escapeXml, round } from './booklet-text.js';
import { markupRuns, measureRuns, runBaselineShift, runFont, runsText, sliceRuns } from './chordpro-markup.js';

const CHORD_COLOR = '#1d4ed8';
const LABEL_COLOR = '#555555';

/** Multiples of the font size. Chords sit tighter than lyrics by convention. */
const LYRIC_LINE = 1.35;
const CHORD_LINE = 1.25;
const LABEL_LINE = 1.5;
const PARAGRAPH_GAP = 0.9;

/** Breathing room after a chord, so neighbouring chords never touch. */
const CHORD_GAP = 0.4;

/** Lay out booklet columns as page-sized SVG blocks in reading order. */
export function chordproBookletBlocks(paragraphs, options) {
    const count = Math.min(4, Math.max(1, Math.round(Number(options.columns) || 1)));
    if (count === 1) {
        return chordproRows(paragraphs, options);
    }

    const gap = options.fontSize * 2;
    const columnWidth = (options.layoutWidth - gap * (count - 1)) / count;
    const contentHeight = options.contentHeight ?? Infinity;
    const rows = chordproRows(paragraphs, { ...options, layoutWidth: columnWidth });
    const columns = packPages(rows, contentHeight);
    const blocks = [];

    for (let start = 0; start < columns.length; start += count) {
        const pageColumns = columns.slice(start, start + count);
        const pageRows = pageColumns.flatMap((column) => column.items.map(({ block }) => block));
        const balanced = packColumns(pageRows, count);
        const placed = balanced.every((column) => column.height <= contentHeight) ? balanced : pageColumns;
        const height = Math.max(...placed.map((column) => column.height));
        const body = placed.map((column, index) => column.items.map(({ block, y }) =>
            `<g transform="translate(${round(index * (columnWidth + gap))} ${round(y)})">${block.svg}</g>`,
        ).join('')).join('');

        blocks.push({
            height,
            spaceBefore: pageRows[0]?.spaceBefore ?? 0,
            keepWithNext: false,
            svg: svgDocument(body, options.layoutWidth, height),
        });
    }

    return blocks;
}

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
 * @param {(chord: string) => string} [options.spell] respells a rendered chord,
 *        for the notations chordsheetjs has no setting for
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

            wrapColumns(columns, layoutWidth, options).forEach((rowColumns) => {
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
    const { spell = (chord) => chord } = options;

    // An open `<i>` runs on into the columns that follow it: chordsheetjs cuts
    // the line at every chord, and formatting written across a chord arrives
    // split in two.
    let style;

    return (line.items ?? [])
        .filter((item) => typeof item?.chords === 'string' || typeof item?.lyrics === 'string')
        .map((item) => {
            const marked = markupRuns(item.lyrics ?? '', style);
            style = marked.open;

            return sizedColumn(spell((item.chords ?? '').trim()), marked.runs, options);
        })
        .filter((column) => column.chord !== '' || column.lyric !== '');
}

/**
 * One chord and the syllables under it, measured.
 *
 * The width is the wider of the two: a chord narrower than its word costs
 * nothing, and one wider pushes the next column along so the two never touch.
 * The syllables arrive as styled runs, each measured in the face it will be
 * drawn in, so an italic word takes the room italics actually need.
 */
function sizedColumn(chord, runs, { measure, fontSize }) {
    const chordWidth = chord === '' ? 0 : measureRuns(chordDisplayRuns(chord), measure, fontSize) + fontSize * CHORD_GAP;

    return {
        chord,
        runs,
        lyric: runsText(runs),
        width: Math.max(measureRuns(runs, measure, fontSize), chordWidth),
    };
}

/**
 * Break a line's columns into rows no wider than the page.
 *
 * A column is one chord and the syllables sung under it, so it is kept whole
 * wherever it can be: moving half of it to the next line would put the chord
 * over the wrong word. Where it cannot be — chordsheetjs hands the whole tail of
 * a line back as one column when no further chord interrupts it, and that tail
 * is routinely wider than a page — the column is split between its words. The
 * chord stays with the first piece, which is where it was already standing.
 *
 * Without this a long chordless run left a row wider than the content box, and
 * the renderer's fit-to-width then scaled that row — and only that row — down:
 * a booklet set at 10.5 pt printed those lines a point or so smaller.
 */
function wrapColumns(columns, layoutWidth, options) {
    const rows = [];
    let row = [];
    let width = 0;

    const flush = () => {
        if (row.length > 0) {
            rows.push(row);
            row = [];
            width = 0;
        }
    };

    const place = (column) => {
        row.push(column);
        width += column.width;
    };

    columns.forEach((column) => {
        let rest = column;

        while (rest !== null) {
            if (rest.width <= layoutWidth - width) {
                place(rest);
                rest = null;

                continue;
            }

            // It fits on a row of its own: start one rather than break it.
            if (row.length > 0 && rest.width <= layoutWidth) {
                flush();

                continue;
            }

            const [head, tail] = splitColumn(rest, layoutWidth - width, options);

            if (head === null) {
                // Not one word of it fits in what is left. On a fresh row that
                // means a single word wider than the page, which nothing here
                // can help; otherwise the next row has more room to offer.
                if (row.length === 0) {
                    place(rest);
                    rest = null;
                } else {
                    flush();
                }

                continue;
            }

            place(head);
            flush();
            rest = tail;
        }
    });

    flush();

    return rows;
}

/**
 * Cut a column after the last whole word that fits in `room`.
 *
 * The trailing space stays with the word before it, the way chordsheetjs hands
 * lyrics over, so the pieces still join back into the line as sung.
 *
 * @returns {[object|null, object|null]} the piece that fits and what is left of
 *          it; a null head means not even the first word did.
 */
function splitColumn(column, room, options) {
    const words = column.lyric.match(/\S+\s*/g) ?? [];

    if (words.length < 2) {
        return [null, column];
    }

    const { measure } = options;
    let taken = 0;

    for (let i = 1; i <= words.length; i += 1) {
        if (measure(words.slice(0, i).join('')) > room) {
            break;
        }

        taken = i;
    }

    if (taken === 0 || taken === words.length) {
        return taken === 0 ? [null, column] : [column, null];
    }

    const cut = words.slice(0, taken).join('').length;

    return [
        sizedColumn(column.chord, sliceRuns(column.runs, 0, cut), options),
        sizedColumn('', sliceRuns(column.runs, cut), options),
    ];
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
            let chordX = x;
            chordDisplayRuns(column.chord).forEach((run) => {
                const font = runFont(run, fontSize);
                parts.push(text(run.text, chordX, chordHeight * 0.8 + runBaselineShift(run, fontSize), {
                    fontFamily,
                    fontSize: font.fontSize,
                    fill: CHORD_COLOR,
                    bold: true,
                }));
                chordX += options.measure(run.text, font);
            });
        }

        let lyricX = x;
        const baseline = chordHeight + lyricHeight * 0.78;

        column.runs.forEach((run) => {
            if (run.text === '') {
                return;
            }

            const font = runFont(run, fontSize);

            parts.push(text(run.text, lyricX, baseline + runBaselineShift(run, fontSize), {
                fontFamily,
                fontSize: font.fontSize,
                bold: run.bold,
                italic: run.italic,
                underline: run.underline,
            }));

            lyricX += options.measure(run.text, font);
        });

        x += column.width;
    });

    return { height, svg: svgDocument(parts.join(''), Math.max(width, 1), height) };
}

/**
 * A section label or a `{comment}`, set apart from the words that are sung.
 *
 * Bold italic to begin with, which is also the style the label's own markup
 * starts from: a `</i>` inside one takes the italic away again, and an `<i>`
 * inside one changes nothing, both of which read the way they are written.
 */
function labelRow(label, options) {
    const { fontSize, fontFamily, layoutWidth, measure } = options;
    const height = fontSize * LABEL_LINE;
    const { runs } = markupRuns(label, { bold: true, italic: true });

    const parts = [];
    let x = 0;

    runs.forEach((run) => {
        if (run.text === '') {
            return;
        }

        const font = runFont(run, fontSize);

        parts.push(text(run.text, x, height * 0.75 + runBaselineShift(run, fontSize), {
            fontFamily,
            fontSize: font.fontSize,
            fill: LABEL_COLOR,
            bold: run.bold,
            italic: run.italic,
            underline: run.underline,
        }));

        x += measure(run.text, font);
    });

    return { height, svg: svgDocument(parts.join(''), Math.max(layoutWidth, 1), height) };
}

/**
 * A `{comment}` line, which ChordPro uses for performance notes.
 */
function commentOf(line) {
    const tag = (line.items ?? []).find((item) => item?.name === 'comment' && item?.value);

    return tag ? String(tag.value) : null;
}

function text(content, x, y, { fontFamily, fontSize, fill = '#000000', bold = false, italic = false, underline = false }) {
    const weight = bold ? ' font-weight="bold"' : '';
    const style = italic ? ' font-style="italic"' : '';
    const rule = underline ? ' text-decoration="underline"' : '';

    return `<text x="${round(x)}" y="${round(y)}" font-family="${escapeXml(fontFamily)}" `
        + `font-size="${round(fontSize)}" fill="${fill}"${weight}${style}${rule} `
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

    return (content, { bold = false, italic = false, fontSize: size = fontSize } = {}) => {
        context.font = `${italic ? 'italic ' : ''}${bold ? 'bold ' : ''}${size}px ${fontFamily}`;

        return context.measureText(content ?? '').width;
    };
}
