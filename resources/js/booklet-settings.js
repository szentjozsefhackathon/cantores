import {
    abcLyricSizeForPt,
    abcPageScaleForStaffHeight,
    aretinoLyricSizeForPt,
    aretinoStaffSizeForStaffHeight,
    chordproFontSizeForPt,
    gabcLyricSizeForPt,
    gabcStaffSizeForStaffHeight,
} from './booklet-geometry.js';

/**
 * How a score gets its render settings inside a booklet.
 *
 * Four layers, deliberately the same shape as applyRatioSettings() in the score
 * editor (defaults, then the user's, then the score's, then the session's), so
 * there is one idea to hold rather than two:
 *
 *   1. the format's own defaults
 *   2. what the score's author chose — fonts, spacings, transposition
 *   3. the booklet's unification, which owns size and width and nothing else
 *   4. the per-score override, which is whatever a person changed by hand
 *
 * Layer 3 is narrow on purpose. A booklet's job is to make a pile of scores the
 * same size, not to overrule the person who engraved them: it sets how wide the
 * page is, how big the type is and how tightly the systems are stacked, and
 * leaves the note spacing, the font and the transposition exactly as the author
 * left them.
 *
 * The stacking is in there because a booklet packs whole services onto small
 * pages, and vertical air a score can afford on its own sheet is what costs the
 * booklet a page. So the space between staves comes from the booklet rather than
 * the score. The gap between a staff and its lyrics is not in there: it is the
 * author's `abcLyricFirstSkip`, which travels with the score and can be
 * overridden per score in the booklet like any other authored setting.
 *
 * Layer 4 wins over all of it, including over the booklet's own width — which is
 * the point. Widening one score past the content box is how you get rid of a bad
 * line break; the renderer then scales that score down to fit, so the page still
 * holds.
 */

/**
 * The keys the booklet computes for itself. Everything else is inherited.
 *
 * @param {string} format
 * @param {object} geometry from pageGeometry()
 */
export function unifiedSettings(format, geometry) {
    const { contentWidthPx, contentWidthMm, lyricSizePt, staffHeightMm } = geometry;

    if (format === 'abc') {
        return {
            abcPageRatio: 'paper',
            abcPageWidth: Math.floor(contentWidthPx),
            abcPageScale: round(abcPageScaleForStaffHeight(staffHeightMm), 4),
            abcLyricSize: round(abcLyricSizeForPt(lyricSizePt), 4),
            // abc2svg reserves the staff separation above the first staff too,
            // so this is also what stands between a heading and the music it
            // names — which is why the booklet, not the score, gets to say it.
            abcStaffSep: geometry.abcStaffSep,
            abcZoom: 100,
        };
    }

    if (format === 'gabc') {
        return {
            pageRatio: 'paper',
            gabcLayoutWidth: Math.floor(contentWidthPx),
            staffSize: round(gabcStaffSizeForStaffHeight(staffHeightMm), 4),
            lyricSize: round(gabcLyricSizeForPt(lyricSizePt), 4),
            zoom: 100,
        };
    }

    if (format === 'aretino') {
        return {
            aretinoPageRatio: 'paper',
            // Floored, like the other widths: a value a hair over the content
            // box would put every score through a pointless scale-to-fit.
            aretinoStaffWidth: floor(contentWidthMm, 4),
            aretinoStaffSize: round(aretinoStaffSizeForStaffHeight(staffHeightMm), 4),
            aretinoLyricSize: round(aretinoLyricSizeForPt(lyricSizePt), 4),
            aretinoZoom: 100,
        };
    }

    if (format === 'chordpro') {
        return {
            chordproFontSize: round(chordproFontSizeForPt(lyricSizePt), 4),
            // The booklet's own flow does the packing; a second column inside one
            // score would fight it for the same vertical space.
            chordproColumns: 1,
        };
    }

    return {};
}

/**
 * What an uploaded score is drawn at.
 *
 * A picture has no settings to unify, only a size: it arrives scaled to the
 * width of the page, and this is the factor someone may take it down by.
 *
 * @param {object|null} override booklet_scores.settings_override
 */
export function fileSettings(override) {
    return { fileZoom: 1, ...(override ?? {}) };
}

/**
 * The half of a booklet's per-score override that still means something on a
 * phone.
 *
 * The cantor's overrides are two different kinds of thing wearing one coat.
 * Some are decisions about the music — this hymn is sung a third lower, this one
 * wants German chord names, this chant does without drop caps — and they are
 * true wherever it is read. The rest are decisions about a sheet of A5: widen
 * this score so the line stops breaking, take that staff down so the page holds,
 * shrink this scan so it stops shouting. Those are answers to a page that the
 * reader's screen is not, and carrying them across is how a booklet widened for
 * paper arrives on a phone laid out for paper and then scaled to a smudge.
 *
 * The line between the two is already drawn: the keys the booklet's geometry
 * computes for itself are exactly the page-fitting ones — see unifiedSettings —
 * plus the one an uploaded picture has. So the reader inherits everything else,
 * and the page-bound keys are decided afresh by the screen in their hand.
 *
 * @param {string} format
 * @param {object|null} override booklet_scores.settings_override
 * @param {object} geometry from pageGeometry()
 */
export function travellingOverride(format, override, geometry) {
    const pageBound = new Set([...Object.keys(unifiedSettings(format, geometry)), 'fileZoom']);

    return Object.fromEntries(
        Object.entries(override ?? {}).filter(([key]) => !pageBound.has(key)),
    );
}

/**
 * The width a score is laid out at, and what to do if that overflows the page.
 *
 * A width override above the content box is a request to lay the score out on a
 * wider page and then shrink the result — the typesetter's way of keeping a line
 * from breaking. Below it, nothing is scaled.
 *
 * @returns {{layoutWidthPx: number, scale: number}}
 */
export function layoutWidthFor(format, resolved, geometry) {
    const content = geometry.contentWidthPx;

    let requested = content;
    if (format === 'abc') {
        requested = Number(resolved.abcPageWidth) || content;
    } else if (format === 'gabc') {
        requested = Number(resolved.gabcLayoutWidth) || content;
    } else if (format === 'aretino') {
        const mm = Number(resolved.aretinoStaffWidth);
        requested = mm > 0 ? mm / (25.4 / 96) : content;
    }

    const layoutWidthPx = Math.max(1, requested);

    return {
        layoutWidthPx,
        scale: layoutWidthPx > content ? content / layoutWidthPx : 1,
    };
}

/**
 * Merge the four layers for one score.
 *
 * @param {string} format
 * @param {object} formatDefaults the format mixin's own field values
 * @param {object} scoreSettings the score's full settings column
 * @param {object} geometry from pageGeometry()
 * @param {object} override booklet_scores.settings_override
 */
export function resolveSettings(format, formatDefaults, scoreSettings, geometry, override) {
    return {
        ...formatDefaults,
        ...paperBucket(scoreSettings, format),
        ...unifiedSettings(format, geometry),
        ...(override ?? {}),
    };
}

/**
 * The score's own paper-mode settings.
 *
 * Read from both 'paper' and the legacy 'auto' key, the way the score editor's
 * readRatioBucket does, so a score saved before the rename still contributes its
 * author's choices.
 */
export function paperBucket(scoreSettings, format) {
    const bucket = scoreSettings?.[format];
    if (!bucket) { return {}; }

    return { ...(bucket.auto ?? {}), ...(bucket.paper ?? {}) };
}

function round(value, places) {
    const factor = 10 ** places;

    return Math.round(value * factor) / factor;
}

function floor(value, places) {
    const factor = 10 ** places;

    return Math.floor(value * factor) / factor;
}
