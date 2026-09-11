import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

import { ABC_RATIO_DEFAULTS, ABC_PAGE_WIDTH_DEFAULT, abcMixin, abcStrokeWidths, buildAbcPreamble, hungarianChordsToAbc, normalizeAbcPageWidth } from '../../resources/js/score-editor-abc.js';
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
    assert.match(preamble, /%%transpose -2\n$/);
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

const engine = { abc2svg: {}, console };
vm.createContext(engine);
vm.runInContext(readFileSync(new URL('../../node_modules/@cantoreshu/abc2svg/abc2svg-1.js', import.meta.url), 'utf8'), engine);

function renderedDimensions(settings, width) {
    let svg = '';
    const errors = [];
    new engine.abc2svg.Abc({ img_out: chunk => { svg += chunk; }, errmsg: message => errors.push(message) })
        .tosvg('projection', buildAbcPreamble(settings, width) + 'X:1\nK:C\nC D E F |\nw: one two three four\n');
    assert.deepEqual(errors, []);
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
        Abc: class {
            tosvg(name, source) { engraved.push(source); }
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
