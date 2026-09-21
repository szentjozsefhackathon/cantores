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
/** This browser, as the server knows it — the show names devices, not screens. */
const THIS_DEVICE = 'device-here';

function presenter(total = 3) {
    const component = registered.projectionPresenter({ deviceId: THIS_DEVICE });

    component.slides = Array.from({ length: total }, (unused, index) => ({ entryId: 1, index, svg: null }));
    component.total = total;
    component.presentationId = 1;
    component.show = () => {};

    return component;
}

test('page up and page down move the wall between songs, not slides', () => {
    const deck = presenter();
    deck.slides = [1, 1, 2, 2, 3].map((entryId, index) => ({ entryId, index, svg: null }));
    deck.total = 5;
    const press = (key) => deck.onKey({ key, preventDefault() {} });

    deck.index = 1;
    press('PageDown');
    assert.equal(deck.index, 2);

    press('PageDown');
    assert.equal(deck.index, 4);

    deck.index = 3;
    press('PageUp');
    assert.equal(deck.index, 0);
});

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
   read off this device's own entry among the show's screens, and is a fact
   about the room rather than about the deck — a screen still waiting for one is
   lined up as readily as a screen mid-hymn. */
test('the wall takes the fit its screen was given', async () => {
    const deck = presenter(3);

    deck.presentationId = 1;
    deck._show = { read: () => Promise.resolve({
        presentationId: 1,
        title: 'Vasárnap',
        screens: [
            { id: 4, deviceId: 'device-elsewhere', fit: { scale: 1.5, x: 0.3, y: 0 } },
            { id: 5, deviceId: THIS_DEVICE, fit: { scale: 0.8, x: 0, y: 0.1 } },
        ],
        state: null,
    }) };

    await deck.pull();

    assert.equal(deck.fit.scale, 0.8);
    assert.equal(deck.fit.y, 0.1);
    assert.equal(deck.fitTransform, 'translate(0%, 10%) scale(0.8)');
});

test('a fit that says nothing leaves the picture where the deck was fitted', async () => {
    const deck = presenter(3);

    deck.presentationId = 1;
    deck._show = { read: () => Promise.resolve({ presentationId: 1, screens: [], state: null }) };

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

/*
 * Ctrl+R mid-service. The page opens knowing where the show stands, and draws
 * that slide — not the first one, which its first heartbeat would otherwise
 * report as where the service is, sending the phone back to the beginning.
 */
test('a reloaded wall lands where the service already is', async () => {
    const deck = registered.projectionPresenter({
        presentationId: 1,
        entries: [{ id: 1 }, { id: 2 }],
        state: { version: 7, entryId: 2, slideIndex: 1, splash: 'off', blanked: false, reveals: {} },
    });
    deck.show = () => {};

    let written = null;
    deck._client = { write: (state) => { written = state; return Promise.resolve(null); } };

    deck.land([
        { entryId: 1, index: 0, svg: null },
        { entryId: 2, index: 0, svg: null },
        { entryId: 2, index: 1, svg: null },
    ]);

    assert.equal(deck.index, 2, 'the wall went back to the beginning of the deck');
    assert.equal(deck.appliedVersion, 7);

    await deck.report();

    assert.deepEqual({ entryId: written.entryId, slideIndex: written.slideIndex }, { entryId: 2, slideIndex: 1 });
});

/*
 * The next deck is put up during the sermon, to be shown when the sermon ends.
 * Putting it up is preparation and not a cue: the wall stays black until B.
 */
test('a deck put up takes its blank from the server, not from the deck it replaced', async () => {
    const deck = presenter();
    deck.reengrave = async () => {};
    deck.blanked = true;

    await deck.showDeck({ presentationId: 2, state: { version: 1, entryId: null, slideIndex: 0, splash: 'off', blanked: false, reveals: {} } });

    assert.equal(deck.blanked, false, 'a B pressed on the phone just before the swap was taken back');
});

test('a deck put up blanked on the server arrives blanked', async () => {
    const deck = registered.projectionPresenter({});
    deck.show = () => {};
    deck.reengrave = async () => {};

    await deck.showDeck({ presentationId: 2, state: { version: 1, entryId: null, slideIndex: 0, splash: 'off', blanked: true, reveals: {} } });

    assert.equal(deck.blanked, true);
});

test('a show taken down does not leave its blank behind', async () => {
    const deck = presenter();
    deck.blanked = true;

    await deck.showDeck({ presentationId: null, state: null });

    assert.equal(deck.blanked, false);
});

test('a reloaded wall starts black when the show is blanked', () => {
    const deck = registered.projectionPresenter({ state: { version: 3, entryId: 1, slideIndex: 0, splash: 'off', blanked: true, reveals: {} } });

    assert.equal(deck.blanked, true, 'the wall showed the hidden slide before its first read');
});

/*
 * The blank is changed by B and by nothing else. A slide or a heartbeat sent a
 * moment before this screen heard of a B pressed on the phone must not carry
 * the old blank back to the server with it.
 */
test('only a press of B reports the blank', async () => {
    const deck = presenter();
    const written = [];
    deck._client = { write: (state) => { written.push(state); return Promise.resolve(null); } };

    deck.next();
    await deck.report();
    deck.toggleBlank();

    assert.equal('blanked' in written[0], false, 'moving a slide reported the blank');
    assert.equal('blanked' in written[1], false, 'a heartbeat reported the blank');
    assert.equal(written[2].blanked, true);
});

/** A wall with something drawn, whose reports are remembered rather than sent. */
function reporting(answer = {}) {
    const deck = registered.projectionPresenter({ ackUrl: '/screens/5/ack', deviceId: THIS_DEVICE });
    const sent = [];

    deck.presentationId = 9;
    deck.appliedVersion = 12;
    deck.serverRevision = 'revision-12';
    deck.drawnRevision = 'revision-12';
    deck._show = {
        acknowledge: (url, body) => {
            sent.push({ url, body });

            return Promise.resolve({
                presentationId: body.presentationId,
                appliedVersion: body.appliedVersion,
                drawnRevision: body.drawnRevision,
                ...answer,
            });
        },
    };
    deck.sent = sent;

    return deck;
}

test('the wall says what it has drawn, and says nothing else with it', async () => {
    const deck = reporting();

    await deck.acknowledgeRendered();

    assert.deepEqual(deck.sent, [{
        url: '/screens/5/ack',
        body: {
            presentationId: 9,
            appliedVersion: 12,
            drawnRevision: 'revision-12',
        },
    }]);
    assert.equal('entryId' in deck.sent[0].body, false);
    assert.equal('blanked' in deck.sent[0].body, false);
});

/* This was a heartbeat: a locked write and a log line every ten seconds per
   wall in the country, nearly always to say that nothing had happened. What is
   drawn on a wall is an event, and a quiet hymn is a thing nobody has to be
   told about. */
test('the wall says it once, and does not keep saying it', async () => {
    const deck = reporting();

    await deck.acknowledgeRendered();
    await deck.acknowledgeRendered();
    await deck.acknowledgeRendered();

    assert.equal(deck.sent.length, 1, 'the wall reported the same picture again');

    // The cantor presses space, and the wall draws the next slide.
    deck.appliedVersion = 13;
    await deck.acknowledgeRendered();

    assert.equal(deck.sent.length, 2, 'a slide was drawn and nobody was told');
    assert.equal(deck.sent[1].body.appliedVersion, 13);
});

/* And the show's own answer says what the server has already been told, so a
   wall that reloads mid-hymn into a picture the server already knows about does
   not report it back. */
test('the wall says nothing the show already says it has drawn', async () => {
    const deck = reporting();

    await deck.apply({
        presentationId: 9,
        screens: [{
            id: 5,
            deviceId: THIS_DEVICE,
            offered: true,
            presenting: true,
            responding: true,
            appliedPresentationId: 9,
            appliedVersion: 12,
            drawnRevision: 'revision-12',
        }],
        state: null,
    });

    await deck.acknowledgeRendered();

    assert.deepEqual(deck.sent, []);
});

/* A report that never landed is the one thing the slow beat is still for. */
test('the wall says it again when the report did not land', async () => {
    const deck = reporting();

    deck._show.acknowledge = (url, body) => {
        deck.sent.push({ url, body });

        return Promise.resolve(null);
    };

    await deck.acknowledgeRendered();
    await deck.acknowledgeRendered();

    assert.equal(deck.sent.length, 2, 'a report that failed was never tried again');
});

/*
 * The hub hands the wall the answer itself rather than knocking on the door.
 * What arrives is what a read would have returned, so the wall takes it up the
 * same way — and refuses a frame that arrives behind one it has already drawn,
 * because two of them can overtake each other on the way to a building.
 */
test('the wall takes the show off the stream without reading it back', async () => {
    const deck = presenter(3);
    let reads = 0;

    deck.presentationId = 1;
    deck._show = { read: () => { reads += 1; return Promise.resolve(null); } };
    deck._poll = { busy: false, poke: () => { reads += 1; } };

    await deck.pushed(JSON.stringify({ at: '2', show: { presentationId: 1, title: 'Vasárnap', screens: [], state: null } }));

    assert.equal(deck.title, 'Vasárnap');
    assert.equal(reads, 0, 'the wall went back to the server for what it had just been handed');

    await deck.pushed(JSON.stringify({ at: '1', show: { presentationId: 1, title: 'Előző', screens: [], state: null } }));

    assert.equal(deck.title, 'Vasárnap', 'an overtaken frame was drawn over the newer one');
});

/* A read already out was very often sent after the change being pushed, so the
   two are not raced: the read is a beat away at most and is left to win. */
test('the wall lets a read that is already out win, and asks again for anything it cannot read', async () => {
    const deck = presenter(3);
    let poked = 0;

    deck.presentationId = 1;
    deck._poll = { busy: true, poke: () => { poked += 1; } };

    await deck.pushed(JSON.stringify({ at: '2', show: { presentationId: 1, title: 'Vasárnap', screens: [], state: null } }));

    assert.equal(deck.title, '', 'a pushed frame raced the read that was already out');
    assert.equal(poked, 1);

    // An older server's bare nudge, or the hub's own keep-alive.
    deck._poll.busy = false;
    await deck.pushed('changed');

    assert.equal(poked, 2, 'a frame that said nothing was not answered by asking');
});
