import assert from 'node:assert/strict';
import test from 'node:test';

import { downloadTextFile, scoreSourceFilename } from '../../resources/js/score-editor-file.js';

test('builds aretino filenames from score titles', () => {
    assert.equal(scoreSourceFilename('  Missa Sancta  ', 'aretino'), 'Missa-Sancta.aretino');
    assert.equal(scoreSourceFilename('Áldás / Béke?!', 'aretino'), 'Áldás-Béke.aretino');
    assert.equal(scoreSourceFilename('plain.aretino', 'aretino'), 'plain.aretino');
    assert.equal(scoreSourceFilename('', 'aretino'), 'score.aretino');
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
