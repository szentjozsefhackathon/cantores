/**
 * Writes public/fonts/chord-accidentals.otf: the five accidentals abc2svg
 * writes into a chord symbol, cut out of Bravura and raised to sit on a line
 * of text.
 *
 * abc2svg turns the `#`, `b` and `=` of a chord symbol into ♯ ♭ ♮ (and 𝄪 𝄫),
 * and no text face this application serves has them. Each system then drew them
 * from a face of its own — Android's stood a good deal taller than Windows's —
 * and a chord sheet that fitted on one screen ran over on the next. Bravura has
 * all five at those code points, but it is half a megabyte and an engraving
 * face, whose accidentals sit centred on the staff line, half below the
 * baseline; so the five are taken out of it and lifted by RAISE.
 *
 * Bravura is under the SIL Open Font License with the Reserved Font Name
 * "Bravura", so this Modified Version carries a name of its own, keeps Bravura's
 * notice in its metadata, and is listed in public/fonts/OFL.txt.
 *
 * Run after changing anything here:
 *
 *     npm run fonts:accidentals
 */
import { writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import * as fontkit from 'fontkit';
import opentype from 'opentype.js';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');

/** What abc2svg writes into a chord symbol — see gch_build() in abc2svg-1.js. */
const ACCIDENTALS = [
    { name: 'sharp', codePoint: 0x266F },
    { name: 'flat', codePoint: 0x266D },
    { name: 'natural', codePoint: 0x266E },
    { name: 'doubleSharp', codePoint: 0x1D12A },
    { name: 'doubleFlat', codePoint: 0x1D12B },
];

/**
 * How far every accidental is lifted, in Bravura's thousandths of an em. Its
 * accidentals are centred on y = 0, the staff line they belong to; lifted by
 * this much a sharp stands on the baseline and reaches the capitals' height,
 * and the others keep their places beside it.
 */
const RAISE = 350;

/** Room either side, so a sharp does not touch the letter before or after it. */
const SIDE_BEARING = 30;

const bravura = fontkit.openSync(resolve(root, 'resources/fonts/Bravura.otf'));

/** One Bravura glyph, lifted and moved right by its side bearing. */
function liftedPath(glyph) {
    const path = new opentype.Path();
    const at = (x, y) => [x + SIDE_BEARING, y + RAISE];

    for (const { command, args } of glyph.path.commands) {
        if (command === 'moveTo') { path.moveTo(...at(args[0], args[1])); }
        if (command === 'lineTo') { path.lineTo(...at(args[0], args[1])); }
        if (command === 'quadraticCurveTo') { path.quadTo(...at(args[0], args[1]), ...at(args[2], args[3])); }
        if (command === 'bezierCurveTo') { path.curveTo(...at(args[0], args[1]), ...at(args[2], args[3]), ...at(args[4], args[5])); }
        if (command === 'closePath') { path.close(); }
    }

    return path;
}

const glyphs = [
    new opentype.Glyph({ name: '.notdef', advanceWidth: 500, path: new opentype.Path() }),
    ...ACCIDENTALS.map(({ name, codePoint }) => {
        const glyph = bravura.glyphForCodePoint(codePoint);

        if (glyph.id === 0) { throw new Error(`Bravura has no U+${codePoint.toString(16)}`); }

        return new opentype.Glyph({
            name,
            unicode: codePoint,
            advanceWidth: Math.round(glyph.advanceWidth + 2 * SIDE_BEARING),
            path: liftedPath(glyph),
        });
    }),
];

const [notice] = bravura.copyright.split('\n\n');

const font = new opentype.Font({
    familyName: 'Chord Accidentals',
    styleName: 'Regular',
    unitsPerEm: bravura.unitsPerEm,
    // Inside the text faces' own, so a chord with a sharp in it is no taller a
    // line than one without.
    ascender: 800,
    descender: -200,
    copyright: `${notice} Modified Version: five accidentals, raised to sit on a line of text, for cantores.hu.`,
    // The license itself is in OFL.txt beside the font, with this notice.
    license: 'This Font Software is licensed under the SIL Open Font License, Version 1.1; see OFL.txt beside this font.',
    licenseURL: 'http://scripts.sil.org/OFL',
    version: '1.0',
    glyphs,
});

const target = resolve(root, 'public/fonts/chord-accidentals.otf');

writeFileSync(target, Buffer.from(font.toArrayBuffer()));

console.log(`chord-accidentals.otf: ${glyphs.length - 1} glyphs`);
