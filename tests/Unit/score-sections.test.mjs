import assert from 'node:assert/strict';
import test from 'node:test';

import { arrangeSections, parseSections, stripSectionMarkers } from '../../resources/js/score-sections.js';
import { splitPages } from '../../resources/js/score-editor-pages.js';

/*
 * See plans/score-sections.md. A score with no marker behaves exactly as it
 * does today; a marker cuts the source into numbered parts a row can choose
 * from, repeat, or leave out.
 */

test('a score with no markers comes back unchanged', () => {
    const abc = 'X:1\nT:Teszt\nK:G\nA B c d|\n';

    assert.equal(stripSectionMarkers(abc), abc);
    assert.deepEqual(arrangeSections(abc, 'abc', null, {}), { source: abc, missing: [] });
    assert.deepEqual(arrangeSections(abc, 'abc', [], {}), { source: abc, missing: [] });

    const parsed = parseSections(abc, 'abc');
    assert.equal(parsed.sections.length, 0);
});

test('sections are numbered by position, labelled or not, and labels may repeat', () => {
    const abc = 'X:1\nT:Ki Jézus Szívét\nK:G\n%section 1\nA B|\n%section 2\nc d|\n%section Refrén\ne f|\n%section Refrén\ng a|\n';
    const { sections } = parseSections(abc, 'abc');

    assert.deepEqual(sections.map((s) => [s.n, s.label]), [
        [1, '1'],
        [2, '2'],
        [3, 'Refrén'],
        [4, 'Refrén'],
    ]);
    assert.match(sections[0].body, /A B\|/);
    assert.match(sections[2].body, /e f\|/);
});

test('a preamble is printed once, ahead of the first chosen section', () => {
    const abc = 'X:1\nT:Teszt\nK:G\nC:közjáték\n%section 1\nA B|\n%section 2\nc d|\n';
    const { header, preamble } = parseSections(abc, 'abc');

    assert.match(header, /^X:1\nT:Teszt\nK:G\n$/);
    assert.equal(preamble, 'C:közjáték');

    const { source } = arrangeSections(abc, 'abc', [2, 1], {});
    assert.match(source, /^X:1\nT:Teszt\nK:G\nC:közjáték\nc d\|\nA B\|/);
});

test('a repeated reference repeats the section', () => {
    const abc = 'X:1\nK:G\n%section 1\nA B|\n%section Refrén\nc d|\n';
    const { source } = arrangeSections(abc, 'abc', [1, 2, 2], { separator: '' });

    assert.equal((source.match(/c d\|/g) ?? []).length, 2);
    assert.match(source, /A B\|[\s\S]*c d\|[\s\S]*c d\|/);
});

test('a missing reference is skipped and reported', () => {
    const abc = 'X:1\nK:G\n%section 1\nA B|\n';
    const { source, missing } = arrangeSections(abc, 'abc', [1, 5], {});

    assert.deepEqual(missing, [5]);
    assert.match(source, /A B\|/);
});

test('the ABC header up to K: is kept on every arrangement', () => {
    const abc = 'X:1\nT:Teszt\nM:4/4\nK:G\n%section 1\nA B|\n%section 2\nc d|\n';
    const { source } = arrangeSections(abc, 'abc', [2], {});

    assert.match(source, /^X:1\nT:Teszt\nM:4\/4\nK:G\n/);
});

test('the Aretino and GABC header up to %% is kept', () => {
    const chant = 'name: Teszt;\n%%\n%section 1\n(c4) Ky(f)ri(g)e(h)\n%section 2\ne(g)le(f)i(e)son(d)\n';
    const { source } = arrangeSections(chant, 'gabc', [2], {});

    assert.match(source, /^name: Teszt;\n%%\n/);
    assert.match(source, /son/);
    assert.ok(!source.includes('Kyrie') && !source.includes('Ky(f)'));
});

test('ChordPro marker lines are removed, and it has no header to repeat', () => {
    const sheet = '{title: Teszt}\n%section 1\n[C]Első sor\n%section Refrén\n[G]Má-so-dik sor\n';
    const { header, preamble, sections } = parseSections(sheet, 'chordpro');

    assert.equal(header, '');
    assert.equal(preamble, '{title: Teszt}');
    assert.equal(sections.length, 2);

    const { source } = arrangeSections(sheet, 'chordpro', [2], {});
    assert.ok(!source.includes('%section'));
    assert.match(source, /\{title: Teszt\}/);
    assert.match(source, /Má-so-dik/);
    assert.ok(!source.includes('Első'));

    assert.ok(!stripSectionMarkers(sheet).includes('%section'));
});

test('separator: %pagebreak gives one page per section through splitPages', () => {
    const abc = 'X:1\nK:G\n%section 1\nA B|\n%section 2\nc d|\n%section 3\ne f|\n';
    const { source } = arrangeSections(abc, 'abc', [1, 2, 3], { separator: '%pagebreak' });

    assert.equal(splitPages(source, 'abc', '16/9').length, 3);
});

test('an empty separator flows sections together without a forced break', () => {
    const abc = 'X:1\nK:G\n%section 1\nA B|\n%section 2\nc d|\n';
    const { source } = arrangeSections(abc, 'abc', [1, 2], { separator: '' });

    assert.ok(!source.includes('%pagebreak'));
    assert.equal(splitPages(source, 'abc', '16/9').length, 1);
});

/*
 * A lead sheet is written verse by verse, and a blank line is where one ends:
 * so every sung paragraph of a chord sheet is a section, marked or not, and a
 * `%section` line only names the paragraph below it. The same sheet is read by
 * tests/Unit/ScoreSectionsTest.php.
 */
const LEAD_SHEET = '{title: Teszt}\n{key: G}\n\n%section Verse 1\n1. [G]Hi this is an ordered\nverse with [D]multiple lines\n\n%section Chorus\nR. This is the [C]refrain\nvery long and [G]repeating\n\nVerse 2:\n2. [G]A label typed as lyrics\nnames nothing\n';

test('every sung paragraph of a chord sheet is a section, and a marker names it', () => {
    const { header, preamble, sections } = parseSections(LEAD_SHEET, 'chordpro');

    assert.equal(header, '');
    assert.equal(preamble, '{title: Teszt}\n{key: G}');
    assert.deepEqual(sections.map((s) => [s.n, s.label]), [[1, 'Verse 1'], [2, 'Chorus'], [3, null]]);
    assert.match(sections[2].body, /^Verse 2:\n2\. /);
});

test('a paragraph of directives alone travels with the verse after it', () => {
    const sheet = '[C]Első\n\n{comment: Refrén}\n\n[G]Második\n\n{comment: Vége}\n';
    const { sections } = parseSections(sheet, 'chordpro');

    assert.equal(sections.length, 2);
    assert.equal(sections[1].body, '{comment: Refrén}\n\n[G]Második\n\n{comment: Vége}');
});

test('a marker above several paragraphs names only the first of them', () => {
    const { sections } = parseSections('%section Egy\n[C]Első\n\n[G]Második\n', 'chordpro');

    assert.deepEqual(sections.map((s) => [s.n, s.label]), [[1, 'Egy'], [2, null]]);
});

test('chosen chord-sheet paragraphs stay paragraphs when flowed together', () => {
    const { source } = arrangeSections(LEAD_SHEET, 'chordpro', [3, 1], {});

    assert.ok(!source.includes('%section'));
    assert.match(source, /names nothing\n\n1\. \[G\]Hi/);
    assert.ok(!source.includes('refrain'));
});
