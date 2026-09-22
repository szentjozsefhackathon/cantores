import { PAGE_BREAK, SOFT_PAGE_BREAK, applyConditionalBlocks, headerEndIndex, splitPages } from './score-editor-pages.js';

/**
 * Where each character of the text abc2svg engraves came from in the editor.
 *
 * The preview never engraves the editor's text as typed: it gets an `X:1`, a
 * `[K:clef=none]`, English chord roots and a page split first. abc2svg reports
 * every symbol's place as an offset into the text it was handed, so each of
 * those steps is done here on a text that carries, per character, the offset in
 * the editor it stands for — `-1` for a character the preview made up. The hit
 * boxes written into the score then point at the editor's own text, and a click
 * lands on the character that produced the note. See plans/abc2svg-source-map.md.
 *
 * @typedef {{text: string, origin: number[]}} MappedSource
 */

/** The editor's own text, every character standing for itself. */
export function trackSource(text) {
    const source = String(text ?? '');

    return { text: source, origin: Array.from(source, (_, index) => index) };
}

/** Text the preview adds that is nowhere in the editor. */
export function unmapped(text) {
    return { text, origin: new Array(text.length).fill(-1) };
}

export function sliceMapped(mapped, start, end = mapped.text.length) {
    return { text: mapped.text.slice(start, end), origin: mapped.origin.slice(start, end) };
}

export function concatMapped(...parts) {
    return {
        text: parts.map((part) => part.text).join(''),
        origin: parts.flatMap((part) => part.origin),
    };
}

/** Puts preview-only text in at `index`. */
export function insertUnmapped(mapped, index, insertion) {
    return concatMapped(sliceMapped(mapped, 0, index), unmapped(insertion), sliceMapped(mapped, index));
}

/**
 * `String.prototype.replace` for a mapped text.
 *
 * What a replacement shares with the text it replaces at either end keeps its
 * origin, so turning `"Bm7"` into `"Bbm7"` moves nothing but the new `b`, which
 * is attributed to the character before it.
 *
 * @param {MappedSource} mapped
 * @param {RegExp} pattern a global pattern
 * @param {(...match: any[]) => string} replacer
 */
export function replaceMapped(mapped, pattern, replacer) {
    const parts = [];
    let cursor = 0;

    for (const match of mapped.text.matchAll(pattern)) {
        const start = match.index;
        const found = match[0];
        const replacement = replacer(...match, start, mapped.text);

        parts.push(sliceMapped(mapped, cursor, start));
        parts.push({ text: replacement, origin: alignedOrigin(found, replacement, mapped.origin.slice(start, start + found.length)) });
        cursor = start + found.length;
    }

    parts.push(sliceMapped(mapped, cursor));

    return concatMapped(...parts);
}

function alignedOrigin(found, replacement, foundOrigin) {
    let prefix = 0;
    while (prefix < found.length && prefix < replacement.length && found[prefix] === replacement[prefix]) {
        prefix++;
    }

    let suffix = 0;
    while (suffix < found.length - prefix && suffix < replacement.length - prefix
        && found[found.length - 1 - suffix] === replacement[replacement.length - 1 - suffix]) {
        suffix++;
    }

    const middleLength = replacement.length - prefix - suffix;
    const foundMiddle = foundOrigin.slice(prefix, found.length - suffix);
    const before = prefix > 0 ? foundOrigin[prefix - 1] : (foundOrigin[0] ?? -1);
    const middle = Array.from({ length: middleLength }, (_, index) => foundMiddle[Math.min(index, foundMiddle.length - 1)] ?? before);

    return [...foundOrigin.slice(0, prefix), ...middle, ...foundOrigin.slice(found.length - suffix)];
}

/** The lines of a mapped text, each keeping the newline that ends it. */
export function mappedLines(mapped) {
    const lines = [];
    let start = 0;

    while (start < mapped.text.length) {
        const newline = mapped.text.indexOf('\n', start);
        const end = newline === -1 ? mapped.text.length : newline + 1;
        lines.push(sliceMapped(mapped, start, end));
        start = end;
    }

    return lines;
}

/**
 * splitPages, for a mapped text.
 *
 * The pages are cut by splitPages itself, so there is one idea of where a page
 * ends; this only finds each page's lines again in the source. A page is its
 * header followed by body lines in source order with the page breaks left out,
 * and conditional blocks keep every line where it was, so the lines can be
 * walked in step.
 *
 * @param {MappedSource} mapped
 * @param {string} format
 * @param {string} ratio
 * @return {MappedSource[]}
 */
export function splitPagesMapped(mapped, format, ratio) {
    const pages = splitPages(mapped.text, format, ratio);
    const sourceLines = applyConditionalBlocks(mapped.text, ratio, format).split('\n');
    const lineStarts = [];
    let offset = 0;
    for (const line of sourceLines) {
        lineStarts.push(offset);
        offset += line.length + 1;
    }

    const headerLength = headerEndIndex(sourceLines, format) + 1;
    let cursor = headerLength;

    const isSourceOf = (sourceLine, line) => sourceLine === line || (line === SOFT_PAGE_BREAK && PAGE_BREAK.test(sourceLine));

    /** A page line's origin, and that of the newline after it. */
    const originOfLine = (index, length) => {
        const start = lineStarts[index];
        const sourceLength = sourceLines[index].length;
        const origin = Array.from({ length }, (_, column) => (column < sourceLength ? mapped.origin[start + column] : -1));

        return [...origin, mapped.origin[start + sourceLength] ?? -1];
    };

    return pages.map((page) => {
        const origin = [];

        page.split('\n').forEach((line, index) => {
            let sourceIndex = index < headerLength ? index : cursor;

            if (index >= headerLength) {
                while (sourceIndex < sourceLines.length && !isSourceOf(sourceLines[sourceIndex], line)
                    && PAGE_BREAK.test(sourceLines[sourceIndex])) {
                    sourceIndex++;
                }
                if (sourceIndex < sourceLines.length && isSourceOf(sourceLines[sourceIndex], line)) {
                    cursor = sourceIndex + 1;
                } else {
                    sourceIndex = -1;
                }
            }

            origin.push(...(sourceIndex >= 0 ? originOfLine(sourceIndex, line.length) : new Array(line.length + 1).fill(-1)));
        });

        origin.pop();

        return { text: page, origin };
    });
}

/**
 * abc2svg's offsets for the syllables of a `w:` line are short by the blanks
 * that follow the `w:` — `w: Glo-ri-a` reports `" Gl"` for `Glo` — so the line's
 * indent is added back.
 */
function lyricIndent(text, index) {
    const lineStart = text.lastIndexOf('\n', index - 1) + 1;
    const match = /^w:([ \t]*)/.exec(text.slice(lineStart, lineStart + 64));

    return match ? match[1].length : 0;
}

/** Drawn over whole groups of notes, a box for these would swallow the clicks meant for the notes. */
const UNBOXED_SYMBOLS = new Set(['beam', 'slur', 'tuplet']);

/**
 * An `anno_stop` hook that writes an invisible hit box over every symbol.
 *
 * The box goes into the image being drawn, through the engraver's own output,
 * so it sits under the same transform as the symbol and follows it through any
 * scale, width or line break. Its `data-start`/`data-stop` are the editor's
 * offsets, end exclusive.
 *
 * @param {() => object} engraver returns the abc2svg.Abc drawing the image
 * @param {MappedSource} mapped exactly the text handed to that `tosvg` call
 */
export function abcHitBoxAnnotator(engraver, mapped) {
    return (type, istart, iend, x, y, w, h) => {
        if (UNBOXED_SYMBOLS.has(type) || !(iend > istart)) { return; }

        const shift = type === 'lyrics' ? lyricIndent(mapped.text, istart) : 0;
        const start = mapped.origin[istart + shift] ?? -1;
        const last = mapped.origin[iend + shift - 1] ?? -1;
        if (start < 0 || last < start) { return; }

        const abc = engraver();
        abc.out_svg(`<rect class="abcsym" data-start="${start}" data-stop="${last + 1}" x="`);
        abc.out_sxsy(x, '" y="', y);
        abc.out_svg(`" width="${w.toFixed(2)}" height="${abc.sh(h).toFixed(2)}"/>\n`);
    };
}

/**
 * The hit box a caret at `index` stands in: the innermost one, since a note sits
 * inside the range of its bar. Every copy of it is returned — a header symbol
 * is drawn once per page.
 *
 * @param {Iterable<Element>} boxes
 * @param {number} index
 * @return {Element[]}
 */
export function hitBoxesAtOffset(boxes, index) {
    let best = null;
    const matches = [];

    for (const box of boxes) {
        const start = Number(box.dataset.start);
        const stop = Number(box.dataset.stop);
        if (start > index || index > stop) { continue; }
        matches.push(box);
        if (!best || start > best.start || (start === best.start && stop < best.stop)) {
            best = { start, stop };
        }
    }

    return best
        ? matches.filter((box) => Number(box.dataset.start) === best.start && Number(box.dataset.stop) === best.stop)
        : [];
}
