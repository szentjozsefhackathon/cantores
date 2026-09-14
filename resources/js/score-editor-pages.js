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
const HEADER_END = {
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
 * A suggestion is honoured by whatever is laid out here and therefore has a
 * height known before anything is drawn: screens of words (see packSoftPages in
 * soft-pages.js) and chord sheets, which are words with chords standing over
 * them and are engraved a row at a time by booklet-chordpro.js. The three
 * engraved formats strip one, because deciding whether a staff overflowed is an
 * answer each of their engines gives differently.
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
 * whether it is taken is not known until the page has been laid out: for
 * ChordPro it is left in the page, spelled SOFT_PAGE_BREAK whatever suffix it
 * was written with, and the other three strip it (see PAGE_BREAK). Paper and
 * responsive have no pages at all — every break is stripped and the score comes
 * back whole, which is what `auto` has always meant.
 *
 * The header is re-prefixed onto each page, so a page can be handed to an engine
 * on its own without knowing it was ever part of anything larger.
 *
 * @param {string} content the score's source
 * @param {string} format gabc | abc | aretino | chordpro
 * @param {string} ratio 16/9 | 4/3 | 1/1 — anything else is one whole page
 * @return {string[]} one source per page, never empty
 */
export function splitPages(content, format, ratio) {
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
            } else if (mine && format === 'chordpro') {
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

function headerEndIndex(lines, format) {
    const pattern = HEADER_END[format];

    if (!pattern) { return -1; }

    for (let i = 0; i < lines.length; i++) {
        if (pattern.test(lines[i])) { return i; }
    }

    return -1;
}
