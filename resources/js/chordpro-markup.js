/**
 * Inline text markup, the way ChordPro writes it.
 *
 * A ChordPro lyric may carry a little formatting inline — `<i>so</i>` — and
 * three of those tags are ones an engraved row can honour exactly: italic, bold
 * and underline are all things a font can simply be asked for, so they cost
 * nothing beyond measuring the piece in the face it is drawn in. The rest of
 * ChordPro's markup — colours, font and size spans, super- and subscripts —
 * is left to read literally, on the principle that half-drawn formatting is
 * worse to look at than none.
 *
 * A tag stays open until it is closed, or until the caller stops feeding text
 * in: chordsheetjs hands a line over in pieces, one per chord, so `<i>Ave
 * Maria</i>` with a chord in the middle of it arrives as two of them and the
 * italic has to survive the join. Passing the `open` style of one piece in as
 * the starting style of the next is what carries it across.
 *
 * Nothing here knows about SVG or the DOM; a run is a string and three flags.
 *
 * @typedef {{text: string, bold: boolean, italic: boolean, underline: boolean}} Run
 */

/** The tags that can be drawn exactly, and the style each one turns on. */
const STYLE_OF_TAG = { b: 'bold', i: 'italic', u: 'underline' };

/** @type {{bold: boolean, italic: boolean, underline: boolean}} */
export const PLAIN = Object.freeze({ bold: false, italic: false, underline: false });

/**
 * Split text into styled runs, honouring the markup it carries.
 *
 * @param {string} text
 * @param {{bold?: boolean, italic?: boolean, underline?: boolean}} [open]
 *        the style the text starts in — a style left open by the piece before
 *        it, or the base style of whatever is being set, such as a section
 *        label that is italic to begin with.
 * @returns {{runs: Array<Run>, open: {bold: boolean, italic: boolean, underline: boolean}}}
 */
export function markupRuns(text, open = PLAIN) {
    const source = String(text ?? '');
    const tags = /<(\/?)([biu])>/gi;
    const runs = [];

    let style = { ...PLAIN, ...open };
    let last = 0;
    let match;

    const push = (piece) => {
        if (piece === '') {
            return;
        }

        const previous = runs[runs.length - 1];

        // Runs are joined where the style did not actually change, so a stray
        // `</i>` in plain text leaves one run rather than three.
        if (previous && sameStyle(previous, style)) {
            previous.text += piece;

            return;
        }

        runs.push({ text: piece, ...style });
    };

    while ((match = tags.exec(source)) !== null) {
        push(source.slice(last, match.index));
        style = { ...style, [STYLE_OF_TAG[match[2].toLowerCase()]]: match[1] === '' };
        last = match.index + match[0].length;
    }

    push(source.slice(last));

    return { runs, open: style };
}

/**
 * The text of a set of runs, as it would read without any of the markup.
 *
 * @param {Array<Run>} runs
 * @returns {string}
 */
export function runsText(runs) {
    return runs.map((run) => run.text).join('');
}

/**
 * The runs covering the characters between two offsets into `runsText`.
 *
 * Used where a line has to be broken mid-run: the piece that stays and the
 * piece that moves keep the styles they were written in.
 *
 * @param {Array<Run>} runs
 * @param {number} start
 * @param {number} [end]
 * @returns {Array<Run>}
 */
export function sliceRuns(runs, start, end = Infinity) {
    const sliced = [];
    let at = 0;

    runs.forEach((run) => {
        const from = Math.max(start - at, 0);
        const to = Math.min(end - at, run.text.length);

        if (to > from) {
            sliced.push({ ...run, text: run.text.slice(from, to) });
        }

        at += run.text.length;
    });

    return sliced;
}

/**
 * How wide a set of runs is, each piece measured in the face it is drawn in.
 *
 * @param {Array<Run>} runs
 * @param {(text: string, opts?: {bold?: boolean, italic?: boolean}) => number} measure
 * @returns {number}
 */
export function measureRuns(runs, measure) {
    return runs.reduce(
        (total, run) => total + measure(run.text, { bold: run.bold, italic: run.italic }),
        0,
    );
}

function sameStyle(a, b) {
    return a.bold === b.bold && a.italic === b.italic && a.underline === b.underline;
}
