import assert from 'node:assert/strict';
import test from 'node:test';

/*
 * The deck on the wall, asked the two questions the congregation would ask of it:
 * whether it is showing anything a cantor is meant to touch, and whether the
 * black it was put into stays black while the next hymn is lined up behind it.
 */

globalThis.document = { fullscreenElement: null, addEventListener() {} };
globalThis.window = { Alpine: null };

const registered = {};

globalThis.Alpine = { data: (name, factory) => { registered[name] = factory; } };
globalThis.window.Alpine = globalThis.Alpine;

await import('../../resources/js/projection-presenter.js');

/** A presenter with a deck of `total` slides, drawn already. */
function presenter(total = 3) {
    const component = registered.projectionPresenter({});

    component.slides = Array.from({ length: total }, (unused, index) => ({ entryId: 1, index, svg: null }));
    component.total = total;
    component.show = () => {};

    return component;
}

test('full screen is a picture, not a console', () => {
    const deck = presenter();

    deck.idle = false;
    deck.fullscreen = true;

    assert.equal(deck.controlsHidden, true, 'a control was left on the screen the congregation sees');
});

test('in a window the bar still comes back when somebody moves', () => {
    const deck = presenter();

    deck.fullscreen = false;
    deck.idle = false;

    assert.equal(deck.controlsHidden, false);

    deck.idle = true;

    assert.equal(deck.controlsHidden, true);
});

/* Esc leaves full screen without going through the button, so the state is read
   back off the document rather than toggled alongside the request. */
test('leaving full screen by any route brings the bar back', () => {
    const deck = presenter();

    document.fullscreenElement = {};
    deck.syncFullscreen();
    assert.equal(deck.fullscreen, true);

    document.fullscreenElement = null;
    deck.syncFullscreen();
    assert.equal(deck.fullscreen, false);
});

/* The point of blanking: the sermon is being preached, and the cantor lines the
   next hymn up behind the black rather than in front of the congregation. */
test('a blanked screen is walked through without showing anything', () => {
    const deck = presenter(3);

    deck.blanked = true;
    deck.next();

    assert.equal(deck.index, 1);
    assert.equal(deck.blanked, true, 'the wall came back mid-sermon');

    deck.next();
    deck.previous();

    assert.equal(deck.index, 1);
    assert.equal(deck.blanked, true);
});

test('only B brings the picture back', () => {
    const deck = presenter();
    const press = (key) => deck.onKey({ key, preventDefault() {} });

    press('b');
    assert.equal(deck.blanked, true);

    press(' ');
    press('ArrowRight');
    press('ArrowLeft');

    assert.equal(deck.blanked, true);
    assert.equal(deck.index, 1);

    press('B');
    assert.equal(deck.blanked, false);
});
