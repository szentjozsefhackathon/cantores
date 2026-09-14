/**
 * Cutting a screen of words into the screens it actually needs.
 *
 * The counterpart of score-editor-pages.js, for the rows that hold words rather
 * than music. A score is cut where its author said and nowhere else, because an
 * engraving that ran over would be a wrong engraving; a rubric is different —
 * nobody writing one counts lines against a 16:9 projector, and until now the
 * answer to a rubric that did not fit was to set the whole thing smaller until
 * it did, which is how a screen ends up unreadable from the back of the church.
 *
 * So the author is given breaks of two strengths and they are spent in order,
 * weakest reason last:
 *
 *   1. `%pagebreak` always cuts. The row becomes chunks.
 *   2. A chunk that fits is one screen, and its suggestions go unused.
 *   3. A chunk that does not fit is cut at its own `%pagebreak?` suggestions —
 *      and at as few of them as it takes, since the pieces are packed greedily
 *      afterwards.
 *   4. A piece that still does not fit is cut at its paragraph and heading
 *      boundaries, the way the booklet already flows prose across pages.
 *
 * Below all four a single paragraph taller than the screen is left over-tall and
 * handed back as it is: the caller sets it smaller and says so. Words are never
 * cut mid-sentence, because a screen ending mid-sentence is worse than a screen
 * set small.
 *
 * Nothing here touches the DOM or knows what a row is drawn as — a row is a
 * height and a few flags, which is what packPages already asks for.
 */

import { packPages } from './booklet-flow.js';

/**
 * @typedef {import('./booklet-markdown.js').MarkdownRow} MarkdownRow
 *
 * @typedef {object} TextPage
 * @property {MarkdownRow[]} rows
 * @property {number} height what the rows come to, standing on their own
 */

/**
 * The screens one row of words comes to at one shape.
 *
 * @param {MarkdownRow[]} rows from markdownRows(), laid out for this shape
 * @param {number} boxHeight the room a screen has for words
 * @returns {TextPage[]} never empty unless the row is
 */
export function packTextPages(rows, boxHeight) {
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
 * The last cut there is: at the paragraphs and headings themselves, with
 * keepWithNext holding a heading to what it introduces.
 */
function byBlocks(rows, boxHeight) {
    if (stackHeight(rows) <= boxHeight) { return [page(rows)]; }

    return packed(rows.map((row) => ({ ...row, breakBefore: false, payload: row })), boxHeight)
        .map((rowsOnPage) => page(rowsOnPage));
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
