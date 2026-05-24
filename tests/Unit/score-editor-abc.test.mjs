import assert from 'node:assert/strict';
import test from 'node:test';

import { normalizeAbcPageWidth } from '../../resources/js/score-editor-abc.js';

test('normalizes ABC page width to the renderer-safe range', () => {
    assert.equal(normalizeAbcPageWidth(130), 400);
    assert.equal(normalizeAbcPageWidth('130'), 400);
    assert.equal(normalizeAbcPageWidth(1800), 1800);
    assert.equal(normalizeAbcPageWidth(5000), 4000);
    assert.equal(normalizeAbcPageWidth(''), 1800);
    assert.equal(normalizeAbcPageWidth('not-a-number'), 1800);
});
