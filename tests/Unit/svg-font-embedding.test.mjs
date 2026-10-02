import assert from 'node:assert/strict';
import test from 'node:test';

/*
 * An exported SVG carries the faces it is drawn in, since whoever opens it may
 * have none of them. A chord symbol's ♯ ♭ ♮ come from a face of their own, which
 * the export has to carry too, or the reader's fallback draws them.
 */

const fetched = [];

globalThis.fetch = async (url) => {
    fetched.push(url);

    return { arrayBuffer: async () => new Uint8Array([1, 2, 3]).buffer };
};

function element(name) {
    return {
        name,
        children: [],
        textContent: '',
        insertBefore(child) { this.children.unshift(child); },
        querySelector() { return null; },
    };
}

globalThis.document = { createElementNS: (ns, name) => element(name) };

const { injectWebFontsIntoSvg } = await import('../../resources/js/svg-fonts.js');

function exported(text) {
    const svg = element('svg');

    svg.textContent = text;

    return svg;
}

const styleOf = (svg) => svg.children[0]?.children[0]?.textContent ?? '';

test('an export with a chord accidental carries the face that draws it', async () => {
    const svg = exported('Hm A G F♯');

    await injectWebFontsIntoSvg(svg, ["'Barlow Condensed'"]);

    const style = styleOf(svg);

    assert.match(style, /font-family:'Chord Accidentals'/);
    assert.match(style, /src:url\('data:font\/otf;base64,[^']+'\)format\('opentype'\)/);
    assert.match(style, /unicode-range:U\+266D-266F,U\+1D12A-1D12B/);
    assert.match(style, /font-family:'Barlow Condensed'[^}]*format\('woff2'\)/, 'the lyric face is still carried as it was');
});

test('an export without one does not carry it', async () => {
    fetched.length = 0;

    const svg = exported('Al-le-lu-ja');

    await injectWebFontsIntoSvg(svg, ["'Barlow Condensed'"]);

    assert.doesNotMatch(styleOf(svg), /Chord Accidentals/);
    assert.ok(!fetched.some((url) => url.includes('chord-accidentals')));
});

test('a double sharp, outside the basic plane, is recognised too', async () => {
    const svg = exported('C\u{1D12A}');

    await injectWebFontsIntoSvg(svg, []);

    assert.match(styleOf(svg), /font-family:'Chord Accidentals'/);
});
