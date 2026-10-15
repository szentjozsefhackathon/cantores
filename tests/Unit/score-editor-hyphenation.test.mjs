import assert from 'node:assert/strict';
import test from 'node:test';

import { defaultLyricLanguage, readSelection, replaceSelection } from '../../resources/js/score-editor-hyphenation.js';

function fakeCodeMirrorEditor(doc, from, to) {
    const view = {
        doc,
        dispatched: [],
        state: {
            doc: { toString: () => view.doc },
            selection: { main: { from, to } },
            sliceDoc: (start, end) => view.doc.slice(start, end),
        },
        dispatch(transaction) {
            this.dispatched.push(transaction);
            const { from: start, to: end, insert } = transaction.changes;
            this.doc = this.doc.slice(0, start) + insert + this.doc.slice(end);
        },
        focus() {},
    };

    return { view };
}

function fakeTextarea(value, selectionStart, selectionEnd) {
    return {
        value,
        selectionStart,
        selectionEnd,
        events: [],
        focus() {},
        setSelectionRange(start, end) { this.selectionStart = start; this.selectionEnd = end; },
        setRangeText(text, start, end) { this.value = this.value.slice(0, start) + text + this.value.slice(end); },
        dispatchEvent(event) { this.events.push(event.type); },
    };
}

test('defaults to Latin for chant and to Hungarian otherwise, unless a choice is remembered', () => {
    assert.equal(defaultLyricLanguage('gabc'), 'la');
    assert.equal(defaultLyricLanguage('abc'), 'hu');
    assert.equal(defaultLyricLanguage('aretino', 'la'), 'la');
    assert.equal(defaultLyricLanguage('gabc', 'nonsense'), 'la');
});

test('reads nothing from a collapsed selection', () => {
    assert.equal(readSelection(fakeCodeMirrorEditor('w: Kyrie', 3, 3)), null);
    assert.equal(readSelection(fakeTextarea('Kyrie', 2, 2)), null);
    assert.equal(readSelection(null), null);
});

test('replaces a CodeMirror selection as one edit and selects the result', () => {
    const editor = fakeCodeMirrorEditor('K:C\nw: Kyrie eleison', 7, 20);
    const selection = readSelection(editor);

    assert.deepEqual(selection, { from: 7, to: 20, text: 'Kyrie eleison' });
    assert.equal(replaceSelection(editor, selection, 'Ky-ri-e e-le-i-son'), true);
    assert.equal(editor.view.doc, 'K:C\nw: Ky-ri-e e-le-i-son');
    assert.deepEqual(editor.view.dispatched[0].selection, { anchor: 7, head: 25 });
});

test('writes nothing when the selected text has changed since it was read', () => {
    const editor = fakeCodeMirrorEditor('w: Kyrie', 3, 8);
    const selection = readSelection(editor);
    editor.view.doc = 'w: Gloria';

    assert.equal(replaceSelection(editor, selection, 'Ky-ri-e'), false);
    assert.equal(editor.view.doc, 'w: Gloria');
});

test('replaces a textarea selection and reports the input', () => {
    const textarea = fakeTextarea('(c4) Kyrie (::)', 5, 10);
    const selection = readSelection(textarea);

    assert.equal(replaceSelection(textarea, selection, 'Ky()ri()e()'), true);
    assert.equal(textarea.value, '(c4) Ky()ri()e() (::)');
    assert.deepEqual(textarea.events, ['input']);
    assert.deepEqual([textarea.selectionStart, textarea.selectionEnd], [5, 16]);
});
