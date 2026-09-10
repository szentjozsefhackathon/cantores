import assert from 'node:assert/strict';
import test from 'node:test';

import {
    markupRuns,
    measureRuns,
    runBaselineShift,
    runFont,
    runsText,
    sliceRuns,
} from '../../resources/js/chordpro-markup.js';

const plain = (text) => ({ text, bold: false, italic: false, underline: false, script: null });

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
        { text: 'loud ', bold: true, italic: false, underline: false, script: null },
        { text: 'and italic', bold: true, italic: true, underline: false, script: null },
    ]);
});

test('markup ChordPro does not define is left to read literally', () => {
    // Colours and sizes are ChordPro's too, but neither can be drawn exactly
    // here, so they are not pretended at.
    const { runs } = markupRuns('<span color="red">x</span> <big>y</big>');

    assert.deepEqual(runs, [plain('<span color="red">x</span> <big>y</big>')]);
});

test('a super- or subscript is a state, not a flag', () => {
    const { runs } = markupRuns('H<sub>2</sub>O, 1<sup>st</sup>');

    assert.deepEqual(runs.map((run) => [run.text, run.script]), [
        ['H', null],
        ['2', 'sub'],
        ['O, 1', null],
        ['st', 'sup'],
    ]);
});

test('one script closes the other, since text cannot be raised and lowered at once', () => {
    const { runs } = markupRuns('<sup>up<sub>down</sub>after');

    assert.deepEqual(runs.map((run) => [run.text, run.script]), [
        ['up', 'sup'],
        ['down', 'sub'],
        ['after', null],
    ]);
});

test('a script carries the styles around it', () => {
    const { runs } = markupRuns('<b>x<sup>2</sup></b>');

    assert.deepEqual(runs.map((run) => [run.text, run.bold, run.script]), [['x', true, null], ['2', true, 'sup']]);
});

test('a script is set smaller than the text it belongs to, and off its baseline', () => {
    const [normal, raised, lowered] = markupRuns('a<sup>b</sup><sub>c</sub>').runs;

    assert.equal(runFont(normal, 20).fontSize, 20);
    assert.equal(runFont(raised, 20).fontSize, 14);
    assert.equal(runFont(lowered, 20).fontSize, 14);

    assert.equal(runBaselineShift(normal, 20), 0);
    // Up is negative: an SVG baseline runs down the page.
    assert.ok(runBaselineShift(raised, 20) < 0);
    assert.ok(runBaselineShift(lowered, 20) > 0);
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
        { text: 'label ', bold: true, italic: true, underline: false, script: null },
        { text: '2x', bold: true, italic: true, underline: true, script: null },
    ]);
});

test('a close with nothing open leaves the text in one piece', () => {
    assert.deepEqual(markupRuns('so</i> be it').runs, [plain('so be it')]);
});

test('runs slice by the offsets of the text they read as', () => {
    const { runs } = markupRuns('<b>abc</b>def');

    assert.equal(runsText(runs), 'abcdef');
    assert.deepEqual(sliceRuns(runs, 0, 4), [
        { text: 'abc', bold: true, italic: false, underline: false, script: null },
        plain('d'),
    ]);
    assert.deepEqual(sliceRuns(runs, 4), [plain('ef')]);
    assert.deepEqual(sliceRuns(runs, 6), []);
});

test('runs are measured in the face and at the size each is set in', () => {
    const measure = (text, { bold = false } = {}) => text.length * (bold ? 20 : 5);

    assert.equal(measureRuns(markupRuns('ab<b>cd</b>').runs, measure, 10), 50);
});

test('a script is measured at the size it is set, not the size around it', () => {
    const measure = (text, { fontSize = 10 } = {}) => text.length * fontSize;

    // Two characters at 10, then two at seven tenths of it.
    assert.equal(measureRuns(markupRuns('ab<sup>cd</sup>').runs, measure, 10), 34);
});
