import assert from 'node:assert/strict';
import test from 'node:test';

import { abcMixin } from '../../resources/js/score-editor-abc.js';
import { gabcMixin } from '../../resources/js/score-editor-gabc.js';
import { formatDefaults, incipitSettings } from '../../resources/js/score-editor-settings.js';

test('reports the fields and factory defaults of every format', () => {
    assert.equal(formatDefaults('gabc').defaults.staffSize, 80);
    assert.ok(formatDefaults('gabc').fields.includes('lyricSize'));
    assert.equal(formatDefaults('abc').defaults.abcPageWidth, 1700);
    assert.ok(formatDefaults('abc').fields.includes('abcLyricSize'));
    assert.equal(formatDefaults('chordpro').defaults.chordproColumns, 1);
    assert.equal(formatDefaults('aretino').defaults.aretinoStaffSize, 7);
    assert.deepEqual(formatDefaults('links-only'), { fields: [], defaults: {} });
});

test('renders an incipit at the factory defaults, whatever the score is set to', () => {
    // A projector score's own settings — oversized lyrics, a clef-less staff —
    // must not reach the thumbnail every listing shows.
    const abc = incipitSettings('abc');
    abcMixin().abcFields.forEach(field => {
        assert.equal(abc[field], abcMixin()[field], `abc.${field} is not the factory default`);
    });
    assert.equal(abc.abcLyricSize, 12);
    assert.equal(abc.abcNoClef, false);
    assert.equal(abc.abcTranspose, 0);
    assert.equal(abc.abcPageRatio, 'paper');

    const gabc = incipitSettings('gabc');
    gabcMixin().gabcFields.forEach(field => {
        assert.equal(gabc[field], gabcMixin()[field], `gabc.${field} is not the factory default`);
    });
    assert.equal(gabc.pageRatio, 'paper');
});

test('hands out a fresh settings object each time', () => {
    const first = incipitSettings('gabc');
    first.lyricSize = 40;

    assert.equal(incipitSettings('gabc').lyricSize, 12);
});
