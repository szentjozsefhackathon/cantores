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

test("the factory default is abc2svg's own advance, spelled out", () => {
    assert.equal(abcMixin().abcLyricFirstSkip, 1.1);
    assert.match(buildAbcPreamble(abcMixin(), 1700), /%%lyricfirstskipfac 1\.1\n/);
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
// from the music's own lowest ink — is left as the only staff-to-lyrics control.
test('%%vocalspace is pinned to nothing, whatever the score once stored', () => {
    assert.match(buildAbcPreamble({ ...abcMixin(), abcVocalSpace: 30 }, 1700), /%%vocalspace 0\n/);
});

// Guards the vendor patch against a silent loss on the next abc2svg upgrade.
// See docs/vendor-patches.md — patch "lyricfirstskipfac".
test('the abc2svg vendor patch for lyricfirstskipfac is in place', () => {
    const markers = abc2svgSource.match(/VENDOR PATCH lyricfirstskipfac/g) ?? [];
    assert.equal(markers.length, 4, 'expected all four patch sites to be tagged');

    assert.match(abc2svgSource, /lyricfirstskipfac:1\.1,\/\*VENDOR PATCH lyricfirstskipfac\*\//);
    assert.match(abc2svgSource, /case"lyricfirstskipfac":\/\*VENDOR PATCH lyricfirstskipfac\*\/case"lyricskipfac":/);
    assert.match(abc2svgSource, /var lff=tsfirst\.fmt\.lyricfirstskipfac\|\|1\.1/);
    assert.match(abc2svgSource, /y-=a_h\[j\]\*\(j\?lsf:lff\)/);
});

/**
 * Engraves `abc` with a freshly evaluated abc2svg and returns the baseline of
 * each of the two stacked lyric lines, in the SVG's own units.
 *
 * The point of the patch is that these two numbers move independently: the
 * staff → first line distance is the first baseline, and the gap between the
 * stanzas is their difference. An unpatched abc2svg advances by the same 1.1
 * for both, so a lost patch shows up here as the first baseline following
 * %%lyricskipfac around.
 */
function lyricBaselines(directives) {
    const sandbox = { abc2svg: {} };
    vm.createContext(sandbox);
    vm.runInContext(abc2svgSource, sandbox);

    let svg = '';
    const engraver = new sandbox.abc2svg.Abc({
        img_out: (str) => { svg += str; },
        errmsg: (msg, line) => assert.fail(`abc2svg: ${msg} (line ${line})`),
        read_file: () => null,
    });
    engraver.tosvg('lyricfirstskipfac', `%%vocalspace 0\n${directives}X:1\nL:1/4\nK:C\nCDEF|GABc|\nw: la la la la la la la la\nw: ti ti ti ti ti ti ti ti\n`);

    const ys = [...svg.matchAll(/<text[^>]*\by="([-\d.]+)"[^>]*>(la|ti)<\/text>/g)];
    const first = (syllable) => ys.filter((m) => m[2] === syllable).map((m) => Number(m[1]))[0];
    const [la, ti] = [first('la'), first('ti')];
    assert.ok(la !== undefined && ti !== undefined, 'both lyric lines should be engraved');

    return { first: la, gap: ti - la };
}

/** The baselines are floats built by summing text heights, so compare them as such. */
function sameSpot(actual, expected, what) {
    assert.ok(Math.abs(actual - expected) < 1e-6, `${what}: ${actual} vs ${expected}`);
}

test('%%lyricskipfac no longer moves the first lyric line', () => {
    const builtIn = lyricBaselines('');
    const loose = lyricBaselines('%%lyricskipfac 2\n');

    sameSpot(loose.first, builtIn.first, 'the staff gap is not lyricskipfac\'s business');
    assert.ok(loose.gap > builtIn.gap, `2 should open the stanza gap (${loose.gap} vs ${builtIn.gap})`);
});

test('%%lyricfirstskipfac moves the lyrics under the staff, and only that', () => {
    const builtIn = lyricBaselines('');

    sameSpot(lyricBaselines('%%lyricfirstskipfac 1.1\n').first, builtIn.first, "1.1 is abc2svg's own advance");

    const tight = lyricBaselines('%%lyricfirstskipfac 0.5\n');
    const loose = lyricBaselines('%%lyricfirstskipfac 2\n');

    assert.ok(tight.first < builtIn.first, `0.5 should pull the lyrics up (${tight.first} vs ${builtIn.first})`);
    assert.ok(loose.first > builtIn.first, `2 should push them down (${loose.first} vs ${builtIn.first})`);

    // Whatever it does to the staff gap, the stanzas keep their own spacing.
    sameSpot(tight.gap, builtIn.gap, 'a tight staff gap leaves the stanzas alone');
    sameSpot(loose.gap, builtIn.gap, 'a loose staff gap leaves the stanzas alone');
});

test('the two factors answer to nothing but themselves', () => {
    const both = lyricBaselines('%%lyricfirstskipfac 0.5\n%%lyricskipfac 2\n');

    sameSpot(both.first, lyricBaselines('%%lyricfirstskipfac 0.5\n').first, 'the staff gap');
    sameSpot(both.gap, lyricBaselines('%%lyricskipfac 2\n').gap, 'the stanza gap');
});
