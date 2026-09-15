import assert from 'node:assert/strict';
import test from 'node:test';

import { POLL_BACKOFF_MAX_MS, POLL_MS, poller, screenClient, stateClient } from '../../resources/js/projection-follow.js';

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

    const screen = screenClient({ screenUrl: '/screens/5/state', csrfToken: 'token' });
    const state = stateClient({ stateUrl: '/presentations/7/state', payloadUrl: '/presentations/7/payload', csrfToken: 'token' });

    await screen.read();
    await state.read();
    await state.payload();
    await state.write({ entryId: 1, slideIndex: 0 });
    await screen.point(3);

    assert.equal(sent.length, 5);

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
