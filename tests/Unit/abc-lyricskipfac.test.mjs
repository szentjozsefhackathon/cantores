import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import test from 'node:test';
import vm from 'node:vm';

import { abcMixin, buildAbcPreamble } from '../../resources/js/score-editor-abc.js';

const abc2svgSource = readFileSync(
    fileURLToPath(new URL('../../public/js/abc2svg-1.js', import.meta.url)),
    'utf8',
);

test('the factory defaults leave lyric-line spacing to abc2svg', () => {
    assert.equal(abcMixin().abcLyricSkip, 0);
    assert.doesNotMatch(buildAbcPreamble(abcMixin(), 1700), /%%lyricskipfac/);
});

test('abcLyricSkip is a persisted per-score field', () => {
    assert.ok(abcMixin().abcFields.includes('abcLyricSkip'));
});

test('a positive abcLyricSkip becomes a %%lyricskipfac directive', () => {
    const preamble = buildAbcPreamble({ ...abcMixin(), abcLyricSkip: 1.4 }, 1700);

    assert.match(preamble, /%%lyricskipfac 1\.4\n/);
    assert.match(preamble, /%%vocalspace .*\n%%lyricskipfac 1\.4\n/);
});

test('zero, blank and junk abcLyricSkip emit nothing', () => {
    for (const abcLyricSkip of [0, '', '0', 'x', -1, null, undefined]) {
        assert.doesNotMatch(
            buildAbcPreamble({ ...abcMixin(), abcLyricSkip }, 1700),
            /%%lyricskipfac/,
            `abcLyricSkip=${JSON.stringify(abcLyricSkip)}`,
        );
    }
});

// Guards the vendor patch against a silent loss on the next abc2svg upgrade.
// See docs/vendor-patches.md — patch "lyricskipfac".
test('the abc2svg vendor patch for lyricskipfac is in place', () => {
    const markers = abc2svgSource.match(/VENDOR PATCH lyricskipfac/g) ?? [];
    assert.equal(markers.length, 3, 'expected all three patch sites to be tagged');

    assert.match(abc2svgSource, /lyricskipfac:1\.1,\/\*VENDOR PATCH lyricskipfac\*\//);
    assert.match(abc2svgSource, /case"lyricskipfac":\/\*VENDOR PATCH lyricskipfac\*\/case"maxshrink":/);
    assert.match(abc2svgSource, /var lsf=tsfirst\.fmt\.lyricskipfac\|\|1\.1/);

    const drawLyrics = abc2svgSource.slice(
        abc2svgSource.indexOf('function draw_lyrics('),
        abc2svgSource.indexOf('function draw_all_lyrics('),
    );
    assert.doesNotMatch(drawLyrics, /a_h\[j\]\*1\.1/, 'the hardcoded 1.1 advance should be gone');
    assert.equal((drawLyrics.match(/a_h\[j\]\*lsf/g) ?? []).length, 2);
});

/**
 * Engraves `abc` with a freshly evaluated abc2svg and returns the vertical gap
 * between the two stacked lyric lines, in the SVG's own units.
 *
 * The textual assertions above can only say the patch is still written in the
 * file. This one says it still does something: an abc2svg that does not know
 * `%%lyricskipfac` ignores the directive in silence — no error, no warning,
 * just the built-in 1.1 advance for every value — which is exactly the shape a
 * lost patch would take.
 */
function lyricLineGap(lyricSkipFac) {
    const sandbox = { abc2svg: {} };
    vm.createContext(sandbox);
    vm.runInContext(abc2svgSource, sandbox);

    let svg = '';
    const engraver = new sandbox.abc2svg.Abc({
        img_out: (str) => { svg += str; },
        errmsg: (msg, line) => assert.fail(`abc2svg: ${msg} (line ${line})`),
        read_file: () => null,
    });
    const directive = lyricSkipFac === null ? '' : `%%lyricskipfac ${lyricSkipFac}\n`;
    engraver.tosvg('lyricskipfac', `${directive}X:1\nL:1/4\nK:C\nCDEF|GABc|\nw: la la la la la la la la\nw: ti ti ti ti ti ti ti ti\n`);

    const ys = [...svg.matchAll(/<text[^>]*\by="([-\d.]+)"[^>]*>(la|ti)<\/text>/g)];
    const first = (syllable) => ys.filter((m) => m[2] === syllable).map((m) => Number(m[1]))[0];
    const [la, ti] = [first('la'), first('ti')];
    assert.ok(la !== undefined && ti !== undefined, 'both lyric lines should be engraved');

    return ti - la;
}

test('%%lyricskipfac actually moves the stacked lyric lines', () => {
    const builtIn = lyricLineGap(null);

    assert.ok(builtIn > 0, 'the second lyric line should sit below the first');
    assert.equal(lyricLineGap(1.1), builtIn, "1.1 is abc2svg's own advance");

    const tight = lyricLineGap(0.4);
    const loose = lyricLineGap(3);

    assert.ok(tight < builtIn, `0.4 should tighten the gap (${tight} vs ${builtIn})`);
    assert.ok(loose > builtIn, `3 should open it up (${loose} vs ${builtIn})`);

    // The advance is a straight multiple of the line height, so the gap tracks
    // the factor: halve it against 1.1 and the gap halves too. Both baselines
    // are rounded to a tenth in the SVG, so the halved gap can miss by that.
    assert.ok(
        Math.abs(lyricLineGap(0.55) - builtIn / 2) <= 0.1,
        'the gap should scale linearly with the factor',
    );
});
