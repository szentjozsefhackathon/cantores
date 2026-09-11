import assert from 'node:assert/strict';
import test from 'node:test';
import { renderAretino } from '@aretino-chant/core';
import { formatDefaults } from '../../resources/js/score-editor-settings.js';

import { aretinoProjectorOptions, buildAretinoFromGuido } from '../../resources/js/score-editor-aretino.js';

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

const CHANT = 'c: f\nn: fg h g f g h\nw: Ky-ri-e e-lei-son\n';

for (const [ratio, width, textPx, staffPx] of [
    ['16/9', 960, 45 * 96 / 72 * 2, 13 * 96 / 25.4 * 2],
    ['4/3', 720, 45 * 96 / 72 * 2, 13 * 96 / 25.4 * 2],
    ['1/1', 540, 45 * 96 / 72 * 2, 13 * 96 / 25.4 * 2],
]) {
    test(`${ratio} Aretino factory rendering uses rounded screen sizes at 96 dpi`, () => {
        const defaults = formatDefaults('aretino', ratio).defaults;
        const svg = renderAretino(CHANT, {
            ...aretinoProjectorOptions(ratio),
            lyricSize: defaults.aretinoLyricSize,
            staffSpaceMm: defaults.aretinoStaffSize / 4,
            staffGap: defaults.aretinoStaffGap,
            textFont: defaults.aretinoTextFont,
            zoom: defaults.aretinoZoom / 100,
        });
        const box = svg.match(/viewBox="([^"]+)"/)[1].split(' ').map(Number);
        const fontSize = Number(svg.match(/<text[^>]*font-size="([^"]+)"/)[1]);
        const staffY = [...svg.matchAll(/<line[^>]*y1="([^"]+)"/g)].slice(0, 5).map(match => Number(match[1]));

        assert.deepEqual(box, [0, 0, width, 540]);
        assert.ok(svg.includes('font-family="\'Barlow Condensed\'"'));
        assert.ok(Math.abs(fontSize * 2 - textPx) < 0.001);
        assert.ok(Math.abs((Math.max(...staffY) - Math.min(...staffY)) * 2 - staffPx) < 0.001);
    });

    test(`${ratio} projector dimensions do not shrink long scores to fit`, () => {
        const defaults = formatDefaults('aretino', ratio).defaults;
        const svg = renderAretino(CHANT.repeat(12), {
            ...aretinoProjectorOptions(ratio),
            lyricSize: defaults.aretinoLyricSize,
            staffSpaceMm: defaults.aretinoStaffSize / 4,
            staffGap: defaults.aretinoStaffGap,
            textFont: defaults.aretinoTextFont,
        });

        assert.equal(Number(svg.match(/viewBox="[^ ]+ [^ ]+ [^ ]+ ([^"]+)"/)[1]), 540);
    });
}
