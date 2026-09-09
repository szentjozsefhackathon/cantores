import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

/**
 * The ChordPro preview in the score editor is spaced like the booklet page.
 *
 * The booklet engraves each row at a measured height — LYRIC_LINE, CHORD_LINE
 * and LABEL_LINE multiples of the font size, with PARAGRAPH_GAP between verses
 * — while the preview is chordsheetjs' HTML plus our stylesheet. Nothing keeps
 * the two in step but this test: the booklet's numbers are the truth, and the
 * CSS is read back and compared against them.
 */

const readSource = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');

const engraver = readSource('../../resources/js/booklet-chordpro.js');
const css = readSource('../../resources/css/app.css');

const constantOf = (name) => {
    const match = engraver.match(new RegExp(`const ${name} = ([\\d.]+);`));
    assert.ok(match, `booklet-chordpro.js should define ${name}`);

    return Number(match[1]);
};

const ruleFor = (selector) => {
    const match = css.match(new RegExp(`${selector.replace(/[.()[\]:]/g, (c) => `\\${c}`)} \\{([^}]*)\\}`));
    assert.ok(match, `app.css should carry a rule for ${selector}`);

    return match[1];
};

test('the lyric line is set to the height the booklet gives it', () => {
    assert.match(ruleFor('.chordpro-preview .lyrics'), new RegExp(`line-height:\\s*${constantOf('LYRIC_LINE')}\\b`));
});

test('the chord line is set to the height the booklet gives it', () => {
    const chordLine = constantOf('CHORD_LINE');

    assert.match(ruleFor('.chordpro-preview .chord'), new RegExp(`line-height:\\s*${chordLine}\\b`));
    assert.match(
        ruleFor('.chordpro-preview .row:has(.chord:not(:empty)) .chord'),
        new RegExp(`min-height:\\s*${chordLine}em`),
    );
});

test('a section label stands as tall as the booklet draws it, and no taller', () => {
    const header = ruleFor('.chordpro-preview .paragraph-header');

    assert.match(header, new RegExp(`line-height:\\s*${constantOf('LABEL_LINE')}\\b`));
    assert.match(header, /margin-bottom:\s*0\b/);
});

test('verses are parted by the booklet gap, and rows inside one by nothing', () => {
    assert.match(
        ruleFor('.chordpro-preview .paragraph'),
        new RegExp(`margin-bottom:\\s*${constantOf('PARAGRAPH_GAP')}em`),
    );

    // The booklet stacks the rows of a verse flush; a margin here was what made
    // the preview run longer than the page it previews.
    assert.ok(
        !/margin-bottom/.test(ruleFor('.chordpro-preview .row')),
        'the row rule must not add leading of its own',
    );
});
