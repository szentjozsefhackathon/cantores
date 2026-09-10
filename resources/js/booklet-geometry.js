/**
 * The unit system, shared by the booklet and the score editors.
 *
 * Everything here is physical. A booklet exists because scores engraved on
 * different nominal pages have to sit on one real sheet, and that is only
 * possible if the page, the staves and the lyrics are all measured in
 * millimetres rather than in whatever canvas each renderer grew up with.
 *
 * The score editors now engrave on a real page too, so they read the same
 * conversions rather than a second set of their own: a staff is six millimetres
 * tall in the Aretino editor, in the ABC editor and on a booklet page alike, and
 * one editor's export is the same physical size as another's.
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

export function pxToPt(px) {
    return px / PX_PER_PT;
}

/**
 * The face everything speaks in when nothing else has been chosen.
 *
 * A book face rather than an interface one, because this is the face of a whole
 * printed object — the headings, the rubrics and the lyrics under every staff —
 * and a booklet is read the way a book is. It is also the face the sizes below
 * are calibrated against; see OPTICAL_X_HEIGHT.
 */
export const DEFAULT_TEXT_FONT = 'Alegreya';

/**
 * The page a score is engraved for when nobody has said otherwise: A4 with the
 * 20 mm margins a binder needs, which is also what the Aretino editor has always
 * defaulted its staff width to.
 */
export const DEFAULT_PAGE_WIDTH_MM = 170;

/** A chant staff that reads at arm's length off that page. */
export const DEFAULT_STAFF_HEIGHT_MM = 6;

/** The lyric size that balances it, in points of the reference face. */
export const DEFAULT_LYRIC_SIZE_PT = 11;

/**
 * How large a face looks at a given point size, as x-height per em.
 *
 * A point is a measure of the em square, not of anything the eye can see, so two
 * faces set at the same size do not read as the same size: 11 pt of Alegreya and
 * 11 pt of Merriweather differ by a fifth. What the eye actually compares is the
 * height of a lower-case letter, so sizes are converted between faces by holding
 * that constant — which is what makes a booklet's one lyric size mean the same
 * thing whichever face it is set in.
 *
 * Every value below is measured from the woff2 file in public/fonts (OS/2
 * sxHeight over unitsPerEm), except EB Garamond. Its x-height is unusually small
 * against unusually tall capitals and ascenders, and holding the x-height alone
 * constant sizes it visibly too large; the value here is instead calibrated from
 * the pairing that was actually judged by eye — 11.5 pt of EB Garamond against
 * 11 pt of Alegreya — which is its measured 0.400 raised by eight per cent.
 */
const OPTICAL_X_HEIGHT = {
    'Alegreya': 0.452,
    'Merriweather': 0.555,
    'EB Garamond': 0.432,
    'Lora': 0.500,
    'Inter': 0.546,
    'Barlow Condensed': 0.509,
};

/** The face every size in this application is quoted in. */
export const REFERENCE_TEXT_FONT = 'Alegreya';

/**
 * How many points of `family` read as one point of the reference face.
 *
 * Unknown families are left alone rather than guessed at: a face nobody has
 * measured is likelier to be a fallback stack than a mistake.
 */
export function opticalSizeFactor(family) {
    const bare = String(family ?? '').trim().replace(/['"]/g, '');
    const xHeight = OPTICAL_X_HEIGHT[bare];

    if (!(xHeight > 0)) {
        return 1;
    }

    return OPTICAL_X_HEIGHT[REFERENCE_TEXT_FONT] / xHeight;
}

/**
 * A size quoted in the reference face, restated in the face it will be set in.
 *
 * @param {number} pt points of the reference face
 * @param {string} family the face actually being set
 */
export function opticalLyricSizePt(pt, family) {
    return Number(pt) * opticalSizeFactor(family);
}

/**
 * What a set size has to be multiplied by to get back the size the leading is
 * measured in.
 *
 * Holding the x-height constant makes every face read at the same size, but it
 * does so by giving each one a different em — and leading measured in that em
 * follows the face rather than the eye: Alegreya's lines stand a fifth further
 * apart than Merriweather's for letters of the same apparent height, so choosing
 * a face silently reflows the booklet. So the em is what the letters are drawn
 * from and the nominal size is what the space between the lines is measured in,
 * and a booklet keeps its vertical rhythm whichever face it is set in.
 */
export function leadingScale(family) {
    return 1 / opticalSizeFactor(family);
}

/**
 * How far apart ABC staves stand in a booklet.
 *
 * Tighter than the score's own, and deliberately so: a booklet is read at arm's
 * length off a small page, where the space an engraver left for a projector is
 * simply a hole. It is a booklet-wide setting rather than a constant because the
 * right amount depends on the page, and it is per-format because ABC is the one
 * engine that states this in units of its own.
 */
export const DEFAULT_ABC_STAFF_SEP = 25;

/**
 * A family as an SVG font-family value.
 *
 * Quoted, because the names in play have spaces in them and a bare
 * `font-family="EB Garamond"` is two families neither of which exists.
 */
export function quoteFontFamily(family) {
    const bare = String(family ?? '').trim().replace(/['"]/g, '');

    return `'${bare === '' ? DEFAULT_TEXT_FONT : bare}'`;
}

/**
 * Convert a booklet's millimetre geometry into the pixel geometry the renderers
 * and the page composer work in.
 *
 * @param {object} geometry as produced by Booklet::geometry() in PHP
 */
export function pageGeometry(geometry) {
    const marginPx = mmToPx(geometry.marginMm);
    const textFont = quoteFontFamily(geometry.textFont);
    // The size the cantor set is quoted in the reference face; every renderer
    // downstream is handed the size that reads as that in the face actually
    // chosen, so changing the booklet's face does not change how big it looks.
    const lyricSizePt = opticalLyricSizePt(geometry.lyricSizePt, textFont);

    return {
        pageWidthPx: mmToPx(geometry.pageWidthMm),
        pageHeightPx: mmToPx(geometry.pageHeightMm),
        contentWidthPx: mmToPx(geometry.contentWidthMm),
        contentHeightPx: mmToPx(geometry.contentHeightMm),
        contentWidthMm: geometry.contentWidthMm,
        marginPx,
        nominalLyricSizePt: Number(geometry.lyricSizePt),
        lyricSizePt,
        lyricSizePx: ptToPx(lyricSizePt),
        leadingScale: leadingScale(textFont),
        staffHeightMm: geometry.staffHeightMm,
        textFont,
        headingScale: Number(geometry.headingScale) > 0 ? Number(geometry.headingScale) : 1,
        abcStaffSep: Number(geometry.abcStaffSep) >= 0 ? Number(geometry.abcStaffSep) : DEFAULT_ABC_STAFF_SEP,
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
 * The same conversions read backwards, so an editor can label a knob with the
 * point size it really sets while still storing what its engine takes.
 */

export function ptForAbcLyricSize(size) {
    return pxToPt(Number(size) * 3);
}

export function ptForGabcLyricSize(size) {
    return pxToPt(Number(size) * 13 / 3);
}

export function ptForChordproFontSize(size) {
    return pxToPt(Number(size));
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

export function staffHeightMmForAbcPageScale(scale) {
    return Number(scale) * ABC_STAFF_UNITS * MM_PER_PX;
}

export function staffHeightMmForGabcStaffSize(size) {
    return Number(size) * GABC_UNITS_PER_STAFF_SIZE * MM_PER_PX;
}
