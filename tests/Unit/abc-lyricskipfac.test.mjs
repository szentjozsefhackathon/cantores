import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import test from 'node:test';
import vm from 'node:vm';

import { ABC_LYRIC_SKIP_MIN, abcMixin, buildAbcPreamble } from '../../resources/js/score-editor-abc.js';

const abc2svgSource = readFileSync(
    fileURLToPath(new URL('../../node_modules/@cantoreshu/abc2svg/abc2svg-1.js', import.meta.url)),
    'utf8',
);

test('the factory default sets the stanzas one line height apart', () => {
    assert.equal(abcMixin().abcLyricSkip, 1);
    assert.match(buildAbcPreamble(abcMixin(), 1700), /%%lyricskipfac 1\n/);
});

test('abcLyricSkip is a persisted per-score field', () => {
    assert.ok(abcMixin().abcFields.includes('abcLyricSkip'));
});

test('a positive abcLyricSkip becomes a %%lyricskipfac directive', () => {
    const preamble = buildAbcPreamble({ ...abcMixin(), abcLyricSkip: 1.4 }, 1700);

    assert.match(preamble, /%%lyricskipfac 1\.4\n/);
    assert.match(preamble, /%%lyricfirstskipfac 1\n%%lyricskipfac 1\.4\n/);
});

test('a below-floor, blank or junk abcLyricSkip emits nothing', () => {
    assert.equal(ABC_LYRIC_SKIP_MIN, 0.5);

    for (const abcLyricSkip of [0, '', '0', 'x', -1, null, undefined, 0.4, 0.49]) {
        assert.doesNotMatch(
            buildAbcPreamble({ ...abcMixin(), abcLyricSkip }, 1700),
            /%%lyricskipfac/,
            `abcLyricSkip=${JSON.stringify(abcLyricSkip)}`,
        );
    }
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
