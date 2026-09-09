import assert from 'node:assert/strict';
import test from 'node:test';

import {
    listenForNavigation,
    renderPendingTurnstileWidgets,
    turnstileOptionsFrom,
} from '../../resources/js/turnstile.js';

const widget = (dataset = {}, children = []) => ({
    dataset,
    querySelector: (selector) => children.find((child) => child.tag === selector) ?? null,
});

const documentWith = (widgets) => ({
    querySelectorAll: (selector) => (selector === '.cf-turnstile' ? widgets : []),
});

const recordingTurnstile = () => {
    const calls = [];

    return { calls, render: (element, options) => calls.push({ element, options }) };
};

test('an unrendered placeholder is rendered with its site key', () => {
    const placeholder = widget({ sitekey: 'site-key' });
    const turnstile = recordingTurnstile();

    assert.equal(renderPendingTurnstileWidgets(documentWith([placeholder]), turnstile), 1);
    assert.equal(turnstile.calls.length, 1);
    assert.equal(turnstile.calls[0].element, placeholder);
    assert.equal(turnstile.calls[0].options.sitekey, 'site-key');
});

test('a placeholder that already holds a widget is left alone', () => {
    const placeholder = widget({ sitekey: 'site-key' }, [{ tag: 'iframe' }]);
    const turnstile = recordingTurnstile();

    assert.equal(renderPendingTurnstileWidgets(documentWith([placeholder]), turnstile), 0);
    assert.equal(turnstile.calls.length, 0);
});

test('a placeholder is never rendered twice', () => {
    const placeholder = widget({ sitekey: 'site-key' });
    const turnstile = recordingTurnstile();
    const doc = documentWith([placeholder]);

    renderPendingTurnstileWidgets(doc, turnstile);

    assert.equal(renderPendingTurnstileWidgets(doc, turnstile), 0);
    assert.equal(turnstile.calls.length, 1);
});

test('nothing happens while the Turnstile script is still loading', () => {
    assert.equal(renderPendingTurnstileWidgets(documentWith([widget({ sitekey: 'site-key' })]), undefined), 0);
});

test('data attributes become render options, callbacks resolved by name', () => {
    const passed = () => {};
    const options = turnstileOptionsFrom(
        widget({ sitekey: 'site-key', theme: 'dark', callback: 'humanCheckPassed', errorCallback: 'missing' }),
        { humanCheckPassed: passed },
    );

    assert.deepEqual(options, { sitekey: 'site-key', theme: 'dark', callback: passed });
});

test('the initial navigation event is left to the implicit rendering of api.js', () => {
    const listeners = [];
    const target = { addEventListener: (event, listener) => listeners.push({ event, listener }) };
    const placeholder = widget({ sitekey: 'site-key' });
    const turnstile = recordingTurnstile();

    globalThis.document = documentWith([placeholder]);
    globalThis.turnstile = turnstile;

    try {
        listenForNavigation(target);

        assert.equal(listeners[0].event, 'livewire:navigated');

        listeners[0].listener();
        assert.equal(turnstile.calls.length, 0, 'the initial page load renders itself');

        listeners[0].listener();
        assert.equal(turnstile.calls.length, 1, 'a wire:navigate visit renders the new placeholder');
    } finally {
        delete globalThis.document;
        delete globalThis.turnstile;
    }
});
