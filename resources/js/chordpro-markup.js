/**
 * Inline text markup, the way ChordPro writes it.
 *
 * A ChordPro lyric may carry a little formatting inline — `<i>so</i>` — and
 * five of those tags are ones an engraved row can honour exactly. Italic, bold
 * and underline are things a font can simply be asked for, so they cost nothing
 * beyond measuring the piece in the face it is drawn in; a super- or subscript
 * is the same piece set smaller and moved off the baseline, which is arithmetic
 * on a size and a position rather than a second way of drawing text. What is
 * left of ChordPro's markup — colours, and font and size spans — reads
 * literally, on the principle that half-drawn formatting looks worse than none.
 *
 * A tag stays open until it is closed, or until the caller stops feeding text
 * in: chordsheetjs hands a line over in pieces, one per chord, so `<i>Ave
 * Maria</i>` with a chord in the middle of it arrives as two of them and the
 * italic has to survive the join. Passing the `open` style of one piece in as
 * the starting style of the next is what carries it across.
 *
 * Nothing here knows about SVG or the DOM; a run is a string and three flags.
 *
 * @typedef {{text: string, bold: boolean, italic: boolean, underline: boolean, script: 'sup'|'sub'|null}} Run
 */

/** The tags that turn a style on, and the style each one turns on. */
const STYLE_OF_TAG = { b: 'bold', i: 'italic', u: 'underline' };

/**
 * The tags that move the text off its baseline instead.
 *
 * These are a state rather than a flag: text is raised or lowered, never both,
 * so opening one closes the other and closing either returns to the baseline.
 */
const SCRIPTS = ['sup', 'sub'];

/**
 * How large a super- or subscript is set, against the size around it.
 *
 * The proportion a typographer's superior figure is cut at. Smaller than that
 * and a chord sheet read off a stand loses it altogether.
 */
export const SCRIPT_SIZE = 0.7;

/** How far off the baseline each one sits, as a fraction of the full size. */
const SCRIPT_SHIFT = { sup: -0.35, sub: 0.15 };

/** @type {{bold: boolean, italic: boolean, underline: boolean, script: null}} */
export const PLAIN = Object.freeze({ bold: false, italic: false, underline: false, script: null });

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
    const tags = /<(\/?)(sup|sub|b|i|u)>/gi;
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

        const tag = match[2].toLowerCase();
        const opening = match[1] === '';

        style = SCRIPTS.includes(tag)
            ? { ...style, script: opening ? tag : null }
            : { ...style, [STYLE_OF_TAG[tag]]: opening };

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
 * How a single run is set: the face it is drawn in and the size it is set at.
 *
 * The one place the two agree, so a run is never measured at one size and drawn
 * at another.
 *
 * @param {Run} run
 * @param {number} fontSize the size of the text around it
 * @returns {{bold: boolean, italic: boolean, fontSize: number}}
 */
export function runFont(run, fontSize) {
    return {
        bold: run.bold,
        italic: run.italic,
        fontSize: run.script ? fontSize * SCRIPT_SIZE : fontSize,
    };
}

/**
 * How far a run sits off the baseline of the line it belongs to, downwards.
 *
 * @param {Run} run
 * @param {number} fontSize
 * @returns {number}
 */
export function runBaselineShift(run, fontSize) {
    return (SCRIPT_SHIFT[run.script] ?? 0) * fontSize;
}

/**
 * How wide a set of runs is, each piece measured as it is set.
 *
 * @param {Array<Run>} runs
 * @param {(text: string, opts?: {bold?: boolean, italic?: boolean, fontSize?: number}) => number} measure
 * @param {number} fontSize
 * @returns {number}
 */
export function measureRuns(runs, measure, fontSize) {
    return runs.reduce((total, run) => total + measure(run.text, runFont(run, fontSize)), 0);
}

function sameStyle(a, b) {
    return a.bold === b.bold && a.italic === b.italic && a.underline === b.underline && a.script === b.script;
}
