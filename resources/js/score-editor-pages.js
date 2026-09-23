/**
 * Cutting a score's source into the pages one projector ratio asks for.
 *
 * Both halves of one idea. A score is engraved once and shown at several shapes,
 * and the author says where it should break and what should differ at each of
 * them — `%pagebreak169` cuts a slide only in 16:9, `%[169 … %]` makes a line
 * live only there. Everything here is pure text work over the source, done
 * before any engine sees it, which is why it is kept out of the renderers: the
 * editor, and now a projection, ask the same question of the same source.
 */

const CONDITIONAL_BLOCK_RATIO_SUFFIXES = { '16/9': '169', '4/3': '43', '1/1': '11' };
const KNOWN_CONDITIONAL_SUFFIXES = new Set(Object.values(CONDITIONAL_BLOCK_RATIO_SUFFIXES));

/**
 * Where a format's header ends — everything above it is repeated on every page.
 *
 * ABC states its key last and the music follows; the other two close their
 * header with a bare `%%`. ChordPro has no header at all: its directives travel
 * with the words they belong to, so a page carries whatever it was given.
 */
export const HEADER_END = {
    gabc: /^%%\s*$/,
    aretino: /^%%\s*$/,
    abc: /^K:/,
};

/**
 * A page break written into a source, on a line of its own.
 *
 * Group 1 is the ratio it belongs to, empty for every ratio; group 2 is the `?`
 * that makes it a suggestion rather than an instruction — a break taken only
 * where what it sits in would otherwise overflow.
 *
 * A suggestion is honoured wherever a slide is cut into the screens it needs:
 * screens of words (see packSoftPages in soft-pages.js), chord sheets, and —
 * since each of the three engines can be made to hand its music over one staff
 * system at a time — the engraved formats too (see slide-systems.js). Anything
 * that is not a slide strips it: an incipit, an export, a booklet.
 */
export const PAGE_BREAK = /^\s*%pagebreak(\d*)(\?)?\s*$/;

/**
 * A suggestion, as it is written into a page this module hands on.
 *
 * splitPages resolves the ratio suffixes itself, so a renderer downstream never
 * has to ask which shape it is drawing: what reaches it is this line or nothing.
 */
export const SOFT_PAGE_BREAK = '%pagebreak?';

/** The digits a ratio's own breaks and blocks are numbered with, if any. */
export function ratioSuffix(ratio) {
    return CONDITIONAL_BLOCK_RATIO_SUFFIXES[ratio] ?? null;
}

/**
 * Make the blocks written for this ratio live, and leave the rest inert.
 *
 * A block that is not activated is left exactly as it was written, because in
 * the three engraved formats `%` opens a comment and an untouched block is
 * already invisible. Activating one blanks its delimiters to spaces rather than
 * cutting them out, so the source keeps its length: the Aretino editor maps
 * clicks on the preview back to offsets in the text, and a block that shortened
 * the source would move every note after it.
 *
 * ChordPro is the exception, and has to be. Its comment character is `#`, so a
 * block meant for another ratio is not inert there — it is a line of lyrics
 * reading `%[43`. Those are removed outright. Nothing maps ChordPro clicks back
 * to offsets, so there is nothing for the shortening to break.
 *
 * @param {string} content
 * @param {string} ratio
 * @param {string} [format] the format the source is in, where it matters
 */
export function applyConditionalBlocks(content, ratio, format) {
    const targetSuffix = CONDITIONAL_BLOCK_RATIO_SUFFIXES[ratio];

    return content.replace(/%\[(\S+)(\s)([\s\S]*?)%\]/g, (match, condition, sep, inner) => {
        if (!KNOWN_CONDITIONAL_SUFFIXES.has(condition)) { return match; }

        if (condition !== targetSuffix) {
            return format === 'chordpro' ? '' : match;
        }

        // Replace %[ and condition with spaces, keep sep (preserves newlines),
        // keep inner unchanged, replace %] with spaces — total char count identical.
        return ' '.repeat(2 + condition.length) + sep + inner + '  ';
    });
}

/**
 * The pages one score comes to at one ratio, each a complete source of its own.
 *
 * A fixed ratio cuts at `%pagebreak` and at the break numbered for it, and
 * nowhere else; a break numbered for another ratio is dropped rather than left
 * behind, since it would otherwise be read as a comment on a page it does not
 * belong to. A suggested break — `%pagebreak?` — cuts nothing here, because
 * whether it is taken is not known until the page has been laid out. Where the
 * caller is going to lay the page out as slides it is left in the page, spelled
 * SOFT_PAGE_BREAK whatever suffix it was written with; everywhere else it is
 * stripped, since an engine handed one would draw it. ChordPro always keeps it,
 * because a chord sheet at a fixed ratio is only ever drawn as slides. Paper and
 * responsive have no pages at all — every break is stripped and the score comes
 * back whole, which is what `auto` has always meant.
 *
 * The header is re-prefixed onto each page, so a page can be handed to an engine
 * on its own without knowing it was ever part of anything larger.
 *
 * @param {string} content the score's source
 * @param {string} format gabc | abc | aretino | chordpro
 * @param {string} ratio 16/9 | 4/3 | 1/1 — anything else is one whole page
 * @param {boolean} [keepSoft] leave the suggestions in, for a slide renderer
 * @return {string[]} one source per page, never empty
 */
export function splitPages(content, format, ratio, keepSoft = format === 'chordpro') {
    const targetSuffix = CONDITIONAL_BLOCK_RATIO_SUFFIXES[ratio];
    const body = applyConditionalBlocks(content, ratio, format);
    const lines = body.split('\n');

    const headerEnd = headerEndIndex(lines, format);
    const header = headerEnd >= 0 ? lines.slice(0, headerEnd + 1).join('\n') + '\n' : '';
    const bodyLines = headerEnd >= 0 ? lines.slice(headerEnd + 1) : lines.slice();

    if (!targetSuffix) {
        return [header + bodyLines.filter(line => !PAGE_BREAK.test(line)).join('\n')];
    }

    const pages = [];
    let current = [];

    for (const line of bodyLines) {
        const match = line.match(PAGE_BREAK);

        if (match) {
            const suffix = match[1];
            const mine = suffix === '' || suffix === targetSuffix;

            if (mine && match[2] !== '?') {
                pages.push(current.join('\n'));
                current = [];
            } else if (mine && keepSoft) {
                current.push(SOFT_PAGE_BREAK);
            }

            continue;
        }

        current.push(line);
    }

    pages.push(current.join('\n'));

    return pages.map(page => header + page);
}

/**
 * One page's source, cut at the suggestions splitPages left standing in it.
 *
 * The pieces a soft break offers, in order, whether or not any of them is
 * taken: the renderer lays each one out and then decides how few of the offered
 * cuts it has to spend. A page carrying no suggestion comes back as itself.
 *
 * @param {string} pageSource one entry from splitPages
 * @return {string[]} never empty
 */
export function splitSoftSegments(pageSource) {
    const segments = [[]];

    for (const line of String(pageSource ?? '').split('\n')) {
        const match = line.match(PAGE_BREAK);

        if (match && match[2] === '?') {
            segments.push([]);

            continue;
        }

        segments[segments.length - 1].push(line);
    }

    return segments.map((segment) => segment.join('\n'));
}

/**
 * Where one page's suggestions would cut it, as ranges of its own text.
 *
 * Ranges rather than strings, so the ABC editor can cut a source that still
 * knows where every character of it came from (see abc-source-map.js) by the
 * very same answer. Two readings of the page come back:
 *
 * - `whole` is the page with its suggestions taken out, which is what is
 *   engraved first: a page that fits is one slide and its suggestions go unused.
 * - `segments` are the pieces the suggestions offer. Every piece after the first
 *   is given the page's header again, as splitPages does for a page of its own,
 *   so an engine can be handed one without knowing it was ever part of more.
 *
 * @param {string} text one page from splitPages, suggestions left in
 * @param {string} format
 * @return {{whole: Array<[number, number]>, segments: Array<Array<[number, number]>>}}
 */
export function softSegmentRanges(text, format) {
    const source = String(text ?? '');
    const lines = source.split('\n');
    const starts = [];
    let offset = 0;

    for (const line of lines) {
        starts.push(offset);
        offset += line.length + 1;
    }

    const end = source.length;
    const headerEnd = headerEndIndex(lines, format);
    const header = headerEnd >= 0 ? [0, Math.min(end, starts[headerEnd] + lines[headerEnd].length + 1)] : null;

    const bodies = [];
    let start = 0;

    lines.forEach((line, i) => {
        if (i <= headerEnd) { return; }

        const match = line.match(PAGE_BREAK);

        if (match && match[2] === '?') {
            bodies.push([start, starts[i]]);
            start = Math.min(end, starts[i] + line.length + 1);
        }
    });

    bodies.push([start, end]);

    return {
        whole: bodies.filter(([from, to]) => to > from),
        segments: bodies.map((range, i) => (i === 0 || header === null ? [range] : [header, range])),
    };
}

/** The same, as the source strings themselves. */
export function softSegmentSources(text, format) {
    const source = String(text ?? '');
    const pick = (ranges) => ranges.map(([from, to]) => source.slice(from, to)).join('');
    const { whole, segments } = softSegmentRanges(source, format);

    return { whole: pick(whole), segments: segments.map(pick) };
}

export function headerEndIndex(lines, format) {
    const pattern = HEADER_END[format];

    if (!pattern) { return -1; }

    for (let i = 0; i < lines.length; i++) {
        if (pattern.test(lines[i])) { return i; }
    }

    return -1;
}
