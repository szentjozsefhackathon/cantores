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
 * be cut between any two lines, but not inside a wrapped line or under a section
 * label unless nothing else will hold the screen. Where they also say where a
 * paragraph starts (`startsParagraph`, which a chord sheet carries: a blank line
 * in ChordPro is a new section), the screen ends between two paragraphs rather
 * than inside one — a congregation reads a verse on one screen, and a screen
 * left part empty costs nothing a cut mid-verse does not cost more. A paragraph
 * is cut at its lines only when it is taller than a screen on its own, and then
 * fills the screen it opens on. Rows without a line structure (a row of words)
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
    let lastVerse = null;
    let lastAuto = null;
    let lastForced = null;

    for (let end = start + 1; end <= chunk.length; end++) {
        const row = chunk[end - 1];
        height += row.height + (end - 1 === start ? 0 : (row.spaceBefore ?? 0));

        if (height > boxHeight) { break; }
        if (end === chunk.length) { return end; }

        if (kinds[end] === 'soft') { lastSoft = end; }
        if (kinds[end] === 'verse') { lastVerse = end; }
        if (kinds[end] === 'soft' || kinds[end] === 'verse' || kinds[end] === 'auto') { lastAuto = end; }
        if (kinds[end] !== null) { lastForced = end; }
    }

    // A verse pushed whole onto the next screen is only worth the room it leaves
    // here if it then fits there; one taller than a screen is cut at its lines
    // whatever happens, so this screen may as well be filled with its opening.
    const verse = lastVerse !== null && paragraphFits(chunk, kinds, lastVerse, boxHeight) ? lastVerse : null;
    const cut = lastSoft ?? verse ?? lastAuto ?? lastForced;

    if (cut !== null) { return cut; }

    // Nothing fits: the screen takes the first piece that cannot be cut, and
    // runs over.
    for (let end = start + 1; end < chunk.length; end++) {
        if (kinds[end] !== null) { return end; }
    }

    return chunk.length;
}

/**
 * Whether the paragraph opening at `from` — up to the next paragraph or
 * suggestion — fits on a screen of its own.
 */
function paragraphFits(chunk, kinds, from, boxHeight) {
    let height = 0;

    for (let i = from; i < chunk.length; i++) {
        if (i > from && (kinds[i] === 'verse' || kinds[i] === 'soft')) { break; }

        height += chunk[i].height + (i === from ? 0 : (chunk[i].spaceBefore ?? 0));

        if (height > boxHeight) { return false; }
    }

    return true;
}

/**
 * What a cut above each row of a chunk would be, or null where none may fall.
 *
 * - `soft` — the author suggested it, and it may always be taken.
 * - `verse` — the boundary between two paragraphs of a chord sheet, preferred
 *   to any line inside one.
 * - `auto` — a boundary the packer may choose: between two lines, two
 *   paragraphs of words or two staff systems.
 * - `forced` — a boundary that is half of something: the continuation of a line
 *   too long for the screen, or the line a section label was written above
 *   (`splitBefore: false`). Taken only when no other cut fits on the screen.
 *
 * Rows that say nothing about `splitBefore` — every row markdownRows writes —
 * have no line structure to fall back on, so their keepWithNext is not a
 * preference but the rule: a heading moves with its text, and a group taller
 * than the screen is handed back whole for the caller to set smaller. Rows that
 * do say are held together only by `splitBefore: false`; their keepWithNext
 * gives way to the `verse` tier, which says the same thing as a preference the
 * packer can still overrule for a verse taller than the screen.
 *
 * @param {SoftRow[]} rows
 * @returns {Array<'soft'|'verse'|'auto'|'forced'|null>} indexed like the rows; the
 *          first entry is meaningless, since nothing stands above the first row
 */
function cutKinds(rows) {
    const detailed = rows.some((row) => typeof row.splitBefore === 'boolean');

    return rows.map((row, i) => {
        if (i === 0) { return null; }
        if (row.breakBefore === 'soft') { return 'soft'; }

        if (detailed) {
            if (row.splitBefore === false) { return 'forced'; }

            return row.startsParagraph === true ? 'verse' : 'auto';
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
 * How finely a slide is set smaller to save one: the sizes tried are 97.5%, 95%,
 * 92.5% and so on, and never anything in between. A fixed grid rather than a
 * search, so that every device showing the deck tries the same sizes and lands
 * on the same slides.
 */
export const SHRINK_STEP = 0.025;

/**
 * Set a run smaller, as little as it takes, where that saves a slide.
 *
 * A slide just too tall for the screen used to become two, the second holding a
 * line or two — which is the worst thing a congregation can be shown, and
 * staves a little smaller from one slide to the next is a price nobody in the
 * pews notices. So before anything is cut, the run is tried at the floor the
 * style allows: if that comes to fewer slides (or fewer slides that overrun),
 * the largest size on the grid that does as well is taken. If the floor saves
 * nothing, nothing is shrunk at all.
 *
 * @template P
 * @param {(scale: number) => {pages: P[], overflowing: number}} packAt the run
 *        laid out and packed at a scale
 * @param {number} minScale the floor, in (0, 1]; 1 never shrinks
 * @returns {{pages: P[], scale: number}}
 */
export function shrinkToFit(packAt, minScale) {
    const floor = Math.min(1, Math.max(0.5, Number(minScale) || 1));
    const full = packAt(1);

    if (floor >= 1 || (full.pages.length <= 1 && full.overflowing === 0)) {
        return { pages: full.pages, scale: 1 };
    }

    const best = packAt(floor);

    if (!fewer(best, full)) {
        return { pages: full.pages, scale: 1 };
    }

    for (let step = 1; ; step++) {
        const scale = Math.round((1 - step * SHRINK_STEP) * 1000) / 1000;

        if (scale <= floor) { break; }

        const tried = packAt(scale);

        if (!fewer(best, tried)) {
            return { pages: tried.pages, scale };
        }
    }

    return { pages: best.pages, scale: floor };
}

/** Whether one packing comes to fewer slides than another, or fewer overrunning. */
function fewer(a, b) {
    return a.pages.length < b.pages.length
        || (a.pages.length === b.pages.length && a.overflowing < b.overflowing);
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
