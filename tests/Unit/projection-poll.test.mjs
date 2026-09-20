import assert from 'node:assert/strict';
import test from 'node:test';

import { POLL_BACKOFF_MAX_MS, POLL_MS, PUSHED_POLL_MS, STREAM_RETRY_MS, commandClient, isNewerFrame, ownFit, poller, pushedShow, replayPendingState, screensFor, showClient, showStream, stateClient } from '../../resources/js/projection-follow.js';

/*
 * The beat that both ends of a service keep.
 *
 * What is being tested here is not the projection at all, it is arithmetic
 * about load: one client, multiplied by every parish singing at ten on a
 * Sunday. The rule that matters is that a client which cannot reach the server
 * asks *less* often rather than more — the opposite of what a `setInterval`
 * around an awaited read does, and the reason this exists.
 */

/** A hand-wound clock: nothing here waits for real milliseconds. */
function clock() {
    let at = 0;
    const due = [];

    globalThis.setTimeout = (fn, ms) => {
        const timer = { fn, at: at + ms, cancelled: false };
        due.push(timer);

        return timer;
    };

    globalThis.clearTimeout = (timer) => {
        if (timer) { timer.cancelled = true; }
    };

    return {
        /** Run whatever is owed, oldest first, letting each settle. */
        async advance(ms) {
            at += ms;

            for (;;) {
                const next = due
                    .filter((timer) => !timer.cancelled && timer.at <= at)
                    .sort((one, other) => one.at - other.at)[0];

                if (!next) { return; }

                due.splice(due.indexOf(next), 1);
                next.fn();
                await Promise.resolve();
                await Promise.resolve();
            }
        },
        /** How long the outstanding beat was scheduled for. */
        get pending() {
            const waiting = due.filter((timer) => !timer.cancelled);

            return waiting.length === 0 ? null : waiting[waiting.length - 1].at - at;
        },
    };
}

/** A tick that can be held open, so that two of them overlapping is visible. */
function held() {
    let release = null;
    const state = { started: 0, finished: 0, inFlight: 0, most: 0 };

    return {
        state,
        tick() {
            state.started += 1;
            state.inFlight += 1;
            state.most = Math.max(state.most, state.inFlight);

            return new Promise((resolve) => {
                release = () => {
                    state.inFlight -= 1;
                    state.finished += 1;
                    resolve();
                };
            });
        },
        let_go() {
            release?.();
            release = null;
        },
    };
}

test('never has two reads of the same screen in flight', async () => {
    const time = clock();
    const slow = held();
    const beat = poller(slow.tick, { random: () => 0.5 });

    beat.start();

    // The server has taken longer than the interval, twice over. A clock-driven
    // interval would have fired twice more by now; this has not.
    await time.advance(POLL_MS * 3);

    assert.equal(slow.state.started, 1);
    assert.equal(slow.state.most, 1);

    slow.let_go();
    await Promise.resolve();
    await time.advance(POLL_MS);

    assert.equal(slow.state.started, 2, 'the next beat waits for the last to land, then goes');

    beat.stop();
});

test('backs away from a server that is not answering, and comes straight back', async () => {
    const time = clock();
    let answer = false;
    const beat = poller(() => answer, { random: () => 0.5 });

    beat.start();
    await Promise.resolve();

    // One failure doubles the wait, and each after it doubles again, up to the
    // ceiling — where a hundred parishes cost a fifth of what they did.
    const waits = [];

    for (let attempt = 0; attempt < 6; attempt++) {
        waits.push(beat.wait);
        await time.advance(beat.wait);
    }

    assert.deepEqual(waits, [2000, 4000, 5000, 5000, 5000, 5000]);
    assert.equal(beat.wait, POLL_BACKOFF_MAX_MS);

    // And the first answer that lands puts it back, without an extra beat spent
    // climbing down: a blink of bad wifi costs one beat, not a hymn.
    answer = true;
    await time.advance(beat.wait);

    assert.equal(beat.wait, POLL_MS);

    beat.stop();
});

test('treats a tick that throws as a tick that failed', async () => {
    const time = clock();
    const beat = poller(() => { throw new Error('the network went'); }, { random: () => 0.5 });

    beat.start();
    await Promise.resolve();
    await time.advance(POLL_MS);

    assert.equal(beat.wait, 2000);

    beat.stop();
});

test('treats a tick that simply did its work as a success', async () => {
    const time = clock();
    let beats = 0;
    const beat = poller(() => { beats += 1; }, { random: () => 0.5 });

    beat.start();
    await Promise.resolve();
    await time.advance(POLL_MS * 2);

    assert.equal(beat.wait, POLL_MS);
    assert.ok(beats >= 2);

    beat.stop();
});

test('scatters the return, so that a crowd does not come back as a crowd', async () => {
    const early = poller(() => false, { random: () => 0 });
    const late = poller(() => false, { random: () => 1 });

    const waits = [];

    for (const beat of [early, late]) {
        const time = clock();

        beat.start();
        await Promise.resolve();
        await Promise.resolve();

        waits.push(time.pending);
        beat.stop();
    }

    // The same doubled wait, a quarter either side of it.
    assert.deepEqual(waits, [1500, 2500]);
});

/*
 * The wall's heartbeat is ten seconds where the poll is one, and the ceiling is
 * five. A ceiling below the interval must not turn failing into asking more
 * often, which is the exact thing all of this exists to prevent.
 */
test('never lets a ceiling below the interval speed a failing beat up', async () => {
    const time = clock();
    const beat = poller(() => false, { interval: 10000, maxInterval: POLL_BACKOFF_MAX_MS, random: () => 0.5 });

    beat.start();
    await Promise.resolve();
    await time.advance(10000);

    assert.equal(beat.wait, 10000);

    beat.stop();
});

test('lands nothing after it is stopped', async () => {
    const time = clock();
    let beats = 0;
    const beat = poller(() => { beats += 1; }, { random: () => 0.5 });

    beat.start();
    await Promise.resolve();
    beat.stop();

    const after = beats;
    await time.advance(POLL_MS * 10);

    assert.equal(beats, after);
});

test('starting twice is starting once', async () => {
    const time = clock();
    let beats = 0;
    const beat = poller(() => { beats += 1; }, { random: () => 0.5 });

    beat.start();
    beat.start();
    await Promise.resolve();
    await time.advance(POLL_MS);

    assert.equal(beats, 2, 'one beat on starting, one on the first wait');

    beat.stop();
});

/*
 * Laravel treats a plain GET as a page somebody navigated to, and records it as
 * where they were. A poll is not that, and saying so is one header — without
 * which a wall rewrites its own session once a second for the length of a Mass
 * and leaves every redirect-back pointing at a JSON endpoint.
 */
test('says that a poll is a poll and not a page somebody opened', async () => {
    const sent = [];

    globalThis.fetch = (url, init) => {
        sent.push({ url, ...init });

        return Promise.resolve({ ok: true, json: () => Promise.resolve({}) });
    };

    const show = showClient({ showUrl: '/show/state', csrfToken: 'token' });
    const state = stateClient({ stateUrl: '/presentations/7/state', payloadUrl: '/presentations/7/payload', csrfToken: 'token' });

    await show.read();
    await state.read();
    await state.payload();
    await state.write({ entryId: 1, slideIndex: 0 });
    await show.point(3);
    await show.adjust({ fitUrl: '/screens/5/fit' }, { scale: 1, x: 0, y: 0 });

    assert.equal(sent.length, 6);

    for (const request of sent) {
        assert.equal(request.headers['X-Requested-With'], 'XMLHttpRequest', `${request.url} did not say what it was`);
        assert.equal(request.headers.Accept, 'application/json');
    }

    // And the writes still carry what a write needs.
    for (const request of sent.filter((one) => one.method === 'POST')) {
        assert.equal(request.headers['X-CSRF-TOKEN'], 'token');
        assert.equal(request.headers['Content-Type'], 'application/json');
    }
});

/*
 * ---------------------------------------------------------------
 * Being told, rather than asking.
 * ---------------------------------------------------------------
 */

test('asks at once when poked, and a poke during a read asks once more after it', async () => {
    const time = clock();
    const slow = held();
    const beat = poller(slow.tick, { interval: PUSHED_POLL_MS, random: () => 0.5 });

    beat.start();
    assert.equal(slow.state.started, 1);

    // Three nudges while the first read is still out are one more read, not three.
    beat.poke();
    beat.poke();
    beat.poke();
    slow.let_go();
    await Promise.resolve();
    await Promise.resolve();
    await time.advance(0);

    assert.equal(slow.state.started, 2);
    assert.equal(slow.state.most, 1, 'never two at once');

    // And a poke between reads does not wait out the fifteen seconds.
    slow.let_go();
    await Promise.resolve();
    await Promise.resolve();
    beat.poke();

    assert.equal(slow.state.started, 3);

    beat.stop();
});

test('changes pace as soon as the stream opens or closes', async () => {
    const time = clock();
    let pushed = false;
    const beat = poller(() => {}, { interval: () => (pushed ? PUSHED_POLL_MS : POLL_MS), random: () => 0.5 });

    beat.start();
    await Promise.resolve();
    assert.equal(time.pending, POLL_MS);

    pushed = true;
    await time.advance(POLL_MS);
    assert.equal(time.pending, PUSHED_POLL_MS);

    pushed = false;
    beat.poke();
    await Promise.resolve();
    assert.equal(time.pending, POLL_MS);

    beat.stop();
});

test('does nothing when poked after it was stopped', () => {
    let beats = 0;
    const beat = poller(() => { beats += 1; });

    beat.poke();
    assert.equal(beats, 0);
});

test('delivers one source commands in gesture order', async () => {
    const sent = [];
    const answers = [];
    const storage = new Map();
    storage.getItem = storage.get.bind(storage);
    storage.setItem = storage.set.bind(storage);

    globalThis.fetch = (url, init) => new Promise((resolve) => {
        sent.push(JSON.parse(init.body));
        answers.push(resolve);
    });

    const client = commandClient({
        stateUrl: '/presentations/1/state',
        csrfToken: 'token',
        storage,
        crypto: { randomUUID: () => '11111111-1111-4111-8111-111111111111' },
    });

    const black = client.write({ blanked: true });
    const next = client.write({ entryId: 2, slideIndex: 0 });

    assert.equal(sent.length, 1, 'Next passed Black while Black was still in flight');
    assert.equal(sent[0].sequence, 1);

    answers.shift()({ ok: true, json: () => Promise.resolve({ version: 2, blanked: true }) });
    await black;
    await Promise.resolve();

    assert.equal(sent.length, 2);
    assert.equal(sent[1].sequence, 2);
    assert.deepEqual(sent[1].changes, { entryId: 2, slideIndex: 0 });

    answers.shift()({ ok: true, json: () => Promise.resolve({ version: 3 }) });
    await next;
    client.stop();
});

test('retries a failed command with the same source sequence', async () => {
    const time = clock();
    const sent = [];
    let attempt = 0;

    globalThis.fetch = (url, init) => {
        sent.push(JSON.parse(init.body));
        attempt += 1;

        return Promise.resolve(attempt === 1
            ? { ok: false, json: () => Promise.resolve({}) }
            : { ok: true, json: () => Promise.resolve({ version: 2 }) });
    };

    const client = commandClient({
        stateUrl: '/presentations/1/state',
        csrfToken: 'token',
        storage: null,
        crypto: { randomUUID: () => '22222222-2222-4222-8222-222222222222' },
        random: () => 0.5,
    });

    const delivered = client.write({ blanked: true });
    await Promise.resolve();
    await Promise.resolve();
    await time.advance(500);
    await delivered;

    assert.equal(sent.length, 2);
    assert.equal(sent[0].sourceId, sent[1].sourceId);
    assert.equal(sent[0].sequence, sent[1].sequence);
    client.stop();
});

test('replays pending optimistic changes over an older poll answer', () => {
    const desired = replayPendingState(
        { version: 4, entryId: 1, slideIndex: 0, blanked: false },
        [
            { sequence: 5, changes: { blanked: true } },
            { sequence: 6, changes: { entryId: 2, slideIndex: 1 } },
        ],
    );

    assert.deepEqual(desired, {
        version: 4,
        entryId: 2,
        slideIndex: 1,
        blanked: true,
    });
});

/** An EventSource the test drives by hand. */
function fakeSource() {
    const made = [];

    class Source {
        static CONNECTING = 0;

        static OPEN = 1;

        static CLOSED = 2;

        constructor(url, init) {
            this.url = url;
            this.init = init;
            this.readyState = Source.CONNECTING;
            this.closed = false;
            made.push(this);
        }

        close() {
            this.closed = true;
            this.readyState = Source.CLOSED;
        }

        open() {
            this.readyState = Source.OPEN;
            this.onopen?.();
        }

        message(data = 'changed') {
            this.onmessage?.({ data });
        }

        fail(readyState) {
            this.readyState = readyState;
            this.onerror?.();
        }
    }

    return { Source, made };
}

/** A server that answers the subscription request with `grant`, counting the asks. */
function grants(grant) {
    const asked = [];

    globalThis.fetch = (url, init) => {
        asked.push({ url, ...init });

        return Promise.resolve({ ok: grant !== null, json: () => Promise.resolve(grant) });
    };

    return asked;
}

const settle = async () => {
    for (let i = 0; i < 5; i++) { await Promise.resolve(); }
};

test('listens on its own topic with the cookie, and passes the nudges on', async () => {
    clock();
    const asked = grants({ hubUrl: '/.well-known/mercure', topic: 'http://localhost/show/7' });
    const { Source, made } = fakeSource();
    const events = [];

    const stream = showStream(
        { streamUrl: '/show/stream', csrfToken: 'token', EventSource: Source },
        { change: (data) => events.push(data), open: (isOpen) => events.push(isOpen ? 'open' : 'closed') },
    );

    stream.start();
    await settle();

    assert.equal(asked.length, 1);
    assert.equal(asked[0].method, 'POST');
    assert.equal(made.length, 1);
    assert.equal(made[0].url, '/.well-known/mercure?topic=http%3A%2F%2Flocalhost%2Fshow%2F7');
    assert.equal(made[0].init.withCredentials, true);
    assert.equal(stream.open, false);

    made[0].open();
    made[0].message('{"at":"1","show":{}}');

    assert.equal(stream.open, true);
    assert.deepEqual(events, ['open', '{"at":"1","show":{}}'], 'the frame did not reach the page whole');

    stream.stop();

    assert.equal(made[0].closed, true);
    assert.deepEqual(events, ['open', '{"at":"1","show":{}}', 'closed']);
});

test('lets the browser reconnect a blip by itself, and asks again when turned away', async () => {
    const time = clock();
    const asked = grants({ hubUrl: '/.well-known/mercure', topic: 't' });
    const { Source, made } = fakeSource();
    const events = [];

    const stream = showStream(
        { streamUrl: '/show/stream', csrfToken: 'token', EventSource: Source },
        { open: (isOpen) => events.push(isOpen) },
    );

    stream.start();
    await settle();
    made[0].open();

    // A blip: the browser is already reconnecting, and polling picks up the pace.
    made[0].fail(Source.CONNECTING);
    assert.equal(stream.open, false);
    assert.equal(made.length, 1);
    made[0].open();

    // Turned away — the token lapsed. A new one is asked for after a wait.
    made[0].fail(Source.CLOSED);
    assert.equal(made[0].closed, true);

    await time.advance(STREAM_RETRY_MS - 1);
    assert.equal(asked.length, 1);

    await time.advance(1);
    await settle();

    assert.equal(asked.length, 2);
    assert.equal(made.length, 2);
    assert.deepEqual(events, [true, false, true, false]);

    stream.stop();
});

test('keeps polling and stops asking when the server has no hub', async () => {
    const time = clock();
    const asked = grants({ hubUrl: null, topic: null });
    const { Source, made } = fakeSource();

    const stream = showStream({ streamUrl: '/show/stream', csrfToken: 'token', EventSource: Source });

    stream.start();
    await settle();
    await time.advance(STREAM_RETRY_MS * 100);

    assert.equal(asked.length, 1);
    assert.equal(made.length, 0);
    assert.equal(stream.open, false);

    stream.stop();
});

test('backs off asking for a stream the server will not give', async () => {
    const time = clock();
    const asked = grants(null);
    const { Source } = fakeSource();

    const stream = showStream({ streamUrl: '/show/stream', csrfToken: 'token', EventSource: Source });

    stream.start();
    await settle();

    for (const wait of [STREAM_RETRY_MS, STREAM_RETRY_MS * 2, STREAM_RETRY_MS * 4]) {
        assert.equal(time.pending, wait);
        await time.advance(wait);
        await settle();
    }

    assert.equal(asked.length, 4);

    stream.stop();
    await time.advance(STREAM_RETRY_MS * 100);
    assert.equal(asked.length, 4, 'nothing after stopping');
});

test('does nothing in a browser without EventSource', async () => {
    const asked = grants({ hubUrl: '/.well-known/mercure', topic: 't' });

    const stream = showStream({ streamUrl: '/show/stream', csrfToken: 'token', EventSource: null });

    // `null` falls back to the global, which node does not have before 22.
    if (typeof globalThis.EventSource === 'function') { return; }

    stream.start();
    await settle();

    assert.equal(asked.length, 0);
});

/*
 * ---------------------------------------------------------------
 * The show, described for a person and read by a device.
 * ---------------------------------------------------------------
 *
 * One description reaches every device of one person's, which is what lets the
 * hub carry it instead of each device coming back for its own. The two
 * questions that used to be settled on the server are settled here, against the
 * flags each screen arrives with.
 */

/** A screen as the show's answer lists it. */
function listed(id, deviceId, flags = {}) {
    return { id, deviceId, fit: { scale: 1, x: 0, y: 0 }, offered: true, presenting: true, responding: true, ...flags };
}

test('a device keeps the screens it may be shown, and its own whatever it is called', () => {
    const answer = {
        screens: [
            listed(1, 'wall'),
            listed(2, 'laptop-at-home', { offered: false }),
            listed(3, 'here', { offered: false }),
        ],
    };

    const screens = screensFor(answer, 'here');

    assert.deepEqual(screens.map((screen) => screen.id), [1, 3], 'a device its owner said is not a screen was drawn, or this device was dropped for being one');
    assert.equal(screens.find((screen) => screen.id === 3).isThisDevice, true);
    assert.equal(screens.find((screen) => screen.id === 1).isThisDevice, false);
});

/* A phone that pressed Present a minute ago and came back must not tell its
   holder that the show is on the phone in their hand. */
test('this device counts only while its own wall is actually up', () => {
    const answer = { screens: [listed(9, 'here', { presenting: false })] };

    assert.deepEqual(screensFor(answer, 'here'), []);
    assert.equal(ownFit(screensFor(answer, 'here')), null, 'a fit was taken off a screen that is not up');

    // Somebody else's, which is shown for the whole of the stale window: a
    // phone cannot walk across the church to check.
    assert.equal(screensFor({ screens: [listed(9, 'wall', { presenting: false })] }, 'here').length, 1);
});

test('a page with no device of its own is shown the screens anybody may see', () => {
    const answer = { screens: [listed(1, 'wall'), listed(2, 'laptop-at-home', { offered: false })] };

    assert.deepEqual(screensFor(answer, null).map((screen) => screen.id), [1]);
});

/*
 * A frame off the stream. Two of them can overtake each other between the hub
 * and a phone, and a page that drew the later one must not then draw the
 * earlier; anything that is not a show at all — an older server's bare nudge,
 * the hub's own keep-alive — is not one to draw either.
 */
test('takes a show off the stream, once and in order', () => {
    assert.deepEqual(pushedShow('{"at":"20260920120000000001","show":{"presentationId":4}}'), {
        at: '20260920120000000001',
        show: { presentationId: 4 },
    });

    assert.equal(pushedShow('changed'), null, 'a bare nudge was mistaken for a show');
    assert.equal(pushedShow(undefined), null);
    assert.equal(pushedShow('{"at":"1"}'), null, 'a frame with no show in it was taken up');
    assert.equal(pushedShow('{"show":{}}'), null, 'a frame with nothing to order it by was taken up');

    // Late is not the same as unreadable, and is told apart from it because a
    // frame that merely arrived behind a newer one is nothing to go back to
    // the server about.
    assert.equal(isNewerFrame(pushedShow('{"at":"2","show":{}}'), '3'), false, 'a frame older than the last one was taken up');
    assert.equal(isNewerFrame(pushedShow('{"at":"3","show":{}}'), '3'), false, 'the same frame was taken up twice');
    assert.equal(isNewerFrame(pushedShow('{"at":"4","show":{}}'), '3'), true);
    assert.equal(isNewerFrame(pushedShow('{"at":"1","show":{}}'), null), true, 'the first frame of all was refused');
});
