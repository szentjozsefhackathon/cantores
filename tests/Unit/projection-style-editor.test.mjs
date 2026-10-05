import assert from 'node:assert/strict';
import test from 'node:test';

/*
 * A style editor speaks of sizes in the units the score editor does. A chord
 * sheet opens on a 16:9 screen at 62 pt there, and its style knob used to read
 * the same size as 82.88 — the px it is stored in — so a size copied from one
 * editor to the other came out a quarter smaller.
 */

globalThis.window = globalThis.window ?? {};
globalThis.document = globalThis.document ?? { addEventListener() {}, removeEventListener() {} };

const registered = {};
globalThis.Alpine = { data: (name, factory) => { registered[name] = factory; } };
globalThis.window.Alpine = globalThis.Alpine;

await import('../../resources/js/projection-style-editor.js');

const fontSize = { key: 'chordproFontSize', min: 6, max: 200, step: 1, percent: 10 };

function styleEditor(settings = {}) {
    const saved = [];
    const component = registered.projectionStyleFormats({ ratio: '16/9', settings });
    component.$wire = { saveSetting: (...args) => saved.push(args) };
    component.init();

    return component;
}

test('a ChordPro size reads in points, the factory 16:9 size as the score editor shows it', () => {
    assert.equal(styleEditor().shownValue('chordpro', 'chordproFontSize'), 62);
});

test('a size typed in points is stored as the px the score editor stores for it', () => {
    const component = styleEditor();

    component.type('chordpro', 'chordproFontSize', 85);

    assert.equal(component.settings.chordpro.chordproFontSize, 113.3333);
    assert.equal(component.shownValue('chordpro', 'chordproFontSize'), 85);
});

test('a press moves a size by a share of its points and lands on the half-point grid', () => {
    const component = styleEditor();

    component.nudge('chordpro', fontSize, 1);

    assert.equal(component.shownValue('chordpro', 'chordproFontSize'), 68.5);
});

test('the limits of a size are given in points too', () => {
    assert.equal(styleEditor().shownLimit(fontSize, 'max'), 150);
});

test('a knob with no unit behind it reads and takes its own number', () => {
    const component = styleEditor();

    component.type('chordpro', 'chordproColumns', 2);

    assert.equal(component.settings.chordpro.chordproColumns, 2);
    assert.equal(component.shownValue('chordpro', 'chordproColumns'), 2);
});
