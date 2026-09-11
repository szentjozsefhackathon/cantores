import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

import { hungarianChordsToAbc } from '../../resources/js/score-editor-abc.js';

/**
 * End-to-end cover for Hungarian ABC chord symbols.
 *
 * `hungarianChordsToAbc()` rewrites the roots to English so abc2svg can parse and
 * transpose them; the packaged fork's `huchords` hook then rewrites every root
 * and slashed bass onto the
 * fixed palette `C Db D Eb E F Gb G Ab A Bb H` after abc2svg has transposed.
 * The engraver is run for real, headless.
 */

const FLAT = '♭';
const ABC2SVG_SRC = readFileSync(new URL('../../node_modules/@cantoreshu/abc2svg/abc2svg-1.js', import.meta.url), 'utf8');

function loadAbc2svg() {
    const sandbox = { abc2svg: {} };
    vm.createContext(sandbox);
    vm.runInContext(ABC2SVG_SRC, sandbox);

    return sandbox.abc2svg;
}

function renderChords(abc) {
    const abc2svg = loadAbc2svg();
    let svg = '';
    const engraver = new abc2svg.Abc({
        img_out: (str) => { svg += str; },
        errmsg: () => {},
        read_file: () => null,
    });
    engraver.tosvg('hu-chords', abc);

    // Guitar-chord <text> nodes start with a note letter A-H; drop the music-font
    // glyph nodes (clef, meter) and any lyric syllables.
    return [...svg.matchAll(/<text[^>]*>([^<]*)<\/text>/g)]
        .map((m) => m[1])
        .filter((t) => t && t.charCodeAt(0) >= 65 && t.charCodeAt(0) <= 72);
}

function chords(abcBody, transpose = 0) {
    const head = 'X:1\nL:1/4\nK:C\n';
    const pre = transpose ? `%%transpose ${transpose}\n` : '';

    return renderChords(pre + head + hungarianChordsToAbc(abcBody));
}

// "H" is B natural, "B" is B flat - the spelling used in the repertoire.
const BODY = '"C"G2 "H"GF | "B"ED "Bb"C2 | "C/H"BA "F/B"G2 |\n';

test('the huchords hook is registered', () => {
    assert.equal(typeof loadAbc2svg().mhooks?.huchords, 'function');
});

test('untransposed: B flat prints as B flat, B natural as H', () => {
    assert.deepEqual(chords(BODY), ['C', 'H', `B${FLAT}`, `B${FLAT}`, 'C/H', `F/B${FLAT}`]);
});

test('a semitone up: every chord moves, "H" and "B" included', () => {
    // C->Db, H(B natural)->C, B and Bb (B flat)->H, and the slashed basses too.
    assert.deepEqual(chords(BODY, 1), [`D${FLAT}`, 'C', 'H', 'H', `D${FLAT}/C`, `G${FLAT}/H`]);
});

test('a fourth up: chords land on the flat side of the palette', () => {
    // C->F, H->E, B/Bb->Eb, C/H->F/E, F/B->Bb/Eb.
    assert.deepEqual(chords(BODY, 5), ['F', 'E', `E${FLAT}`, `E${FLAT}`, 'F/E', `B${FLAT}/E${FLAT}`]);
});

test('the exact cases from the report', () => {
    assert.deepEqual(chords('"B"C "H"D "Bb"E |\n'), [`B${FLAT}`, 'H', `B${FLAT}`]);
    assert.deepEqual(chords('"B"C "H"D "Bb"E |\n', 1), ['H', 'C', 'H']);
});

test('odd enharmonic spellings are normalised even without transposing', () => {
    // Cb == B natural == H, E# == F, F# folds onto the flat palette as Gb.
    assert.deepEqual(chords('"Cb"C "E#"D "F#"E |\n'), ['H', 'F', `G${FLAT}`]);
});
