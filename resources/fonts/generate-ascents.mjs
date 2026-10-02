/**
 * Writes resources/js/font-ascents.json: how far above the baseline every
 * letter of every web font this application serves reaches, read straight out
 * of the font files.
 *
 * Every screen engraves a deck for itself and must cut it into the same slides,
 * and a lyric line hangs from the ink height of its syllables. A browser
 * reports that height in steps of 1/64 of the font size and rounds it its own
 * way: Windows measured the lower-case letters of Barlow Condensed a step
 * shorter than Android and Linux did, and a page came to a unit less there.
 * The outlines themselves say the same thing everywhere, so the height is
 * taken from them instead. See resources/js/font-ascents.js.
 *
 * Run after adding or replacing a web font:
 *
 *     npm run fonts:ascents
 *
 * The faces are the ones svg-fonts.js serves: a family split into Latin and
 * Latin Extended files is put back together, and a variable face is read at the
 * weights a lyric is set in.
 */
import { writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import * as fontkit from 'fontkit';
import { WEB_FONTS } from '../js/svg-fonts.js';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');

/** The weights a lyric asks for: its text, and its bold. */
const WEIGHTS = [400, 700];

/**
 * Where each served face's outlines are read from: the whole fonts kept beside
 * this script. fontkit cannot decode the composite glyphs — every accented
 * letter — of a WOFF2 file, so the served files only say which letters they
 * carry, and the outlines, the same drawings, come from these.
 */
const SOURCES = {
    'EB Garamond': { normal: 'EBGaramond.ttf', italic: 'EBGaramond-Italic.ttf' },
    'Alegreya': { normal: 'Alegreya.ttf', italic: 'Alegreya-Italic.ttf' },
    'Merriweather': { normal: 'Merriweather.ttf', italic: 'Merriweather-Italic.ttf' },
    'Inter': { normal: 'Inter.ttf', italic: 'Inter-Italic.ttf' },
    'Barlow Condensed': { normal: { 500: 'BarlowCondensed-Medium.ttf', 700: 'BarlowCondensed-Bold.ttf' } },
};

/** The whole font a served face was cut from, at the weight asked. */
function sourceFont(family, style, weight) {
    const source = SOURCES[family]?.[style];
    const file = typeof source === 'object' ? source?.[weight] : source;

    if (!file) { throw new Error(`no source font for ${family} ${style} ${weight}`); }

    const font = fontkit.openSync(resolve(root, 'resources/fonts/lyric', file));

    return font.variationAxes?.wght ? font.getVariation({ wght: weight }) : font;
}

const faces = {};

for (const [family, files] of Object.entries(WEB_FONTS)) {
    for (const file of files) {
        const [low, high = low] = String(file.weight).split(/\s+/).map(Number);
        const weights = low === high ? [low] : WEIGHTS.filter((weight) => weight >= low && weight <= high);
        const served = fontkit.openSync(resolve(root, 'public', file.url.replace(/^\//, ''))).characterSet;

        for (const weight of weights) {
            const font = sourceFont(family, file.style, weight);
            const key = `${family}|${weight}|${file.style}`;
            const face = (faces[key] ??= { unitsPerEm: font.unitsPerEm, ascender: font.ascent, heights: {} });

            for (const codePoint of served) {
                const glyph = font.glyphForCodePoint(codePoint);

                if (glyph.id === 0) { continue; }

                // A space has no ink, and reaches nowhere.
                const height = Number.isFinite(glyph.bbox.maxY) ? Math.round(glyph.bbox.maxY) : 0;
                const character = String.fromCodePoint(codePoint);

                face.heights[height] ??= '';

                if (!face.heights[height].includes(character)) { face.heights[height] += character; }
            }
        }
    }
}

const sorted = Object.fromEntries(Object.keys(faces).sort().map((key) => [key, faces[key]]));

writeFileSync(resolve(root, 'resources/js/font-ascents.json'), `${JSON.stringify(sorted, null, 1)}\n`);

console.log(`font-ascents.json: ${Object.keys(sorted).length} faces`);
