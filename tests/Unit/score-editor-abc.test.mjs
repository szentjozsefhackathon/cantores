import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

import { ABC_CHORD_CLASS, ABC_RATIO_DEFAULTS, ABC_PAGE_WIDTH_DEFAULT, abcMixin, abcStrokeWidths, applyAbcStrokeWidths, applyAbcSvgStyle, buildAbcPreamble, hungarianChordsToAbc, normalizeAbcPageWidth } from '../../resources/js/score-editor-abc.js';
import { abcLyricSizeForPt, abcPageScaleForStaffHeight, DEFAULT_PAGE_WIDTH_MM, mmToPx, pxToMm, staffHeightMmForAbcPageScale } from '../../resources/js/booklet-geometry.js';

test('normalizes ABC page width to the renderer-safe range', () => {
    assert.equal(normalizeAbcPageWidth(30), 100);
    assert.equal(normalizeAbcPageWidth('30'), 100);
    assert.equal(normalizeAbcPageWidth(1800), 1800);
    assert.equal(normalizeAbcPageWidth(5000), 3000);
    assert.equal(normalizeAbcPageWidth(''), ABC_PAGE_WIDTH_DEFAULT);
    assert.equal(normalizeAbcPageWidth('not-a-number'), ABC_PAGE_WIDTH_DEFAULT);
});

test('the ABC page is the same sheet of paper the Aretino editor engraves on', () => {
    assert.equal(ABC_PAGE_WIDTH_DEFAULT, 642.52);
    // Exactly the page it says it is: the toolbar states this width in
    // millimetres, and a whole 170 has to come back out of it as a whole 170.
    assert.equal(Math.round(pxToMm(ABC_PAGE_WIDTH_DEFAULT) * 100) / 100, DEFAULT_PAGE_WIDTH_MM);
    assert.equal(normalizeAbcPageWidth(mmToPx(DEFAULT_PAGE_WIDTH_MM)), ABC_PAGE_WIDTH_DEFAULT);
});

test('turns a settings bucket into abc2svg directives', () => {
    const preamble = buildAbcPreamble({
        abcLyricFont: 'Barlow Condensed',
        abcLyricSize: 31,
        abcLyricBold: true,
        abcPageScale: 3.1,
        abcNoteSpacing: 1.1,
        abcStaffSep: 15,
        abcTranspose: -2,
    }, 1920);

    assert.match(preamble, /^%%fullsvg 1\n%%pagewidth 1920px\n/);
    assert.match(preamble, /%%pagescale 3\.1\n/);
    assert.match(preamble, /%%vocalfont "Barlow Condensed" bold 30\n/);
    assert.match(preamble, /%%staffsep 15\n/);
    assert.match(preamble, /%%gchordfont "Barlow Condensed" bold 30 class=abc-chord\n/);
    assert.match(preamble, /%%transpose -2\n$/);
});

// abc2svg's own chord face is a regular sans-serif at 12 of its units, which
// on a slide came out well under half the size of the words under it. A chord
// sheet sets its chords in the words' own face, bold, and as large.
test('sets the chord symbols in the lyric face, bold, at a multiple of the lyric size', () => {
    const settings = { abcLyricFont: 'Barlow Condensed', abcLyricSize: 31, abcPageScale: 3.1 };

    assert.match(buildAbcPreamble({ ...settings, abcChordSize: 0.8 }, 1920),
        /%%gchordfont "Barlow Condensed" bold 24 class=abc-chord\n/);
    assert.match(buildAbcPreamble({ ...settings, abcChordSize: 0 }, 1920),
        /%%gchordfont "Barlow Condensed" bold 30 class=abc-chord\n/);
    assert.match(buildAbcPreamble({ abcLyricFont: 'Comic Sans; }', abcLyricSize: 0, abcPageScale: 0 }, 1700),
        /%%gchordfont Alegreya bold 36 class=abc-chord\n/);
});

test('a slide sets its chords smaller than the lyrics, and paper as large', () => {
    assert.equal(abcMixin().abcChordSize, 1);
    assert.ok(abcMixin().abcFields.includes('abcChordSize'));

    for (const ratio of ['16/9', '4/3', '1/1']) {
        assert.equal(ABC_RATIO_DEFAULTS[ratio].abcChordSize, 0.8);
    }
});

test('falls back to a safe font and scale for unusable settings', () => {
    const preamble = buildAbcPreamble({ abcLyricFont: 'Comic Sans; }', abcLyricSize: 0, abcPageScale: 0 }, 1700);

    assert.match(preamble, /%%vocalfont Alegreya 36\n/);
    assert.match(preamble, /%%pagescale 1\n/);
    assert.doesNotMatch(preamble, /%%transpose/);
});

test('the factory defaults describe an untransposed paper page', () => {
    const defaults = abcMixin();
    const preamble = buildAbcPreamble(defaults, normalizeAbcPageWidth(defaults.abcPageWidth));

    assert.match(preamble, /%%pagewidth 642\.52px\n/);
    assert.match(preamble, /%%pagescale 0\.9449\n/);
    // The rendered lyric size is the directive times the page scale, which comes
    // to 11 pt whatever the scale; see abcLyricSizeForPt.
    assert.match(preamble, /%%vocalfont Alegreya 15\.522\n/);
    assert.match(preamble, /%%staffsep 36\n/);
    assert.doesNotMatch(preamble, /%%transpose/);
});

test('the factory staff is six millimetres tall', () => {
    assert.ok(Math.abs(staffHeightMmForAbcPageScale(abcMixin().abcPageScale) - 6) < 0.005);
});

test('rewrites Hungarian chord roots to the English spelling abc2svg parses', () => {
    const source = hungarianChordsToAbc('K:C\n"C"G2 "H"GF | "B"E "Hm7"D "C/H"C "F/B"B |\n');

    assert.match(source, /"C"G2 "B"GF/);          // H  -> B  (B natural)
    assert.match(source, /"Bb"E "Bm7"D/);         // B  -> Bb (B flat); Hm7 -> Bm7
    assert.match(source, /"C\/B"C "F\/Bb"B/);     // slashed bass note too
});

test('leaves an already English B flat, information fields and annotations alone', () => {
    assert.equal(hungarianChordsToAbc('"Bb"C "Bbm"D\n'), '"Bb"C "Bbm"D\n');
    assert.equal(hungarianChordsToAbc('T:Best of B\nw: hall-B-ha\n'), 'T:Best of B\nw: hall-B-ha\n');
    assert.equal(hungarianChordsToAbc('"^Bridge"C "_Boo"D\n'), '"^Bridge"C "_Boo"D\n');
});

test('keeps stem and staff-line widths on the projector ratios only', () => {
    const settings = { abcStemWidth: 1.4, abcStaffLineWidth: 1 };

    assert.deepEqual(abcStrokeWidths({ ...settings, abcPageRatio: '16/9' }), { stem: 1.4, staffLine: 1 });
    assert.deepEqual(abcStrokeWidths({ ...settings, abcPageRatio: '4/3' }), { stem: 1.4, staffLine: 1 });
    assert.deepEqual(abcStrokeWidths({ ...settings, abcPageRatio: '1/1' }), { stem: 1.4, staffLine: 1 });
    assert.deepEqual(abcStrokeWidths({ ...settings, abcPageRatio: 'paper' }), { stem: 0.7, staffLine: 0.7 });
    assert.deepEqual(abcStrokeWidths({ ...settings, abcPageRatio: 'responsive' }), { stem: 0.7, staffLine: 0.7 });
});

/*
 * A slide is a projector ratio whatever its bucket says. The score editor keeps
 * `abcPageRatio` as a field of its own and never writes it into a saved layout,
 * so a projection resolving a score's 16:9 bucket reads `paper` there — and
 * every stem in the deck came out at the engine's hairline however the panel's
 * knobs were set.
 */
test('a slide gets the stroke widths it was set, whatever the bucket says the ratio is', () => {
    const settings = { abcStemWidth: 2.5, abcStaffLineWidth: 1.8, abcPageRatio: 'paper' };

    assert.deepEqual(abcStrokeWidths(settings, true), { stem: 2.5, staffLine: 1.8 });
    assert.deepEqual(abcStrokeWidths({ abcPageRatio: 'paper' }, true), { stem: 0.7, staffLine: 0.7 });
});

const engine = { abc2svg: {}, console };
vm.createContext(engine);
vm.runInContext(readFileSync(new URL('../../node_modules/@cantoreshu/abc2svg/abc2svg-1.js', import.meta.url), 'utf8'), engine);

const ONE_LINE_TUNE = 'X:1\nK:C\nC D E F |\nw: one two three four\n';

// Long enough to be engraved as several music lines, which is when abc2svg
// starts defining a staff and drawing the rest with a <use> of it.
const TWO_LINE_TUNE = 'X:1\nK:C\nC D E F | G A B c |\nw: one two three four five six sev eight\n'
    + 'C D E F | G A B c |\nw: nine ten twelve thir four fif six sev\n';

function renderedMarkup(settings, width, scope = undefined, tune = ONE_LINE_TUNE) {
    let svg = '';
    const errors = [];
    new engine.abc2svg.Abc({ img_out: chunk => { svg += chunk; }, errmsg: message => errors.push(message) })
        .tosvg('projection', buildAbcPreamble(settings, width, ...(scope === undefined ? [] : [scope])) + tune);
    assert.deepEqual(errors, []);

    return svg;
}

function renderedDimensions(settings, width) {
    const svg = renderedMarkup(settings, width);
    const scale = Number(svg.match(/class="g" transform="scale\(([^)]+)\)"/)[1]);
    const fontPx = Number(svg.match(/font:([\d.]+)px "Barlow Condensed"/)[1]);
    const staffPath = svg.match(/class="slW" d="([^"]+)"/)[1];
    const staffHeight = [...staffPath.matchAll(/m[-\d.]+ (-[\d.]+)/g)]
        .reduce((height, match) => height - Number(match[1]), 0);

    return { width: Number(svg.match(/viewBox="0 0 (\d+)/)[1]), fontPx: fontPx * scale, staffPx: staffHeight * scale };
}

for (const [ratio, width, fontPx, staffPx] of [['16/9', 1920, 70 * 96 / 72, mmToPx(19.5)], ['4/3', 1440, 78, mmToPx(14.5)]]) {
    test(`${ratio} factory layout engraves at its screen dimensions`, () => {
        const actual = renderedDimensions({ ...abcMixin(), ...ABC_RATIO_DEFAULTS[ratio] }, width);

        assert.equal(actual.width, width);
        assert.ok(Math.abs(actual.fontPx - fontPx) < 0.2);
        assert.ok(Math.abs(actual.staffPx - staffPx) < 0.01);
    });

    test(`${ratio} renders 72 point lyrics as 96 pixels independently of staff height`, () => {
        for (const staffMm of [6, 12, 24]) {
            const actual = renderedDimensions({
                ...abcMixin(),
                ...ABC_RATIO_DEFAULTS[ratio],
                abcLyricSize: abcLyricSizeForPt(72),
                abcPageScale: abcPageScaleForStaffHeight(staffMm),
            }, width);

            assert.equal(actual.width, width);
            assert.ok(Math.abs(actual.fontPx - 96) < 0.2);
            assert.ok(Math.abs(actual.staffPx - mmToPx(staffMm)) < 0.01);
        }
    });
}

/*
 * Every name abc2svg shares between engravings carries the suffix it is given:
 * `stdef`, the staff-line path each music line draws with a <use>, among them.
 * A document showing several scores at once is a projection's contact sheet,
 * and ids resolve across the whole of it — so with one suffix for all of them
 * every slide drew the first slide's staff lines, at the first slide's staff
 * scale. Setting one score's staff size moved the scores around it.
 */
test('scopes the names one engraving shares, so a slide is not drawn with anothers staff', () => {
    const staffIds = (mm, scope) => {
        const markup = renderedMarkup({
            ...abcMixin(),
            ...ABC_RATIO_DEFAULTS['16/9'],
            abcPageScale: abcPageScaleForStaffHeight(mm),
        }, 1920, scope, TWO_LINE_TUNE);

        return {
            defined: [...new Set(markup.match(/id="stdef[^"]*"/g))],
            used: [...new Set(markup.match(/href="#stdef[^"]*"/g))],
        };
    };

    const tall = staffIds(19.5, 's1');
    const short = staffIds(8, 's2');

    assert.deepEqual(tall.defined, ['id="stdefs1"']);
    assert.deepEqual(short.defined, ['id="stdefs2"']);
    // And each one draws with the staff it defined itself.
    assert.deepEqual(tall.used, ['href="#stdefs1"']);
    assert.deepEqual(short.used, ['href="#stdefs2"']);
});

test('passes zero and sub-half first lyric clearance to the renderer', () => {
    for (const value of [0, '0', 0.1, 0.4]) {
        assert.match(buildAbcPreamble({ ...abcMixin(), abcLyricFirstSkip: value }, 1700),
            new RegExp(`%%lyricfirstskipfac ${Number(value)}\\n`));
    }
    for (const value of [undefined, null, -0.1, 'invalid', Infinity]) {
        assert.doesNotMatch(buildAbcPreamble({ ...abcMixin(), abcLyricFirstSkip: value }, 1700),
            /%%lyricfirstskipfac/);
    }
});

function deferredFontPreview(t) {
    const previousDocument = globalThis.document;
    const previousAbc = globalThis.abc2svg;
    t.after(() => {
        globalThis.document = previousDocument;
        globalThis.abc2svg = previousAbc;
    });
    const loads = [];
    const engraved = [];
    const container = { innerHTML: 'previous preview', clientWidth: 800, appendChild() {} };
    globalThis.document = {
        fonts: {
            load(spec, text) {
                return new Promise(resolve => loads.push({ spec, text, resolve }));
            },
        },
        createElement() {
            return { style: {}, querySelectorAll: () => [] };
        },
    };
    globalThis.abc2svg = {
        // One engraving per engraver, however many tosvg calls it takes: the
        // preview hands the preamble over in a call of its own.
        Abc: class {
            constructor() { this.engraving = engraved.push('') - 1; }
            tosvg(name, source) { engraved[this.engraving] += source; }
        },
    };
    const component = {
        ...abcMixin(),
        abcLyricFont: 'Deferred Font',
        abcLyricSize: 40,
        abcPageScale: 2,
        localContent: 'K:C\nC D E F|\nw: ár víz tű rő',
        hasPages: true,
        $wire: { format: 'abc' },
        $refs: { abcPreview: container },
        isFixedRatio: () => false,
        isResponsiveRatio: () => false,
        isPaperRatio: () => true,
        getVirtualCanvasSize: () => ({ width: 800, height: 1000 }),
        splitPages: content => [content],
        addPageControls() {},
    };

    return { component, container, loads, engraved };
}

test('ABC waits for every font style and accented subset before engraving metrics', async t => {
    const { component, container, loads, engraved } = deferredFontPreview(t);
    const render = component.renderAbcPreview();

    assert.equal(container.innerHTML, 'previous preview');
    assert.equal(component.hasPages, true);
    assert.deepEqual(engraved, []);
    assert.equal(loads.length, 4);
    assert.ok(loads.every(load => load.spec.includes('60px "Deferred Font"')));
    assert.ok(loads.every(load => /[őű]/.test(load.text)));

    loads.slice(0, 3).forEach(load => load.resolve([]));
    await new Promise(resolve => setImmediate(resolve));
    assert.deepEqual(engraved, []);
    loads[3].resolve([]);
    await render;
    assert.equal(engraved.length, 1);
    assert.match(engraved[0], /%%vocalfont "Deferred Font" 60/);
});

test('a slow old font cannot replace a newer ABC preview', async t => {
    const { component, loads, engraved } = deferredFontPreview(t);
    component.abcLyricFont = 'Slow Font';
    const oldRender = component.renderAbcPreview();
    component.abcLyricFont = 'Fast Font';
    component.localContent = 'K:C\nG A B c|';
    const newRender = component.renderAbcPreview();

    loads.filter(load => load.spec.includes('Fast Font')).forEach(load => load.resolve([]));
    await newRender;
    assert.equal(engraved.length, 1);
    assert.match(engraved[0], /%%vocalfont "Fast Font"/);
    assert.match(engraved[0], /G A B c/);

    loads.filter(load => load.spec.includes('Slow Font')).forEach(load => load.resolve([]));
    await oldRender;
    assert.equal(engraved.length, 1);
});

test('an ABC render invalidated while fonts load leaves the preview alone', async t => {
    const { component, container, loads, engraved } = deferredFontPreview(t);
    component.abcLyricFont = 'Cancelled Font';
    const render = component.renderAbcPreview();
    component._abcRenderVersion++;
    loads.forEach(load => load.resolve([]));
    await render;

    assert.equal(container.innerHTML, 'previous preview');
    assert.deepEqual(engraved, []);
});

test('responsive ABC lays the music out across the whole container, not just a paper page', async t => {
    const { component, container, loads, engraved } = deferredFontPreview(t);
    component.isResponsiveRatio = () => true;
    component.isPaperRatio = () => false;
    component.abcZoom = 100;
    container.clientWidth = 1400;
    const render = component.renderAbcPreview();

    loads.forEach(load => load.resolve([]));
    await render;
    assert.equal(engraved.length, 1);
    assert.match(engraved[0], /%%pagewidth 1396px\n/);
});

test('responsive ABC trades container width for note size as the zoom rises', async t => {
    const { component, container, loads, engraved } = deferredFontPreview(t);
    component.isResponsiveRatio = () => true;
    component.isPaperRatio = () => false;
    component.abcZoom = 200;
    container.clientWidth = 1400;
    const render = component.renderAbcPreview();

    loads.forEach(load => load.resolve([]));
    await render;
    assert.match(engraved[0], /%%pagewidth 698px\n/);
});

/*
 * A staff abc2svg reused is a <use> of a path kept in the defs, and the content
 * of a <use> is a shadow tree no selector written outside it can reach. So the
 * widths have to be written onto the paths — the one in the defs included, or
 * every music line but the few drawn inline keeps the engine's hairline.
 */
function fakeElement(className) {
    const declarations = {};

    return {
        className,
        style: { setProperty: (name, value) => { declarations[name] = value; } },
        declarations,
    };
}

function fakeSvgDocument(elements) {
    return {
        children: [],
        querySelectorAll: (selector) => elements.filter(el => `.${el.className}` === selector),
        appendChild(child) { this.children.push(child); },
    };
}

test('the reused staff is drawn from the defs, which is what a rule cannot style', () => {
    const markup = renderedMarkup({ ...abcMixin(), ...ABC_RATIO_DEFAULTS['16/9'] }, 1920, 's1', TWO_LINE_TUNE);
    const defs = markup.match(/<defs>[\s\S]*?<\/defs>/)[0];

    assert.match(markup, /<use [^>]*xlink:href="#stdefs1"/);
    assert.match(defs, /id="stdefs1" class="slW"/);
});

test('writes the stroke widths onto the paths, defs and body alike', () => {
    const staffInDefs = fakeElement('slW');
    const staffInline = fakeElement('slW');
    const stem = fakeElement('sW');
    const svg = fakeSvgDocument([staffInDefs, staffInline, stem]);

    applyAbcStrokeWidths(svg, { abcStemWidth: 1.4, abcStaffLineWidth: 1 }, true);

    assert.deepEqual(staffInDefs.declarations, { 'stroke-width': '1' });
    assert.deepEqual(staffInline.declarations, { 'stroke-width': '1' });
    assert.deepEqual(stem.declarations, { 'stroke-width': '1.4' });
});

test('leaves the stroke widths out of the scoped stylesheet, which only carries the ink', t => {
    const previousDocument = globalThis.document;
    t.after(() => { globalThis.document = previousDocument; });
    globalThis.document = { createElementNS: () => ({ textContent: '' }) };

    const staff = fakeElement('slW');
    const svg = fakeSvgDocument([staff]);

    applyAbcSvgStyle(svg, 'abc-slide-s1', { abcStemWidth: 1.4, abcStaffLineWidth: 1 }, true);

    assert.equal(svg.id, 'abc-slide-s1');
    assert.equal(svg.children[0].textContent, '#abc-slide-s1{color:#000!important;fill:#000!important}#abc-slide-s1 .abc-chord{fill:#1d4ed8}');
    assert.deepEqual(staff.declarations, { 'stroke-width': '1' });
});

// The music is ink on paper whatever the deck's theme, so a slide's chords take
// the light palette's chord colour; a page stays black for the photocopier.
test('colours the chord symbols on a slide only', t => {
    const previousDocument = globalThis.document;
    t.after(() => { globalThis.document = previousDocument; });
    globalThis.document = { createElementNS: () => ({ textContent: '' }) };

    const paper = fakeSvgDocument([]);
    applyAbcSvgStyle(paper, 'abc-svg-1', {});

    assert.equal(paper.children[0].textContent, '#abc-svg-1{color:#000!important;fill:#000!important}');
});

test('draws every chord symbol with the class a slide colours it by, and no other text', () => {
    const markup = renderedMarkup({ ...abcMixin(), ...ABC_RATIO_DEFAULTS['16/9'] }, 1920, 's1',
        'X:1\nL:1/4\nK:G\n"G"G A "D7"B c |\nw: one two three four\n');
    const chordTexts = [...markup.matchAll(new RegExp(`<text class="(f\\d+s1) ${ABC_CHORD_CLASS}"[^>]*>([^<]*)<`, 'g'))];

    assert.deepEqual(chordTexts.map(match => match[2]), ['G', 'D7']);
    assert.match(markup, new RegExp(`\\.${chordTexts[0][1]}\\{font:700 [\\d.]+px "Barlow Condensed"\\}`));
    assert.equal(markup.match(new RegExp(ABC_CHORD_CLASS, 'g')).length, 2);
});
