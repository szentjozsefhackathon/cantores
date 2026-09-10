import assert from 'node:assert/strict';
import test from 'node:test';

import { ABC_PAGE_WIDTH_DEFAULT, abcMixin, abcStrokeWidths, buildAbcPreamble, hungarianChordsToAbc, normalizeAbcPageWidth } from '../../resources/js/score-editor-abc.js';
import { DEFAULT_PAGE_WIDTH_MM, mmToPx, pxToMm, staffHeightMmForAbcPageScale } from '../../resources/js/booklet-geometry.js';

test('normalizes ABC page width to the renderer-safe range', () => {
    assert.equal(normalizeAbcPageWidth(30), 100);
    assert.equal(normalizeAbcPageWidth('30'), 100);
    assert.equal(normalizeAbcPageWidth(1800), 1800);
    assert.equal(normalizeAbcPageWidth(5000), 3000);
    assert.equal(normalizeAbcPageWidth(''), ABC_PAGE_WIDTH_DEFAULT);
    assert.equal(normalizeAbcPageWidth('not-a-number'), ABC_PAGE_WIDTH_DEFAULT);
});

test('the ABC page is the same sheet of paper the Aretino editor engraves on', () => {
    assert.equal(ABC_PAGE_WIDTH_DEFAULT, 642.52);
    // Exactly the page it says it is: the toolbar states this width in
    // millimetres, and a whole 170 has to come back out of it as a whole 170.
    assert.equal(Math.round(pxToMm(ABC_PAGE_WIDTH_DEFAULT) * 100) / 100, DEFAULT_PAGE_WIDTH_MM);
    assert.equal(normalizeAbcPageWidth(mmToPx(DEFAULT_PAGE_WIDTH_MM)), ABC_PAGE_WIDTH_DEFAULT);
});

test('turns a settings bucket into abc2svg directives', () => {
    const preamble = buildAbcPreamble({
        abcLyricFont: 'Barlow Condensed',
        abcLyricSize: 31,
        abcLyricBold: true,
        abcPageScale: 3.1,
        abcNoteSpacing: 1.1,
        abcStaffSep: 15,
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

    assert.match(preamble, /%%vocalfont Alegreya 36\n/);
    assert.match(preamble, /%%pagescale 1\n/);
    assert.doesNotMatch(preamble, /%%transpose/);
});

test('the factory defaults describe an untransposed paper page', () => {
    const defaults = abcMixin();
    const preamble = buildAbcPreamble(defaults, normalizeAbcPageWidth(defaults.abcPageWidth));

    assert.match(preamble, /%%pagewidth 642\.52px\n/);
    assert.match(preamble, /%%pagescale 0\.9449\n/);
    // The rendered lyric size is the directive times the page scale, which comes
    // to 11 pt whatever the scale; see abcLyricSizeForPt.
    assert.match(preamble, /%%vocalfont Alegreya 15\.522\n/);
    assert.match(preamble, /%%staffsep 36\n/);
    assert.doesNotMatch(preamble, /%%transpose/);
});

test('the factory staff is six millimetres tall', () => {
    assert.ok(Math.abs(staffHeightMmForAbcPageScale(abcMixin().abcPageScale) - 6) < 0.005);
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

test('keeps stem and staff-line widths on the projector ratios only', () => {
    const settings = { abcStemWidth: 1.4, abcStaffLineWidth: 1 };

    assert.deepEqual(abcStrokeWidths({ ...settings, abcPageRatio: '16/9' }), { stem: 1.4, staffLine: 1 });
    assert.deepEqual(abcStrokeWidths({ ...settings, abcPageRatio: '4/3' }), { stem: 1.4, staffLine: 1 });
    assert.deepEqual(abcStrokeWidths({ ...settings, abcPageRatio: '1/1' }), { stem: 1.4, staffLine: 1 });
    assert.deepEqual(abcStrokeWidths({ ...settings, abcPageRatio: 'paper' }), { stem: 0.7, staffLine: 0.7 });
    assert.deepEqual(abcStrokeWidths({ ...settings, abcPageRatio: 'responsive' }), { stem: 0.7, staffLine: 0.7 });
});
