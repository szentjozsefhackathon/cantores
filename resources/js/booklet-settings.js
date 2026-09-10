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
 *   3. the booklet's unification, which owns size, width and the face
 *   4. the per-score override, which is whatever a person changed by hand
 *
 * Layer 3 is narrow on purpose. A booklet's job is to make a pile of scores the
 * same size, not to overrule the person who engraved them: it sets how wide the
 * page is, how big the type is, how tightly the systems are stacked and which
 * face everything is set in, and leaves the note spacing and the transposition
 * exactly as the author left them.
 *
 * The face is in there because a booklet is one printed object. Two faces have
 * different widths and different weights, and the balance between a staff and
 * the lyrics under it that looks right in one is wrong in the other — so a
 * booklet in which each score keeps the face its author happened to pick cannot
 * be balanced at all, however carefully its sizes are unified. The score's own
 * `lyricFont` is therefore ignored here, exactly as its own lyric size is, and
 * for the same reason. A cantor who does want one score in another face still
 * has layer 4.
 *
 * The stacking is in there because a booklet packs whole services onto small
 * pages, and vertical air a score can afford on its own sheet is what costs the
 * booklet a page. So the space between staves comes from the booklet rather than
 * the score.
 *
 * The gap between a staff and its lyrics, and the gap between two lyric lines,
 * are in there for the same reason the face is — they are the two numbers a face
 * makes right or wrong, and a booklet that imposed the face while leaving each
 * score the spacing that face needs would be imposing half a decision. Three
 * faces genuinely want three different balances, judged by eye rather than
 * derived, so the numbers are held per style beside the face: see
 * App\Support\BookletStyles. The author's own numbers are not consulted, for the
 * same reason their face is not — they were judged against the face they
 * engraved in, and the booklet is not in that face.
 *
 * Layer 4 wins over all of it, including over the booklet's own width — which is
 * the point. Widening one score past the content box is how you get rid of a bad
 * line break; the renderer then scales that score down to fit, so the page still
 * holds.
 */

/**
 * Where each engine keeps the face it sets lyrics in.
 *
 * One key per format because no two of them agree on a name for it, and the
 * booklet has to write the same face into all four.
 */
const FONT_KEY = {
    gabc: 'lyricFont',
    abc: 'abcLyricFont',
    chordpro: 'chordproFontFamily',
    aretino: 'aretinoTextFont',
};

/**
 * The booklet's face, in the key the format's renderer reads.
 *
 * Kept apart from unifiedSettings() rather than folded into it because the two
 * are unified for different reasons and travel differently. Everything in
 * unifiedSettings is an answer to a sheet of paper and is thrown away when the
 * booklet is read on a screen; a face is an answer to the booklet itself, and a
 * per-score override of it means the same thing on a phone as on A5. See
 * travellingOverride().
 *
 * @param {string} format
 * @param {object} geometry from pageGeometry(), whose textFont is already quoted
 */
export function unifiedFont(format, geometry) {
    const key = FONT_KEY[format];

    return key ? { [key]: geometry.textFont } : {};
}

/**
 * The keys the booklet computes from the page it is printed on. Everything else
 * is inherited or, in the case of the face, unified separately above.
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
            abcLyricFirstSkip: geometry.abcLyricFirstSkip,
            abcLyricSkip: geometry.abcLyricSkip,
            abcZoom: 100,
        };
    }

    if (format === 'gabc') {
        return {
            pageRatio: 'paper',
            gabcLayoutWidth: Math.floor(contentWidthPx),
            staffSize: round(gabcStaffSizeForStaffHeight(staffHeightMm), 4),
            lyricSize: round(gabcLyricSizeForPt(lyricSizePt), 4),
            // ABC's staff-to-lyrics gap under exsurge's name for it.
            minSpaceBelowStaff: geometry.minSpaceBelowStaff,
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
            // And under Aretino's, which states it twice: lyrics sit this far
            // below the lowest note, but never closer than the floor to the
            // bottom staff line.
            aretinoLyricDistance: geometry.aretinoLyricDistance,
            aretinoLyricMinStaffDistance: geometry.aretinoLyricMinStaffDistance,
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
 * That derivation is self-maintaining rather than coincidental, which is worth
 * saying because the spacings look at first like an accident of it. An override
 * means different things depending on which layer it overrules. While the
 * staff-to-lyrics gap was the author's, overriding it in a booklet said "this
 * author's gap is wrong under the face my booklet imposes" — a statement about
 * the music, which would deserve to travel. Now that the style owns the gap, the
 * style has already got the face right, and the only reason left to depart from
 * it is the page: this hymn runs two lines over, tighten it. So whatever the
 * booklet computes for itself, an override of it is by definition a departure
 * made for the booklet's own page, and stops at the paper.
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
 * One knob of a settings bucket, moved a step.
 *
 * Two things a plain `value + step` gets wrong on a music stand. It steps from
 * where the score is actually being drawn — the size a screen's geometry
 * computed, an arbitrary fraction — so pressing bigger four times leaves a
 * reader on 12.9067; snapping to the step's own grid keeps the numbers the
 * booklet is engraved at tidy. And it walks past the ends: a transposition is
 * eleven semitones each way and a picture cannot be enlarged past the page.
 *
 * @param {number} current what the score is drawn at now
 * @param {{min: number, max: number, step: number}} field from a booklet setting panel
 * @param {number} direction -1 or 1
 */
/** How much bigger or smaller one press of a reader's size knob is, in points. */
export const READER_SIZE_STEP_PT = 0.5;

/**
 * Where a reader's size knob is stored, per key — the same conversions
 * unifiedSettings() lays a booklet out with, reused for the step so that a press
 * is worth the same rise in type whichever engine drew the score.
 */
const SIZE_KNOB_UNIT = {
    lyricSize: gabcLyricSizeForPt,
    abcLyricSize: abcLyricSizeForPt,
    aretinoLyricSize: aretinoLyricSizeForPt,
    chordproFontSize: chordproFontSizeForPt,
};

/**
 * How far one press of a reader's knob moves it, in the knob's own unit.
 *
 * The panel's knobs are not in the same units and mostly not in points: what
 * ChordPro calls a font size is pixels, ABC's is a third of them and GABC's is
 * three thirteenths, so the one step the panel used to send moved ChordPro by a
 * third of a point and GABC by a point and a half — the reader pressing bigger
 * on a hymn and getting nothing, then pressing it on the chant next to it and
 * overshooting. So a size is asked for in points and converted here, and every
 * other knob keeps the step the panel gave it: a semitone is a semitone, and a
 * picture is enlarged by a twentieth of itself.
 *
 * @param {{key: string, role?: string, step?: number}} field from a reader panel
 */
export function readerStep(field) {
    const toKnobUnit = field.role === 'size' ? SIZE_KNOB_UNIT[field.key] : undefined;

    return toKnobUnit ? round(toKnobUnit(READER_SIZE_STEP_PT), 4) : Number(field.step) || 1;
}

export function steppedValue(current, field, direction) {
    const step = Number(field.step) || 1;
    const from = Number.isFinite(Number(current)) ? Number(current) : 0;
    const stepped = Math.round((from + direction * step) / step) * step;
    const clamped = Math.min(Number(field.max), Math.max(Number(field.min), stepped));

    return Math.round(clamped * 1e4) / 1e4;
}

/**
 * Whether an override on one key actually moves that knob.
 *
 * Storing a key is not the same as changing anything. A reader who transposes a
 * hymn up and then back down again has an `abcTranspose: 0` in their bucket, and
 * a cantor who steps a staff size to the value the booklet had already computed
 * has one just like it — both of them, on the old test of "is this key present",
 * lit the control blue and said the score had been adjusted when nothing about
 * it had moved. The question the panel is really asking is whether what is drawn
 * differs from what would be drawn without the override, so that is what is
 * compared: the stored value against the layers underneath it.
 *
 * Numbers are compared as numbers, and loosely, because the layers below are
 * computed from a page geometry and rounded to four places — an override that
 * came from the same arithmetic must not count as a change for a difference in
 * the twelfth digit. Faces are compared unquoted, since the score editor's
 * selects emit `'Merriweather'` and a format's own defaults may not.
 *
 * @param {*} value the override's value for the key
 * @param {*} inherited what the score would be drawn at without it
 */
export function movesSetting(value, inherited) {
    if (value === undefined || value === null) { return false; }

    if (typeof value === 'boolean' || typeof inherited === 'boolean') {
        return Boolean(value) !== Boolean(inherited);
    }

    const moved = Number(value);
    const base = Number(inherited);

    if (Number.isFinite(moved) && Number.isFinite(base) && value !== '' && inherited !== '') {
        return Math.abs(moved - base) > 1e-6;
    }

    return unquoted(value) !== unquoted(inherited);
}

function unquoted(value) {
    return typeof value === 'string' ? value.trim().replace(/^['"]|['"]$/g, '') : value;
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
        ...unifiedFont(format, geometry),
        ...(override ?? {}),
    };
}

/**
 * The booklet's entries with every override held as a plain object.
 *
 * PHP writes an empty override as `[]` and a filled one as `{}`, while the
 * browser writes `{}` either way — so a score whose override had just been
 * emptied here was described one way by the pages already on screen and another
 * way by the payload that came back from the server saving it. Two different
 * strings to layoutSignature(), and the whole booklet was laid out a second time
 * to arrive at the pages it was already showing.
 *
 * @param {Array<object>} entries one payload from BookletRenderPayload
 */
export function withPlainOverrides(entries) {
    return (entries ?? []).map((entry) => (
        entry?.override === undefined ? entry : { ...entry, override: { ...entry.override } }
    ));
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
