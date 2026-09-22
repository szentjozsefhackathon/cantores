import assert from 'node:assert/strict';
import test from 'node:test';

/**
 * The room the engravers measure in, against just enough of a DOM to see where
 * things are put and what is left behind.
 *
 * Why it exists is a matter for the browser's style engine, which no test here
 * can time; what can be held down is that abc2svg's rules and span and exsurge's
 * drawing all end up inside the shadow root rather than in the document, that
 * the rules do not pile up from one engraving to the next, and that a string is
 * measured once per face rather than once per render.
 */

class FakeSheet {
    cssRules = [];

    insertRule(rule, index) { this.cssRules.splice(index, 0, rule); }

    deleteRule(index) { this.cssRules.splice(index, 1); }
}

class FakeElement {
    constructor(tag) {
        this.tag = tag;
        this.children = [];
        this.parentElement = null;
        this.parentNode = null;
        this.style = {};
        this.attributes = {};
        this.shadowRoot = null;
        this.sheet = tag === 'style' ? new FakeSheet() : undefined;
    }

    get isConnected() {
        let node = this;

        while (node) {
            if (node === document.body) { return true; }
            node = node.parentNode?.host ?? node.parentNode;
        }

        return false;
    }

    setAttribute(name, value) { this.attributes[name] = value; }

    appendChild(child) {
        child.remove();
        this.children.push(child);
        child.parentNode = this;
        child.parentElement = this;

        return child;
    }

    remove() {
        if (!this.parentNode) { return; }

        const siblings = this.parentNode.children;
        siblings.splice(siblings.indexOf(this), 1);
        this.parentNode = null;
        this.parentElement = null;
    }

    attachShadow() {
        const host = this;
        this.shadowRoot = {
            host,
            children: [],
            append(...nodes) {
                nodes.forEach((node) => {
                    node.remove();
                    this.children.push(node);
                    // A shadow root is a parent node but not a parent element.
                    node.parentNode = this;
                    node.parentElement = null;
                });
            },
        };

        return this.shadowRoot;
    }
}

const fontListeners = [];

globalThis.document = {
    body: new FakeElement('body'),
    head: new FakeElement('head'),
    createElement: (tag) => new FakeElement(tag),
    fonts: { addEventListener: (type, listener) => { fontListeners.push({ type, listener }); } },
};

/**
 * A span that measures what it holds by the face its class sets, as a browser
 * would, and counts how often it was asked.
 */
function measuringSpan(faces) {
    const element = new FakeElement('span');
    element.reads = 0;

    Object.defineProperty(element, 'clientWidth', {
        get() {
            element.reads++;

            return element.innerHTML.length * (faces[element.className] ?? 1);
        },
    });
    Object.defineProperty(element, 'clientHeight', { get: () => faces[element.className] ?? 1 });

    return element;
}

/** How a real engraving measures one string: the class, the text, the width. */
function strwh(className, text) {
    const el = abc2svg.el;
    el.className = className;
    el.innerHTML = text;

    return [el.clientWidth, el.clientHeight];
}

const { measuringHost, withAbcMeasuring } = await import('../../resources/js/measuring-room.js');

/** What abc2svg's add_fstyle does with each font it uses. */
function engraveWithRules(...rules) {
    return () => {
        rules.forEach((rule) => abc2svg.styles.sheet.insertRule(rule, abc2svg.styles.sheet.cssRules.length));

        return 'engraved';
    };
}

function roomOf(element) {
    let node = element;

    while (node && !node.host) { node = node.parentNode; }

    return node;
}

test('a chant is measured inside a shadow root hung off the body', () => {
    const host = measuringHost();
    const shadow = roomOf(host);

    assert.ok(shadow, 'the host stands inside a shadow root');
    assert.equal(shadow.host.parentNode, document.body);
    assert.equal(measuringHost(), host, 'one room, not one per render');
});

test('the room is hung back up when the body it stood in was replaced', () => {
    const host = measuringHost();
    const outer = roomOf(host).host;

    document.body = new FakeElement('body');

    assert.equal(measuringHost(), host);
    assert.equal(outer.parentNode, document.body);
});

test('abc2svg measures inside the room and takes its rules back out', () => {
    const span = new FakeElement('span');
    document.body.appendChild(span);

    const documentStyles = new FakeElement('style');
    document.head.appendChild(documentStyles);

    globalThis.abc2svg = { el: span, styles: documentStyles };

    const result = withAbcMeasuring(engraveWithRules('.f1a1{font:12px serif}', 'tspan{white-space:pre}'));
    const roomStyles = roomOf(span).children.find((child) => child.tag === 'style');

    assert.equal(result, 'engraved');
    assert.ok(roomOf(span), 'the span is inside the shadow root');
    assert.ok(span.parentElement, 'wrapped, so abc2svg does not move it back onto the body');
    assert.notEqual(abc2svg.el, span, 'abc2svg measures through the stand-in');
    assert.ok(abc2svg.el.parentElement, 'which abc2svg also sees as placed');
    assert.equal(abc2svg.styles.sheet.cssRules, roomStyles.sheet.cssRules, 'the rules are written inside the shadow root');
    assert.equal(documentStyles.parentNode, null, 'the document-wide sheet is gone');
    assert.deepEqual(roomStyles.sheet.cssRules, [], 'nothing is left behind for the next engraving');

    delete globalThis.abc2svg;
});

test('a string is measured once per face, whatever the face is called this time', () => {
    const span = measuringSpan({ f1a1: 2, f1a2: 2, f2a2: 3 });
    globalThis.abc2svg = { el: span };

    const first = withAbcMeasuring(() => {
        engraveWithRules('.f1a1{font:12px serif}')();

        return [strwh('f1a1', 'Glória'), strwh('f1a1', 'Glória')];
    });

    // The next render names the same face afresh, and a bigger one beside it.
    const second = withAbcMeasuring(() => {
        engraveWithRules('.f1a2{font:12px serif}', '.f2a2{font:14px serif}')();

        return [strwh('f1a2', 'Glória'), strwh('f2a2', 'Glória')];
    });

    assert.deepEqual(first, [[12, 2], [12, 2]]);
    assert.deepEqual(second, [[12, 2], [18, 3]]);
    assert.equal(span.reads, 2, 'once at 12 px, once at 14 px');

    delete globalThis.abc2svg;
});

test('markup is known by the faces its classes stand for', () => {
    const span = measuringSpan({});
    globalThis.abc2svg = { el: span };

    const measure = (suffix) => withAbcMeasuring(() => {
        engraveWithRules(`.f1${suffix}{font:10px serif}`)();

        return strwh('', `<tspan class="f1${suffix}">Am</tspan>`);
    });

    measure('b1');
    measure('b2');

    assert.equal(span.reads, 1);

    delete globalThis.abc2svg;
});

test('a face that finishes loading is measured again', () => {
    const span = measuringSpan({});
    globalThis.abc2svg = { el: span };

    const measure = () => withAbcMeasuring(() => {
        engraveWithRules('.f1c1{font:10px Merriweather}')();

        return strwh('f1c1', 'Kyrie');
    });

    measure();
    fontListeners.filter(({ type }) => type === 'loadingdone').forEach(({ listener }) => listener());
    measure();

    assert.equal(span.reads, 2);

    delete globalThis.abc2svg;
});

test('the rules come out even when the engraving throws', () => {
    const span = new FakeElement('span');
    globalThis.abc2svg = { el: span };

    assert.throws(() => withAbcMeasuring(() => {
        engraveWithRules('.f2a2{font:12px serif}')();
        throw new Error('bad tune');
    }), /bad tune/);

    assert.deepEqual(abc2svg.styles.sheet.cssRules, []);

    delete globalThis.abc2svg;
});

test('an abc2svg with no span to measure with is left to its own tables', () => {
    globalThis.abc2svg = {};

    assert.equal(withAbcMeasuring(() => 'engraved'), 'engraved');
    assert.equal(abc2svg.styles, undefined);

    delete globalThis.abc2svg;
});
