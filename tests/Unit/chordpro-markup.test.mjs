import assert from 'node:assert/strict';
import test from 'node:test';

import { markupRuns, measureRuns, runsText, sliceRuns } from '../../resources/js/chordpro-markup.js';

const plain = (text) => ({ text, bold: false, italic: false, underline: false });

test('text without markup is one run', () => {
    assert.deepEqual(markupRuns('Ave Maria').runs, [plain('Ave Maria')]);
});

test('each of the three tags turns its own style on and off', () => {
    const { runs } = markupRuns('a<i>b</i><b>c</b><u>d</u>e');

    assert.deepEqual(runs.map((run) => run.text), ['a', 'b', 'c', 'd', 'e']);
    assert.deepEqual(runs.map((run) => run.italic), [false, true, false, false, false]);
    assert.deepEqual(runs.map((run) => run.bold), [false, false, true, false, false]);
    assert.deepEqual(runs.map((run) => run.underline), [false, false, false, true, false]);
});

test('tags nest', () => {
    const { runs } = markupRuns('<b>loud <i>and italic</i></b>');

    assert.deepEqual(runs, [
        { text: 'loud ', bold: true, italic: false, underline: false },
        { text: 'and italic', bold: true, italic: true, underline: false },
    ]);
});

test('markup ChordPro does not define is left to read literally', () => {
    // Colours, sizes and scripts are ChordPro's too, but none of them can be
    // drawn exactly here, so they are not pretended at.
    const { runs } = markupRuns('H<sub>2</sub>O <span color="red">x</span>');

    assert.deepEqual(runs, [plain('H<sub>2</sub>O <span color="red">x</span>')]);
});

test('an open tag is handed on, so a lyric split at a chord stays styled', () => {
    const first = markupRuns('<i>Ave ');
    const second = markupRuns('Maria</i> now', first.open);

    assert.equal(first.open.italic, true);
    assert.deepEqual(second.runs.map((run) => [run.text, run.italic]), [['Maria', true], [' now', false]]);
    assert.equal(second.open.italic, false);
});

test('a base style is what the text starts in', () => {
    const { runs } = markupRuns('label <u>2x</u>', { bold: true, italic: true });

    assert.deepEqual(runs, [
        { text: 'label ', bold: true, italic: true, underline: false },
        { text: '2x', bold: true, italic: true, underline: true },
    ]);
});

test('a close with nothing open leaves the text in one piece', () => {
    assert.deepEqual(markupRuns('so</i> be it').runs, [plain('so be it')]);
});

test('runs slice by the offsets of the text they read as', () => {
    const { runs } = markupRuns('<b>abc</b>def');

    assert.equal(runsText(runs), 'abcdef');
    assert.deepEqual(sliceRuns(runs, 0, 4), [
        { text: 'abc', bold: true, italic: false, underline: false },
        plain('d'),
    ]);
    assert.deepEqual(sliceRuns(runs, 4), [plain('ef')]);
    assert.deepEqual(sliceRuns(runs, 6), []);
});

test('runs are measured in the face each is drawn in', () => {
    const measure = (text, { bold = false } = {}) => text.length * (bold ? 20 : 5);

    assert.equal(measureRuns(markupRuns('ab<b>cd</b>').runs, measure), 50);
});
