import { pxToMm } from './booklet-geometry.js';

/**
 * The booklet as one person's screen, rather than as paper.
 *
 * A booklet is engraved for a sheet: A5, twelve millimetres of margin, one lyric
 * size chosen so the whole service fits. The people sent the link are not
 * holding that sheet. They are holding a phone in portrait, a tablet on a stand,
 * a laptop at the back of the church — and what they need is the same booklet
 * laid out for the width they actually have, at the size their own eyes want.
 *
 * So the reader keeps a geometry of their own, derived here. Two things go into
 * it: the screen, which decides the width and therefore where every line breaks,
 * and the reader, who decides how big it all is. Everything else — the face, the
 * heading proportions, the stacking — is the cantor's and is inherited.
 *
 * Nothing here touches the booklet. These values live on the device that
 * computed them; the handout the cantor made is untouched by anyone reading it.
 */

/**
 * The margin a screen wants.
 *
 * Paper needs a wide one because paper is held, bound and trimmed. A screen is
 * none of those things, and every millimetre given to a margin is taken off the
 * width the music is engraved at — which on a phone is the scarcest thing there
 * is. So: enough that the notes do not touch the bezel, and no more.
 */
export const READER_MARGIN_MM = 3;

export const ZOOM_MIN = 0.5;
export const ZOOM_MAX = 3;
export const ZOOM_STEP = 0.1;

/**
 * One, meaning "the booklet's own proportions".
 *
 * The default is not a size in points but a likeness: at 1 the screen shows what
 * the printed page shows, scaled to whatever width it has, so a musician who has
 * seen the paper booklet recognises this one. Everything above and below is that
 * likeness deliberately given up in exchange for legibility.
 */
export const ZOOM_DEFAULT = 1;

export function clampZoom(value) {
    const zoom = Number(value);

    if (!Number.isFinite(zoom)) { return ZOOM_DEFAULT; }

    return Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, Math.round(zoom * 100) / 100));
}

/**
 * One of the reader's per-score knobs, moved a step.
 *
 * Two things a plain `value + step` gets wrong on a music stand. It steps from
 * where the score is actually being drawn — the size a screen's geometry
 * computed, an arbitrary fraction — so pressing bigger four times leaves a
 * reader on 12.9067; snapping to the step's own grid keeps the numbers the
 * booklet is engraved at tidy. And it walks past the ends: a transposition is
 * eleven semitones each way and a picture cannot be enlarged past the page.
 *
 * @param {number} current what the score is drawn at now
 * @param {{min: number, max: number, step: number}} field from readerPanelFor()
 * @param {number} direction -1 or 1
 */
export function steppedValue(current, field, direction) {
    const step = Number(field.step) || 1;
    const from = Number.isFinite(Number(current)) ? Number(current) : 0;
    const stepped = Math.round((from + direction * step) / step) * step;
    const clamped = Math.min(Number(field.max), Math.max(Number(field.min), stepped));

    return Math.round(clamped * 1e4) / 1e4;
}

/**
 * A height no flowing booklet can reach.
 *
 * The reader's booklet does not paginate — see renderBookletFlow — but the
 * geometry it is laid out with has the same shape as the printed one, and a page
 * height belongs in that shape. Rather than leave it undefined, it is set past
 * anything an entry can be, so that any code that does consult it packs nothing.
 */
const UNBOUNDED_MM = 100000;

/**
 * The geometry for one screen, at one reader's chosen size.
 *
 * @param {object} booklet Booklet::geometry() as the server sent it
 * @param {number} widthPx how wide the column holding the pages actually is
 * @param {{zoom?: number, textFont?: string|null}} settings the reader's own
 */
export function readerGeometry(booklet, widthPx, settings = {}) {
    const pageWidthMm = Math.max(20, pxToMm(Number(widthPx) || 0));
    const contentWidthMm = Math.max(10, pageWidthMm - 2 * READER_MARGIN_MM);

    // The whole point of the default: the type shrinks and grows with the width
    // it is set in, so a narrow screen shows the booklet's own proportions
    // rather than the booklet's own point sizes crammed into half the room.
    const proportion = contentWidthMm / Math.max(1, Number(booklet.contentWidthMm) || 1);
    const scale = proportion * clampZoom(settings.zoom ?? ZOOM_DEFAULT);

    return {
        pageWidthMm,
        pageHeightMm: UNBOUNDED_MM,
        marginMm: READER_MARGIN_MM,
        contentWidthMm,
        contentHeightMm: UNBOUNDED_MM,
        lyricSizePt: (Number(booklet.lyricSizePt) || 10.5) * scale,
        staffHeightMm: (Number(booklet.staffHeightMm) || 5) * scale,
        // Inherited, all three. A face, a heading's proportion to its lyrics and
        // the air abc2svg leaves between staves are the cantor's typography, and
        // they read the same at any width — only the face is offered to the
        // reader, because that one is about eyes rather than about the page.
        textFont: settings.textFont || booklet.textFont,
        headingScale: booklet.headingScale,
        abcStaffSep: booklet.abcStaffSep,
    };
}

/**
 * Where one booklet's reading settings are kept.
 *
 * Per link, because a musician may hold several: last Sunday's, this Sunday's,
 * the wedding. Per device, because that is what the setting is about — the
 * screen in this hand and the eyes above it. Nothing is sent anywhere.
 */
export function readerStorageKey(token) {
    return `booklet-reader:${token}`;
}

/**
 * Read what this device remembers, defensively.
 *
 * Storage can be off, full, or hold something a previous version wrote. A
 * booklet that will not open because a preference could not be parsed is a
 * booklet that fails at exactly the moment it is needed, so anything unreadable
 * is simply forgotten.
 *
 * @returns {{zoom: number, textFont: string|null, overrides: object}}
 */
export function readReaderSettings(storage, token) {
    const empty = { zoom: ZOOM_DEFAULT, textFont: null, overrides: {} };

    try {
        const raw = storage?.getItem(readerStorageKey(token));

        if (!raw) { return empty; }

        const saved = JSON.parse(raw);

        return {
            zoom: clampZoom(saved?.zoom ?? ZOOM_DEFAULT),
            textFont: typeof saved?.textFont === 'string' && saved.textFont !== '' ? saved.textFont : null,
            overrides: saved?.overrides && typeof saved.overrides === 'object' ? saved.overrides : {},
        };
    } catch {
        return empty;
    }
}

export function writeReaderSettings(storage, token, settings) {
    try {
        storage?.setItem(readerStorageKey(token), JSON.stringify({
            zoom: clampZoom(settings.zoom),
            textFont: settings.textFont ?? null,
            overrides: settings.overrides ?? {},
        }));
    } catch {
        // A phone with storage disabled still reads the booklet; it just starts
        // afresh next time.
    }
}
