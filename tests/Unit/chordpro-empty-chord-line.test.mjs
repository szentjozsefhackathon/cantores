import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

import ChordSheetJS from 'chordsheetjs';

/**
 * A lyrics-only ChordPro sheet must not carry a blank chord line above every
 * line of text. The booklet gets this right by construction — it measures each
 * row and gives a chordless one no chord band — but the on-screen preview and
 * the HTML export are chordsheetjs' HtmlDivFormatter plus our own stylesheet,
 * and there the blank line is entirely a question of which rows the chord
 * min-height applies to.
 */

const format = (content) => new ChordSheetJS.HtmlDivFormatter()
    .format(new ChordSheetJS.ChordProParser().parse(content));

const readSource = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');

test('the div formatter emits an empty chord div for a chordless line', () => {
    // The :empty selector below rests on this. If chordsheetjs ever puts
    // whitespace inside the div, or drops it altogether, the styling has to
    // follow — so pin the assumption rather than the workaround.
    const html = format('Nincsen akkord ezen a soron\n');

    assert.match(html, /<div class="chord"><\/div>/);
    assert.ok(!/<div class="chord">\s+<\/div>/.test(html));
});

test('a chord-bearing line still has a filled chord div in the same row', () => {
    const html = format('[C]Ave [G]Maria\n');
    const row = html.match(/<div class="row">.*?<\/div><\/div><\/div>/)?.[0] ?? html;

    assert.match(row, /<div class="chord">C<\/div>/);
});

test('the preview reserves the chord line only for rows that carry a chord', () => {
    const css = readSource('../../resources/css/app.css');

    assert.match(
        css,
        /\.chordpro-preview \.row:has\(\.chord:not\(:empty\)[^)]*\) \.chord \{[^}]*min-height/,
    );

    // The unscoped rule is what put a blank line over every lyric.
    assert.ok(
        !/\.chordpro-preview \.chord \{[^}]*min-height/.test(css),
        'the bare .chordpro-preview .chord rule must not set min-height',
    );
});

test('the HTML export reserves the chord line only for rows that carry a chord', () => {
    // Two stylesheets live in the editor: the div-based export, which needs the
    // scoping, and the table-based clipboard copy, whose formatter drops the
    // chord row for a chordless line before CSS ever sees it.
    const divStyles = readSource('../../resources/js/score-editor-chordpro.js')
        .split('<style>')
        .find((block) => block.includes('.column{display:flex'));

    assert.ok(divStyles, 'the div-formatter stylesheet should be findable');
    assert.match(divStyles, /\.row:has\(\.chord:not\(:empty\)[^)]*\) \.chord\{[^}]*min-height/);
    assert.ok(
        !/\n\.chord\{[^}]*min-height/.test(divStyles),
        'the div-formatter stylesheet must not set min-height on every .chord',
    );
});

test('chordsheetjs blanks a lyric that is nothing but a space', () => {
    // Which is why a line of chords alone needs the gap below: the columns
    // under `[Am] [C]` come out empty, so nothing but the chords themselves can
    // hold them apart. Pin the assumption rather than the workaround.
    const html = format('||: [Am] [C] :||\n');

    assert.match(html, /<div class="chord">Am<\/div><div class="lyrics"><\/div>/);
});

test('the preview and both exports give a chord the gap the SVG engraves', () => {
    // CHORD_GAP in booklet-chordpro.js, in em so it follows the font size — the
    // same number, or a line of chords without lyrics reads as one long word in
    // HTML and as spaced chords on the page.
    const sources = [
        readSource('../../resources/css/app.css'),
        readSource('../../resources/js/score-editor-chordpro.js'),
    ];

    assert.match(sources[0], /\.chordpro-preview \.chord:not\(:empty\),\n\.chordpro-preview \.annotation \{[^}]*padding-right: 0\.4em/);

    const gapRules = sources[1].match(/\.chord:not\(:empty\),\.annotation\{padding-right:0\.4em;\}/g) ?? [];
    assert.equal(gapRules.length, 2, 'both the clipboard copy and the HTML export need the gap');

    // The 0.1em fudge it replaces spaced every column, which the SVG does not.
    sources.forEach((source) => {
        assert.ok(!/margin-right: ?0\.1em/.test(source), 'the per-column fudge should be gone');
    });
});

test('an annotation is the way to write chords and marks on one line', () => {
    // `||: Cm :|| (2x)`: the repeat marks are not sung, so they cannot be lyrics,
    // and they are not chords either. ChordPro's `[*text]` puts them in the chord
    // slot, which is what the reference implementation does with them too, and
    // both chordsheetjs formatters give them a class of their own.
    const html = format('[*||:][Cm][*:||][* (2x)]\n');

    assert.match(html, /<div class="annotation">\|\|:<\/div>/);
    assert.match(html, /<div class="chord">Cm<\/div>/);

    // Every lyric column is empty, so the row collapses to the one line.
    assert.ok(!/<div class="lyrics">[^<]/.test(html));
});

test('both export stylesheets and the preview style the annotation', () => {
    const css = readSource('../../resources/css/app.css');
    const js = readSource('../../resources/js/score-editor-chordpro.js');

    assert.match(css, /\.chordpro-preview \.annotation \{[^}]*color: #555/);
    assert.match(css, /\.dark \.chordpro-preview \.annotation \{[^}]*color: #9ca3af/);

    const rules = js.match(/\.annotation\{font-weight:bold;color:#555;white-space:nowrap;\}/g) ?? [];
    assert.equal(rules.length, 2, 'the clipboard copy and the HTML export both need it');
});
