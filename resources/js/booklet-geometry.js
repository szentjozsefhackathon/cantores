/**
 * The booklet's unit system.
 *
 * Everything here is physical. A booklet exists because scores engraved on
 * different nominal pages have to sit on one real sheet, and that is only
 * possible if the page, the staves and the lyrics are all measured in
 * millimetres rather than in whatever canvas each renderer grew up with.
 *
 * The bridge between the two is the convention the rest of the codebase already
 * uses (SvgToPdfConverter, score-editor-export.js): scores are laid out in user
 * units where 1 unit = 1 px at 96 dpi. So a viewBox measured in those units maps
 * to millimetres by a constant, and rsvg-convert prints what the editor promised.
 */

/** Millimetres per CSS pixel at 96 dpi. */
export const MM_PER_PX = 25.4 / 96;

/** CSS pixels per PostScript point (72 pt to the inch, 96 px to the inch). */
export const PX_PER_PT = 96 / 72;

export function mmToPx(mm) {
    return mm / MM_PER_PX;
}

export function pxToMm(px) {
    return px * MM_PER_PX;
}

export function ptToPx(pt) {
    return pt * PX_PER_PT;
}

/**
 * Convert a booklet's millimetre geometry into the pixel geometry the renderers
 * and the page composer work in.
 *
 * @param {object} geometry as produced by Booklet::geometry() in PHP
 */
export function pageGeometry(geometry) {
    const marginPx = mmToPx(geometry.marginMm);

    return {
        pageWidthPx: mmToPx(geometry.pageWidthMm),
        pageHeightPx: mmToPx(geometry.pageHeightMm),
        contentWidthPx: mmToPx(geometry.contentWidthMm),
        contentHeightPx: mmToPx(geometry.contentHeightMm),
        contentWidthMm: geometry.contentWidthMm,
        marginPx,
        lyricSizePt: geometry.lyricSizePt,
        lyricSizePx: ptToPx(geometry.lyricSizePt),
        staffHeightMm: geometry.staffHeightMm,
        showTitles: geometry.showTitles !== false,
    };
}

/*
 * Lyric size, per format.
 *
 * One point of type must come out the same height whichever engine drew it, so
 * each format's knob is converted from the rendered pixel size it actually
 * produces — traced through the renderers rather than assumed:
 *
 *   Aretino   renderAretino takes `lyricSize` in points already.
 *   ChordPro  the container's font-size, in px.
 *   ABC       %%vocalfont is written as size/pageScale*3 and abc2svg then scales
 *             the drawing by pageScale, so the pageScale cancels and the
 *             rendered size is size*3, whatever the scale.
 *   GABC      exsurge is given lyricSize * (100/30) * 1.3 = size*13/3.
 */

export function abcLyricSizeForPt(pt) {
    return ptToPx(pt) / 3;
}

export function gabcLyricSizeForPt(pt) {
    return ptToPx(pt) * 3 / 13;
}

export function chordproFontSizeForPt(pt) {
    return ptToPx(pt);
}

export function aretinoLyricSizeForPt(pt) {
    return pt;
}

/*
 * Staff height, per format — the secondary unifier, meaningless to ChordPro.
 *
 * Each factor below is read out of the engine rather than guessed at:
 *
 *   Aretino   `staffSpaceMm` is the gap between two staff lines and the editor
 *             passes aretinoStaffSize/4, so aretinoStaffSize is already the
 *             height of a four-space staff in millimetres.
 *   ABC       abc2svg sets `topbar = 6*(lines-1)`, so a five-line staff is 24
 *             user units tall, which %%pagescale then multiplies.
 *   GABC      exsurge's staffInterval is glyphPunctumWidth (100) * glyphScaling
 *             and a four-line staff spans six intervals — its own helper says as
 *             much, calling setGlyphScaling(height/600). The editor passes
 *             (staffSize/100) * (100/30) / 16 = staffSize/480, so a staff is
 *             600 * staffSize/480 = 1.25 * staffSize units tall.
 *
 * They are still worth one confirming measurement against a printed PDF, since
 * a wrong factor here is invisible on screen and obvious on paper.
 */

/** A five-line abc2svg staff, in user units at pagescale 1. */
const ABC_STAFF_UNITS = 24;

/** User units of staff height per unit of exsurge's staffSize setting. */
const GABC_UNITS_PER_STAFF_SIZE = 1.25;

export function abcPageScaleForStaffHeight(mm) {
    return mm / (ABC_STAFF_UNITS * MM_PER_PX);
}

export function gabcStaffSizeForStaffHeight(mm) {
    return mm / MM_PER_PX / GABC_UNITS_PER_STAFF_SIZE;
}

export function aretinoStaffSizeForStaffHeight(mm) {
    return mm;
}
