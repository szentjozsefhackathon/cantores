import assert from 'node:assert/strict';
import test from 'node:test';

import { renderCurrentPreview } from '../../resources/js/score-editor-render.js';

test('restores scroll after the async Aretino preview finishes rendering', async () => {
    const events = [];
    let finishRender;

    const component = {
        $wire: { format: 'aretino' },
        async renderAretinoPreview() {
            events.push('render-start');
            await new Promise(resolve => { finishRender = resolve; });
            events.push('render-finished');
        },
    };

    const viewport = {
        scrollY: 320,
        scrollTo(x, y) {
            events.push(`scroll:${x}:${y}`);
        },
    };

    const renderPromise = renderCurrentPreview(component, viewport);

    assert.deepEqual(events, ['render-start']);

    finishRender();
    await renderPromise;

    assert.deepEqual(events, ['render-start', 'render-finished', 'scroll:0:320']);
});

test('uses the synchronous renderer for the active format before restoring scroll', async () => {
    const events = [];
    const component = {
        $wire: { format: 'abc' },
        renderAbcPreview() {
            events.push('abc');
        },
    };
    const viewport = {
        scrollY: 140,
        scrollTo(x, y) {
            events.push(`scroll:${x}:${y}`);
        },
    };

    await renderCurrentPreview(component, viewport);

    assert.deepEqual(events, ['abc', 'scroll:0:140']);
});
