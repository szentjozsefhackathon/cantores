import assert from 'node:assert/strict';
import test from 'node:test';
import { renderAretino, splitRowSVGs } from '@aretino-chant/core';
import { formatDefaults } from '../../resources/js/score-editor-settings.js';

import { aretinoContentHeight, aretinoProjectorOptions, buildAretinoFromGuido, engraveAretinoSlide } from '../../resources/js/score-editor-aretino.js';

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

/*
 * Handed a canvas height, Aretino holds it: a chant taller than the slide was
 * cut off by its own viewBox, and the only overflow test looked at the width,
 * so the slide came out short of its last rows without a word. Engraved again
 * without the height, the chant says how tall it really is — which is what the
 * slide is now measured against, and what it is cut between rows by.
 */
const ROW = 'n: fg h g f g h\nw: Ky-ri-e e-lei-son\n';

test('a chant taller than the slide is measured as taller, though the slide holds its height', () => {
    const settings = formatDefaults('aretino', '16/9').defaults;
    const tall = 'c: f\n' + ROW.repeat(8);

    const held = engraveAretinoSlide(tall, settings, '16/9', true);
    const free = engraveAretinoSlide(tall, settings, '16/9', false);

    assert.equal(Number(held.match(/viewBox="[^ ]+ [^ ]+ [^ ]+ ([^"]+)"/)[1]), 540, 'the slide is the canvas');
    assert.ok(aretinoContentHeight(free) > 540, `the chant is ${aretinoContentHeight(free)} tall`);
});

test('a chant that fits is measured as fitting', () => {
    const settings = formatDefaults('aretino', '16/9').defaults;

    assert.ok(aretinoContentHeight(engraveAretinoSlide('c: f\n' + ROW, settings, '16/9', false)) < 540);
});

test('a chant too tall for its slide comes apart into one system per staff row', () => {
    const settings = formatDefaults('aretino', '16/9').defaults;
    const free = engraveAretinoSlide('c: f\n' + ROW.repeat(8), settings, '16/9', false);
    const rows = splitRowSVGs(free);

    assert.ok(rows.length > 1, 'more than one row');
    rows.forEach((row) => {
        const height = Number(row.match(/viewBox="[^ ]+ [^ ]+ [^ ]+ ([^"]+)"/)[1]);

        assert.ok(height > 0 && height < 540, `a row ${height} tall fits a slide on its own`);
    });
});
