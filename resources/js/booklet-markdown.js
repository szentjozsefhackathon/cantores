/**
 * Instructions, written in Markdown and engraved as SVG.
 *
 * A booklet says things it does not sing: stand, sit, the cantor sings the
 * verses, the congregation repeats the antiphon. Those paragraphs have to reach
 * the page the same way the music does — as standalone SVG rows with a height —
 * because a booklet page becomes one SVG document handed to rsvg-convert, and
 * rsvg has no HTML in it.
 *
 * The Markdown understood here is the small part of it that a rubric needs:
 * headings, paragraphs, bullet and numbered lists, block quotes, rules, and
 * bold or italic inside any of them. Anything else is left as the literal text
 * someone typed, which is the friendlier failure for a person writing a rubric
 * rather than a document.
 *
 * Nothing here touches the DOM. Widths arrive through an injected `measure`, so
 * the wrapping can be tested against a known metric instead of a real font.
 */

import { escapeXml, round } from './booklet-text.js';

/** Multiples of the font size. */
const LINE = 1.45;
const BLOCK_GAP = 0.6;
const LIST_INDENT = 1.4;
const QUOTE_INDENT = 1.0;
const RULE_HEIGHT = 0.9;

const HEADING_SCALE = [1.3, 1.15, 1.0, 1.0, 1.0, 1.0];

const QUOTE_COLOR = '#555555';
const RULE_COLOR = '#999999';

/**
 * @typedef {object} MarkdownRow
 * @property {number} height
 * @property {number} spaceBefore
 * @property {boolean} keepWithNext
 * @property {string} svg
 */

/**
 * Lay Markdown out into flowable rows.
 *
 * @param {string} source
 * @param {object} options
 * @param {number} options.fontSize in px
 * @param {string} options.fontFamily
 * @param {number} options.layoutWidth in px
 * @param {(text: string, opts?: {bold?: boolean, italic?: boolean}) => number} options.measure
 * @returns {MarkdownRow[]}
 */
export function markdownRows(source, options) {
    const rows = [];

    parseBlocks(source).forEach((block, blockIndex) => {
        const built = renderBlock(block, options);

        built.forEach((row, i) => {
            rows.push({
                ...row,
                spaceBefore: i === 0 && blockIndex > 0 ? options.fontSize * BLOCK_GAP : 0,
                // A heading belongs to what follows it; the lines of one
                // paragraph do not, so a long rubric may break across pages.
                keepWithNext: i < built.length - 1
                    ? block.type !== 'paragraph'
                    : block.type === 'heading',
            });
        });
    });

    return rows;
}

/**
 * Cut the source into blocks.
 *
 * @returns {Array<{type: string, level?: number, marker?: string, text: string}>}
 */
export function parseBlocks(source) {
    const lines = String(source ?? '').replace(/\r\n?/g, '\n').split('\n');
    const blocks = [];
    let paragraph = [];

    const flush = () => {
        if (paragraph.length > 0) {
            blocks.push({ type: 'paragraph', text: paragraph.join(' ') });
            paragraph = [];
        }
    };

    lines.forEach((raw) => {
        const line = raw.trimEnd();

        if (line.trim() === '') {
            flush();

            return;
        }

        const heading = line.match(/^(#{1,6})\s+(.*)$/);
        if (heading) {
            flush();
            blocks.push({ type: 'heading', level: heading[1].length, text: heading[2].trim() });

            return;
        }

        if (/^\s*([-*_])(\s*\1){2,}\s*$/.test(line)) {
            flush();
            blocks.push({ type: 'rule', text: '' });

            return;
        }

        const bullet = line.match(/^\s*[-*+]\s+(.*)$/);
        if (bullet) {
            flush();
            blocks.push({ type: 'list', marker: '•', text: bullet[1].trim() });

            return;
        }

        const ordered = line.match(/^\s*(\d+)[.)]\s+(.*)$/);
        if (ordered) {
            flush();
            blocks.push({ type: 'list', marker: `${ordered[1]}.`, text: ordered[2].trim() });

            return;
        }

        const quote = line.match(/^\s*>\s?(.*)$/);
        if (quote) {
            flush();
            blocks.push({ type: 'quote', text: quote[1].trim() });

            return;
        }

        paragraph.push(line.trim());
    });

    flush();

    return blocks;
}

function renderBlock(block, options) {
    const { fontSize, fontFamily, layoutWidth } = options;

    if (block.type === 'rule') {
        const height = fontSize * RULE_HEIGHT;
        const y = round(height / 2);
        const body = `<line x1="0" y1="${y}" x2="${round(layoutWidth)}" y2="${y}" `
            + `stroke="${RULE_COLOR}" stroke-width="${round(Math.max(fontSize / 14, 0.5))}"/>`;

        return [{ height, svg: svgDocument(body, layoutWidth, height) }];
    }

    const size = block.type === 'heading'
        ? fontSize * (HEADING_SCALE[block.level - 1] ?? 1)
        : fontSize;

    const style = {
        bold: block.type === 'heading',
        italic: block.type === 'quote',
        fill: block.type === 'quote' ? QUOTE_COLOR : '#000000',
    };

    const indent = block.type === 'list'
        ? fontSize * LIST_INDENT
        : block.type === 'quote' ? fontSize * QUOTE_INDENT : 0;

    const measure = (text, opts = {}) => options.measure(text, {
        bold: opts.bold ?? style.bold,
        italic: opts.italic ?? style.italic,
        fontSize: size,
    });

    const words = inlineWords(block.text, style);
    const lines = wrapWords(words, Math.max(layoutWidth - indent, size), measure);
    const lineHeight = size * LINE;

    return lines.map((line, i) => {
        const parts = [];

        if (block.type === 'list' && i === 0) {
            parts.push(textElement(block.marker, 0, lineHeight * 0.78, {
                fontFamily,
                fontSize: size,
                fill: style.fill,
            }));
        }

        runsOf(line, measure).forEach((run) => {
            parts.push(textElement(run.text, indent + run.x, lineHeight * 0.78, {
                fontFamily,
                fontSize: size,
                fill: style.fill,
                bold: run.bold,
                italic: run.italic,
            }));
        });

        return {
            height: lineHeight,
            svg: svgDocument(parts.join(''), layoutWidth, lineHeight),
        };
    });
}

/**
 * The words of one block, each carrying the emphasis it was written with.
 *
 * @returns {Array<{text: string, bold: boolean, italic: boolean}>}
 */
export function inlineWords(text, style = {}) {
    const words = [];

    inlineSegments(text, style).forEach((segment) => {
        segment.text.split(/\s+/).forEach((word) => {
            if (word !== '') {
                words.push({ text: word, bold: segment.bold, italic: segment.italic });
            }
        });
    });

    return words;
}

/**
 * Split a line on its emphasis markers.
 *
 * A marker that never closes is left standing as the character someone typed,
 * because a lone asterisk in a rubric is far likelier to be an asterisk than a
 * mistake.
 *
 * @returns {Array<{text: string, bold: boolean, italic: boolean}>}
 */
export function inlineSegments(text, style = {}) {
    const baseBold = !!style.bold;
    const baseItalic = !!style.italic;
    const source = String(text ?? '');
    const segments = [];

    let bold = false;
    let italic = false;
    let buffer = '';
    let i = 0;

    const push = () => {
        if (buffer !== '') {
            segments.push({ text: buffer, bold: baseBold || bold, italic: baseItalic || italic });
            buffer = '';
        }
    };

    /** A marker already open is always the one that closes it. */
    const isOpen = (marker) => marker.length === 3
        ? bold && italic
        : marker.length === 2 ? bold : italic;

    while (i < source.length) {
        const rest = source.slice(i);
        const marker = rest.match(/^(\*\*\*|___|\*\*|__|\*|_)/);

        if (marker && (isOpen(marker[1]) || closes(source, i, marker[1]))) {
            push();

            if (marker[1].length === 3) {
                bold = !bold;
                italic = !italic;
            } else if (marker[1].length === 2) {
                bold = !bold;
            } else {
                italic = !italic;
            }

            i += marker[1].length;

            continue;
        }

        buffer += source[i];
        i += 1;
    }

    push();

    return segments;
}

/**
 * Whether a marker has a partner later in the line.
 */
function closes(source, index, marker) {
    return source.indexOf(marker, index + marker.length) !== -1;
}

/**
 * Greedy wrapping. A word longer than the line gets a line of its own and
 * overflows, which is visible and readable; hyphenating it would be neither.
 *
 * @returns {Array<Array<{text: string, bold: boolean, italic: boolean}>>}
 */
export function wrapWords(words, width, measure) {
    const lines = [];
    let line = [];
    let used = 0;

    words.forEach((word) => {
        const wordWidth = measure(word.text, word);
        const spaceWidth = line.length === 0 ? 0 : measure(' ', word);

        if (line.length > 0 && used + spaceWidth + wordWidth > width) {
            lines.push(line);
            line = [word];
            used = wordWidth;

            return;
        }

        line.push(word);
        used += spaceWidth + wordWidth;
    });

    if (line.length > 0) {
        lines.push(line);
    }

    return lines.length > 0 ? lines : [[]];
}

/**
 * Adjacent words of the same emphasis are drawn as one text element, spaces and
 * all, so a wrapped paragraph is a handful of elements rather than one per word.
 */
function runsOf(words, measure) {
    const runs = [];
    let x = 0;

    words.forEach((word, i) => {
        const previous = runs[runs.length - 1];
        const space = i === 0 ? '' : ' ';

        if (previous && previous.bold === word.bold && previous.italic === word.italic) {
            previous.text += space + word.text;
            x += measure(space + word.text, word);

            return;
        }

        x += measure(space, word);
        runs.push({ text: word.text, bold: word.bold, italic: word.italic, x });
        x += measure(word.text, word);
    });

    return runs;
}

function textElement(content, x, y, { fontFamily, fontSize, fill = '#000000', bold = false, italic = false }) {
    const weight = bold ? ' font-weight="bold"' : '';
    const style = italic ? ' font-style="italic"' : '';

    return `<text x="${round(x)}" y="${round(y)}" font-family="${escapeXml(fontFamily)}" `
        + `font-size="${round(fontSize)}" fill="${fill}"${weight}${style} `
        + `xml:space="preserve">${escapeXml(content)}</text>`;
}

function svgDocument(body, width, height) {
    const w = round(Math.max(width, 1));

    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${w} ${round(height)}" `
        + `width="${w}" height="${round(height)}">${body}</svg>`;
}
