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

/*
 * ---------------------------------------------------------------
 * The laptop's left pane: the deck as it was arranged.
 * ---------------------------------------------------------------
 */

/** Enough of a document for the two sheets this page builds by hand. */
function fakeElement(tag) {
    return {
        tagName: tag.toUpperCase(),
        className: '',
        innerHTML: '',
        textContent: '',
        style: {},
        children: [],
        attributes: {},
        classes: new Set(),
        classList: {
            toggle(name, on) { on ? this.owner.classes.add(name) : this.owner.classes.delete(name); },
        },
        listeners: {},
        appendChild(child) { this.children.push(child); return child; },
        replaceChildren(...kids) { this.children = kids; return undefined; },
        setAttribute(name, value) { this.attributes[name] = value; },
        addEventListener(type, handler) { this.listeners[type] = handler; },
    };
}

globalThis.document.createComment = (text) => ({ nodeType: 8, textContent: text });

globalThis.document.createElement = (tag) => {
    const element = fakeElement(tag);

    element.classList.owner = element;

    return element;
};

/**
 * A remote with a deck drawn and a pane to draw it into: three slides in one
 * row, the middle one left out by the deck itself.
 */
function pane() {
    const component = registered.projectionRemote({ excluded: { 7: [1] }, skippedText: 'Left out' });

    component.entries = [{ id: 7 }];
    component.drawn = [0, 1, 2].map((index) => ({
        entryId: 7,
        index,
        svg: { cloneNode: () => fakeElement('svg') },
    }));

    component.wide = true;
    component.$refs = {
        deck: fakeElement('div'),
        currentBox: fakeElement('div'),
        nextBox: fakeElement('div'),
        strip: null,
    };
    component.push = () => {};

    component.repaint({ entryId: 7, slideIndex: 0 });

    return component;
}

/** What each slide of the pane reads under its picture. */
function numbers(component) {
    return component.$refs.deck.children.map((figure) => figure.children[1].children[0].textContent);
}

/* The sheet numbers the way the editor's does and the way the projector does:
   the slides being walked past take no number, or the pane and the room would
   be counting differently. */
test('the deck pane numbers what the room will be shown', () => {
    const deck = pane();

    assert.equal(deck.$refs.deck.children.length, 3, 'a slide left out went missing from the sheet');
    assert.deepEqual(numbers(deck), ['1', 'Left out', '2']);
});

/* Today's deviation is symmetric: the verse the deck leaves out comes back, and
   the verse it shows is taken out. The same Sunday wants both. */
test('a slide is taken out of today’s service and put back from the pane', () => {
    const deck = pane();

    assert.equal(deck.total, 2);

    deck.toggleReveal(7, 0);

    assert.equal(deck.total, 1, 'the slide taken out is still being shown');
    assert.deepEqual(numbers(deck), ['Left out', 'Left out', '1']);
    assert.deepEqual(deck.reveals, { 7: [0] });

    deck.toggleReveal(7, 0);

    assert.equal(deck.total, 2, 'the slide put back is still missing');
    assert.deepEqual(deck.reveals, {});
});

/* And the verse the deck leaves out, brought back mid-procession, is where the
   service should now be looking. */
test('a verse brought back is the one the service lands on', () => {
    const deck = pane();

    deck.toggleReveal(7, 1);

    assert.equal(deck.total, 3);
    assert.equal(deck.index, 1, 'the service did not follow the verse it brought back');
    assert.deepEqual(numbers(deck), ['1', '2', '3']);
});

/* Taking out the slide the room is on does not leave the service pointing at
   something nobody can see: it is handed on to the nearest slide still shown. */
test('taking out the slide the room is on hands the service on', () => {
    const deck = pane();

    deck.go(1);
    assert.equal(deck.slides[deck.index].index, 2);

    deck.toggleReveal(7, 2);

    assert.equal(deck.total, 1);
    assert.equal(deck.slides[deck.index].index, 0);
});

/* The pane is sixty clones of engraved slides, and a phone has no room to read
   it. Below the breakpoint it is not built at all. */
test('the deck pane is not built for a phone', () => {
    const deck = pane();

    deck.wide = false;
    deck.buildDeck();

    assert.equal(deck.$refs.deck.children.length, 0);
});

/*
 * ---------------------------------------------------------------
 * The laptop's middle column: what is coming, full size.
 * ---------------------------------------------------------------
 */

/* Not a thumbnail of the next verse but the next verse: the hand presses Next
   knowing what lands. Drawn from the slides today is actually being shown, so a
   verse the deck leaves out is not what the box promises. */
test('the laptop is shown the slide the room is about to be on', () => {
    const deck = pane();

    assert.equal(deck.$refs.nextBox.children[0].tagName, 'SVG', 'nothing was drawn under the controls');
    assert.equal(deck.slides[deck.index + 1].index, 2, 'the box promised a slide today leaves out');

    deck.go(1);

    assert.equal(deck.$refs.nextBox.children[0].nodeType, 8, 'the end of the deck still promised a slide');
});

/* And a phone pays nothing for it: there is no room for the box down there, and
   a clone nobody can see is a clone not worth making. */
test('the next slide is not drawn for a phone', () => {
    const deck = pane();

    deck.wide = false;
    deck.show();

    assert.equal(deck.$refs.nextBox.children[0].nodeType, 8);
});

/*
 * ---------------------------------------------------------------
 * The laptop's left column: the deck as the service it came from.
 * ---------------------------------------------------------------
 */

/** A deck of two slots: one music sung two ways, and one sung once. */
function plan() {
    const component = registered.projectionRemote({ excluded: { 8: [0] } });

    component.entries = [
        { id: 7, slotName: 'Kezdőének', label: 'Veni Creator', variation: 'I. tónus' },
        { id: 8, slotName: 'Kezdőének', label: 'Veni Creator', variation: 'II. tónus' },
        { id: 9, slotName: 'Áldozás', label: 'Ave verum' },
    ];

    component.drawn = [
        { entryId: 7, index: 0, svg: null },
        { entryId: 7, index: 1, svg: null },
        { entryId: 8, index: 0, svg: null },
        { entryId: 9, index: 0, svg: null },
    ];

    component.$refs = {};
    component.push = () => {};
    component.repaint({ entryId: 7, slideIndex: 0 });

    return component;
}

/* The question the pane answers during a service is not "what is on slide 41"
   but "is the Communion hymn in, and which verses of it". So it groups by what
   the row is filed under — the deck may print none of it — and every row says
   how much of itself the room is being shown. */
test('the plan column groups the deck by slot and by music', () => {
    const outline = plan().outline;

    assert.deepEqual(outline.map((slot) => slot.name), ['Kezdőének', 'Áldozás']);
    assert.deepEqual(outline[0].musics.map((music) => music.name), ['Veni Creator']);

    const sung = outline[0].musics[0].rows;

    assert.deepEqual(sung.map((row) => row.variation), ['I. tónus', 'II. tónus']);
    assert.deepEqual(sung.map((row) => `${row.shownCount}/${row.slideCount}`), ['2/2', '0/1']);
});

/* A slot that comes round twice is two bands rather than one gathered from
   both ends of the deck: the deck's order is the service's order, and that is
   what the person reading the column is looking at. */
test('the plan column keeps the service’s own order', () => {
    const deck = plan();

    deck.entries = [
        { id: 7, slotName: 'Kezdőének', label: 'Veni Creator' },
        { id: 9, slotName: 'Áldozás', label: 'Ave verum' },
        { id: 8, slotName: 'Kezdőének', label: 'Veni Creator' },
    ];

    assert.deepEqual(deck.outline.map((slot) => slot.name), ['Kezdőének', 'Áldozás', 'Kezdőének']);
});

/* Taking a verse out in the column opposite is a number changing here, which is
   the whole of what the two columns have to say to each other. */
test('the plan column counts what today’s service leaves out', () => {
    const deck = plan();

    assert.equal(deck.outline[1].musics[0].rows[0].shownCount, 1);

    deck.toggleReveal(9, 0);

    assert.equal(deck.outline[1].musics[0].rows[0].shownCount, 0);
});
