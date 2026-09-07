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
        /\.chordpro-preview \.row:has\(\.chord:not\(:empty\)\) \.chord \{[^}]*min-height/,
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
    assert.match(divStyles, /\.row:has\(\.chord:not\(:empty\)\) \.chord\{[^}]*min-height/);
    assert.ok(
        !/\n\.chord\{[^}]*min-height/.test(divStyles),
        'the div-formatter stylesheet must not set min-height on every .chord',
    );
});
