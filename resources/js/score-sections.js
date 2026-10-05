import { HEADER_END, headerEndIndex } from './score-editor-pages.js';

/**
 * Cutting a score into the parts its author marked, and letting a booklet or
 * slide-deck row choose which of them it prints — see plans/score-sections.md.
 *
 * A `%section` line starts a part and runs to the next one, or to the end of
 * the source. Parts are numbered by where they stand, 1, 2, 3 — the label
 * after the marker is only a name for people to read. `%` already opens a
 * comment in ABC, GABC and Aretino, so a marker is invisible to those three
 * renderers; ChordPro has no comment character that would hide it, so its
 * marker lines are always stripped before anything is laid out.
 *
 * ChordPro is cut differently, because its author already cuts it: a blank line
 * starts a new verse, and a lead sheet is written verse by verse. So there every
 * paragraph that carries something sung is a section of its own, marked or not,
 * and a `%section` line only names the paragraph standing below it. A text label
 * such as "Verse 1:" is a line of lyrics like any other and names nothing.
 * Mirrored by App\Support\ScoreSections, which lists the same parts.
 */

export const SECTION_MARKER = /^\s*%section(?:\s+(.+?))?\s*$/;

/**
 * A score's source, cut at its own `%section` markers.
 *
 * `header` is whatever HEADER_END recognises for this format — the same slice
 * splitPages repeats onto every page. `preamble` is whatever stands between
 * the header and the first marker, belonging to no section. A score with no
 * marker at all comes back with one section: it does not have — everything
 * that is not the header is the preamble, and `sections` is empty.
 *
 * @param {string} content
 * @param {string} format gabc | abc | aretino | chordpro
 * @return {{header: string, preamble: string, sections: Array<{n: number, label: string|null, body: string}>}}
 */
export function parseSections(content, format) {
    if (format === 'chordpro') {
        return parseChordproSections(content);
    }

    // A single trailing newline is just how the file ends, not an empty final
    // line — without dropping it, the last section (or the preamble, when
    // there are no markers) would end in a stray blank line no other section
    // has to carry.
    const normalized = String(content ?? '');
    const trimmed = normalized.endsWith('\n') ? normalized.slice(0, -1) : normalized;
    const lines = trimmed.split('\n');
    const headerEnd = headerEndIndex(lines, format);
    const header = headerEnd >= 0 ? lines.slice(0, headerEnd + 1).join('\n') + '\n' : '';
    const bodyLines = headerEnd >= 0 ? lines.slice(headerEnd + 1) : lines.slice();

    const sections = [];
    const preambleLines = [];
    let current = null;

    for (const line of bodyLines) {
        const match = line.match(SECTION_MARKER);

        if (match) {
            current = { n: sections.length + 1, label: match[1] ?? null, body: [] };
            sections.push(current);

            continue;
        }

        (current ? current.body : preambleLines).push(line);
    }

    return {
        header,
        preamble: preambleLines.join('\n'),
        sections: sections.map((section) => ({ ...section, body: section.body.join('\n') })),
    };
}

/**
 * A chord sheet's sections: its paragraphs.
 *
 * Whatever stands before the first sung paragraph — `{title}`, `{key}` and the
 * like — is the preamble every chosen section is printed under. A paragraph of
 * directives or comments alone after that carries nothing to choose on its own,
 * so it travels with the sung paragraph after it, or with the last one when
 * nothing follows: a `{comment: Refrén}` stays above the chorus it announces.
 *
 * @param {string} content
 * @return {{header: string, preamble: string, sections: Array<{n: number, label: string|null, body: string}>}}
 */
function parseChordproSections(content) {
    const normalized = String(content ?? '');
    const lines = (normalized.endsWith('\n') ? normalized.slice(0, -1) : normalized).split('\n');

    const preamble = [];
    const sections = [];
    let paragraph = [];
    let label;
    let carried = [];

    const close = () => {
        if (paragraph.length === 0) { return; }

        if (!paragraph.some(isSungLine)) {
            (sections.length === 0 ? preamble : carried).push(paragraph.join('\n'));
        } else {
            sections.push({
                n: sections.length + 1,
                label: label ?? null,
                body: [...carried, paragraph.join('\n')].join('\n\n'),
            });
            carried = [];
            label = undefined;
        }

        paragraph = [];
    };

    for (const line of lines) {
        const marker = line.match(SECTION_MARKER);

        if (marker) {
            close();
            label = marker[1] ?? null;

            continue;
        }

        if (line.trim() === '') {
            close();

            continue;
        }

        paragraph.push(line);
    }

    close();

    if (carried.length > 0 && sections.length > 0) {
        const last = sections[sections.length - 1];
        last.body = [last.body, ...carried].join('\n\n');
    }

    return { header: '', preamble: preamble.join('\n\n'), sections };
}

/**
 * Whether a line of a chord sheet is sung, as opposed to a directive, a comment
 * or a page break standing between the verses.
 */
function isSungLine(line) {
    const trimmed = line.trim();

    return trimmed !== '' && !/^\{.*\}$/.test(trimmed) && !trimmed.startsWith('#') && !trimmed.startsWith('%');
}

/**
 * A source with every `%section` line taken out, everything else untouched.
 *
 * What a row that has chosen nothing prints: the whole score, exactly as it
 * renders today, minus marker lines that could not have existed before this
 * feature.
 */
export function stripSectionMarkers(content) {
    return String(content ?? '')
        .split('\n')
        .filter((line) => !SECTION_MARKER.test(line))
        .join('\n');
}

/**
 * The source one row prints: the header, the preamble, and its chosen
 * sections in order — a section repeated as many times as it is listed.
 *
 * A `null` or empty reference list is answered with stripSectionMarkers, so
 * an existing row — or a score nobody has marked yet — gets exactly what it
 * gets today.
 *
 * @param {string} content
 * @param {string} format gabc | abc | aretino | chordpro
 * @param {number[]|null} references
 * @param {{separator?: string}} [options] `separator` is joined between two
 *        chosen sections — '%pagebreak' for a slide deck, '' for a booklet.
 * @return {{source: string, missing: number[]}}
 */
export function arrangeSections(content, format, references, { separator = '' } = {}) {
    if (!Array.isArray(references) || references.length === 0) {
        return { source: stripSectionMarkers(content), missing: [] };
    }

    const { header, preamble, sections } = parseSections(content, format);
    const byNumber = new Map(sections.map((section) => [section.n, section]));

    const missing = [];
    const pieces = [];

    for (const reference of references) {
        const section = byNumber.get(reference);

        if (!section) {
            missing.push(reference);

            continue;
        }

        pieces.push(section.body);
    }

    // Two chord-sheet paragraphs joined by a single newline would be one verse.
    const join = separator === '' ? (format === 'chordpro' ? '\n\n' : '\n') : `\n${separator}\n`;

    return {
        source: header + (preamble !== '' ? `${preamble}\n` : '') + pieces.join(join),
        missing,
    };
}

export { HEADER_END };
