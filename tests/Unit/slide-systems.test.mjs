import assert from 'node:assert/strict';
import test from 'node:test';

import { packSystems } from '../../resources/js/slide-systems.js';
import { startsAtAutomaticCut } from '../../resources/js/soft-pages.js';

/*
 * An engraved page that runs past the bottom of the screen, cut between its
 * staff systems. Drawing the slides needs a browser; deciding where they are
 * cut does not, and that is where a wrong answer would be silent — a system
 * dropped, a title left alone at the bottom, a cut the author asked for that
 * the editor then calls automatic.
 */

const system = (name, height, extra = {}) => ({ svg: name, height, ...extra });

const names = (pages) => pages.map((page) => page.systems.map((s) => s.svg));

test('a page that fits is one slide, cut nowhere', () => {
    const pages = packSystems([[system('a', 300), system('b', 300), system('c', 300)]], 1080);

    assert.deepEqual(names(pages), [['a', 'b', 'c']]);
    assert.equal(pages[0].autoSplit, false);
    assert.equal(pages[0].height, 900);
});

test('a page that runs over is cut between systems, each slide filled before the next', () => {
    const pages = packSystems([[
        system('a', 400), system('b', 400), system('c', 400), system('d', 400), system('e', 400),
    ]], 1080);

    assert.deepEqual(names(pages), [['a', 'b'], ['c', 'd'], ['e']]);
    assert.deepEqual(pages.map((page) => page.autoSplit), [false, true, true]);
});

test('nothing is lost and nothing is repeated', () => {
    const systems = Array.from({ length: 13 }, (_, i) => system(`s${i}`, 150 + (i % 4) * 90));
    const pages = packSystems([systems], 1080);

    assert.deepEqual(names(pages).flat(), systems.map((s) => s.svg));
    pages.forEach((page) => assert.ok(page.height <= 1080, `a slide came to ${page.height}`));
});

test('a title is never left alone at the bottom of a slide', () => {
    const pages = packSystems([[
        system('a', 500), system('b', 400), system('title', 100, { keepWithNext: true }), system('c', 400),
    ]], 1080);

    assert.deepEqual(names(pages), [['a', 'b'], ['title', 'c']]);
});

test('a suggestion the page needs is spent, and the slide it starts is not called automatic', () => {
    const pages = packSystems([
        [system('a', 400), system('b', 400)],
        [system('c', 400), system('d', 400)],
    ], 1080);

    assert.deepEqual(names(pages), [['a', 'b'], ['c', 'd']]);
    assert.deepEqual(pages.map((page) => page.autoSplit), [false, false]);
});

test('a suggestion is not spent where the page fits without it', () => {
    const pages = packSystems([[system('a', 300)], [system('b', 300)]], 1080);

    assert.deepEqual(names(pages), [['a', 'b']]);
});

test('a piece still too tall after its suggestion is cut again, and that cut is automatic', () => {
    const pages = packSystems([
        [system('a', 400)],
        [system('b', 400), system('c', 400), system('d', 400)],
    ], 1080);

    assert.deepEqual(names(pages), [['a'], ['b', 'c'], ['d']]);
    assert.deepEqual(pages.map((page) => page.autoSplit), [false, false, true]);
});

// Below every tier: no cut helps a system taller than the screen, and it is
// handed back over-tall for the caller to flag rather than shrunk or dropped.
test('a system taller than the screen is a slide of its own, over-tall', () => {
    const pages = packSystems([[system('a', 300), system('giant', 1500), system('b', 300)]], 1080);

    assert.deepEqual(names(pages), [['a'], ['giant'], ['b']]);
    assert.equal(pages[1].height, 1500);
});

test('an empty page comes to no slides', () => {
    assert.deepEqual(packSystems([[]], 1080), []);
});

test('a slide begun by a written break is not automatic; any other after the first is', () => {
    assert.equal(startsAtAutomaticCut({ rows: [{ breakBefore: 'soft' }] }, 0), false);
    assert.equal(startsAtAutomaticCut({ rows: [{ breakBefore: false }] }, 0), false);
    assert.equal(startsAtAutomaticCut({ rows: [{ breakBefore: 'hard' }] }, 2), false);
    assert.equal(startsAtAutomaticCut({ rows: [{ breakBefore: 'soft' }] }, 1), false);
    assert.equal(startsAtAutomaticCut({ rows: [{ breakBefore: false }] }, 1), true);
    assert.equal(startsAtAutomaticCut({ rows: [{}] }, 1), true);
});
