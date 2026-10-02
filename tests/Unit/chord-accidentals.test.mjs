import assert from 'node:assert/strict';
import test from 'node:test';
import * as fontkit from 'fontkit';

/*
 * abc2svg writes a chord symbol's accidentals as ♯ ♭ ♮ 𝄪 𝄫, which no text face
 * served here has, and every system drew them from a face of its own — so the
 * same chord sheet came to a different height on every screen. The five are cut
 * out of Bravura into a face of their own, raised to sit on a line of text.
 */

const font = fontkit.openSync(new URL('../../public/fonts/chord-accidentals.otf', import.meta.url).pathname);

test('every accidental abc2svg writes into a chord symbol is in the face', () => {
    for (const codePoint of [0x266F, 0x266D, 0x266E, 0x1D12A, 0x1D12B]) {
        assert.notEqual(font.glyphForCodePoint(codePoint).id, 0, `U+${codePoint.toString(16)}`);
    }
});

/* Bravura centres its accidentals on the staff line; a chord's stand on the
   baseline and reach no higher than the line box the letters already make. */
test('the accidentals sit on the baseline, inside the letters\' line', () => {
    for (const codePoint of [0x266F, 0x266D, 0x266E, 0x1D12A, 0x1D12B]) {
        const { minY, maxY } = font.glyphForCodePoint(codePoint).bbox;

        assert.ok(minY >= 0, `U+${codePoint.toString(16)} hangs below the baseline`);
        assert.ok(maxY <= font.ascent, `U+${codePoint.toString(16)} reaches above the face's ascender`);
    }

    assert.ok(font.ascent - font.descent <= font.unitsPerEm, 'a chord with a sharp in it would make a taller line');
});

test('the Modified Version does not carry the Reserved Font Name', () => {
    assert.equal(font.familyName, 'Chord Accidentals');
    assert.match(font.copyright, /Bravura/);
});
