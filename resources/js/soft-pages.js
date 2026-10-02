/**
 * Cutting a screen into the screens it actually needs.
 *
 * The counterpart of score-editor-pages.js, for everything cut by its height
 * rather than only where its author said. Nobody writing a rubric, a chord sheet
 * or a hymn counts lines against a 16:9 projector, and the answer to one that
 * did not fit used to be to set the whole thing smaller until it did, or to cut
 * it off at the bottom edge, which is how a screen ends up unreadable from the
 * back of the church or short of its last verse.
 *
 * Three callers, the same rules: a row of words (projection-deck.js), a chord
 * sheet on a slide (score-editor-chordpro.js), and an engraved score cut between
 * its staff systems (slide-systems.js).
 *
 * `%pagebreak` always cuts, and the rows become chunks. A chunk that fits is one
 * screen, and its suggestions go unused. One that does not is filled a screen at
 * a time, and each screen is cut in two steps:
 *
 *   1. Find how far it can be filled: the last place a cut may fall with
 *      everything above it still on the screen.
 *   2. Look back from there for a `%pagebreak?`. If the screen holds one, the
 *      cut is made at the last of them; if not, it is made where (1) stopped.
 *
 * So a suggestion is taken only when a cut is needed on the screen it stands
 * on, and one further down is never reached for early: whatever the cut leaves
 * over flows on to the next screen, with the pieces after it.
 *
 * Where (1) may stop depends on what the rows say about themselves. Rows with a
 * line structure (`splitBefore`, which chord sheets and staff systems carry) may
 * be cut between any two lines — a verse is cut sooner than left behind a
 * half-empty screen — but not inside a wrapped line or under a section label
 * unless nothing else will hold the screen. Rows without one (a row of words)
 * are held together by keepWithNext, so a heading moves with its text. A row, or
 * a group, taller than the screen on its own is handed back over-tall.

 * Nothing is ever cut mid-sentence where a screen can be set small instead, and
 * nothing here touches the DOM or knows what a row is drawn as — a row is a
 * height and a few flags.
 */

/**
 * @typedef {import('./booklet-flow.js').Block & {breakBefore?: 'hard'|'soft'}} SoftRow
 *        a laid-out row: markdownRows() writes these, and so does chordproRows()
 *
 * @typedef {object} SoftPage
 * @property {SoftRow[]} rows
 * @property {number} height what the rows come to, standing on their own
 * @property {number} [start] the index of its first row in the run it was cut from
 */

/**
 * The screens one run of rows comes to at one shape.
 *
 * Every page says which row it starts at, counted across the whole run: that
 * index is the cut, written down so that a different browser can make the same
 * one. See packAtStarts.
 *
 * @param {SoftRow[]} rows laid out for this shape
 * @param {number} boxHeight the room a screen has
 * @param {number[]|null} [starts] cuts already decided elsewhere, which are
 *        made instead of any of this packer's own
 * @returns {SoftPage[]} never empty unless the rows are
 */
export function packSoftPages(rows, boxHeight, starts = null) {
    if (Array.isArray(starts) && starts.length > 0) { return packAtStarts(rows, starts); }
    if (rows.length === 0) { return []; }

    let start = 0;

    return cutAt(rows, 'hard')
        .flatMap((chunk) => fit(chunk, boxHeight))
        .map((packed) => {
            const placed = { ...packed, start };

            start += packed.rows.length;

            return placed;
        });
}

/**
 * The same rows, cut exactly where another browser cut them.
 *
 * The wall is the one whose slides the room sees, so the cuts it made are the
 * ones every remote must make too: two browsers measure the same words a pixel
 * apart, and a page that is one slide on the projector must not be two on the
 * phone, or every address after it names a different slide on each.
 *
 * Exactly one page per start, whatever the rows come to here — the count is
 * the whole point. A start past the last row this browser has, which only a
 * line wrapped differently can cause, comes back as an empty page rather than
 * as no page at all.
 *
 * @param {SoftRow[]} rows laid out for this shape
 * @param {number[]} starts the first row of each page
 * @returns {SoftPage[]} as many as there are starts
 */
export function packAtStarts(rows, starts) {
    const cuts = [];

    starts.forEach((start, i) => {
        const at = i === 0 ? 0 : Math.min(Math.max(Math.trunc(Number(start)) || 0, cuts[i - 1]), rows.length);

        cuts.push(at);
    });

    return cuts.map((start, i) => ({ ...page(rows.slice(start, cuts[i + 1] ?? rows.length)), start }));
}

/**
 * One chunk, a screen at a time: filled as far as it goes, then cut at the last
 * suggestion on it, if it has one.
 */
function fit(chunk, boxHeight) {
    const kinds = cutKinds(chunk);
    const pages = [];

    for (let start = 0; start < chunk.length;) {
        const end = screenEnd(chunk, kinds, start, boxHeight);

        pages.push(page(chunk.slice(start, end)));
        start = end;
    }

    return pages;
}

/**
 * Where the screen opening at `start` ends: the index of the first row it does
 * not take.
 */
function screenEnd(chunk, kinds, start, boxHeight) {
    let height = 0;
    let lastSoft = null;
    let lastAuto = null;
    let lastForced = null;

    for (let end = start + 1; end <= chunk.length; end++) {
        const row = chunk[end - 1];
        height += row.height + (end - 1 === start ? 0 : (row.spaceBefore ?? 0));

        if (height > boxHeight) { break; }
        if (end === chunk.length) { return end; }

        if (kinds[end] === 'soft') { lastSoft = end; }
        if (kinds[end] === 'soft' || kinds[end] === 'auto') { lastAuto = end; }
        if (kinds[end] !== null) { lastForced = end; }
    }

    const cut = lastSoft ?? lastAuto ?? lastForced;

    if (cut !== null) { return cut; }

    // Nothing fits: the screen takes the first piece that cannot be cut, and
    // runs over.
    for (let end = start + 1; end < chunk.length; end++) {
        if (kinds[end] !== null) { return end; }
    }

    return chunk.length;
}

/**
 * What a cut above each row of a chunk would be, or null where none may fall.
 *
 * - `soft` — the author suggested it, and it may always be taken.
 * - `auto` — a boundary the packer may choose: between two lines, two verses,
 *   two paragraphs or two staff systems.
 * - `forced` — a boundary that is half of something: the continuation of a line
 *   too long for the screen, or the line a section label was written above
 *   (`splitBefore: false`). Taken only when no other cut fits on the screen.
 *
 * Rows that say nothing about `splitBefore` — every row markdownRows writes —
 * have no line structure to fall back on, so their keepWithNext is not a
 * preference but the rule: a heading moves with its text, and a group taller
 * than the screen is handed back whole for the caller to set smaller. Rows that
 * do say are held together only by `splitBefore: false`; a verse's keepWithNext
 * gives way, since a verse moved whole leaves the room above it empty.
 *
 * @param {SoftRow[]} rows
 * @returns {Array<'soft'|'auto'|'forced'|null>} indexed like the rows; the
 *          first entry is meaningless, since nothing stands above the first row
 */
function cutKinds(rows) {
    const detailed = rows.some((row) => typeof row.splitBefore === 'boolean');

    return rows.map((row, i) => {
        if (i === 0) { return null; }
        if (row.breakBefore === 'soft') { return 'soft'; }

        if (detailed) {
            return row.splitBefore === false ? 'forced' : 'auto';
        }

        return rows[i - 1].keepWithNext === true ? null : 'auto';
    });
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
 * Whether a packed page begins where nobody asked for a cut.
 *
 * The first page begins where the source does. Any other begins either at a
 * break the author wrote — `%pagebreak`, or a `%pagebreak?` the packer spent —
 * which its first row carries, or at a cut the packer made on its own.
 *
 * @param {SoftPage} page
 * @param {number} index its place in the list
 */
export function startsAtAutomaticCut(page, index) {
    if (index === 0) { return false; }

    const first = page.rows?.[0]?.breakBefore;

    return first !== 'hard' && first !== 'soft';
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
