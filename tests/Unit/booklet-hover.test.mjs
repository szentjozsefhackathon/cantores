import assert from 'node:assert/strict';
import test from 'node:test';
import { appendEntryRegions, entryRegions, highlightEntry } from '../../resources/js/booklet-hover.js';
import { packPages } from '../../resources/js/booklet-flow.js';

const node = (attributes = {}, bounds = {}) => ({
    attributes: { ...attributes },
    setAttribute(name, value) { this.attributes[name] = value; },
    getAttribute(name) { return this.attributes[name]; },
    getBoundingClientRect() { return bounds; },
});

test('a score spanning pages retains its own region on each page', () => {
    const pages = packPages([
        { entryId: 7, height: 20 },
        { entryId: 7, height: 30 },
        { entryId: 7, height: 40 },
        { entryId: 8, height: 10 },
    ], 60);
    assert.deepEqual(pages.map(entryRegions), [
        [{ entryId: 7, top: 0, bottom: 50 }],
        [{ entryId: 7, top: 0, bottom: 40 }, { entryId: 8, top: 40, bottom: 50 }],
    ]);
});

test('page regions use laid out dimensions and start without exportable color', () => {
    const children = [];
    const svg = { ownerDocument: { createElementNS: () => node() }, appendChild: (child) => children.push(child) };
    appendEntryRegions(svg, { items: [{ block: { entryId: 7, height: 30, scale: 0.5 }, y: 20 }] }, {
        marginPx: 12, contentWidthPx: 200,
    });
    assert.deepEqual(children[0].attributes, {
        'data-booklet-entry': '7', x: '12', y: '32', width: '200', height: '30', fill: 'none', 'pointer-events': 'none',
    });
});

function preview(bounds = { top: 400, bottom: 450 }) {
    const regions = [node({ 'data-booklet-entry': '7' }, bounds), node({ 'data-booklet-entry': '7' }), node({ 'data-booklet-entry': '8' })];
    const scrolls = [];
    const pane = { scrollHeight: 1000, clientHeight: 300, scrollTop: 20,
        getBoundingClientRect: () => ({ top: 100, bottom: 400 }), scrollTo: (options) => scrolls.push(options) };
    const container = { querySelectorAll: () => regions, closest: () => pane };
    return { regions, scrolls, container, pane };
}

test('hover outlines every matching fragment and scrolls only to the first', () => {
    const { regions, container, scrolls } = preview();
    highlightEntry(container, 7, true);
    assert.deepEqual(regions.map((region) => region.attributes.stroke), ['#3b82f6', '#3b82f6', 'none']);
    assert.ok(regions.every((region) => region.attributes.fill === 'none'));
    assert.ok(regions.every((region) => region.attributes['stroke-width'] === '2'));
    assert.ok(regions.every((region) => region.attributes['vector-effect'] === 'non-scaling-stroke'));
    assert.deepEqual(scrolls, [{ top: 304, behavior: 'instant' }]);
    highlightEntry(container, null, true);
    assert.deepEqual(regions.map((region) => region.attributes.stroke), ['none', 'none', 'none']);
    assert.equal(scrolls.length, 1);
});

test('visible scores, missing scores, repaints and unscrollable panes do not move the page', () => {
    const { container, scrolls, pane } = preview({ top: 150, bottom: 200 });
    highlightEntry(container, 7, true);
    highlightEntry(container, 99, true);
    highlightEntry(container, 7);
    pane.clientHeight = 1000;
    highlightEntry(container, 7, true);
    assert.deepEqual(scrolls, []);
    highlightEntry(null, 7, true);
});
