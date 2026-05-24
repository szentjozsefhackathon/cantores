import assert from 'node:assert/strict';
import test from 'node:test';

import { applyConditionalBlocks } from '../../resources/js/score-editor-pages.js';

test('replaces delimiters with spaces on match, preserving character count', () => {
    // %[169 = 5 chars → 5 spaces; sep kept; %] = 2 chars → 2 spaces
    assert.equal(applyConditionalBlocks('%[169 (z) a b %]', '16/9'), '      (z) a b   ');
    assert.equal(applyConditionalBlocks('%[43 (z) a b %]',  '4/3'),  '     (z) a b   ');
    assert.equal(applyConditionalBlocks('%[11 (z) a b %]',  '1/1'),  '     (z) a b   ');
});

test('output length equals input length on match', () => {
    const input = '%[169 (z) a b %]';
    const result = applyConditionalBlocks(input, '16/9');
    assert.equal(result.length, input.length);
});

test('leaves non-matching ratio block unchanged', () => {
    assert.equal(applyConditionalBlocks('%[169 (z) a b %]', '4/3'), '%[169 (z) a b %]');
    assert.equal(applyConditionalBlocks('%[43 (z) a b %]', '16/9'), '%[43 (z) a b %]');
});

test('leaves unknown condition block unchanged', () => {
    assert.equal(applyConditionalBlocks('%[unknownEditor foo bar %]', '16/9'), '%[unknownEditor foo bar %]');
    assert.equal(applyConditionalBlocks('%[if someFlag %]', '16/9'), '%[if someFlag %]');
});

test('preserves line count and char positions in multiline block', () => {
    const input = '%[169 (z)\na b\nc d %]';
    const result = applyConditionalBlocks(input, '16/9');
    assert.equal(result, '      (z)\na b\nc d   ');
    assert.equal(result.length, input.length);
    assert.equal(applyConditionalBlocks(input, '4/3'), input);
});

test('handles newline as separator between condition and content', () => {
    const input = '%[169\n(z) a b\n%]';
    const result = applyConditionalBlocks(input, '16/9');
    // %[169 = 5 chars → 5 spaces; \n sep kept; inner = (z) a b\n; %] → 2 spaces
    assert.equal(result, '     \n(z) a b\n  ');
    assert.equal(result.length, input.length);
});

test('handles multiple blocks, applies only matching one', () => {
    const input = 'x %[169 A %] y %[43 B %] z';
    assert.equal(applyConditionalBlocks(input, '16/9'), 'x       A    y %[43 B %] z');
    assert.equal(applyConditionalBlocks(input, '4/3'),  'x %[169 A %] y      B    z');
    assert.equal(applyConditionalBlocks(input, '1/1'),  input);
});

test('leaves pure comment block unchanged (unknown condition)', () => {
    assert.equal(applyConditionalBlocks('%[comment stuff %]', '16/9'), '%[comment stuff %]');
});

test('returns content unchanged when no blocks present', () => {
    const input = 'a b c % regular comment\nd e f';
    assert.equal(applyConditionalBlocks(input, '16/9'), input);
});
