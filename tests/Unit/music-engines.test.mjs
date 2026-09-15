import assert from 'node:assert/strict';
import test from 'node:test';

/*
 * The wait a drawing owes the two engines that are globals.
 *
 * The case this exists for is a page reached by wire:navigate, where the script
 * tags that load exsurge and abc2svg are re-created by Livewire and therefore
 * load asynchronously, while Alpine starts drawing as soon as the head bundles
 * are in. What is asserted here is the whole of the contract: wait for an engine
 * the page is loading, never for one it does not carry, and never for ever.
 */

globalThis.window = {};
globalThis.document = { scripts: [] };

const { enginesReady } = await import('../../resources/js/music-engines.js');

/** A page carrying these script tags, with neither engine arrived yet. */
function page(...srcs) {
    globalThis.window.exsurge = undefined;
    globalThis.window.abc2svg = undefined;
    globalThis.document.scripts = srcs.map((src) => ({ getAttribute: () => src }));
}

const EXSURGE = 'https://cdn.jsdelivr.net/gh/bbloomf/exsurge@v1.22.1/dist/exsurge.min.js';
const ABC2SVG = '/js/abc2svg-1.js?v=1757000000';

test('a page that carries neither engine waits for nothing', async () => {
    page();

    const startedAt = Date.now();
    await enginesReady();

    assert.ok(Date.now() - startedAt < 20, 'a deck of words was made to wait for engines it never uses');
});

test('engines already loaded are not waited for', async () => {
    page(EXSURGE, ABC2SVG);
    globalThis.window.exsurge = {};
    globalThis.window.abc2svg = { Abc: function Abc() {} };

    const startedAt = Date.now();
    await enginesReady();

    assert.ok(Date.now() - startedAt < 20, 'a page whose engines had arrived was made to wait anyway');
});

test('a drawing waits for an engine still on its way', async () => {
    page(EXSURGE, ABC2SVG);

    let arrived = false;

    setTimeout(() => {
        globalThis.window.exsurge = {};
        globalThis.window.abc2svg = { Abc: function Abc() {} };
        arrived = true;
    }, 120);

    await enginesReady();

    assert.equal(arrived, true, 'the deck was drawn before the engines that draw it had loaded');
});

test('the object the measuring span hangs on is not the library', async () => {
    page(ABC2SVG);

    // The page makes `window.abc2svg` itself, to put the off-screen span
    // abc2svg measures text with on it, so the object exists a moment before
    // the library behind it does. Taking that for the library is exactly the
    // race this file is here to end.
    globalThis.window.abc2svg = { el: {} };

    let loaded = false;

    setTimeout(() => {
        globalThis.window.abc2svg.Abc = function Abc() {};
        loaded = true;
    }, 120);

    await enginesReady();

    assert.equal(loaded, true, 'the bare abc2svg object was taken for the loaded library');
});

test('an engine that never comes costs the wait and not the deck', async () => {
    page(EXSURGE, ABC2SVG);
    globalThis.window.abc2svg = { Abc: function Abc() {} };

    const startedAt = Date.now();
    await enginesReady(100);

    const waited = Date.now() - startedAt;

    assert.ok(waited >= 100, 'the deadline was not waited out');
    assert.ok(waited < 1000, 'a deck was left undrawn by an engine that was never coming');
});
