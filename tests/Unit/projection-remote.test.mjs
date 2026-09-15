import assert from 'node:assert/strict';
import test from 'node:test';

/*
 * The deck in the hand, asked the questions the laptop asks of it. The phone's
 * half is a thumb on glass and is answered by the buttons; this is the other
 * window of the desktop arrangement — the deck on the projector, the remote
 * beside it — where the only control anybody reaches for is the keyboard.
 */

globalThis.document = { fullscreenEnabled: false, fullscreenElement: null, addEventListener() {}, body: { style: {} } };
globalThis.window = { Alpine: null };

const registered = {};

globalThis.Alpine = { data: (name, factory) => { registered[name] = factory; } };
globalThis.window.Alpine = globalThis.Alpine;

await import('../../resources/js/projection-remote.js');

/**
 * A remote holding a screen that is showing a deck of `total` slides, drawn
 * already and going nowhere: nothing here touches the DOM or the wire, so what
 * is left is exactly what a key does to the service.
 */
function remote(total = 5) {
    const component = registered.projectionRemote({});

    component.slides = Array.from({ length: total }, (unused, index) => ({ entryId: 1, index, svg: null }));
    component.total = total;
    component.presentationId = 1;
    component.show = () => {};
    component.push = () => {};

    return component;
}

/** A key as the window hands it over, remembering whether it was swallowed. */
function press(key) {
    return { key, target: null, prevented: false, preventDefault() { this.prevented = true; } };
}

test('the keys every display program taught the person at the laptop', () => {
    const deck = remote();

    for (const key of [' ', 'ArrowRight', 'ArrowDown', 'PageDown', 'Enter']) {
        deck.index = 0;
        deck.onKey(press(key));

        assert.equal(deck.index, 1, `${key} did not advance the service`);
    }

    for (const key of ['ArrowLeft', 'ArrowUp', 'PageUp', 'Backspace']) {
        deck.index = 2;
        deck.onKey(press(key));

        assert.equal(deck.index, 1, `${key} did not go back`);
    }

    deck.onKey(press('End'));
    assert.equal(deck.index, 4);

    deck.onKey(press('Home'));
    assert.equal(deck.index, 0);
});

/* The deck stops at both ends rather than running off them: a key held down at
   the last verse must not leave the service pointing past the deck. */
test('the ends of the deck hold', () => {
    const deck = remote(2);

    deck.onKey(press('ArrowLeft'));
    assert.equal(deck.index, 0);

    deck.onKey(press('ArrowRight'));
    deck.onKey(press('ArrowRight'));
    assert.equal(deck.index, 1);
});

/* The thumb's half-second lock is for glass pressed without looking. A second
   press of a key is a second press, and the wall has never locked one. */
test('a keyed move is not held back by the lock the thumb needs', () => {
    const deck = remote();

    deck.onKey(press('ArrowRight'));
    deck.onKey(press('ArrowRight'));
    deck.onKey(press('ArrowRight'));

    assert.equal(deck.index, 3);

    deck.next();
    assert.equal(deck.index, 4, 'the button and the key are the same move');
    deck.next();
    assert.equal(deck.index, 4, 'the button lost its lock');
});

/* B behind the black, exactly as on the wall: the next hymn is lined up while
   the sermon is preached, and only B brings the picture back. */
test('the screen is blanked and walked through behind the black', () => {
    const deck = remote();

    deck.onKey(press('b'));
    assert.equal(deck.blanked, true);

    deck.onKey(press('ArrowRight'));
    assert.equal(deck.index, 1);
    assert.equal(deck.blanked, true, 'the wall came back mid-sermon');

    deck.onKey(press('B'));
    assert.equal(deck.blanked, false);
});

/* The plan is a swipe on a phone and has no gesture on a laptop. Esc closes it
   where it is open, and otherwise belongs to the browser — it is how a
   full-screen window is left. */
test('the plan opens and closes from the keyboard', () => {
    const deck = remote();

    deck.onKey(press('l'));
    assert.equal(deck.listOpen, true);

    const escape = press('Escape');
    deck.onKey(escape);

    assert.equal(deck.listOpen, false);
    assert.equal(escape.prevented, true);

    const again = press('Escape');
    deck.onKey(again);

    assert.equal(again.prevented, false, 'Esc was taken from the browser with nothing to close');
});

/* Every key bound here is a key a text field needs, and the remote carries
   fields. A keystroke aimed at one belongs to it and not to the service. */
test('a key typed into a field is not a slide', () => {
    const deck = remote();
    const typed = { key: ' ', target: { tagName: 'INPUT' }, prevented: false, preventDefault() { this.prevented = true; } };

    deck.onKey(typed);

    assert.equal(deck.index, 0);
    assert.equal(typed.prevented, false);
});

/* Nor is a browser shortcut: nothing here is worth costing somebody the tab
   they meant to switch to. */
test('a browser shortcut is left to the browser', () => {
    const deck = remote();
    const shortcut = { ...press('ArrowRight'), metaKey: true };

    deck.onKey(shortcut);

    assert.equal(deck.index, 0);
});

/* The laptop's remote is a window beside the window the room is reading, often
   literally beside it on the other display. Taking the whole screen at the
   first press is a phone's answer and would hide the deck it is driving. */
test('the laptop is not thrown into full screen by pressing next', () => {
    const deck = remote();

    document.fullscreenEnabled = true;
    window.matchMedia = () => ({ matches: false });

    let asked = false;
    deck.$refs = { stage: { requestFullscreen: () => { asked = true; } } };

    deck.onKey(press('ArrowRight'));

    assert.equal(asked, false, 'the control window swallowed the screen');

    window.matchMedia = () => ({ matches: true });
    deck.onKey(press('ArrowRight'));

    assert.equal(asked, true, 'the phone stopped taking the screen at the first press');

    document.fullscreenEnabled = false;
    delete window.matchMedia;
});
