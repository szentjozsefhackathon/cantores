/**
 * Embedding the site's web fonts into an SVG document.
 *
 * A score leaving the browser — copied as an image, downloaded as SVG, or sent
 * to the server to become a PDF — is read somewhere that has never loaded this
 * site's stylesheets, so a font referenced only by name falls back to whatever
 * the consumer happens to have. The faces are therefore inlined as base64
 * @font-face rules inside the document itself.
 *
 * Extracted from score-editor.js so the booklet renderer embeds exactly the same
 * faces the score editor does; rsvg-convert has no network access at all, so a
 * booklet page that skipped this would print in a substitute face.
 */

const LATIN_EXT = 'U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF';
const LATIN = 'U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD';

export const WEB_FONTS = {
    'EB Garamond': [
        { style: 'normal', weight: '400', unicodeRange: LATIN_EXT, url: '/fonts/eb-garamond-latin-ext-400.woff2' },
        { style: 'normal', weight: '400', unicodeRange: LATIN,     url: '/fonts/eb-garamond-latin-400.woff2' },
        { style: 'italic', weight: '400', unicodeRange: LATIN_EXT, url: '/fonts/eb-garamond-latin-ext-400i.woff2' },
        { style: 'italic', weight: '400', unicodeRange: LATIN,     url: '/fonts/eb-garamond-latin-400i.woff2' },
        { style: 'normal', weight: '700', unicodeRange: LATIN_EXT, url: '/fonts/eb-garamond-latin-ext-700.woff2' },
        { style: 'normal', weight: '700', unicodeRange: LATIN,     url: '/fonts/eb-garamond-latin-700.woff2' },
        { style: 'italic', weight: '700', unicodeRange: LATIN_EXT, url: '/fonts/eb-garamond-latin-ext-700i.woff2' },
        { style: 'italic', weight: '700', unicodeRange: LATIN,     url: '/fonts/eb-garamond-latin-700i.woff2' },
    ],
    'Alegreya': [
        { style: 'normal', weight: '400 900', unicodeRange: LATIN_EXT, url: '/fonts/alegreya-latin-ext.woff2' },
        { style: 'normal', weight: '400 900', unicodeRange: LATIN,     url: '/fonts/alegreya-latin.woff2' },
        { style: 'italic', weight: '400 900', unicodeRange: LATIN_EXT, url: '/fonts/alegreya-latin-ext-italic.woff2' },
        { style: 'italic', weight: '400 900', unicodeRange: LATIN,     url: '/fonts/alegreya-latin-italic.woff2' },
    ],
    'Merriweather': [
        { style: 'normal', weight: '300 900', unicodeRange: LATIN_EXT, url: '/fonts/merriweather-latin-ext.woff2' },
        { style: 'normal', weight: '300 900', unicodeRange: LATIN,     url: '/fonts/merriweather-latin.woff2' },
        { style: 'italic', weight: '300 900', unicodeRange: LATIN_EXT, url: '/fonts/merriweather-latin-ext-italic.woff2' },
        { style: 'italic', weight: '300 900', unicodeRange: LATIN,     url: '/fonts/merriweather-latin-italic.woff2' },
    ],
    'Inter': [
        { style: 'normal', weight: '100 900', unicodeRange: LATIN_EXT, url: '/fonts/inter-latin-ext.woff2' },
        { style: 'normal', weight: '100 900', unicodeRange: LATIN,     url: '/fonts/inter-latin.woff2' },
    ],
    'Barlow Condensed': [
        { style: 'normal', weight: '500', unicodeRange: LATIN_EXT, url: '/fonts/barlow-condensed-latin-ext-500.woff2' },
        { style: 'normal', weight: '500', unicodeRange: LATIN,     url: '/fonts/barlow-condensed-latin-500.woff2' },
        { style: 'normal', weight: '700', unicodeRange: LATIN_EXT, url: '/fonts/barlow-condensed-latin-ext-700.woff2' },
        { style: 'normal', weight: '700', unicodeRange: LATIN,     url: '/fonts/barlow-condensed-latin-700.woff2' },
    ],
};

/**
 * The face the ♯ ♭ ♮ 𝄪 𝄫 of an abc2svg chord symbol are drawn from, named
 * after the lyric family in the chord font (see score-editor-abc.js), and the
 * five characters it holds. Kept apart from WEB_FONTS: it is no face a lyric is
 * set in, and it is an OpenType file rather than a WOFF2 one.
 */
export const CHORD_ACCIDENTALS = {
    family: 'Chord Accidentals',
    style: 'normal',
    weight: '100 900',
    unicodeRange: 'U+266D-266F,U+1D12A-1D12B',
    url: '/fonts/chord-accidentals.otf',
    format: 'opentype',
    mime: 'font/otf',
};

const CHORD_ACCIDENTAL_TEXT = /[\u266D-\u266F\u{1D12A}\u{1D12B}]/u;

const fontBase64Cache = {};

async function fetchFontBase64(url) {
    if (fontBase64Cache[url]) { return fontBase64Cache[url]; }
    const res = await fetch(url);
    const buf = await res.arrayBuffer();
    let binary = '';
    new Uint8Array(buf).forEach(b => { binary += String.fromCharCode(b); });
    const b64 = btoa(binary);
    fontBase64Cache[url] = b64;
    return b64;
}

export function parsePrimaryFontFamily(fontValue) {
    return (fontValue ?? '').split(',')[0].trim().replace(/['"]/g, '');
}

/**
 * Faces already asked for, keyed by family and size, holding the load itself so
 * that two renders started in the same breath wait on one request rather than
 * racing each other.
 *
 * @type {Map<string, Promise<unknown>>}
 */
const fontLoads = new Map();

/** Request both Latin and Latin Extended faces, including Hungarian double accents. */
const FONT_LOAD_SAMPLE = 'Árvíztűrő tükörfúrógép';

/**
 * Wait until the browser actually holds the faces a drawing is about to be
 * measured against.
 *
 * A web font is fetched only when something on the page asks to be painted in
 * it, so the first drawing made in a font the page has never used measures
 * against the fallback face: `canvas.measureText`, abc2svg's hidden span and
 * Aretino's own metrics all answer for whatever the browser has right now.
 * Every one of those numbers is baked into an SVG that is never re-measured, so
 * the mistake stays on screen until something else forces a re-render — which is
 * why a font change used to come out right only on the second attempt.
 *
 * The four styles are loaded, not just the regular one: a heading is bold and a
 * variation is italic, and each is a separate face with metrics of its own.
 *
 * @param {string[]} fontValues CSS font-family values; only the primary family counts
 * @param {number} [sizePx] the size to ask for, which picks between size-specific faces
 */
export async function ensureFontsLoaded(fontValues, sizePx = 16) {
    if (typeof document === 'undefined' || !document.fonts) { return; }

    const waits = [];
    for (const value of fontValues) {
        const family = parsePrimaryFontFamily(value);
        if (family === '') { continue; }
        const key = `${sizePx}_${family}`;
        if (!fontLoads.has(key)) {
            const spec = `${sizePx}px "${family}"`;
            fontLoads.set(key, Promise.allSettled([
                document.fonts.load(spec, FONT_LOAD_SAMPLE),
                document.fonts.load(`italic ${spec}`, FONT_LOAD_SAMPLE),
                document.fonts.load(`bold ${spec}`, FONT_LOAD_SAMPLE),
                document.fonts.load(`italic bold ${spec}`, FONT_LOAD_SAMPLE),
            ]).then(results => {
                if (results.some(result => result.status === 'rejected')) {
                    fontLoads.delete(key);
                }
            }));
        }
        waits.push(fontLoads.get(key));
    }

    await Promise.all(waits);
}

/** One face as an @font-face rule carrying the font itself. */
function fontFaceRule(family, face, base64) {
    const format = face.format ?? 'woff2';
    const mime = face.mime ?? 'font/woff2';

    return `@font-face{font-family:'${family}';font-style:${face.style};font-weight:${face.weight};`
        + `unicode-range:${face.unicodeRange};src:url('data:${mime};base64,${base64}')format('${format}');}`;
}

export async function injectWebFontsIntoSvg(svgEl, fontValues) {
    const rules = [];
    const seenFamilies = new Set();
    for (const value of fontValues) {
        const family = parsePrimaryFontFamily(value);
        if (seenFamilies.has(family)) { continue; }
        const descriptors = WEB_FONTS[family];
        if (!descriptors) { continue; }
        seenFamilies.add(family);
        for (const d of descriptors) {
            try {
                const b64 = await fetchFontBase64(d.url);
                rules.push(fontFaceRule(family, d, b64));
            } catch (e) {
                console.warn('[svg-fonts] could not embed font:', family, d.url, e);
            }
        }
    }

    // A chord symbol's accidentals come from a face of their own, which the
    // drawing names whatever lyric face it was asked to carry; left out, the
    // reader's own fallback draws them, and draws them badly.
    if (CHORD_ACCIDENTAL_TEXT.test(svgEl.textContent ?? '')) {
        try {
            rules.push(fontFaceRule(CHORD_ACCIDENTALS.family, CHORD_ACCIDENTALS, await fetchFontBase64(CHORD_ACCIDENTALS.url)));
        } catch (e) {
            console.warn('[svg-fonts] could not embed font:', CHORD_ACCIDENTALS.family, CHORD_ACCIDENTALS.url, e);
        }
    }

    if (!rules.length) { return; }
    const style = document.createElementNS('http://www.w3.org/2000/svg', 'style');
    style.textContent = rules.join('');
    let defs = svgEl.querySelector('defs');
    if (!defs) {
        defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
        svgEl.insertBefore(defs, svgEl.firstChild);
    }
    defs.insertBefore(style, defs.firstChild);
}
