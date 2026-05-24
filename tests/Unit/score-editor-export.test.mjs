import assert from 'node:assert/strict';
import test from 'node:test';

import { removeEditorOnlySvgMarkup } from '../../resources/js/score-editor-export.js';

class TestClassList {
    constructor(owner, names = []) {
        this.owner = owner;
        this.names = new Set(names);
        this.sync();
    }

    contains(name) {
        return this.names.has(name);
    }

    remove(...names) {
        names.forEach(name => this.names.delete(name));
        this.sync();
    }

    sync() {
        this.owner.attributes.class = Array.from(this.names).join(' ');
    }
}

class TestElement {
    constructor(className = '') {
        this.attributes = {};
        this.children = [];
        this.parentNode = null;
        this.removed = false;
        this.classList = new TestClassList(this, className.split(/\s+/).filter(Boolean));
    }

    appendChild(child) {
        child.parentNode = this;
        this.children.push(child);

        return child;
    }

    querySelectorAll(selector) {
        const className = selector.startsWith('.') ? selector.slice(1) : selector;
        const matches = [];

        const visit = (node) => {
            node.children.forEach(child => {
                if (!child.removed && child.classList.contains(className)) {
                    matches.push(child);
                }

                visit(child);
            });
        };

        visit(this);

        return matches;
    }

    getAttribute(name) {
        return this.attributes[name] ?? null;
    }

    removeAttribute(name) {
        delete this.attributes[name];

        if (name === 'class') {
            this.classList.names.clear();
        }
    }

    remove() {
        this.removed = true;

        if (!this.parentNode) {
            return;
        }

        this.parentNode.children = this.parentNode.children.filter(child => child !== this);
        this.parentNode = null;
    }
}

test('removes Aretino cursor highlight markup from SVG clones', () => {
    const svg = new TestElement();
    const activeNote = svg.appendChild(new TestElement('aretino-active'));
    const highlightedLigature = activeNote.appendChild(new TestElement('note aretino-active'));
    const cursorBackground = activeNote.appendChild(new TestElement('aretino-cursor-bg'));

    assert.equal(removeEditorOnlySvgMarkup(svg), svg);

    assert.equal(cursorBackground.removed, true);
    assert.equal(activeNote.getAttribute('class'), null);
    assert.equal(highlightedLigature.getAttribute('class'), 'note');
    assert.equal(svg.querySelectorAll('.aretino-cursor-bg').length, 0);
    assert.equal(svg.querySelectorAll('.aretino-active').length, 0);
});

test('removes Aretino active class from the exported SVG root', () => {
    const svg = new TestElement('score-page aretino-active');

    removeEditorOnlySvgMarkup(svg);

    assert.equal(svg.getAttribute('class'), 'score-page');
});
