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

/* The blank goes out only with the press that changed it: a slide moved a
   moment before this phone heard of a B pressed on the wall must not light the
   wall back up. */
test('only the blank button reports the blank', () => {
    const deck = registered.projectionRemote({});
    const written = [];

    deck.slides = Array.from({ length: 5 }, (unused, index) => ({ entryId: 1, index, svg: null }));
    deck.total = 5;
    deck.presentationId = 1;
    deck.show = () => {};
    deck._client = { write: (state) => { written.push(state); return Promise.resolve(null); } };

    deck.onKey(press('ArrowRight'));
    deck.onKey(press('b'));

    assert.equal('blanked' in written[0], false, 'moving a slide reported the blank');
    assert.equal(written[1].blanked, true);
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
        // Enough geometry for the pane that follows the service: laid out
        // nowhere, so nothing is ever out of view and nothing scrolls.
        getBoundingClientRect() { return { top: 0, bottom: 0, height: 0 }; },
        scrollBy() {},
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
    const component = registered.projectionRemote({
        excluded: { 8: [0] },
        outline: [
            {
                kind: 'slot', id: 1, name: 'Kezdőének', canMoveUp: false, canMoveDown: true,
                children: [
                    { kind: 'music', local: false, assignmentId: 100, addedMusicId: null, title: 'Veni Creator', canMoveUp: false, canMoveDown: false, offers: [],
                        children: [
                            { kind: 'entry', entryId: 7, canMoveUp: false, canMoveDown: false },
                            { kind: 'entry', entryId: 8, canMoveUp: false, canMoveDown: false },
                        ] },
                ],
            },
            {
                kind: 'slot', id: 2, name: 'Áldozás', canMoveUp: true, canMoveDown: false,
                children: [
                    { kind: 'music', local: false, assignmentId: 200, addedMusicId: null, title: 'Ave verum', canMoveUp: false, canMoveDown: false, offers: [],
                        children: [
                            { kind: 'entry', entryId: 9, canMoveUp: false, canMoveDown: false },
                        ] },
                ],
            },
        ],
    });

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

    const musics = outline[0].blocks.filter((block) => block.kind === 'music');
    assert.deepEqual(musics.map((music) => music.name), ['Veni Creator']);

    const sung = musics[0].rows;

    assert.deepEqual(sung.map((row) => row.variation), ['I. tónus', 'II. tónus']);
    assert.deepEqual(sung.map((row) => `${row.shownCount}/${row.slideCount}`), ['2/2', '0/1']);
});

/* A slot that comes round twice is two bands rather than one gathered from
   both ends of the deck: the plan's own order is the service's order, and
   that is what the person reading the column is looking at. */
test('the plan column keeps the service’s own order', () => {
    const deck = plan();

    deck.outlineTree = [
        deck.outlineTree[0],
        deck.outlineTree[1],
        { ...deck.outlineTree[0], id: 3 },
    ];

    assert.deepEqual(deck.outline.map((slot) => slot.name), ['Kezdőének', 'Áldozás', 'Kezdőének']);
});

/* Taking a verse out in the column opposite is a number changing here, which is
   the whole of what the two columns have to say to each other. */
test('the plan column counts what today’s service leaves out', () => {
    const deck = plan();
    const musicIn = (slotIndex) => deck.outline[slotIndex].blocks.find((block) => block.kind === 'music');

    assert.equal(musicIn(1).rows[0].shownCount, 1);

    deck.toggleReveal(9, 0);

    assert.equal(musicIn(1).rows[0].shownCount, 0);
});

/* A slot's music is usually sung from one of several engravings of it, all
   sharing its title, so a row named by its music alone is a row nobody can tell
   from the two beneath it. The pane names rows the way the editor's plan does:
   the score, the file chosen out of it, the variation, the opening words of a
   paragraph — all read off the score rather than off the headings, since a deck
   that prints none of it still has to be legible in the hand. */
test('the plan column names a row the way the editor does', () => {
    const deck = plan();

    deck.entries = [
        {
            id: 7,
            slotName: 'Kezdőének',
            label: 'Veni Creator',
            scoreName: 'Veni Creator Spiritus',
            fileName: 'Orgonakíséret',
            variationName: 'I. tónus',
            incipitUrl: '/incipits/7.png',
        },
        { id: 8, kind: 'text', label: null, text: '# Állunk\nA kántor énekli a verseket.' },
    ];

    const [score, words] = deck.rows;

    assert.equal(deck.rowName(score), 'Veni Creator Spiritus · Orgonakíséret');
    assert.equal(score.variationName, 'I. tónus');
    assert.equal(score.incipit, '/incipits/7.png');
    assert.equal(score.isText, false);

    assert.equal(words.isText, true);
    assert.equal(deck.rowName(words), 'Állunk');
});

/* A score holding one file names only itself, and a row whose score has gone
   since the deck was made still has to say something. */
test('a row with nothing chosen out of it names the score alone', () => {
    const deck = plan();

    deck.entries = [
        { id: 7, slotName: 'Kezdőének', label: 'Veni Creator', scoreName: 'Veni Creator Spiritus' },
        { id: 8, slotName: 'Kezdőének', label: 'Veni Creator' },
    ];

    const [named, bare] = deck.rows;

    assert.equal(deck.rowName(named), 'Veni Creator Spiritus');
    assert.equal(deck.rowName(bare), 'Veni Creator');
});

/*
 * ---------------------------------------------------------------
 * The sheet of slides down the laptop's right-hand side.
 * ---------------------------------------------------------------
 */

/** A deck pane 300 high, holding a slide wherever the test puts it. */
function sheet(top, height = 100) {
    const scrolled = [];
    const component = registered.projectionRemote({});

    component.$refs = {
        deck: {
            getBoundingClientRect: () => ({ top: 0, bottom: 300, height: 300 }),
            scrollBy: (options) => scrolled.push(options),
        },
    };

    const item = { figure: { getBoundingClientRect: () => ({ top, bottom: top + height, height }) } };

    return { component, item, scrolled };
}

/* A sixty-slide deck is several screens of pictures, and the mark on the slide
   the room is reading is worth nothing on the screenful nobody is looking at. */
test('the sheet scrolls to the slide the room is reading', () => {
    const { component, item, scrolled } = sheet(700);

    component.scrollDeckTo(item, { entryId: 7, index: 3 });

    assert.equal(scrolled.length, 1);
    // Centred: the jump lands with what came before and what is coming either
    // side of it rather than pinned to an edge.
    assert.equal(scrolled[0].top, 600);
});

/* An ordinary Next moves nothing while the next slide is still on screen:
   a pane that re-scrolled on every move could not be read ahead in. */
test('a slide already in view is left where it is', () => {
    const { component, item, scrolled } = sheet(120);

    component.scrollDeckTo(item, { entryId: 7, index: 3 });

    assert.deepEqual(scrolled, []);
});

/* The poll answers twice a second and says the same thing each time. A pane
   that scrolled on every answer could not be scrolled by hand at all. */
test('the sheet follows a move rather than an answer', () => {
    const { component, item, scrolled } = sheet(700);

    component.scrollDeckTo(item, { entryId: 7, index: 3 });
    component.scrollDeckTo(item, { entryId: 7, index: 3 });

    assert.equal(scrolled.length, 1);

    component.scrollDeckTo(item, { entryId: 7, index: 4 });

    assert.equal(scrolled.length, 2);
});

/*
 * ---------------------------------------------------------------
 * Where the picture lands on the wall.
 * ---------------------------------------------------------------
 *
 * The one control on this page that is not about the service at all: a square
 * screen hung high off a beamer nobody may touch, and a deck landing above the
 * heads it was built for. The phone is the only device in the building that can
 * see whether it has been fixed.
 */

/** A wall as the show's answer lists it. */
function wall(id, fit = { scale: 1, x: 0, y: 0 }, isThisDevice = false) {
    return { id, label: `Screen ${id}`, fit, fitUrl: `/screens/${id}/fit`, isThisDevice };
}

/** A remote with one wall on, whose fit writes are remembered rather than sent. */
function lining() {
    const deck = remote();
    const sent = [];

    deck.screens = [wall(5)];
    deck._show = { adjust: (screen, fit) => { sent.push({ screen: screen.id, ...fit }); return Promise.resolve(fit); } };
    deck.sent = sent;

    return deck;
}

test('the four arrows move the picture and tell the screen', () => {
    const deck = lining();

    deck.moveFit(0, 1);
    deck.moveFit(1, 0);

    assert.ok(deck.fit.y > 0, 'the picture did not come down');
    assert.ok(deck.fit.x > 0, 'the picture did not go right');
    assert.equal(deck.sent.length, 2, 'the wall was not told');
    assert.deepEqual(deck.sent.at(-1), { screen: 5, ...deck.fit }, 'the wall was told something other than what the phone shows');
});

/* The show is on every screen, but every projector is hung differently. The
   server lists this device only while its own wall is up — the laptop with the
   remote in the other window — so that wall is lined up like any other, and
   nothing is when no wall is on. */
test('the panel lines up this device when its own wall is up', () => {
    const deck = lining();

    deck.screens = [wall(9, { scale: 1, x: 0, y: 0 }, true)];

    assert.equal(deck.fitTarget?.id, 9);
});

test('the panel lines up nothing when no wall is on', () => {
    const deck = lining();

    deck.screens = [];

    assert.equal(deck.fitTarget, null);

    deck.openFit();
    deck.moveFit(1, 0);

    assert.equal(deck.fitOpen, false, 'the panel opened with no wall to line up');
    assert.equal(deck.sent.length, 0, 'a nudge was sent to nowhere');
});

/* Two walls on at once is the laptop at home left open. Then the chooser
   decides, and the picture shown is the chosen wall's own. */
test('a chosen wall is the one nudged, starting from where it has the picture', () => {
    const deck = lining();

    deck.screens = [wall(5), wall(6, { scale: 0.5, x: 0, y: 0.1 })];

    deck.chooseFitScreen('6');

    assert.equal(deck.fit.scale, 0.5, 'the panel showed the other wall\'s picture');

    deck.moveFit(0, 1);

    assert.deepEqual(deck.sent.map((one) => one.screen), [6]);
});

test('the zoom scales the picture and centring puts it back', () => {
    const deck = lining();

    deck.zoomFit(-1);
    assert.ok(deck.fit.scale < 1, 'the picture did not shrink');
    assert.equal(deck.fitIsNeutral, false);

    deck.resetFit();

    assert.deepEqual(deck.fit, { scale: 1, x: 0, y: 0 });
    assert.equal(deck.fitIsNeutral, true);
    assert.equal(deck.fitPercent, 100);
});

/* The phone cannot see that the tenth press did nothing, and a picture driven
   off the edge of the room is one somebody has to walk to the laptop to fix. */
test('the picture cannot be pressed off the edge of the screen', () => {
    const deck = lining();

    for (let press = 0; press < 200; press += 1) {
        deck.moveFit(-1, -1);
        deck.zoomFit(-1);
    }

    assert.ok(deck.fit.x >= -1 && deck.fit.y >= -1, 'the picture left the screen');
    assert.ok(deck.fit.scale >= 0.25, 'the picture shrank to nothing');
});

/* While the panel is open the arrows are aimed at the picture: nobody opens it
   in order to change slide, and two meanings for one key is one too many. */
test('the keyboard drives the picture while the panel is open', () => {
    const deck = lining();

    deck.openFit();
    deck.index = 1;

    deck.onKey(press('ArrowRight'));

    assert.equal(deck.index, 1, 'lining the projector up moved the service');
    assert.ok(deck.fit.x > 0, 'the arrow did not move the picture');

    deck.onKey(press('0'));
    assert.equal(deck.fitIsNeutral, true);

    deck.onKey(press('Escape'));
    assert.equal(deck.fitOpen, false);

    deck.onKey(press('ArrowRight'));
    assert.equal(deck.index, 2, 'the service stayed put once the panel was shut');
});

/* An arrow pressed with the mouse leaves the focus inside the dialog, and every
   key inside a dialog otherwise belongs to the dialog. It must not cost the
   keyboard the arrows for the rest of the session. */
test('the keyboard still works after an arrow has been pressed with the mouse', () => {
    const deck = lining();

    deck.openFit();
    deck.onKey({ key: 'ArrowUp', target: { tagName: 'BUTTON', closest: (what) => (what.includes('dialog') ? {} : null) }, preventDefault() {} });

    assert.ok(deck.fit.y < 0, 'the arrow was swallowed by the panel it was aimed at');
});

/* Every press here is optimistic, and the poll that lands a moment later was
   answered before the write. Lining a projector up by pressing an arrow that
   undoes itself is not something anybody can do from across a building. */
test('a poll answered before the press does not undo the press', async () => {
    const deck = lining();

    deck.presentationId = 1;
    deck.refresh = () => Promise.resolve();
    deck._show.read = () => Promise.resolve({
        presentationId: 1,
        title: 'Vasárnap',
        screens: [wall(5)],
        state: { version: 0, revision: 'r', drawnRevision: 'r', endedAt: null },
    });

    deck.moveFit(1, 0);

    const pressed = deck.fit.x;

    await deck.pull();

    assert.equal(deck.fit.x, pressed, 'the screen’s stale answer undid the arrow');

    // And once the write has plainly landed, the screen is the authority again:
    // another phone lining the same wall up is followed like anything else.
    deck._fitAt = Date.now() - 60000;

    await deck.pull();

    assert.equal(deck.fit.x, 0, 'the phone went on believing its own hand');
});

/*
 * The opening of a service, from the thumb's side. The press that walks it is as
 * often this one as the keyboard across the building, and either way the deck
 * stays where it stands until the opening is over.
 */
function carded(total = 5) {
    const deck = remote(total);

    deck.splash = 'card';

    return deck;
}

test('one press of anything walks the opening on and moves the service nowhere', () => {
    for (const key of [' ', 'ArrowRight', 'PageDown', 'Enter', 'ArrowLeft', 'End']) {
        const deck = carded();

        deck.onKey(press(key));

        assert.equal(deck.splash, 'dark', `${key} did not black the wall out`);
        assert.equal(deck.index, 0, `${key} moved the service past the slide nobody had seen`);

        deck.onKey(press(key));

        assert.equal(deck.splash, 'off', `${key} did not start the deck`);
        assert.equal(deck.blanked, true, `${key} showed the deck instead of only starting it`);
        assert.equal(deck.index, 0, `${key} skipped the first slide`);
    }
});

test('the Next button walks card, dark, first slide — all of it black until B', () => {
    const deck = carded();

    deck.next();
    assert.equal(deck.splash, 'dark');
    assert.equal(deck.index, 0);

    deck._pressedAt = {};
    deck.next();
    assert.equal(deck.splash, 'off');
    assert.equal(deck.blanked, true, 'the press that ended the opening showed a slide instead of only selecting one');
    assert.equal(deck.index, 0);

    deck._pressedAt = {};
    deck.next();
    assert.equal(deck.index, 1);
    assert.equal(deck.blanked, true, 'an ordinary Next lit the wall up on its own');
});

test('the blank button walks the opening and shows the first slide as soon as it is over', () => {
    const deck = carded();

    deck.toggleBlank();
    assert.equal(deck.splash, 'dark');
    assert.equal(deck.blanked, false, 'the dark of the opening was mistaken for a blank');

    deck._pressedAt = {};
    deck.toggleBlank();
    assert.equal(deck.splash, 'off');
    assert.equal(deck.blanked, false, 'the blank button ended the opening without showing anything, though it is the button that shows slides');
    assert.equal(deck.index, 0);

    deck._pressedAt = {};
    deck.toggleBlank();
    assert.equal(deck.blanked, true, 'a third press, now an ordinary blank, did not darken the wall');
    assert.equal(deck.index, 0);
});

/* A row tapped in the plan is somebody reaching past the opening entirely. */
test('a slide tapped in the plan reaches past the opening and goes there', () => {
    const deck = carded();

    deck.go(3);

    assert.equal(deck.splash, 'off');
    assert.equal(deck.index, 3);
});

/* The card is what the beamer is lined up against, and the person doing the
   lining up is holding the phone: both pictures have to be the same one. */
test('the phone draws the card and says what the next press does', () => {
    const deck = registered.projectionRemote({ cardHint: 'card hint', darkHint: 'dark hint' });

    deck.presentationId = 1;

    deck.splash = 'card';
    assert.equal(deck.showingSplash, true);
    assert.equal(deck.openingDark, false);
    assert.equal(deck.openingHint, 'card hint');

    deck.splash = 'dark';
    assert.equal(deck.showingSplash, false, 'the phone held a card the room had stopped looking at');
    assert.equal(deck.openingDark, true);
    assert.equal(deck.openingHint, 'dark hint');

    deck.splash = 'off';
    assert.equal(deck.opening, false);
    assert.equal(deck.openingHint, '');
});

test('the phone reports each picture of the opening as it walks it', () => {
    const deck = carded();
    const written = [];

    deck.push = registered.projectionRemote({}).push.bind(deck);
    deck._client = { write: (state) => { written.push(state); return Promise.resolve(null); } };

    deck.push();
    deck.next();
    deck._pressedAt = {};
    deck.next();

    assert.deepEqual(written.map((state) => state.splash), ['card', 'dark', 'off']);
});

/* A wall still engraving a deck is not holding a card, it is holding nothing. */
test('the preview shows no card while the screen is preparing a deck', () => {
    const deck = carded();

    deck.preparing = true;

    assert.equal(deck.showingSplash, false);
});

/* Where the opening comes from at all: the server, because the press that walked
   it may have been a keyboard across the building. */
test('the phone walks the opening as the server says', async () => {
    const deck = remote();
    let answer = null;

    deck.repaint = () => {};
    deck._show = { read: () => Promise.resolve(answer) };

    const says = (version, splash) => {
        answer = {
            presentationId: 1,
            state: { version, splash, blanked: false, reveals: {}, entryId: 1, slideIndex: 0, revision: 'a', drawnRevision: 'a', endedAt: null },
        };

        return deck.pull();
    };

    await says(2, 'card');
    assert.equal(deck.splash, 'card');

    await says(3, 'dark');
    assert.equal(deck.splash, 'dark');

    await says(4, 'off');
    assert.equal(deck.splash, 'off', 'the phone went on thinking the wall was still opening');
});

/*
 * Every field the page reads has to be answerable before the first poll comes
 * back.
 *
 * Alpine evaluates each expression as the page is built, and one that reaches
 * for a field the component never declared does not merely draw nothing: it
 * throws, and the throw takes down the directives still queued behind it —
 * which on this page is the strip, the plan and the three controls under the
 * thumb. A remote whose Next button is not wired to anything is worse than one
 * that says the wrong thing.
 */
test('the phone can answer for its own state before it has been told anything', () => {
    const deck = registered.projectionRemote({ clearText: 'end it?' });

    for (const field of ['ended', 'waiting', 'preparing', 'busy', 'blanked', 'wallBehind', 'opening', 'openingHint', 'showingSplash', 'total', 'index', 'clearText']) {
        assert.notEqual(deck[field], undefined, `the page reads ${field} and the phone cannot say what it is`);
    }

    assert.equal(deck.ended, false, 'a service nobody has closed is not over');
    assert.equal(deck.clearText, 'end it?');
});

/* And the screen closed from the laptop, which is where `ended` comes from. */
test('the phone hears that the service was closed on the screen itself', async () => {
    const deck = remote();

    deck.repaint = () => {};
    deck._show = {
        read: () => Promise.resolve({
            presentationId: 1,
            state: { version: 2, splash: 'off', blanked: false, reveals: {}, entryId: 1, slideIndex: 0, revision: 'a', drawnRevision: 'a', endedAt: '2026-09-15T10:00:00+00:00' },
        }),
    };

    await deck.pull();

    assert.equal(deck.ended, true);
});

/*
 * The other half of the same latch, from the thumb's side. A press made in the
 * same second as the laptop's reports a picture the service has already walked
 * past; the server refuses it and moves no version, so the answer to the press
 * is the only thing that will ever say so. Without reading it, the phone goes on
 * walking an opening that is over instead of driving the deck — and holds a card
 * the room stopped looking at.
 */
test('the phone takes back an opening the server refused', async () => {
    const deck = carded();
    const written = [];

    deck.push = registered.projectionRemote({}).push.bind(deck);
    deck._client = {
        write: (state) => {
            written.push(state);

            return Promise.resolve({ version: 9, splash: 'off', entryId: 1, slideIndex: 0, blanked: false, reveals: {} });
        },
    };

    deck.next();

    await new Promise((resolve) => setTimeout(resolve, 0));

    assert.deepEqual(written.map((state) => state.splash), ['dark'], 'the phone did not report the press it had made');
    assert.equal(deck.splash, 'off', 'the phone went on walking an opening the service had left');
    assert.equal(deck.appliedVersion, 9);

    deck._pressedAt = {};
    deck.next();

    assert.equal(deck.index, 1, 'the next press was spent on an opening that was over');
});

/*
 * ---------------------------------------------------------------
 * A score taken out of today's deck from underneath the room.
 * ---------------------------------------------------------------
 */

/*
 * `applyPayload` overwrites `this.entries` with the deck's new order before it
 * repaints, so by the time `indexOfAddress` goes looking for what used to come
 * after the row that just disappeared, that row is not in `this.entries`
 * either — and a lookup that cannot find it lands on the last slide of the
 * whole deck rather than the row that was actually next. `repaint` is handed
 * the order from before the removal for exactly this reason.
 */
test('taking the shown row out of the deck hands the service to the row after it', () => {
    const component = registered.projectionRemote({});

    const previousEntries = [{ id: 1 }, { id: 2 }, { id: 3 }];

    component.excluded = {};
    component.reveals = {};
    component.$refs = {};
    component.push = () => {};
    component.buildDeck = () => {};
    component.buildStrip = () => {};
    component.show = () => {};

    // The room is reading row 2's only slide when it is taken out of the deck.
    const previousAddress = { entryId: 2, slideIndex: 0 };

    component.entries = [{ id: 1 }, { id: 3 }];
    component.drawn = [
        { entryId: 1, index: 0, svg: null },
        { entryId: 3, index: 0, svg: null },
    ];

    component.repaint(previousAddress, previousEntries);

    assert.equal(component.slides[component.index].entryId, 3, 'the service landed on the last slide of the deck instead of the row after the one removed');
});

/*
 * Moving the row the room is reading must not take the room anywhere. A move
 * removes nothing, so the address the wall keeps — the row and the slide in it —
 * is still in the reordered deck, and that is where the service stays.
 */
test('moving the shown row leaves the service on the same slide', () => {
    const component = registered.projectionRemote({});

    component.excluded = {};
    component.reveals = {};
    component.$refs = {};
    component.push = () => {};
    component.buildDeck = () => {};
    component.buildStrip = () => {};
    component.show = () => {};

    const previousEntries = [{ id: 1 }, { id: 2 }, { id: 3 }];
    const previousAddress = { entryId: 2, slideIndex: 1 };

    component.entries = [{ id: 2 }, { id: 1 }, { id: 3 }];
    component.drawn = [
        { entryId: 2, index: 0, svg: null },
        { entryId: 2, index: 1, svg: null },
        { entryId: 1, index: 0, svg: null },
        { entryId: 3, index: 0, svg: null },
    ];

    component.repaint(previousAddress, previousEntries);

    assert.deepEqual(
        { entryId: component.slides[component.index].entryId, index: component.slides[component.index].index },
        { entryId: 2, index: 1 },
    );
});

/*
 * The arrows the phone draws in Reorder are the server's own verdicts, keyed
 * the way the move endpoint is asked — and a music only this deck holds is
 * moved by its own id, not by an assignment's.
 */
test('the plan column draws the arrows the server allows', () => {
    const component = registered.projectionRemote({
        outline: [
            {
                kind: 'slot', id: 4, name: 'Gloria', canMoveUp: false, canMoveDown: true,
                children: [
                    { kind: 'music', local: false, assignmentId: 9, addedMusicId: null, title: 'Glória', canMoveUp: false, canMoveDown: false, offers: [],
                        children: [{ kind: 'entry', entryId: 1, canMoveUp: false, canMoveDown: false }] },
                ],
            },
            { kind: 'music', local: true, assignmentId: null, addedMusicId: 5, title: 'Boldog születésnapot', canMoveUp: true, canMoveDown: false, offers: [], children: [] },
        ],
    });

    component.entries = [{ id: 1, label: 'Glória' }];
    component.drawn = [{ entryId: 1, index: 0, svg: null }];
    component.slides = component.drawn;

    const [gloria, song] = component.outline;

    assert.deepEqual(gloria.moves, { up: false, down: true });
    assert.equal(gloria.slotId, 4);
    assert.deepEqual(gloria.blocks[0].rows[0].moves, { up: false, down: false });
    assert.equal(song.name, null);
    assert.deepEqual(
        { local: song.blocks[0].local, moveKind: song.blocks[0].moveKind, moveId: song.blocks[0].moveId, moves: song.blocks[0].moves },
        { local: true, moveKind: 'added', moveId: 5, moves: { up: true, down: false } },
    );
});

/* The status line naming the walls no longer polls: the show read that already
   lists them asks it again, and only when the walls it names have changed. */
test('the status line is asked again only when the walls change', async () => {
    const dispatched = [];

    globalThis.window.Livewire = { dispatch: (name) => dispatched.push(name) };

    const deck = remote();

    deck._fitAt = 0;
    deck.screens = [wall(5)];
    deck._show = { read: () => Promise.resolve({ presentationId: 1, title: '', screens: [wall(5)], state: null }) };

    await deck.pull();
    assert.deepEqual(dispatched, [], 'an unchanged wall re-rendered the status line');

    deck._show.read = () => Promise.resolve({ presentationId: 1, title: '', screens: [wall(5), wall(6)], state: null });

    await deck.pull();
    assert.deepEqual(dispatched, ['show-screens-changed']);

    delete globalThis.window.Livewire;
});
