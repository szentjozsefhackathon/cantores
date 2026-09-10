import assert from 'node:assert/strict';
import test from 'node:test';

import { configureChantContext, GABC_LAYOUT_WIDTH_DEFAULT, gabcMixin, normalizeGabcLayoutWidth } from '../../resources/js/score-editor-gabc.js';
import { ptForGabcLyricSize, staffHeightMmForGabcStaffSize } from '../../resources/js/booklet-geometry.js';

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
        lyricFont: "'Merriweather'",
        lyricSize: 30,
        staffSize: 160,
        minLyricWordSpacing: 3,
        hyphenWidth: 2,
        condensingTolerance: 0.5,
        spaceBetweenSystems: 4,
        minSpaceBelowStaff: 1,
    });

    assert.equal(ctxt.font, "'Merriweather'");
    assert.equal(ctxt.fontSize, 30 * (100 / 30) * 1.3);
    assert.equal(ctxt.glyphScaling, (160 / 100) * (100 / 30) / 16);
    assert.equal(ctxt.minLyricWordSpacing, 3 * (100 / 30));
    assert.equal(ctxt.hyphenWidth, 2 * (100 / 30));
    assert.equal(ctxt.condensingTolerance, 0.5);
    assert.equal(ctxt.spaceBetweenSystems, 4);
    assert.equal(ctxt.minSpaceBelowStaff, 1);
});

test('leaves the renderer to pick the spacings a settings bucket zeroes out', () => {
    const defaults = gabcMixin();
    const ctxt = configureChantContext(fakeChantContext(), defaults);

    assert.equal(ctxt.fontSize, defaults.lyricSize * (100 / 30) * 1.3);
    assert.equal(ctxt.glyphScaling, (defaults.staffSize / 100) * (100 / 30) / 16);
    assert.equal('minLyricWordSpacing' in ctxt, false);
    assert.equal('hyphenWidth' in ctxt, false);
});

// The two knobs are stored in exsurge's units and stated in the toolbar as the
// millimetres and points a printed page is measured in; the factory settings are
// what those units come to for the page every editor now engraves on.
test('the factory settings are eleven points of type on a six millimetre staff', () => {
    const defaults = gabcMixin();

    assert.ok(Math.abs(ptForGabcLyricSize(defaults.lyricSize) - 11) < 0.005);
    assert.ok(Math.abs(staffHeightMmForGabcStaffSize(defaults.staffSize) - 6) < 0.005);
    assert.equal(defaults.lyricFont, "'Alegreya'");
});

test('normalizes the GABC layout width to the renderer-safe range', () => {
    assert.equal(normalizeGabcLayoutWidth(30), 100);
    assert.equal(normalizeGabcLayoutWidth('30'), 100);
    assert.equal(normalizeGabcLayoutWidth(1800), 1800);
    assert.equal(normalizeGabcLayoutWidth(5000), 3000);
    assert.equal(normalizeGabcLayoutWidth(''), GABC_LAYOUT_WIDTH_DEFAULT);
    assert.equal(normalizeGabcLayoutWidth('not-a-number'), GABC_LAYOUT_WIDTH_DEFAULT);
});

test('the layout width is a persisted per-score field', () => {
    assert.ok(gabcMixin().gabcFields.includes('gabcLayoutWidth'));
    assert.equal(gabcMixin().gabcLayoutWidth, GABC_LAYOUT_WIDTH_DEFAULT);
});
