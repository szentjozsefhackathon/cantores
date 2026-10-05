import assert from 'node:assert/strict';
import test from 'node:test';

/*
 * The plan and the contact sheet answer each other: pointing at one lights up
 * the other, and clicking one brings the other to it. Only the click moves a
 * pane — a plan that scrolled under a mouse merely crossing the slides on its
 * way somewhere else was a plan nobody could keep their place in.
 */

const frames = [];

globalThis.window = globalThis.window ?? {};
globalThis.document = globalThis.document ?? { addEventListener() {}, removeEventListener() {} };
globalThis.requestAnimationFrame = (callback) => { frames.push(callback); return frames.length; };
globalThis.cancelAnimationFrame = () => {};

const registered = {};
globalThis.Alpine = { data: (name, factory) => { registered[name] = factory; } };
globalThis.window.Alpine = globalThis.Alpine;

await import('../../resources/js/projection-editor.js');

/** A pane scrolled to its top, with the element asked for far below the fold. */
function scrollingPane(selectorAnswer = null) {
    return {
        scrollTop: 0,
        scrollHeight: 2000,
        clientHeight: 400,
        getBoundingClientRect: () => ({ top: 0, bottom: 400 }),
        querySelector: () => selectorAnswer,
    };
}

function farBelow() {
    return { getBoundingClientRect: () => ({ top: 1200, bottom: 1260 }) };
}

function figureFor(entryId) {
    return {
        dataset: { projectionEntry: String(entryId) },
        classList: { toggle() {} },
        closest(selector) { return selector === '[data-projection-entry]' ? this : null; },
        getBoundingClientRect: () => ({ top: 1500, bottom: 1700 }),
    };
}

function editor() {
    const plan = scrollingPane(farBelow());
    const slidesPane = scrollingPane();
    const figure = figureFor(7);
    const host = {
        querySelectorAll: () => [figure],
        closest: () => slidesPane,
    };

    const component = registered.projectionEditor({});
    component.$root = { querySelector: (selector) => (selector === '[data-projection-pane="plan"]' ? plan : null) };
    component.$refs = { slides: host };

    return { component, figure };
}

test('hovering a slide or a row lights up its partner without scrolling anything', () => {
    frames.length = 0;
    const { component, figure } = editor();

    component.hoverPreview(figure);
    assert.equal(component.hoveredEntryId, 7);

    component.hoverEntry(7);
    assert.equal(component.hoveredEntryId, 7);

    assert.equal(frames.length, 0, 'a pane began to scroll on hover');
});

test('clicking a slide brings its row into view in the plan', () => {
    frames.length = 0;
    const { component, figure } = editor();

    component.revealSlideRow(figure);

    assert.equal(component.hoveredEntryId, 7);
    assert.equal(frames.length, 1, 'the plan did not scroll to the clicked slide');
});

test('clicking a row brings its slides into view', () => {
    frames.length = 0;
    const { component } = editor();

    component.revealEntry(7);

    assert.equal(frames.length, 1, 'the contact sheet did not scroll to the clicked row');
});

/*
 * The badge saying which layout a row follows is offered only where the choice
 * changes something: a score whose own layout for this shape differs from the
 * style. "Every score follows the deck style" marks every row, and a row with no
 * layout of its own then showed a badge that vanished as soon as it was clicked.
 */
function layoutEditor(scoreSettings, override = {}) {
    const component = registered.projectionEditor({
        geometry: { ratio: '16/9', style: null },
        styleKeys: { chordpro: ['chordproFontSize'] },
        entries: [{ id: 3, kind: 'score', format: 'chordpro', settings: scoreSettings, override }],
    });

    return component;
}

test('a score with its own layout for this shape is offered the choice, whichever it follows', () => {
    const own = { chordpro: { '16/9': { chordproFontSize: 977 } } };

    assert.equal(layoutEditor(own).offersLayoutChoice(3), true);
    assert.equal(layoutEditor(own, { fromStyle: true }).offersLayoutChoice(3), true);
});

test('a score without its own layout is not offered a choice, even when told to follow the style', () => {
    assert.equal(layoutEditor({}).offersLayoutChoice(3), false);
    assert.equal(layoutEditor({}, { fromStyle: true }).offersLayoutChoice(3), false);
});
