import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { fileURLToPath } from 'node:url';
import vm from 'node:vm';

import { hitBoxesAtOffset, replaceMapped, splitPagesMapped, trackSource } from '../../resources/js/abc-source-map.js';
import { removeEditorOnlySvgMarkup } from '../../resources/js/score-editor-export.js';
import { buildAbcPreamble, abcMixin, hungarianChordsToAbc, prepareAbcPreviewPages, renderAbcToSvgMarkup } from '../../resources/js/score-editor-abc.js';

/**
 * Clicking a note in the ABC preview lands on the characters that wrote it.
 *
 * Run against the real abc2svg, evaluated into a sandbox the way
 * booklet-abc-blocks.test.mjs does it.
 */
const sandbox = { abc2svg: {}, console };
vm.createContext(sandbox);
vm.runInContext(
    readFileSync(fileURLToPath(new URL('../../node_modules/@cantoreshu/abc2svg/abc2svg-1.js', import.meta.url)), 'utf8'),
    sandbox,
);
globalThis.abc2svg = sandbox.abc2svg;

/** Every hit box in the markup, as the editor text it points at. */
function boxedSource(markup, source) {
    return [...markup.matchAll(/<rect class="abcsym" data-start="(\d+)" data-stop="(\d+)"/g)]
        .map(([, start, stop]) => source.slice(Number(start), Number(stop)));
}

function engrave(source, { ratio = 'paper', noClef = false, page = 0 } = {}) {
    const preamble = buildAbcPreamble(abcMixin(), 642.52);

    return renderAbcToSvgMarkup(prepareAbcPreviewPages(source, ratio, noClef)[page], preamble);
}

test('a mapped text leaves every character pointing at itself', () => {
    const mapped = trackSource('K:C\nCDE');

    assert.deepEqual(mapped.origin, [0, 1, 2, 3, 4, 5, 6]);
});

test('a replacement keeps the origin of what it shares with the text it replaces', () => {
    const mapped = replaceMapped(trackSource('x "Bm7" y'), /"[^"]*"/g, () => '"Bbm7"');

    assert.equal(mapped.text, 'x "Bbm7" y');
    assert.deepEqual(mapped.origin, [0, 1, 2, 3, 3, 4, 5, 6, 7, 8]);
});

test('the mapped chord rewrite spells the chords as the plain one does', () => {
    const source = 'K:C\n"H"G "B"E "Am/B"C |]\n"^B"D';

    assert.equal(prepareAbcPreviewPages(source, 'paper')[0].text, 'X:1\n' + hungarianChordsToAbc(source));
});

test('notes, bars and syllables point at their own characters in the editor', () => {
    const source = 'K:C\nL:1/4\nC D E|]\nw: Glo-ri-a';
    const boxed = boxedSource(engrave(source), source);

    for (const expected of ['C', 'D', 'E', '|]', 'Glo', 'ri', 'a']) {
        assert.ok(boxed.includes(expected), `${expected} is boxed, got ${JSON.stringify(boxed)}`);
    }
});

test('rewritten chords, and the notes after them, point at the editor text', () => {
    const source = 'K:C\nL:1/4\n"B"C "H"D E|]';
    const boxed = boxedSource(engrave(source), source);

    for (const expected of ['"B"', 'C', '"H"', 'D', 'E', '|]']) {
        assert.ok(boxed.includes(expected), `${expected} is boxed, got ${JSON.stringify(boxed)}`);
    }
});

test('the clef the preview inserts is not boxed, and the notes after it stay put', () => {
    const source = 'K:C\nL:1/4\nC D|E F|]';
    const markup = engrave(source, { noClef: true });
    const boxed = boxedSource(markup, source);

    assert.match(markup, /abcsym/);
    for (const expected of ['C', 'D', 'E', 'F']) {
        assert.ok(boxed.includes(expected), `${expected} is boxed, got ${JSON.stringify(boxed)}`);
    }
});

test('a later page points into the editor text past the page break', () => {
    const source = 'K:C\nL:1/4\nC D|]\n%pagebreak\nE F|]\n';
    const pages = prepareAbcPreviewPages(source, '16/9');
    const boxed = boxedSource(engrave(source, { ratio: '16/9', page: 1 }), source);

    assert.equal(pages.length, 2);
    assert.ok(boxed.includes('E'), JSON.stringify(boxed));
    assert.ok(boxed.includes('F'), JSON.stringify(boxed));
    assert.ok(!boxed.includes('C'), JSON.stringify(boxed));
});

test('an empty page between two breaks does not throw the next page off', () => {
    const source = 'K:C\nC|]\n%pagebreak\n%pagebreak\nD|]';
    const pages = splitPagesMapped(trackSource(source), 'abc', '16/9');
    const last = pages[pages.length - 1];
    const d = last.text.indexOf('D');

    assert.equal(source[last.origin[d]], 'D');
    assert.equal(last.origin[d], source.lastIndexOf('D'));
});

test('the caret marks the innermost box it stands in', () => {
    const box = (start, stop) => ({ dataset: { start: String(start), stop: String(stop) } });
    const bar = box(10, 20);
    const note = box(12, 13);

    assert.deepEqual(hitBoxesAtOffset([bar, note], 12), [note]);
    assert.deepEqual(hitBoxesAtOffset([bar, note], 13), [note]);
    assert.deepEqual(hitBoxesAtOffset([bar, note], 15), [bar]);
    assert.deepEqual(hitBoxesAtOffset([bar, note], 30), []);
});

test('the preview renders the same music with or without hit boxes', () => {
    const source = 'K:C\nL:1/4\n"B"C D E|]\nw: Glo-ri-a';
    const preamble = buildAbcPreamble(abcMixin(), 642.52);
    const page = prepareAbcPreviewPages(source, 'paper')[0];
    const plain = renderAbcToSvgMarkup(preamble + page.text);
    const boxed = renderAbcToSvgMarkup(page, preamble);
    const withoutBoxes = boxed.replace(/<rect class="abcsym"[^>]*\/>\n/g, '').replace(/\bf(\d)\d*1\b/g, 'f');

    assert.equal(withoutBoxes.length > 0, true);
    assert.equal(plain.replace(/\bf(\d)\d*1\b/g, 'f'), withoutBoxes);
});

test('hit boxes are dropped from an exported score', () => {
    const removed = [];
    const svg = {
        classList: { contains: () => false },
        querySelectorAll: (selector) => (selector === '.abcsym' ? [{ remove: () => removed.push(selector) }] : []),
    };

    removeEditorOnlySvgMarkup(svg);

    assert.deepEqual(removed, ['.abcsym']);
});
