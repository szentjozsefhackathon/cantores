import assert from 'node:assert/strict';
import test from 'node:test';

import { configureChantContext, gabcMixin } from '../../resources/js/score-editor-gabc.js';

function fakeChantContext() {
    return {
        font: null,
        fontSize: null,
        glyphScaling: null,
        setFont(font, size) {
            this.font = font;
            this.fontSize = size;
        },
        setGlyphScaling(scaling) {
            this.glyphScaling = scaling;
        },
    };
}

test('sizes an exsurge context from a settings bucket', () => {
    const ctxt = configureChantContext(fakeChantContext(), {
        lyricFont: "'Lora'",
        lyricSize: 30,
        staffSize: 160,
        minLyricWordSpacing: 3,
        hyphenWidth: 2,
        condensingTolerance: 0.5,
        spaceBetweenSystems: 4,
        minSpaceBelowStaff: 1,
    });

    assert.equal(ctxt.font, "'Lora'");
    assert.equal(ctxt.fontSize, 30 * (100 / 30) * 1.3);
    assert.equal(ctxt.glyphScaling, (160 / 100) * (100 / 30) / 16);
    assert.equal(ctxt.minLyricWordSpacing, 3 * (100 / 30));
    assert.equal(ctxt.hyphenWidth, 2 * (100 / 30));
    assert.equal(ctxt.condensingTolerance, 0.5);
    assert.equal(ctxt.spaceBetweenSystems, 4);
    assert.equal(ctxt.minSpaceBelowStaff, 1);
});

test('leaves the renderer to pick the spacings a settings bucket zeroes out', () => {
    const ctxt = configureChantContext(fakeChantContext(), gabcMixin());

    assert.equal(ctxt.fontSize, 12 * (100 / 30) * 1.3);
    assert.equal(ctxt.glyphScaling, (80 / 100) * (100 / 30) / 16);
    assert.equal('minLyricWordSpacing' in ctxt, false);
    assert.equal('hyphenWidth' in ctxt, false);
});
