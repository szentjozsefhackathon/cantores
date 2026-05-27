import assert from 'node:assert/strict';
import test from 'node:test';

import { clampAbcGuidePageWidth, prepareAbcGuideSource } from '../../resources/js/abc-mini-editor.js';

test('clamps abc guide page width to renderer-friendly values', () => {
    assert.equal(clampAbcGuidePageWidth(200), 420);
    assert.equal(clampAbcGuidePageWidth('760'), 760);
    assert.equal(clampAbcGuidePageWidth(1600), 1200);
    assert.equal(clampAbcGuidePageWidth('not-a-number'), 760);
});

test('prepares abc guide source with rendering preamble and X field', () => {
    const source = prepareAbcGuideSource("T:Példa\nM:2/4\nL:1/4\nK:C\nC D |]", 600);

    assert.match(source, /^%%fullsvg 1\n%%pagewidth 600px/m);
    assert.match(source, /^X:1$/m);
    assert.match(source, /^T:Példa$/m);
});

test('keeps an existing X field when preparing guide source', () => {
    const source = prepareAbcGuideSource('X:7\nT:Saját\nK:G\nG A |]', 600);

    assert.equal((source.match(/^X:/gm) || []).length, 1);
    assert.match(source, /^X:7$/m);
});
