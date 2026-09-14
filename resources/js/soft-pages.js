/**
 * Cutting a screen into the screens it actually needs.
 *
 * The counterpart of score-editor-pages.js, for everything whose height is known
 * here rather than reported by an engine. An engraved score is cut where its
 * author said and nowhere else, because an engraving that ran over would be a
 * wrong engraving; the rest is different — nobody writing a rubric or a chord
 * sheet counts lines against a 16:9 projector, and the answer to one that did
 * not fit used to be to set the whole thing smaller until it did, or to cut it
 * off at the bottom edge, which is how a screen ends up unreadable from the back
 * of the church or short of its last verse.
 *
 * Two callers, the same four tiers: a row of words (projection-deck.js) and a
 * chord sheet on a slide (score-editor-chordpro.js).
 *
 * So the author is given breaks of two strengths and they are spent in order,
 * weakest reason last:
 *
 *   1. `%pagebreak` always cuts. The rows become chunks.
 *   2. A chunk that fits is one screen, and its suggestions go unused.
 *   3. A chunk that does not fit is cut at its own `%pagebreak?` suggestions —
 *      and at as few of them as it takes, since the pieces are packed greedily
 *      afterwards.
 *   4. A piece that still does not fit is cut at its own boundaries — a
 *      paragraph, a heading, a verse — the way the booklet already flows prose
 *      across pages, with keepWithNext holding together whatever asked to be
 *      held together. A verse that would rather not be cut is cut anyway when
 *      keeping it whole would cost a screen, and a line longer than the screen
 *      is cut in the middle of itself sooner than hidden; see byBlocks.
 *
 * Below all four a single row taller than the screen is left over-tall and
 * handed back as it is: the caller sets it smaller, or says so. Nothing is ever
 * cut mid-sentence, because a screen ending mid-sentence is worse than a screen
 * set small.
 *
 * Nothing here touches the DOM or knows what a row is drawn as — a row is a
 * height and a few flags, which is what packPages already asks for.
 */

import { packPages } from './booklet-flow.js';

/**
 * @typedef {import('./booklet-flow.js').Block & {breakBefore?: 'hard'|'soft'}} SoftRow
 *        a laid-out row: markdownRows() writes these, and so does chordproRows()
 *
 * @typedef {object} SoftPage
 * @property {SoftRow[]} rows
 * @property {number} height what the rows come to, standing on their own
 */

/**
 * The screens one run of rows comes to at one shape.
 *
 * @param {SoftRow[]} rows laid out for this shape
 * @param {number} boxHeight the room a screen has
 * @returns {SoftPage[]} never empty unless the rows are
 */
export function packSoftPages(rows, boxHeight) {
    if (rows.length === 0) { return []; }

    return cutAt(rows, 'hard').flatMap((chunk) => fit(chunk, boxHeight));
}

/**
 * One chunk, on as few screens as its own breaks allow.
 *
 * The whole of the tier order is these four lines: fitting wins, then the
 * author's suggestions, then the paragraph boundaries, then nothing.
 */
function fit(chunk, boxHeight) {
    if (stackHeight(chunk) <= boxHeight) { return [page(chunk)]; }

    const segments = cutAt(chunk, 'soft');

    if (segments.length > 1) {
        return packed(segments.map(asBlock), boxHeight)
            .flatMap((rowsOnPage) => rowsOnPage.length === 1
                ? byBlocks(rowsOnPage[0], boxHeight)
                : [page(rowsOnPage.flat())]);
    }

    return byBlocks(chunk, boxHeight);
}

/**
 * The last cuts there are: at the rows themselves.
 *
 * Three attempts, each giving up something the one before it kept, and the
 * first that holds the screen wins:
 *
 *   a. keepWithNext as the rows asked for it — a heading with what it
 *      introduces, a verse with the rest of itself.
 *   b. at the line boundaries the rows point at with `splitBefore`: a verse is
 *      cut rather than moved whole to the next screen, but a line too long for
 *      the screen keeps its wrapped pieces together and a section label keeps
 *      the line it was written above.
 *   c. anywhere at all, which cuts a long line in the middle of itself.
 *
 * (a) also gives way to (b) when it merely costs a screen: a verse moved
 * wholesale leaves the room above it empty, and a congregation reading four
 * verses off five screens is being asked to look up one time too many.
 *
 * (c) is reached only by a single line of words taller than the screen on its
 * own — at which point the choice is between cutting a sentence and hiding the
 * end of it, and the reader is better served by the cut. Rows that say nothing
 * about `splitBefore` (a row of words from markdownRows) never leave (a): there
 * is no line structure there to fall back on, and the caller sets them smaller.
 */
function byBlocks(rows, boxHeight) {
    if (stackHeight(rows) <= boxHeight) { return [page(rows)]; }

    const whole = packed(glued(rows, (row) => row.keepWithNext === true), boxHeight);

    if (!rows.some((row) => typeof row.splitBefore === 'boolean')) {
        return whole.map(page);
    }

    const atLines = packed(glued(rows, () => false), boxHeight);
    const best = holds(atLines, boxHeight) && (!holds(whole, boxHeight) || atLines.length < whole.length)
        ? atLines
        : whole;

    if (holds(best, boxHeight)) { return best.map(page); }

    const anywhere = packed(rows.map((row) => ({ ...row, breakBefore: false, keepWithNext: false, payload: row })), boxHeight);

    return (holds(anywhere, boxHeight) ? anywhere : best).map(page);
}

/** Whether an attempt left every screen inside the room it has. */
function holds(pages, boxHeight) {
    return pages.every((rowsOnPage) => stackHeight(rowsOnPage) <= boxHeight);
}

/**
 * The rows as blocks packPages can take, with the glue this attempt asks for on
 * top of the glue it may not drop.
 *
 * `splitBefore: false` is not a preference — it is a row that is half of
 * something: the continuation of a line too long for the screen, or the line a
 * section label was written above. Rows that say nothing about it are left to
 * `keep` alone.
 */
function glued(rows, keep) {
    return rows.map((row, i) => ({
        ...row,
        breakBefore: false,
        keepWithNext: i < rows.length - 1 && (keep(row) || rows[i + 1].splitBefore === false),
        payload: row,
    }));
}

/**
 * packPages, with the pages restated as the payloads that went in.
 *
 * @returns {Array<any[]>}
 */
function packed(blocks, boxHeight) {
    return packPages(blocks, boxHeight).map((packedPage) => packedPage.items.map((item) => item.block.payload));
}

/**
 * A segment of a chunk, as something packPages can move whole.
 *
 * Its height is what it comes to standing alone; the gap its first row asked for
 * is carried separately, so two segments sharing a screen keep the space between
 * them and a segment opening one does not float down from the margin.
 */
function asBlock(segment) {
    return {
        height: stackHeight(segment),
        spaceBefore: segment[0]?.spaceBefore ?? 0,
        payload: segment,
    };
}

/**
 * Cut a run of rows above every row that asked for a break of this strength.
 *
 * Only ever above: a break at the head of the run has nothing before it to be
 * cut from, and one at the end would make an empty screen.
 */
function cutAt(rows, strength) {
    const runs = [[]];

    for (const row of rows) {
        if (row.breakBefore === strength && runs[runs.length - 1].length > 0) {
            runs.push([]);
        }

        runs[runs.length - 1].push(row);
    }

    return runs.filter((run) => run.length > 0);
}

/**
 * What a run of rows comes to when it stands on its own — the space above the
 * first one belongs to whatever it was separated from, and there is nothing
 * above it here.
 */
export function stackHeight(rows) {
    return rows.reduce(
        (total, row, i) => total + row.height + (i === 0 ? 0 : (row.spaceBefore ?? 0)),
        0,
    );
}

function page(rows) {
    return { rows, height: stackHeight(rows) };
}
