import assert from 'node:assert/strict';
import test from 'node:test';

import { buildAretinoFromGuido } from '../../resources/js/score-editor-aretino.js';

test('combines Guido notes and lyrics into a w: lyric line', () => {
    const aretino = buildAretinoFromGuido('<0123', 'Ky-ri-e');

    assert.equal(aretino, '(g2)cdef\nw: Ky-ri-e\n');
});

test('omits the w: line when no lyrics are given', () => {
    const aretino = buildAretinoFromGuido('<0123', '   ');

    assert.equal(aretino, '(g2)cdef\n');
});

test('returns an empty string when both inputs are blank', () => {
    assert.equal(buildAretinoFromGuido('', ''), '');
    assert.equal(buildAretinoFromGuido('   ', '\n'), '');
    assert.equal(buildAretinoFromGuido(null, null), '');
});
