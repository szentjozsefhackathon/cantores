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
 * The opening of a service, which has three pictures. The card is up while the
 * window is dragged onto the beamer and the projector is lined up against it;
 * then the room fills and the wall should be showing nothing at all; then the
 * first hymn is announced. Each press walks it on one, and none of them moves
 * the deck — which is what makes the last of them land on the first slide.
 */
function carded(total = 3) {
    const deck = presenter(total);

    deck.splash = 'card';

    return deck;
}

test('the opening walks card, dark, first slide — one press each, all of it black until B', () => {
    const deck = carded();

    deck.next();

    assert.equal(deck.splash, 'dark', 'the card did not give way to the dark');
    assert.equal(deck.dark, true, 'the wall was not black between the card and the deck');
    assert.equal(deck.index, 0);

    deck.next();

    assert.equal(deck.splash, 'off');
    assert.equal(deck.blanked, true, 'the press that ended the opening showed a slide instead of only selecting one');
    assert.equal(deck.dark, true, 'the deck started in front of the room instead of behind the black');
    assert.equal(deck.index, 0);

    deck.next();

    assert.equal(deck.index, 1, 'Next did not go on advancing once the opening was over');
    assert.equal(deck.dark, true, 'an ordinary Next lit the wall up on its own');
});

/* There is nothing behind the beginning of a service, and an opening that could
   be rewound is one a stale heartbeat could rewind for you, over a hymn. */
test('going back during the opening walks it forwards too', () => {
    const deck = carded();

    deck.previous();
    assert.equal(deck.splash, 'dark');

    deck.previous();
    assert.equal(deck.splash, 'off');
    assert.equal(deck.index, 0);
    assert.equal(deck.blanked, true, 'the opening handed the deck over already showing');
});

/* Over the card, B asks for black — which is exactly what comes next. Over the
   dark, B is the button that shows the slides, so it shows the first one in
   the same press rather than handing back more black to un-press later. */
test('B walks the opening and shows the first slide as soon as it is over', () => {
    const deck = carded();

    deck.toggleBlank();

    assert.equal(deck.splash, 'dark');
    assert.equal(deck.blanked, false, 'the dark of the opening was mistaken for the cantor blanking the wall');
    assert.equal(deck.dark, true);

    deck.toggleBlank();

    assert.equal(deck.splash, 'off');
    assert.equal(deck.blanked, false, 'B ended the opening without showing anything, though B is the button that shows slides');
    assert.equal(deck.dark, false);
    assert.equal(deck.index, 0, 'coming back from black skipped the first slide');

    deck.toggleBlank();

    assert.equal(deck.blanked, true, 'a third press, now an ordinary blank, did not darken the wall');
});

test('a jump straight to a slide reaches past the whole opening', () => {
    const deck = carded(4);

    deck.go(2);

    assert.equal(deck.splash, 'off');
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

/* The person at the keyboard is told what the next press does. The room is not:
   in full screen the bar is gone, and a line of instructions thrown across a
   church is the one thing this page exists to prevent. */
test('each picture of the opening says what the next press will do', () => {
    const deck = registered.projectionPresenter({ cardHint: 'card hint', darkHint: 'dark hint' });

    deck.presentationId = 1;
    deck.splash = 'card';
    assert.equal(deck.openingHint, 'card hint');

    deck.splash = 'dark';
    assert.equal(deck.openingHint, 'dark hint');

    deck.splash = 'off';
    assert.equal(deck.openingHint, '');
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

/* The wall reports where the opening is; what it must never report is a picture
   it has already walked past, since that is the state the phone reads back. */
test('the wall reports the picture of the opening it is holding', () => {
    const deck = carded();
    const written = [];

    deck._client = { write: (state) => { written.push(state); return Promise.resolve(null); } };

    deck.report();
    deck.next();
    deck.next();

    assert.deepEqual(written.map((state) => state.splash), ['card', 'dark', 'off']);
});

/* Where the opening comes from at all: the server, because the press that walked
   it may have been a thumb on a phone across the building. */
test('the wall walks the opening as the server says', () => {
    const deck = presenter(3);

    deck.adopt({ version: 2, entryId: 1, slideIndex: 0, splash: 'card', blanked: false, reveals: {} });
    assert.equal(deck.splash, 'card');

    deck.adopt({ version: 3, entryId: 1, slideIndex: 0, splash: 'dark', blanked: false, reveals: {} });
    assert.equal(deck.splash, 'dark');

    deck.adopt({ version: 4, entryId: 1, slideIndex: 0, splash: 'off', blanked: false, reveals: {} });
    assert.equal(deck.splash, 'off');
});

/*
 * The heartbeat is a report and not an argument: the server keeps the opening as
 * a latch, refuses a picture the service has already walked past, and bumps no
 * version for the refusal — so the poll that corrects everything else never
 * fires for this. A wall that does not take the refusal back from the answer to
 * its own beat holds the title card over the hymn a phone has just started.
 */
test('the wall takes back an opening the server refused', async () => {
    const deck = carded();

    deck._client = { write: () => Promise.resolve({ version: 9, splash: 'off', entryId: 1, slideIndex: 0, blanked: false, reveals: {} }) };

    await deck.report();

    assert.equal(deck.splash, 'off', 'the wall went on holding a card the room had stopped looking at');
    assert.equal(deck.appliedVersion, 9);
});
