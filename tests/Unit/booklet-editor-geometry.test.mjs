import assert from 'node:assert/strict';
import test from 'node:test';

/*
 * A typography knob on the booklet's bar is laid out from the browser's own copy
 * of the geometry, rather than after its save has been to the server and back:
 * the server hands those values over untouched, so there was nothing to wait for
 * but wire:model's debounce and a round trip.
 */

globalThis.document = { addEventListener() {} };
globalThis.window = { Alpine: null };

const registered = {};

globalThis.Alpine = { data: (name, factory) => { registered[name] = factory; } };
globalThis.window.Alpine = globalThis.Alpine;

await import('../../resources/js/booklet-editor.js');

const { layoutSignature } = await import('../../resources/js/booklet-pacing.js');

const GEOMETRY = { pageWidthMm: 148, pageHeightMm: 210, marginMm: 12, lyricSizePt: 10.5, staffHeightMm: 5 };

function editor() {
    const component = registered.bookletEditor({ geometry: { ...GEOMETRY }, entries: [] });

    component.renders = [];
    component.scheduleRender = (delay) => { component.renders.push(delay); };

    return component;
}

/** A field on the bar, wrapped the way the Blade view wraps it. */
function knob(value, { geometry = null, style = false, valid = true } = {}) {
    return {
        target: {
            value: String(value),
            validity: { valid },
            closest(selector) {
                if (selector === '[data-booklet-geometry]' && geometry) {
                    return { dataset: { bookletGeometry: geometry } };
                }

                if (selector === '[data-booklet-style]' && style) {
                    return {};
                }

                return null;
            },
        },
    };
}

test('a typography knob is laid out at once, without waiting for the server', () => {
    const component = editor();

    component.adoptGeometryKnob(knob('12', { geometry: 'lyricSizePt' }));

    assert.equal(component.geometry.lyricSizePt, 12);
    assert.equal(component.geometry.marginMm, 12, 'the rest of the geometry is kept');
    assert.deepEqual(component.renders, [undefined], 'laid out after the usual knob wait');
});

test('a value the field calls invalid is left to the server', () => {
    const component = editor();

    component.adoptGeometryKnob(knob('100', { geometry: 'lyricSizePt', valid: false }));
    component.adoptGeometryKnob(knob('', { geometry: 'lyricSizePt' }));

    assert.equal(component.geometry.lyricSizePt, 10.5);
    assert.deepEqual(component.renders, []);
});

test('a knob that is not typography waits for the round trip', () => {
    const component = editor();

    component.adoptGeometryKnob(knob('20'));

    assert.equal(component.geometry.marginMm, 12);
    assert.deepEqual(component.renders, []);
});

test('the save that follows is only adopted, since it describes the pages already drawn', () => {
    const component = editor();
    component._busy = { settle() { component.settled = true; }, start() {} };

    component.adoptGeometryKnob(knob('12', { geometry: 'lyricSizePt' }));

    // What render() writes down once the booklet at 12 pt is on screen.
    component._drawnSignature = layoutSignature(component.entries, component.geometry);
    component.renders.length = 0;

    component.applyUpdate({ geometry: { ...GEOMETRY, lyricSizePt: 12 } });

    assert.deepEqual(component.renders, [], 'not laid out a second time');
    assert.equal(component.settled, true);
    assert.deepEqual(component._pendingGeometry, {}, 'the answer carried the value, so nothing is held any more');
});

test('an answer to an older save does not take the preview back', () => {
    const component = editor();

    component.adoptGeometryKnob(knob('11', { geometry: 'lyricSizePt' }));
    component.adoptGeometryKnob(knob('12', { geometry: 'lyricSizePt' }));

    component.applyUpdate({ geometry: { ...GEOMETRY, lyricSizePt: 11 } });

    assert.equal(component.geometry.lyricSizePt, 12);
    assert.deepEqual(component._pendingGeometry, { lyricSizePt: 12 });
});

test('choosing a style lets the server say what every typography knob now is', () => {
    const component = editor();

    component.adoptGeometryKnob(knob('12', { geometry: 'lyricSizePt' }));
    component.adoptGeometryKnob(knob('graduale', { style: true }));

    component.applyUpdate({ geometry: { ...GEOMETRY, lyricSizePt: 9 } });

    assert.equal(component.geometry.lyricSizePt, 9);
});
