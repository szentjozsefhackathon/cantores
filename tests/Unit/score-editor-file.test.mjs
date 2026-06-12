import assert from 'node:assert/strict';
import test from 'node:test';

import { downloadTextFile, openTextFile, scoreSourceExtension, scoreSourceFilename } from '../../resources/js/score-editor-file.js';

test('maps score formats to file extensions', () => {
    assert.equal(scoreSourceExtension('abc'), 'abc');
    assert.equal(scoreSourceExtension('aretino'), 'aretino');
    assert.equal(scoreSourceExtension('gabc'), 'gabc');
    assert.equal(scoreSourceExtension('chordpro'), 'cho');
    assert.equal(scoreSourceExtension('unknown'), 'txt');
});

test('builds aretino filenames from score titles', () => {
    assert.equal(scoreSourceFilename('  Missa Sancta  ', 'aretino'), 'Missa-Sancta.aretino');
    assert.equal(scoreSourceFilename('Áldás / Béke?!', 'aretino'), 'Áldás-Béke.aretino');
    assert.equal(scoreSourceFilename('plain.aretino', 'aretino'), 'plain.aretino');
    assert.equal(scoreSourceFilename('', 'aretino'), 'score.aretino');
});

function fakeFileInputDocument() {
    const inputs = [];

    return {
        inputs,
        body: {
            appendChild() {},
        },
        createElement(tagName) {
            assert.equal(tagName, 'input');

            const input = {
                accept: '',
                clicked: false,
                files: [],
                listeners: {},
                removed: false,
                style: {},
                type: '',
                addEventListener(event, handler) {
                    this.listeners[event] = handler;
                },
                click() {
                    this.clicked = true;
                },
                remove() {
                    this.removed = true;
                },
            };
            inputs.push(input);

            return input;
        },
    };
}

test('opens a text file through a file input', async () => {
    const documentRef = fakeFileInputDocument();
    const promise = openTextFile('.gabc', { documentRef });
    const input = documentRef.inputs[0];

    assert.equal(input.type, 'file');
    assert.equal(input.accept, '.gabc');
    assert.equal(input.style.display, 'none');
    assert.equal(input.clicked, true);

    input.files = [{ name: 'gloria.gabc', text: async () => '(c3) Glo(f)ri(g)a(h)' }];
    input.listeners.change();

    assert.deepEqual(await promise, { name: 'gloria.gabc', content: '(c3) Glo(f)ri(g)a(h)' });
    assert.equal(input.removed, true);
});

test('resolves null when the file picker is cancelled', async () => {
    const documentRef = fakeFileInputDocument();
    const promise = openTextFile('.abc', { documentRef });
    const input = documentRef.inputs[0];

    input.listeners.cancel();

    assert.equal(await promise, null);
    assert.equal(input.removed, true);
});

test('downloads text content through an anchor', async () => {
    const anchors = [];
    const blobs = [];
    const revokedUrls = [];

    const documentRef = {
        body: {
            appendChild(anchor) {
                anchors.push(anchor);
            },
        },
        createElement(tagName) {
            assert.equal(tagName, 'a');

            return {
                clicked: false,
                download: '',
                href: '',
                removed: false,
                style: {},
                click() {
                    this.clicked = true;
                },
                remove() {
                    this.removed = true;
                },
            };
        },
    };

    const urlRef = {
        createObjectURL(blob) {
            blobs.push(blob);

            return 'blob:score-file';
        },
        revokeObjectURL(url) {
            revokedUrls.push(url);
        },
    };

    downloadTextFile('(g2) g h i ||', 'gloria.aretino', { documentRef, urlRef });

    assert.equal(anchors.length, 1);
    assert.equal(anchors[0].download, 'gloria.aretino');
    assert.equal(anchors[0].href, 'blob:score-file');
    assert.equal(anchors[0].style.display, 'none');
    assert.equal(anchors[0].clicked, true);
    assert.equal(anchors[0].removed, true);
    assert.deepEqual(revokedUrls, ['blob:score-file']);
    assert.equal(await blobs[0].text(), '(g2) g h i ||');
    assert.equal(blobs[0].type, 'text/plain;charset=utf-8');
});
