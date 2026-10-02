import FONT_ASCENTS from './font-ascents.json' with { type: 'json' };
import { parsePrimaryFontFamily } from './svg-fonts.js';

/**
 * How far a word reaches above its baseline, read from the outlines of the
 * served web fonts rather than asked of the browser.
 *
 * A lyric line hangs from the ink height of its syllables, and a browser reports
 * that height in steps of 1/64 of the font size, rounded its own way: Windows
 * measured Barlow Condensed's lower-case letters a step shorter than Android and
 * Linux did, and the same chant came to a page a unit shorter there. Every
 * screen must cut a deck into the same slides, so the height is taken from the
 * font files, which say the same thing everywhere.
 *
 * The table is written by `npm run fonts:ascents` — see
 * resources/fonts/generate-ascents.mjs.
 */

/** @type {Map<string, {unitsPerEm: number, ascender: number, heights: Map<string, number>}>|null} */
let faces = null;

function decodedFaces() {
    if (faces) { return faces; }

    faces = new Map();

    for (const [key, face] of Object.entries(FONT_ASCENTS)) {
        const heights = new Map();

        for (const [height, characters] of Object.entries(face.heights)) {
            for (const character of characters) { heights.set(character, Number(height)); }
        }

        faces.set(key, { unitsPerEm: face.unitsPerEm, ascender: face.ascender, heights });
    }

    return faces;
}

/**
 * The served face a browser would draw a family in, chosen the way CSS
 * chooses: the italic where there is one, and the nearest weight — for normal
 * text the weight itself or the next one up to 500 first, for bold the weight
 * or the nearest heavier one first.
 *
 * @return {{unitsPerEm: number, ascender: number, heights: Map<string, number>}|null}
 */
export function servedFace(fontFamily, bold = false, italic = false) {
    const family = parsePrimaryFontFamily(fontFamily);
    const all = decodedFaces();
    const ofFamily = [...all.keys()].filter((key) => key.startsWith(`${family}|`));

    if (ofFamily.length === 0) { return null; }

    const wanted = italic && ofFamily.some((key) => key.endsWith('|italic')) ? 'italic' : 'normal';
    const weights = ofFamily
        .filter((key) => key.endsWith(`|${wanted}`))
        .map((key) => Number(key.split('|')[1]))
        .sort((a, b) => a - b);

    if (weights.length === 0) { return null; }

    const target = bold ? 700 : 400;
    const lighter = weights.filter((weight) => weight < target).reverse();
    const heavier = weights.filter((weight) => weight > target);
    const order = weights.includes(target) ? [target]
        : target <= 500
            ? [...heavier.filter((weight) => weight <= 500), ...lighter, ...heavier.filter((weight) => weight > 500)]
            : [...heavier, ...lighter];

    return all.get(`${family}|${order[0]}|${wanted}`) ?? null;
}

/**
 * The ink height of a word set at `fontSize` in a served face, or null where
 * the face, or any letter of the word, is not in the table — which leaves the
 * caller to ask the browser.
 *
 * A word with no ink at all reaches the face's own ascender, as a browser
 * answers when there is nothing to measure.
 *
 * @return {number|null}
 */
export function tableAscent(text, fontSize, fontFamily, bold = false, italic = false) {
    const face = servedFace(fontFamily, bold, italic);

    if (!face) { return null; }

    let highest = 0;

    for (const character of String(text)) {
        const height = face.heights.get(character);

        if (height === undefined) { return null; }
        if (height > highest) { highest = height; }
    }

    return (highest > 0 ? highest : face.ascender) * Number(fontSize) / face.unitsPerEm;
}
