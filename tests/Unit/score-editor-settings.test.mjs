import assert from 'node:assert/strict';
import test from 'node:test';

import { abcMixin } from '../../resources/js/score-editor-abc.js';
import {
    DEFAULT_LYRIC_SIZE_PT,
    DEFAULT_STAFF_HEIGHT_MM,
    abcLyricSizeForPt,
    ptForAbcLyricSize,
    staffHeightMmForAbcPageScale,
    gabcStaffSizeForStaffHeight,
} from '../../resources/js/booklet-geometry.js';
import { gabcMixin } from '../../resources/js/score-editor-gabc.js';
import { formatDefaults, incipitSettings, resetFormatSettings } from '../../resources/js/score-editor-settings.js';

test('reports the fields and factory defaults of every format', () => {
    assert.equal(
        formatDefaults('gabc').defaults.staffSize,
        Number(gabcStaffSizeForStaffHeight(DEFAULT_STAFF_HEIGHT_MM).toFixed(4)),
    );
    assert.ok(formatDefaults('gabc').fields.includes('lyricSize'));
    assert.equal(formatDefaults('abc').defaults.abcPageWidth, 642.52);
    assert.ok(formatDefaults('abc').fields.includes('abcLyricSize'));
    assert.equal(formatDefaults('chordpro').defaults.chordproColumns, 1);
    assert.equal(formatDefaults('aretino').defaults.aretinoStaffSize, DEFAULT_STAFF_HEIGHT_MM);
    assert.deepEqual(formatDefaults('links-only'), { fields: [], defaults: {} });
});

test('renders an incipit at the factory defaults, whatever the score is set to', () => {
    // A projector score's own settings — oversized lyrics, a clef-less staff —
    // must not reach the thumbnail every listing shows.
    const abc = incipitSettings('abc');
    abcMixin().abcFields.forEach(field => {
        assert.equal(abc[field], abcMixin()[field], `abc.${field} is not the factory default`);
    });
    assert.equal(abc.abcLyricSize, Number(abcLyricSizeForPt(DEFAULT_LYRIC_SIZE_PT).toFixed(4)));
    assert.equal(abc.abcNoClef, false);
    assert.equal(abc.abcTranspose, 0);
    assert.equal(abc.abcPageRatio, 'paper');

    const gabc = incipitSettings('gabc');
    gabcMixin().gabcFields.forEach(field => {
        assert.equal(gabc[field], gabcMixin()[field], `gabc.${field} is not the factory default`);
    });
    assert.equal(gabc.pageRatio, 'paper');
});

test('hands out a fresh settings object each time', () => {
    const untouched = incipitSettings('gabc').lyricSize;
    const first = incipitSettings('gabc');
    first.lyricSize = 40;

    assert.equal(incipitSettings('gabc').lyricSize, untouched);
});

for (const [ratio, lyricSize, staffScale] of [['16/9', 70, 19.5], ['4/3', 58.5, 14.5], ['1/1', 52, 13.5]]) {
    test(`factory reset restores the ${ratio} projector layout without switching to paper`, () => {
        const component = {
            ...abcMixin(),
            abcPageRatio: ratio,
            abcLyricSize: 2,
            abcPageScale: 0.5,
            abcTranspose: 7,
            abcNoClef: false,
            lyricSize: 17,
        };

        resetFormatSettings(component, 'abc');

        assert.equal(component.abcPageRatio, ratio);
        assert.ok(Math.abs(ptForAbcLyricSize(component.abcLyricSize) - lyricSize) < 0.0001);
        assert.ok(Math.abs(staffHeightMmForAbcPageScale(component.abcPageScale) - staffScale) < 0.0001);
        assert.equal(component.abcTranspose, 0);
        assert.equal(component.abcNoClef, true);
        assert.equal(component.lyricSize, 17);
    });
}

test('reset keeps responsive mode while restoring paper sizes', () => {
    const component = { ...abcMixin(), abcPageRatio: 'responsive', abcLyricSize: 31 };

    resetFormatSettings(component, 'abc');

    assert.equal(component.abcPageRatio, 'responsive');
    assert.equal(component.abcLyricSize, abcMixin().abcLyricSize);
    assert.equal(component.abcPageWidth, abcMixin().abcPageWidth);
});

for (const ratio of ['16/9', '4/3', '1/1']) {
    test(`Aretino ${ratio} opens and resets with the projector font, staff and spacing`, () => {
        const initial = formatDefaults('aretino', ratio).defaults;
        const component = {
            ...initial,
            aretinoPageRatio: ratio,
            aretinoLyricSize: 60,
            aretinoStaffSize: 30,
            aretinoStaffGap: 8,
            abcLyricSize: 22,
        };

        resetFormatSettings(component, 'aretino');

        assert.equal(component.aretinoPageRatio, ratio);
        assert.equal(component.aretinoTextFont, "'Barlow Condensed'");
        assert.equal(component.aretinoLyricSize, 45);
        assert.equal(component.aretinoStaffSize, 13);
        assert.equal(component.aretinoHideRepeatClef, true);
        assert.equal(component.aretinoStaffGap, 1);
        assert.equal(component.aretinoLyricSize, initial.aretinoLyricSize);
        assert.equal(component.aretinoStaffSize, initial.aretinoStaffSize);
        assert.equal(component.abcLyricSize, 22);
    });

    test(`GABC ${ratio} retains screen-sized lyrics and staves on load and reset`, () => {
        const initial = formatDefaults('gabc', ratio).defaults;
        const component = { ...initial, pageRatio: ratio, lyricSize: 2, staffSize: 8, dropCaps: true };

        resetFormatSettings(component, 'gabc');

        assert.equal(component.pageRatio, ratio);
        assert.equal(component.lyricSize, 12);
        assert.equal(component.staffSize, 80);
        assert.equal(component.lyricSize, initial.lyricSize);
        assert.equal(component.staffSize, initial.staffSize);
        assert.equal(component.dropCaps, false);
    });
}

for (const [format, ratioField, lyricField, staffField] of [
    ['aretino', 'aretinoPageRatio', 'aretinoLyricSize', 'aretinoStaffSize'],
    ['gabc', 'pageRatio', 'lyricSize', 'staffSize'],
]) {
    for (const ratio of ['paper', 'responsive', 'auto']) {
        test(`${format} ${ratio} retains physical paper defaults`, () => {
            const paper = formatDefaults(format).defaults;
            const component = { ...paper, [ratioField]: ratio, [lyricField]: 70, [staffField]: 90 };

            resetFormatSettings(component, format);

            assert.equal(component[ratioField], ratio);
            assert.equal(component[lyricField], paper[lyricField]);
            assert.equal(component[staffField], paper[staffField]);
        });
    }
}

test('ChordPro reset restores font, columns and transposition without changing other formats', () => {
    const defaults = formatDefaults('chordpro').defaults;
    const component = {
        ...defaults,
        chordproFontSize: 96,
        chordproColumns: 3,
        chordproTranspose: 5,
        aretinoPageRatio: '16/9',
        aretinoLyricSize: 35,
    };

    resetFormatSettings(component, 'chordpro');

    assert.equal(component.chordproFontSize, defaults.chordproFontSize);
    assert.equal(component.chordproColumns, 1);
    assert.equal(component.chordproTranspose, 0);
    assert.equal(component.aretinoPageRatio, '16/9');
    assert.equal(component.aretinoLyricSize, 35);
});

for (const ratio of ['paper', 'responsive', '16/9', '4/3', '1/1']) {
    test(`${ratio} ABC and Aretino factory sizes lie on the half-step toolbar grid`, () => {
        const abc = formatDefaults('abc', ratio).defaults;
        const aretino = formatDefaults('aretino', ratio).defaults;
        const displayedSizes = [
            ptForAbcLyricSize(abc.abcLyricSize),
            staffHeightMmForAbcPageScale(abc.abcPageScale),
            aretino.aretinoLyricSize,
            aretino.aretinoStaffSize,
        ];

        for (const size of displayedSizes) {
            assert.ok(Math.abs(size * 2 - Math.round(size * 2)) < 0.001, `${size} is off the half-step grid`);
        }
    });
}
