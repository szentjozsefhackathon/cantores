import assert from 'node:assert/strict';
import test from 'node:test';

/*
 * The wall's white lives inside each slide, not behind it: the box a slide sits
 * in is sized in fractions of a pixel, and a white box showed a hairline beside
 * every slide of words.
 */

function element(name, attributes = {}) {
    return {
        name,
        nodeName: name,
        attributes: { ...attributes },
        children: [],
        getAttribute(key) { return this.attributes[key] ?? null; },
        setAttribute(key, value) { this.attributes[key] = value; },
        insertBefore(child, before) {
            const at = this.children.indexOf(before);
            this.children.splice(at < 0 ? this.children.length : at, 0, child);
        },
        get firstChild() { return this.children[0] ?? null; },
        cloneNode() {
            const copy = element(this.name, this.attributes);
            copy.children = [...this.children];
            return copy;
        },
    };
}

globalThis.document = { createElementNS: (ns, name) => element(name) };

const { onPaper } = await import('../../resources/js/slide-frame.js');

test('a score is laid on white the size of its own canvas', () => {
    const notes = element('path');
    const slide = element('svg', { viewBox: '0 0 1920 1080' });
    slide.children.push(notes);

    const shown = onPaper(slide);

    assert.equal(shown.children[0].name, 'rect');
    assert.equal(shown.children[0].getAttribute('fill'), 'white');
    assert.equal(shown.children[0].getAttribute('width'), '1920');
    assert.equal(shown.children[0].getAttribute('height'), '1080');
    assert.equal(shown.children[1], notes);
});

test('a slide of words is left on its own ground, with no white under it', () => {
    const ground = element('rect', { x: '0', y: '0', width: '1920', height: '1080', fill: '#000' });
    const slide = element('svg', { viewBox: '0 0 1920 1080' });
    slide.children.push(ground);

    const shown = onPaper(slide);

    assert.equal(shown.children.length, 1);
    assert.equal(shown.children[0], ground);
});

test('a score\'s white ends on a whole pixel', () => {
    const shown = onPaper(element('svg', { viewBox: '0 0 1920 1080' }));

    assert.equal(shown.children[0].getAttribute('shape-rendering'), 'crispEdges');
});

test('a rectangle that is only part of the drawing is not taken for a ground', () => {
    const box = element('rect', { x: '10', y: '10', width: '100', height: '100', fill: '#000' });
    const slide = element('svg', { viewBox: '0 0 1920 1080' });
    slide.children.push(box);

    const shown = onPaper(slide);

    assert.equal(shown.children[0].getAttribute('fill'), 'white');
});

test('the deck itself is never written on', () => {
    const slide = element('svg', { viewBox: '0 0 1920 1080' });

    onPaper(slide);

    assert.equal(slide.children.length, 0);
});
