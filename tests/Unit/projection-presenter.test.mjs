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

/**
 * A presenter with a deck of `total` slides, drawn already.
 *
 * With a presentation on it, because that is what having a deck means: a screen
 * with none is waiting, and a waiting screen keeps its bar.
 */
function presenter(total = 3) {
    const component = registered.projectionPresenter({});

    component.slides = Array.from({ length: total }, (unused, index) => ({ entryId: 1, index, svg: null }));
    component.total = total;
    component.presentationId = 1;
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

/* The screen waiting for a deck is also the screen carrying a text field, and
   every key this page binds is a key that field needs. A name being typed is not
   a service being driven. */
test('a name being typed is left alone by the deck', () => {
    const deck = presenter(3);
    const field = { tagName: 'INPUT', closest: () => null };

    let prevented = false;
    const type = (key) => deck.onKey({ key, target: field, preventDefault() { prevented = true; } });

    type('ArrowLeft');
    type('Backspace');
    type(' ');
    type('b');
    type('f');

    assert.equal(deck.index, 0, 'typing moved the service');
    assert.equal(deck.blanked, false, 'typing a b blanked the wall');
    assert.equal(prevented, false, 'the field never received the key it was typed into');
});

test('a key pressed inside an open dialog belongs to the dialog', () => {
    const deck = presenter(3);
    const inside = { tagName: 'BUTTON', closest: (selector) => (selector.includes('dialog') ? {} : null) };

    deck.onKey({ key: 'ArrowRight', target: inside, preventDefault() {} });

    assert.equal(deck.index, 0);
});

test('the keyboard still drives the deck from the page itself', () => {
    const deck = presenter(3);
    const stage = { tagName: 'DIV', closest: () => null };

    deck.onKey({ key: 'ArrowRight', target: stage, preventDefault() {} });

    assert.equal(deck.index, 1);
});

/* The no-toolbar rule protects the picture the congregation is reading. A screen
   waiting for a deck has no picture — and a full-screen page showing one centred
   line on black with no way out is what a browser's scam heuristics look for. */
test('a waiting screen keeps its bar even in full screen', () => {
    const deck = presenter(0);

    deck.presentationId = null;
    deck.fullscreen = true;
    deck.idle = true;

    assert.equal(deck.waiting, true);
    assert.equal(deck.controlsHidden, false, 'the wall sat in full screen with no way out of it');
});

test('a screen with a deck on it still hides everything in full screen', () => {
    const deck = presenter(3);

    deck.presentationId = 7;
    deck.fullscreen = true;
    deck.idle = false;

    assert.equal(deck.controlsHidden, true, 'a control was left over the hymn');
});

/* A dialog waiting for text in the middle of a full-screen page is what a
   browser's scam heuristics are built to catch, and Edge blocks the whole site
   rather than the dialog. The page steps out of full screen before it asks. */
test('the wall leaves full screen before anything asks for typing', () => {
    const deck = presenter(3);
    let left = false;

    document.fullscreenElement = {};
    document.exitFullscreen = () => { left = true; return Promise.resolve(); };

    deck.leaveFullscreenToType();

    assert.equal(left, true, 'a dialog was opened over a full-screen page');

    document.fullscreenElement = null;
});

test('a windowed screen is not thrown out of anything to type in', () => {
    const deck = presenter(3);
    let left = false;

    document.fullscreenElement = null;
    document.exitFullscreen = () => { left = true; return Promise.resolve(); };

    deck.leaveFullscreenToType();

    assert.equal(left, false);
});

/* The wall is the one device that cannot be lined up from where it stands: the
   laptop faces the room from somewhere else, and the person who can see whether
   the picture is where it belongs is at the organ holding a phone. So the fit is
   read off the screen, and is a fact about the room rather than about the deck —
   a screen still waiting for one is lined up as readily as a screen mid-hymn. */
test('the wall takes the fit its screen was given', async () => {
    const deck = presenter(3);

    deck.presentationId = 1;
    deck._screen = { read: () => Promise.resolve({ presentationId: 1, title: 'Vasárnap', fit: { scale: 0.8, x: 0, y: 0.1 }, state: null }) };

    await deck.pull();

    assert.equal(deck.fit.scale, 0.8);
    assert.equal(deck.fit.y, 0.1);
    assert.equal(deck.fitTransform, 'translate(0%, 10%) scale(0.8)');
});

test('a fit that says nothing leaves the picture where the deck was fitted', async () => {
    const deck = presenter(3);

    deck.presentationId = 1;
    deck._screen = { read: () => Promise.resolve({ presentationId: 1, state: null }) };

    await deck.pull();

    assert.deepEqual(deck.fit, { scale: 1, x: 0, y: 0 });
});

/*
 * The title card. The window is opened on the laptop and dragged onto the
 * beamer, and what the congregation reads while that happens must not be the
 * first slide of a hymn nobody is singing yet.
 */
function carded(total = 3) {
    const deck = presenter(total);

    deck.splash = true;

    return deck;
}

test('the first Next ends the card and lands on the first slide, not the second', () => {
    const deck = carded();

    deck.next();

    assert.equal(deck.splash, false, 'the card outlived the press that was meant to end it');
    assert.equal(deck.index, 0, 'the room was shown the second slide without ever seeing the first');

    deck.next();

    assert.equal(deck.index, 1);
});

/* There is nothing behind the beginning, so going back out of the card is the
   only honest thing Previous can do there. */
test('going back out of the card leaves it without moving the deck', () => {
    const deck = carded();

    deck.previous();

    assert.equal(deck.splash, false);
    assert.equal(deck.index, 0);
});

/* Blanking during the card is the cantor saying "not yet, and not this either".
   The card is over and the wall is black; B again brings up the first slide. */
test('blanking during the card ends it and shows black', () => {
    const deck = carded();

    deck.toggleBlank();

    assert.equal(deck.splash, false);
    assert.equal(deck.blanked, true);
    assert.equal(deck.index, 0);

    deck.toggleBlank();

    assert.equal(deck.blanked, false);
    assert.equal(deck.index, 0, 'coming back from black skipped the first slide');
});

test('a jump straight to a slide ends the card and goes there', () => {
    const deck = carded(4);

    deck.go(2);

    assert.equal(deck.splash, false);
    assert.equal(deck.index, 2);
});

/* The card is a picture, not a console — but it is also the half hour before
   Mass, when a full-screen page showing one centred line with no visible way out
   is exactly what a browser's scam heuristics are built to catch. */
test('the card keeps its bar even in full screen', () => {
    const deck = carded();

    deck.fullscreen = true;
    deck.idle = true;

    assert.equal(deck.showingSplash, true);
    assert.equal(deck.controlsHidden, false, 'the wall sat in full screen with no way out of it');
});

/* A screen still engraving a deck has nothing to say, and a card over that would
   claim a service was about to start when none is. */
test('the card is not drawn over a deck that is still being prepared', () => {
    const deck = carded();

    deck.preparing = true;

    assert.equal(deck.showingSplash, false);

    deck.preparing = false;
    deck.presentationId = null;

    assert.equal(deck.showingSplash, false);
});

/* The wall reports where it is; what it must never report is a card it has
   already let go of, since that is the state the phone reads back. */
test('the wall reports the card it is holding, and the one it has ended', () => {
    const deck = carded();
    const written = [];

    deck._client = { write: (state) => { written.push(state); return Promise.resolve(null); } };

    deck.report();
    deck.next();

    assert.deepEqual(written.map((state) => state.splash), [true, false]);
});

/* Where the card comes from at all: the server, because the press that ends it
   may have been a thumb on a phone across the building. */
test('the wall takes the card up and puts it down as the server says', () => {
    const deck = presenter(3);

    deck.adopt({ version: 2, entryId: 1, slideIndex: 0, splash: true, blanked: false, reveals: {} });
    assert.equal(deck.splash, true);

    deck.adopt({ version: 3, entryId: 1, slideIndex: 0, splash: false, blanked: false, reveals: {} });
    assert.equal(deck.splash, false);
});
