import assert from 'node:assert/strict';
import test from 'node:test';

import { abcMixin, buildAbcPreamble, hungarianChordsToAbc, normalizeAbcPageWidth } from '../../resources/js/score-editor-abc.js';

test('normalizes ABC page width to the renderer-safe range', () => {
    assert.equal(normalizeAbcPageWidth(130), 400);
    assert.equal(normalizeAbcPageWidth('130'), 400);
    assert.equal(normalizeAbcPageWidth(1800), 1800);
    assert.equal(normalizeAbcPageWidth(5000), 4000);
    assert.equal(normalizeAbcPageWidth(''), 1700);
    assert.equal(normalizeAbcPageWidth('not-a-number'), 1700);
});

test('turns a settings bucket into abc2svg directives', () => {
    const preamble = buildAbcPreamble({
        abcLyricFont: 'Barlow Condensed',
        abcLyricSize: 31,
        abcLyricBold: true,
        abcPageScale: 3.1,
        abcNoteSpacing: 1.1,
        abcStaffSep: 15,
        abcVocalSpace: 0,
        abcTranspose: -2,
    }, 1920);

    assert.match(preamble, /^%%fullsvg 1\n%%pagewidth 1920px\n/);
    assert.match(preamble, /%%pagescale 3\.1\n/);
    assert.match(preamble, /%%vocalfont "Barlow Condensed" bold 30\n/);
    assert.match(preamble, /%%staffsep 15\n/);
    assert.match(preamble, /%%transpose -2\n$/);
});

test('falls back to a safe font and scale for unusable settings', () => {
    const preamble = buildAbcPreamble({ abcLyricFont: 'Comic Sans; }', abcLyricSize: 0, abcPageScale: 0 }, 1700);

    assert.match(preamble, /%%vocalfont "EB Garamond" 36\n/);
    assert.match(preamble, /%%pagescale 1\n/);
    assert.doesNotMatch(preamble, /%%transpose/);
});

test('the factory defaults describe an untransposed paper page', () => {
    const preamble = buildAbcPreamble(abcMixin(), normalizeAbcPageWidth(abcMixin().abcPageWidth));

    assert.match(preamble, /%%pagewidth 1700px\n/);
    assert.match(preamble, /%%pagescale 2\.3\n/);
    assert.match(preamble, /%%vocalfont "EB Garamond" 15\.652\n/);
    assert.match(preamble, /%%staffsep 36\n/);
    assert.doesNotMatch(preamble, /%%transpose/);
});

test('rewrites Hungarian chord roots to the English spelling abc2svg parses', () => {
    const source = hungarianChordsToAbc('K:C\n"C"G2 "H"GF | "B"E "Hm7"D "C/H"C "F/B"B |\n');

    assert.match(source, /"C"G2 "B"GF/);          // H  -> B  (B natural)
    assert.match(source, /"Bb"E "Bm7"D/);         // B  -> Bb (B flat); Hm7 -> Bm7
    assert.match(source, /"C\/B"C "F\/Bb"B/);     // slashed bass note too
});

test('leaves an already English B flat, information fields and annotations alone', () => {
    assert.equal(hungarianChordsToAbc('"Bb"C "Bbm"D\n'), '"Bb"C "Bbm"D\n');
    assert.equal(hungarianChordsToAbc('T:Best of B\nw: hall-B-ha\n'), 'T:Best of B\nw: hall-B-ha\n');
    assert.equal(hungarianChordsToAbc('"^Bridge"C "_Boo"D\n'), '"^Bridge"C "_Boo"D\n');
});
