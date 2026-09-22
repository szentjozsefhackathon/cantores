import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { fileURLToPath } from 'node:url';
import vm from 'node:vm';

/**
 * The real abc2svg, measuring its text through the room's stand-in for its span.
 *
 * The engraver is loaded with a span to measure with, so it takes the path it
 * takes in the browser — every lyric and chord asked of the span in turn — and
 * the span answers by the length of what it holds and counts the asking. What is
 * held down is that remembering the answers changes nothing on the page, and
 * that a knob which only moves things apart asks the span nothing at all.
 */

class FakeSheet {
    cssRules = [];

    insertRule(rule, index) { this.cssRules.splice(index, 0, rule); }

    deleteRule(index) { this.cssRules.splice(index, 1); }
}

function fakeElement(tag) {
    const element = {
        tag,
        parentNode: null,
        parentElement: null,
        style: {},
        sheet: tag === 'style' ? new FakeSheet() : undefined,
        isConnected: true,
        setAttribute() {},
        appendChild(child) {
            child.parentNode = element;
            child.parentElement = element;

            return child;
        },
        attachShadow() {
            return {
                append(...nodes) {
                    nodes.forEach((node) => { node.parentNode = this; });
                },
            };
        },
    };

    return element;
}

globalThis.document = {
    body: fakeElement('body'),
    head: fakeElement('head'),
    createElement: fakeElement,
    fonts: { addEventListener() {} },
};

const span = fakeElement('span');
span.reads = 0;
Object.defineProperty(span, 'clientWidth', {
    get() {
        span.reads++;

        return span.innerHTML.replace(/<[^>]*>/g, '').length * 6;
    },
});
Object.defineProperty(span, 'clientHeight', { get: () => 14 });
document.body.appendChild(span);

const sandbox = { abc2svg: { el: span }, console, document: globalThis.document };
vm.createContext(sandbox);
vm.runInContext(
    readFileSync(fileURLToPath(new URL('../../node_modules/@cantoreshu/abc2svg/abc2svg-1.js', import.meta.url)), 'utf8'),
    sandbox,
);
globalThis.abc2svg = sandbox.abc2svg;

const { pageGeometry } = await import('../../resources/js/booklet-geometry.js');
const { buildScoreBlocks } = await import('../../resources/js/booklet-render.js');

const geometry = (over = {}) => pageGeometry({
    pageWidthMm: 148,
    pageHeightMm: 210,
    marginMm: 12,
    contentWidthMm: 124,
    contentHeightMm: 186,
    lyricSizePt: 11,
    staffHeightMm: 7,
    ...over,
});

const entry = {
    id: 1,
    kind: 'score',
    slot: 'Kezdőének',
    music: null,
    variation: null,
    format: 'abc',
    settings: {},
    override: null,
    startOnNewPage: false,
    content: 'L:1/4\nK:C\n"Am"CDEF "G"GABc|cBAG FEDC|CDEF GABc|cBAG FEDC|\n'
        + 'w: Glo-ri-a in ex-cel-sis De-o la la la la la la la la la la\n',
};

/**
 * One engraving, as markup that can be compared with another's: abc2svg numbers
 * its fonts across the whole page and %%fullsvg suffixes every name per tune, so
 * the names differ between two engravings of the same tune by design.
 */
async function engrave(over = {}) {
    const before = span.reads;
    const { blocks } = await buildScoreBlocks(entry, geometry(over), null);

    return {
        markup: blocks.map((block) => block.svg.replace(/\bf\d+a\d+\b/g, 'fN').replace(/\bstdefa\d+\b/g, 'stdefN')).join('\n'),
        reads: span.reads - before,
    };
}

test('a string measured before is not measured again, and the page does not change', async () => {
    const first = await engrave();
    const second = await engrave();

    assert.ok(first.reads > 0, 'the span was asked in the first place');
    assert.equal(second.reads, 0);
    assert.equal(second.markup, first.markup);
});

test('a knob that only moves things apart asks the span nothing', async () => {
    await engrave();

    for (const over of [{ abcStaffSep: 40 }, { abcLyricSkip: 1.3, abcLyricFirstSkip: 2 }, { headingScale: 1.3 }]) {
        assert.equal((await engrave(over)).reads, 0, JSON.stringify(over));
    }
});

test('a new lyric size is measured afresh', async () => {
    await engrave();

    assert.ok((await engrave({ lyricSizePt: 12.5 })).reads > 0);
});
