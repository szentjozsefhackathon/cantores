import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import test from 'node:test';
import vm from 'node:vm';

import { ABC_LYRIC_FIRST_SKIP_MIN, abcMixin, buildAbcPreamble } from '../../resources/js/score-editor-abc.js';

const abc2svgSource = readFileSync(
    fileURLToPath(new URL('../../public/js/abc2svg-1.js', import.meta.url)),
    'utf8',
);

/** The lyric size every fixture is engraved at, and the ascent that follows from it. */
const LYRIC_SIZE = 36;

/**
 * Outside a browser abc2svg has no font metrics to measure, so the patch falls
 * back to this fraction of the line height — which is itself the font size
 * there. In a browser both come from the face itself.
 */
const FALLBACK_ASCENT = LYRIC_SIZE * 0.78;

test('the factory default sets the first lyric one ascent below the staff', () => {
    assert.equal(abcMixin().abcLyricFirstSkip, 1);
    assert.match(buildAbcPreamble(abcMixin(), 1700), /%%lyricfirstskipfac 1\n/);
});

test('abcLyricFirstSkip is a persisted per-score field', () => {
    assert.ok(abcMixin().abcFields.includes('abcLyricFirstSkip'));
});

test('a positive abcLyricFirstSkip becomes a %%lyricfirstskipfac directive', () => {
    const preamble = buildAbcPreamble({ ...abcMixin(), abcLyricFirstSkip: 0.7 }, 1700);

    assert.match(preamble, /%%lyricfirstskipfac 0\.7\n/);
});

test('a below-floor, blank or junk abcLyricFirstSkip emits nothing', () => {
    assert.equal(ABC_LYRIC_FIRST_SKIP_MIN, 0.5);

    for (const abcLyricFirstSkip of [0, '', '0', 'x', -1, null, undefined, 0.4, 0.49]) {
        assert.doesNotMatch(
            buildAbcPreamble({ ...abcMixin(), abcLyricFirstSkip }, 1700),
            /%%lyricfirstskipfac/,
            `abcLyricFirstSkip=${JSON.stringify(abcLyricFirstSkip)}`,
        );
    }
});

// %%vocalspace can only push the lyrics further from the staff, never closer
// than the stems hang, so it is pinned out of the way and this knob — counted
// from the bottom staff line — is left as the only staff-to-lyrics control.
test('%%vocalspace is pinned to nothing, whatever the score once stored', () => {
    assert.match(buildAbcPreamble({ ...abcMixin(), abcVocalSpace: 30 }, 1700), /%%vocalspace 0\n/);
});

// Guards the vendor patch against a silent loss on the next abc2svg upgrade.
// See docs/vendor-patches.md — patch "lyricfirstskipfac".
test('the abc2svg vendor patch for lyricfirstskipfac is in place', () => {
    const markers = abc2svgSource.match(/VENDOR PATCH lyricfirstskipfac/g) ?? [];
    assert.equal(markers.length, 7, 'expected all seven patch sites to be tagged');

    assert.match(abc2svgSource, /lyricfirstskipfac:1\.1,\/\*VENDOR PATCH lyricfirstskipfac\*\//);
    assert.match(abc2svgSource, /case"lyricfirstskipfac":\/\*VENDOR PATCH lyricfirstskipfac\*\/case"lyricskipfac":/);
    assert.match(abc2svgSource, /function lyric_ascent\(font,a_h\)/);
    assert.match(abc2svgSource, /var lff=tsfirst\.fmt\.lyricfirstskipfac\|\|1\.1/);

    // The anchor is the bottom staff line, and the lowest ink of the music is
    // only a floor under it — the inverse of what abc2svg does on its own.
    assert.match(abc2svgSource, /yg=y\*sc-asc\*\.35;yl=-tsfirst\.fmt\.vocalspace\*sc-asc\*lff/);
    assert.match(abc2svgSource, /y=\(yl<yg\?yl:yg\)-a_h\[0\]\*\.22/);
});

/**
 * Engraves `abc` with a freshly evaluated abc2svg and returns, per system, how
 * far below the bottom staff line each lyric baseline sits.
 *
 * abc2svg nests a system's lyrics in a `<g>` translated to that staff's bottom
 * line, so the `y` of a lyric `<text>` is that distance already.
 *
 * @return {Array<Array<number>>} one array of baselines per engraved system
 */
function lyricBaselines(directives, tune) {
    const sandbox = { abc2svg: {} };
    vm.createContext(sandbox);
    vm.runInContext(abc2svgSource, sandbox);

    let svg = '';
    const engraver = new sandbox.abc2svg.Abc({
        img_out: (str) => { svg += str; },
        errmsg: () => {},
        read_file: () => null,
    });
    engraver.tosvg(
        'lyricfirstskipfac',
        `%%pagewidth 642.52px\n%%pagescale 1\n%%vocalfont "Merriweather" ${LYRIC_SIZE}\n`
        + `%%musicspace 0\n%%topspace 0\n%%vocalspace 0\n${directives}${tune}`,
    );

    const systems = [];
    for (const [, body] of svg.matchAll(/<g transform="translate\(0,[\d.]+\)">([\s\S]*?)<\/g>/g)) {
        const ys = [...new Set(
            [...body.matchAll(/<text class="f\d+" x="[\d.]+" y="([-\d.]+)"/g)].map((m) => Number(m[1])),
        )];
        if (ys.length) { systems.push(ys); }
    }
    assert.ok(systems.length, 'the tune should engrave some lyrics');

    return systems;
}

/** The baselines are floats built by summing text heights, so compare them as such. */
function sameSpot(actual, expected, what) {
    assert.ok(Math.abs(actual - expected) < 0.11, `${what}: ${actual} vs ${expected}`);
}

// Nothing in it reaches below the bottom staff line but the lowest note head,
// so the anchor governs at every setting and the ink floor stays out of the way
// — it gets its own test below.
const ONE_SYSTEM = 'X:1\nK:C\nL:1/4\nGAGF|EFGA|\nw: la la la la la la la la\nw: ti ti ti ti ti ti ti ti\n';

// The hymn that showed the fault: its three systems dip 8, 4 and 11 units below
// the bottom staff line, and under abc2svg's own rule — which counts the gap
// from the lowest ink — that alone spread the lyrics 32.1, 36.1 and 39.1 units
// from the staff on one page, at one setting.
const THREE_SYSTEMS = 'X:1\nK:Eb\nL:1/4\n'
    + 'E F G G | G A c2 | B4 | E F G G | G A B2 | G4 | B B c B | A G F G |'
    + ' B A G2 | F4 | B B c B | A G F G | A G F2 | E4 |]\n'
    + 'w: Áld-jad em-ber e nagy Jó-dat, Ke-nyér-szín-ben Meg-vál-tó-dat. Itt je-len van szent tes-té-vel'
    + ' é-des Jé-zus, Je-len va-gyon szent vé-ré-vel ál-dott Jé-zus.\n';

test('every system of a tune puts its lyrics the same distance from the staff', () => {
    const systems = lyricBaselines('%%lyricfirstskipfac 1\n', THREE_SYSTEMS);

    assert.ok(systems.length >= 3, `expected the hymn to break into systems, got ${systems.length}`);
    for (const [i, baselines] of systems.entries()) {
        sameSpot(baselines[0], FALLBACK_ASCENT, `system ${i + 1} of ${systems.length}`);
    }
});

test('%%lyricfirstskipfac scales that distance, and only it', () => {
    const one = lyricBaselines('%%lyricfirstskipfac 1\n', ONE_SYSTEM)[0];
    const tight = lyricBaselines('%%lyricfirstskipfac 0.5\n', ONE_SYSTEM)[0];
    const loose = lyricBaselines('%%lyricfirstskipfac 2\n', ONE_SYSTEM)[0];

    sameSpot(one[0], FALLBACK_ASCENT, 'a factor of 1 is one ascent below the bottom line');
    sameSpot(tight[0], FALLBACK_ASCENT * 0.5, 'half an ascent');
    sameSpot(loose[0], FALLBACK_ASCENT * 2, 'two ascents');

    // Whatever it does to the staff gap, the stanzas keep their own spacing.
    const gap = (baselines) => baselines[1] - baselines[0];
    sameSpot(gap(tight), gap(one), 'a tight staff gap leaves the stanzas alone');
    sameSpot(gap(loose), gap(one), 'a loose staff gap leaves the stanzas alone');
});

// The anchor is not the whole rule: music that hangs far enough below the staff
// would be written over, so the lowest ink is still a floor — it just no longer
// sets the distance for every ordinary system.
test('music hanging below the staff pushes the lyrics out of its way', () => {
    const deep = 'X:1\nK:C clef=treble\nL:1/4\nC,D,E,F,|GABc|\nw: la la la la la la la la\n';
    const [baselines] = lyricBaselines('%%lyricfirstskipfac 1\n', deep);

    // The lowest ink of that first bar sits 31 units under the bottom line, and
    // the first baseline clears it by a further .35 of an ascent.
    sameSpot(baselines[0], 31 + FALLBACK_ASCENT * 0.35, 'the ink floor');
    assert.ok(baselines[0] > FALLBACK_ASCENT, 'the floor should win over the anchor here');
});

test('the two factors answer to nothing but themselves', () => {
    const both = lyricBaselines('%%lyricfirstskipfac 0.5\n%%lyricskipfac 2\n', ONE_SYSTEM)[0];
    const first = lyricBaselines('%%lyricfirstskipfac 0.5\n', ONE_SYSTEM)[0];
    const skip = lyricBaselines('%%lyricskipfac 2\n', ONE_SYSTEM)[0];

    sameSpot(both[0], first[0], 'the staff gap');
    sameSpot(both[1] - both[0], skip[1] - skip[0], 'the stanza gap');
});

test('%%lyricskipfac no longer moves the first lyric line', () => {
    const builtIn = lyricBaselines('', ONE_SYSTEM)[0];
    const loose = lyricBaselines('%%lyricskipfac 2\n', ONE_SYSTEM)[0];

    sameSpot(loose[0], builtIn[0], 'the staff gap is not lyricskipfac\'s business');
    assert.ok(loose[1] - loose[0] > builtIn[1] - builtIn[0], '2 should open the stanza gap');
});
