import { ensureFontsLoaded } from './svg-fonts.js';
import { SLIDE_FIT_TOLERANCE, emptySlide, frameSlide } from './slide-frame.js';
import { SLIDE_PALETTES } from './slide-palette.js';
import { systemSlides } from './slide-systems.js';
import { softSegmentSources } from './score-editor-pages.js';
import { stackSvgs, viewBoxOf } from './svg-stack.js';
import { diatarToAbc } from './diatar-to-abc.js';
import { abcHitBoxAnnotator, concatMapped, editorDiagnostics, hitBoxesAtOffset, insertUnmapped, mappedLines, replaceMapped, softSegmentsMapped, splitPagesMapped, trackSource, unmapped } from './abc-source-map.js';
import {
    DEFAULT_LYRIC_SIZE_PT,
    DEFAULT_PAGE_WIDTH_MM,
    DEFAULT_STAFF_HEIGHT_MM,
    DEFAULT_TEXT_FONT,
    abcLyricSizeForPt,
    abcPageScaleForStaffHeight,
    mmToPx,
} from './booklet-geometry.js';

const DEFAULT_ABC_FONT = DEFAULT_TEXT_FONT;

/**
 * The page an ABC score is laid out on, in the user units abc2svg counts in —
 * which are CSS pixels at 96 dpi, so the page is a real 170 mm rather than the
 * 450 mm this nominally was before the editors were put on one scale.
 */
export const ABC_PAGE_WIDTH_DEFAULT = roundWidth(mmToPx(DEFAULT_PAGE_WIDTH_MM));

// Down to a business card and up to a poster; the floor used to sit at 400,
// which is wider than A4 now that the number means millimetres of real paper.
const ABC_PAGE_WIDTH_MIN = 100;
const ABC_PAGE_WIDTH_MAX = 3000;

/**
 * The tightest lyric-line advance that still reads: below half a line height
 * the stanzas start to collide. Anything under it is treated as unset, which
 * leaves abc2svg on its own 1.1.
 */
export const ABC_LYRIC_SKIP_MIN = 0.5;

/**
 * Allow the staff-to-first-lyric clearance to reach zero.
 * Negative values leave the renderer's default in place.
 */
export const ABC_LYRIC_FIRST_SKIP_MIN = 0;

/**
 * Two decimals of a pixel, so a width stated in whole millimetres survives the
 * trip through the engine's unit and back: 170 mm is 642.52 units, and rounding
 * that to a whole one would hand the toolbar 170.13 mm back.
 */
function roundWidth(px) {
    return Math.round(px * 100) / 100;
}

export function normalizeAbcPageWidth(value) {
    if (value === null || value === undefined || String(value).trim() === '') {
        return ABC_PAGE_WIDTH_DEFAULT;
    }

    const width = Number(value);
    if (!Number.isFinite(width)) {
        return ABC_PAGE_WIDTH_DEFAULT;
    }

    return Math.min(ABC_PAGE_WIDTH_MAX, Math.max(ABC_PAGE_WIDTH_MIN, roundWidth(width)));
}

// Per-ratio factory defaults for ABC. Keys match effectiveRatioKey() output.
// The 'paper' entry is the baseline; fixed-ratio entries override only the
// fields that need to differ from the paper defaults.
const ABC_RATIO_DEFAULTS = {
    '16/9': {
        abcLyricFont: 'Barlow Condensed',
        abcLyricSize: abcLyricSizeForPt(70),
        abcLyricBold: false,
        abcPageScale: abcPageScaleForStaffHeight(19.5),
        abcPageWidth: 1920,
        abcNoteSpacing: 1.1,
        abcStaffSep: 15,
        abcNoClef: true,
        abcStemWidth: 1.4,
        abcStaffLineWidth: 1,
        abcZoom: 100,
        // Chords at the lyrics' full size widen every bar they sit over, and
        // on a slide that is enough to push a line onto a second system.
        abcChordSize: 0.8,
    },
    '4/3': {
        abcLyricFont: 'Barlow Condensed',
        abcLyricSize: abcLyricSizeForPt(58.5),
        abcLyricBold: false,
        abcPageScale: abcPageScaleForStaffHeight(14.5),
        abcPageWidth: 1440,
        abcNoteSpacing: 1.1,
        abcStaffSep: 15,
        abcNoClef: true,
        abcStemWidth: 1.4,
        abcStaffLineWidth: 1,
        abcZoom: 100,
        // Chords at the lyrics' full size widen every bar they sit over, and
        // on a slide that is enough to push a line onto a second system.
        abcChordSize: 0.8,
    },
    '1/1': {
        abcLyricFont: 'Barlow Condensed',
        abcLyricSize: abcLyricSizeForPt(52),
        abcLyricBold: false,
        abcPageScale: abcPageScaleForStaffHeight(13.5),
        abcPageWidth: 1080,
        abcNoteSpacing: 1.1,
        abcStaffSep: 15,
        abcNoClef: true,
        abcStemWidth: 1.4,
        abcStaffLineWidth: 1,
        abcZoom: 100,
        // Chords at the lyrics' full size widen every bar they sit over, and
        // on a slide that is enough to push a line onto a second system.
        abcChordSize: 0.8,
    },
};

export { ABC_RATIO_DEFAULTS };

/**
 * Chord symbols are written in Hungarian: `H` is B natural, `B` is B flat.
 *
 * abc2svg parses and transposes chord roots as English note names (A–G), so a
 * Hungarian `"H"` would be left untransposed and a `"B"` parsed a semitone high.
 * This rewrites the roots — and the bass note after a slash — to English before
 * the engraver sees them: `H` → `B`, and a bare `B` → `Bb`.
 *
 * The way back — every English root, including whatever abc2svg's transposition
 * spelled it as, mapped onto the fixed palette `C D♭ D E♭ E F G♭ G A♭ A B♭ H` —
 * is the `huchords` hook in the `@cantoreshu/abc2svg` package.
 *
 * Only real chord symbols in the tune body are touched: information fields and
 * `%%` lines are skipped, and so are annotations (`"^text"`, `"_text"`, `"<"`,
 * `">"`, `"@"`).
 *
 * @param {string} source
 * @returns {string}
 */
export function hungarianChordsToAbc(source) {
    return hungarianChordsToAbcMapped(trackSource(source)).text;
}

/** hungarianChordsToAbc, keeping track of where each character came from. */
export function hungarianChordsToAbcMapped(mapped) {
    return concatMapped(...mappedLines(mapped).map((line) => {
        if (line.text.startsWith('%') || /^[A-Za-z][:+]/.test(line.text)) {
            return line;
        }

        return replaceMapped(line, /"([^"]*)"/g, (whole, inner) => {
            if (!inner || /^[_^<>@]/.test(inner)) {
                return whole;
            }

            const converted = inner
                .split(/(;)/)
                .map((part) => (part === ';' ? part : englishChordRoots(part)))
                .join('');

            return `"${converted}"`;
        });
    }));
}

/** Rewrites the root and the slashed bass note of one chord symbol to English. */
function englishChordRoots(chord) {
    return chord
        .split('/')
        .map((segment) => {
            const match = /^([A-H])(.*)$/.exec(segment);
            if (!match) {
                return segment;
            }

            const [, letter, rest] = match;
            if (letter === 'H') {
                return `B${rest}`;
            }
            if (letter === 'B' && !/^[b#♭♯]/.test(rest)) {
                return `Bb${rest}`;
            }

            return segment;
        })
        .join('/');
}

function abcVocalFont(settings) {
    const rawFont = (settings.abcLyricFont || '').trim();
    const family = /^[a-zA-Z0-9 .\-'&]+$/.test(rawFont) ? rawFont : DEFAULT_ABC_FONT;
    const pageScale = Number(settings.abcPageScale) > 0 ? Number(settings.abcPageScale) : 1;
    const rawLyricSize = Number(settings.abcLyricSize) > 0 ? Number(settings.abcLyricSize) : 12;
    const size = Number((rawLyricSize / pageScale * 3).toFixed(3));

    return { family, size, pageScale };
}

export async function ensureAbcFontsLoaded(settings) {
    const { family, size } = abcVocalFont(settings);

    await ensureFontsLoaded([family], size);
}

/**
 * The abc2svg preamble a settings bucket describes.
 *
 * Shared by the preview and the incipit so that a render at the format's
 * factory defaults differs from the on-screen one only in the settings handed
 * in, never in how they are turned into directives.
 *
 * `%%vocalspace` is pinned to 0 rather than exposed: it can only push the
 * lyrics further from the staff, never closer, so it is left out of the way and
 * `abcLyricFirstSkip` — counted from the bottom staff line — is the one knob
 * for the staff-to-lyrics gap.
 *
 * `scope` is the suffix abc2svg appends to every name it would otherwise share
 * between engravings — the `stdef` staff-line path, and the `f0` class carrying
 * the lyric face and its size. A document holding one engraving can leave it at
 * its default; one holding several — a projection's contact sheet, a booklet
 * page — must give each its own, or the last score's `.f0` rule sets the lyric
 * size of every score above it. See abcBlocks in booklet-render.js, which
 * learned this first.
 */
export function buildAbcPreamble(settings, pageWidth, scope = '1') {
    const { family, size: lyricSize, pageScale } = abcVocalFont(settings);
    const fontName = /[ .\-'&]/.test(family) ? `"${family}"` : family;
    const vocalfontLine = ['%%vocalfont', fontName, settings.abcLyricBold ? 'bold' : null, lyricSize].filter(Boolean).join(' ');
    const transposeSemitones = Number(settings.abcTranspose) || 0;
    const transposeLine = transposeSemitones !== 0 ? `%%transpose ${transposeSemitones}\n` : '';
    const lyricSkip = Number(settings.abcLyricSkip) || 0;
    const lyricSkipLine = lyricSkip >= ABC_LYRIC_SKIP_MIN ? `%%lyricskipfac ${lyricSkip}\n` : '';
    const lyricFirstSkip = Number(settings.abcLyricFirstSkip ?? NaN);
    const lyricFirstSkipLine = Number.isFinite(lyricFirstSkip) && lyricFirstSkip >= ABC_LYRIC_FIRST_SKIP_MIN ? `%%lyricfirstskipfac ${lyricFirstSkip}\n` : '';

    return `%%fullsvg ${scope}\n%%pagewidth ${pageWidth}px\n%%leftmargin 10px\n%%rightmargin 10px\n%%pagescale ${pageScale}\n${vocalfontLine}\n${abcChordFontLine(settings, fontName)}%%notespacingfactor ${settings.abcNoteSpacing}\n%%musicspace 0\n%%topspace 0\n%%staffsep ${settings.abcStaffSep}\n%%vocalspace 0\n${lyricFirstSkipLine}${lyricSkipLine}${transposeLine}${abcHideChordsLine(settings)}`;
}

/**
 * The class every chord symbol is drawn with, for a slide to colour them by.
 * abc2svg suffixes its own `f…` classes per engraving but leaves this one as
 * written, so a rule scoped by the drawing's id is enough to reach it.
 */
export const ABC_CHORD_CLASS = 'abc-chord';

/**
 * The chord symbols' face: the lyrics' own family, bold, and as large as the
 * lyrics times `abcChordSize` — what a chord sheet sets over its words.
 *
 * Left to itself abc2svg sets them in a regular sans-serif at 12 of its units,
 * which grow and shrink with the staff while the lyrics are sized against it:
 * a little smaller than the words on paper, and well under half their size on
 * a slide, where the staff is small beside the type. The size here is stated
 * the way `%%vocalfont`'s is, so the two stay in proportion at any staff
 * height.
 *
 * @param {object} settings
 * @param {string} fontName the lyric family, already quoted for a directive
 */
export function abcChordFontLine(settings, fontName) {
    const pageScale = Number(settings.abcPageScale) > 0 ? Number(settings.abcPageScale) : 1;
    const lyricSize = Number(settings.abcLyricSize) > 0 ? Number(settings.abcLyricSize) : 12;
    const chordSize = Number(settings.abcChordSize) > 0 ? Number(settings.abcChordSize) : 1;
    const size = Number((lyricSize * chordSize / pageScale * 3).toFixed(3));

    return `%%gchordfont ${fontName} bold ${size} class=${ABC_CHORD_CLASS}\n`;
}

/**
 * `%%pos gchord hidden` when the chord symbols are to be left out.
 *
 * A directive rather than a source edit, so the chords stay in the text for
 * whoever turns them back on — and it drops the room above the staff they
 * would have taken along with them, which blanking the symbols would not.
 */
export function abcHideChordsLine(settings) {
    return settings?.abcHideChords ? '%%pos gchord hidden\n' : '';
}

/**
 * Engraves an ABC source into SVG markup.
 *
 * The source carries its preamble, unless one is handed in separately. A
 * mapped source is engraved with a hit box over every symbol pointing back at
 * the editor's text (see abc-source-map.js); its preamble has to be separate,
 * since abc2svg counts a symbol's offset from the start of the text of its own
 * `tosvg` call.
 *
 * abc2svg's warnings about a mapped source are added to `report.diagnostics`
 * as ranges in `report.text`, the editor's text; the preamble's are the
 * preview's own business and only go to the console.
 *
 * @param {string|import('./abc-source-map.js').MappedSource} source
 * @param {string} [preamble]
 * @param {{text: string, diagnostics: object[]}|null} [report]
 */
export function renderAbcToSvgMarkup(source, preamble = '', report = null) {
    if (typeof abc2svg === 'undefined' || !abc2svg.Abc) {
        console.error('[score-editor] abc2svg not loaded');

        return '';
    }

    const mapped = typeof source === 'string' ? null : source;
    const svgChunks = [];
    const errs = [];
    const scoreErrors = [];
    let engravingScore = false;
    const user = {
        img_out: (str) => svgChunks.push(str),
        errmsg: (msg, l, c) => {
            errs.push(`${msg} (line ${l})`);
            if (engravingScore) {
                scoreErrors.push({ message: msg, line: l, col: c });
            }
        },
        read_file: () => null,
    };
    if (mapped) {
        user.anno_stop = abcHitBoxAnnotator(() => abc, mapped);
    }
    const abc = new abc2svg.Abc(user);
    if (preamble) {
        abc.tosvg('preamble', preamble);
    }
    engravingScore = true;
    abc.tosvg('score', mapped ? mapped.text : source);
    if (errs.length) {
        console.warn('[score-editor] abc2svg warnings:', errs);
    }
    if (mapped && report) {
        report.diagnostics.push(...editorDiagnostics(mapped, scoreErrors, report.text));
    }

    return svgChunks.join('\n');
}

/** abc2svg omits the viewBox on some pages; without it nothing can be scaled. */
export function ensureAbcSvgViewBox(svg, fallbackWidth) {
    if (svg.getAttribute('viewBox')) { return; }
    const w = parseFloat(svg.getAttribute('width')) || fallbackWidth;
    const h = parseFloat(svg.getAttribute('height')) || 0;
    if (h) {
        svg.setAttribute('viewBox', `0 0 ${w} ${h}`);
    }
}

/** abc2svg's own weight for a stem and for a staff line. */
const ABC_ENGINE_STROKE_WIDTH = 0.7;

/**
 * Stems and staff lines are only worth thickening for the projector ratios,
 * where a hairline dies on the beamer. On paper and in the responsive preview
 * they follow abc2svg, so a value saved before the knobs were taken off those
 * ratios cannot outlive the control that set it.
 *
 * `onSlide` says the engraving is a slide and settles the question without
 * asking the settings, because outside the score editor the settings cannot
 * answer it: a slide's bucket is resolved for a ratio the editor never stores
 * in it — `abcPageRatio` is a field of the editor, not of a saved layout — so a
 * projection read `paper` there and quietly drew every stem at the engine's
 * hairline, whatever the deck's knobs had been set to.
 */
export function abcStrokeWidths(settings, onSlide = false) {
    const isFixed = onSlide || Object.prototype.hasOwnProperty.call(ABC_RATIO_DEFAULTS, settings.abcPageRatio);
    const stem = Number(settings.abcStemWidth);
    const staffLine = Number(settings.abcStaffLineWidth);

    return {
        stem: isFixed && Number.isFinite(stem) ? stem : ABC_ENGINE_STROKE_WIDTH,
        staffLine: isFixed && Number.isFinite(staffLine) ? staffLine : ABC_ENGINE_STROKE_WIDTH,
    };
}

/**
 * Stem and staff-line widths, written onto the paths themselves.
 *
 * They cannot be a rule in a stylesheet. abc2svg draws a staff once and then
 * reuses it: the first full-width music line defines `<path id="stdef…"
 * class="slW">` and every later line whose staff comes out the same width is a
 * `<use>` of it. A `<use>` clones its referent into a shadow tree, which a
 * selector written from outside — `#slide .slW` — cannot reach, so the override
 * landed only on the lines abc2svg happened to draw inline: a short last line,
 * or one the justifier ended at another width. Which lines those are depends on
 * the page width, which is why one ratio thickened the last row and another the
 * last three.
 *
 * An inline style is part of the element and travels into the clone with it,
 * and outranks the engine's own unweighted `.slW` rule. The defs are walked
 * along with the body, since that is where the reused staff lives.
 */
export function applyAbcStrokeWidths(root, settings, onSlide = false) {
    const { stem, staffLine } = abcStrokeWidths(settings, onSlide);

    root.querySelectorAll('.sW').forEach((el) => el.style.setProperty('stroke-width', String(stem)));
    root.querySelectorAll('.slW').forEach((el) => el.style.setProperty('stroke-width', String(staffLine)));
}

let abcSlideSerial = 0;

/**
 * One page of an ABC score engraved onto one projector slide.
 *
 * abc2svg emits a document per music line, so a slide is the lines stacked and
 * then told they are a canvas. The viewBox is overwritten rather than grown:
 * the slide is the size it is, and music taller than it is clipped rather than
 * shrunk, and `overflows` says so. renderAbcSlides is what a projection asks,
 * and cuts such a page between its lines instead.
 *
 * The lyric face is waited for first: abc2svg measures every syllable against
 * whatever face the browser holds at that moment, and the widths it gets decide
 * whether a hyphen fits between two syllables at all — `%%lyrichyphenremove`
 * drops the hyphen and glues the syllables when the gap comes out too small. A
 * slide engraved before the face arrived therefore loses hyphens, which is what
 * a hard reload used to show until something forced a second render.
 *
 * @param {string|import('./abc-source-map.js').MappedSource} pageSource one page, preamble excluded; a mapped one gets hit boxes
 * @param {object} settings the resolved per-ratio settings bucket
 * @param {{width: number, height: number}} canvas
 * @param {{text: string, diagnostics: object[]}|null} [report] see renderAbcToSvgMarkup
 */
export async function renderAbcSlide(pageSource, settings, canvas, report = null) {
    await ensureAbcFontsLoaded(settings);

    return abcWholeSlide(engraveAbcLines(pageSource, settings, canvas, report), settings, canvas);
}

/**
 * One page of an ABC score engraved onto the projector slides it needs.
 *
 * A page that fits is the one slide renderAbcSlide draws. One that does not is
 * cut at its `%pagebreak?` suggestions and then between its music lines — see
 * slide-systems.js. Only the page as a whole reports into `report`: the pieces
 * are the same music again, and would underline every warning twice.
 *
 * @param {string|import('./abc-source-map.js').MappedSource} pageSource one page, suggestions left in
 * @return {Promise<Array<{svg: SVGElement, overflows: boolean, autoSplit: boolean}>>} never empty
 */
export async function renderAbcSlides(pageSource, settings, canvas, report = null) {
    await ensureAbcFontsLoaded(settings);

    const cut = typeof pageSource === 'string'
        ? softSegmentSources(pageSource, 'abc')
        : softSegmentsMapped(pageSource, 'abc');
    const whole = engraveAbcLines(cut.whole, settings, canvas, report);
    const slide = abcWholeSlide(whole, settings, canvas);

    if (!slide.overflows) { return [slide]; }

    const segments = cut.segments.length > 1
        ? cut.segments.map((segment, i) => abcSystemsOf(engraveAbcLines(segment, settings, canvas), i > 0))
        : [abcSystemsOf(whole, false)];

    return systemSlides(segments, canvas, (svg) => {
        applyAbcSvgStyle(svg, `abc-slide-s${++abcSlideSerial}`, settings, true);

        return svg;
    });
}

/**
 * Whether an abc2svg document draws a staff, or only words — the title and
 * whatever else abc2svg sets above the music in a document of its own.
 *
 * Read with the definitions taken out: under `%%fullsvg` every document carries
 * the staff's definition whether or not it uses it.
 *
 * @param {string} markup one document abc2svg emitted
 */
export function abcMarkupHasStaff(markup) {
    const body = String(markup ?? '').replace(/<defs[\s\S]*?<\/defs>/g, '');

    return /href="#stdef|class="slW"/.test(body);
}

/** The documents one source engraves to at the slide's width, one per music line. */
function engraveAbcLines(source, settings, canvas, report = null) {
    const scope = `s${++abcSlideSerial}`;
    const preamble = buildAbcPreamble(settings, canvas.width, scope);
    const markup = typeof source === 'string'
        ? renderAbcToSvgMarkup(preamble + source)
        : renderAbcToSvgMarkup(source, preamble, report);
    const host = document.createElement('div');
    host.innerHTML = markup;

    const fragments = Array.from(host.querySelectorAll('svg'));
    fragments.forEach((fragment) => ensureAbcSvgViewBox(fragment, canvas.width));

    return fragments;
}

function abcWholeSlide(fragments, settings, canvas) {
    if (fragments.length === 0) {
        return { svg: emptySlide(canvas), overflows: false, autoSplit: false };
    }

    // Nothing is hoisted into a sheet of the slide's own: `.sW` and `.slW` are
    // the two names abc2svg does not suffix, so a rule for them written here
    // would be a document-global one, and the next slide's copy would set this
    // slide's stroke widths. They are written on the paths instead.
    const { svg, height } = stackSvgs(fragments);

    applyAbcSvgStyle(svg, `abc-slide-s${++abcSlideSerial}`, settings, true);

    return { svg: frameSlide(svg, canvas), overflows: height > canvas.height + SLIDE_FIT_TOLERANCE, autoSplit: false };
}

/**
 * A run of abc2svg's documents as systems a slide can be cut between.
 *
 * A document without a staff is a title, and is kept with the music under it.
 * A piece after the first `%pagebreak?` carries the page's header again, title
 * and all, so its own title is dropped: it names the hymn a second time on a
 * slide that is still the same hymn.
 *
 * @param {SVGElement[]} fragments
 * @param {boolean} continuation
 * @return {import('./slide-systems.js').SlideSystem[]}
 */
function abcSystemsOf(fragments, continuation) {
    const systems = fragments.map((svg) => ({
        svg,
        height: viewBoxOf(svg).h,
        keepWithNext: !abcMarkupHasStaff(svg.outerHTML),
    }));

    const firstStaff = systems.findIndex((system) => !system.keepWithNext);

    return continuation && firstStaff > 0 ? systems.slice(firstStaff) : systems;
}

/**
 * Ink colour, scoped by id, and the stroke widths of stems and staff lines.
 *
 * Colour is a rule because it inherits, and an inherited property reaches the
 * content of a `<use>` however the selector is written. Stroke widths do not
 * survive that trip as a rule — see applyAbcStrokeWidths, which writes them on
 * the paths instead.
 *
 * On a slide the chord symbols take the chord colour a chord sheet's do, so
 * the guitarist finds them across the room. The music is ink on paper whatever
 * the deck's theme, so it is the light palette's chord colour; on paper they
 * stay black, for the photocopier.
 */
export function applyAbcSvgStyle(svg, svgId, settings, onSlide = false) {
    svg.id = svgId;
    const style = document.createElementNS('http://www.w3.org/2000/svg', 'style');
    style.textContent = `#${svgId}{color:#000!important;fill:#000!important}`
        + (onSlide ? `#${svgId} .${ABC_CHORD_CLASS}{fill:${SLIDE_PALETTES.light.chord}}` : '');
    svg.appendChild(style);
    applyAbcStrokeWidths(svg, settings, onSlide);
}

/**
 * The editor's text as the preview engraves it, page by page, each page still
 * knowing which character of the editor every one of its own came from.
 *
 * @param {string} content the editor's text
 * @param {string} ratio
 * @param {boolean} noClef
 * @return {import('./abc-source-map.js').MappedSource[]}
 */
export function prepareAbcPreviewPages(content, ratio, noClef = false) {
    let mapped = trackSource(content);
    if (!/^X:/m.test(mapped.text)) {
        mapped = concatMapped(unmapped('X:1\n'), mapped);
    }
    if (noClef) {
        const bar = /\|[|:\]]?/.exec(mapped.text);
        if (bar) {
            mapped = insertUnmapped(mapped, bar.index + bar[0].length, '[K:clef=none]');
        }
    }

    // The suggestions are left in for a fixed ratio's slides to spend, and
    // stripped by splitPages everywhere else.
    return splitPagesMapped(hungarianChordsToAbcMapped(mapped), 'abc', ratio, true);
}

function round(value, places) {
    const factor = 10 ** places;

    return Math.round(value * factor) / factor;
}

export function abcMixin() {
    return {
        diatarSource: '',
        abcLyricFont: DEFAULT_ABC_FONT,
        // Both stored in abc2svg's own units, set from the points and
        // millimetres the toolbar states: see abcLyricSizePt and
        // abcStaffHeightMm on the component.
        abcLyricSize: round(abcLyricSizeForPt(DEFAULT_LYRIC_SIZE_PT), 4),
        abcLyricBold: false,
        abcPageRatio: 'paper',
        abcPageScale: round(abcPageScaleForStaffHeight(DEFAULT_STAFF_HEIGHT_MM), 4),
        abcPageWidth: ABC_PAGE_WIDTH_DEFAULT,
        abcNoteSpacing: 1.4,
        // abc2svg reserves this much above every staff, the first one included,
        // so it is the air over the score as much as the air between its lines.
        // 46 was enough for two of them; 36 is enough for either. It is
        // multiplied by the page scale, so it shrank with the rest of the
        // drawing when the page became a real 170 mm.
        abcStaffSep: 36,
        // How far the first lyric baseline sits under the bottom staff line, as
        // a multiple of the lyric face's own ascent — so 1 sets the ascender
        // line on the staff line, in any face and at any size. Music that hangs
        // far enough below the staff still pushes it down. It replaces
        // %%vocalspace, which could only ever push the lyrics further down and
        // so had no way of pulling them closer than the stems hang.
        abcLyricFirstSkip: 1,
        // Vertical advance between stacked lyric lines, as a multiple of the
        // line's own height. abc2svg's own advance is 1.1 (see the
        // `lyricskipfac` support in @cantoreshu/abc2svg); a plain 1 sets
        // the stanzas one line height apart, which is what a hymnal does.
        // Anything below ABC_LYRIC_SKIP_MIN is left to the engine.
        abcLyricSkip: 1,
        abcNoClef: false,
        abcStemWidth: 0.7,
        abcStaffLineWidth: 0.7,
        // The preview is the printed page at 96 dpi, so 100 % is life size —
        // which is smaller than anyone wants to read off a monitor at arm's
        // length. The default magnifies it; the paper underneath is unchanged.
        abcZoom: 120,
        abcTranspose: 0,
        abcHideChords: false,
        // The chord symbols' size as a multiple of the lyrics'; see
        // abcChordFontLine.
        abcChordSize: 1,
        abcFields: ['abcLyricFont', 'abcLyricSize', 'abcLyricBold', 'abcPageRatio', 'abcPageScale', 'abcPageWidth', 'abcNoteSpacing', 'abcStaffSep', 'abcLyricFirstSkip', 'abcLyricSkip', 'abcNoClef', 'abcStemWidth', 'abcStaffLineWidth', 'abcZoom', 'abcTranspose', 'abcHideChords', 'abcChordSize'],

        normalizeAbcPageWidth,

        convertDiatarToAbc() {
            const abc = diatarToAbc(this.diatarSource);
            if (!abc.trim()) { return; }
            this.isContentUserModified = true;
            this.$wire.format = 'abc';
            this.$wire.content = abc;
            this.localContent = abc;
            this.syncAbcEditor();
            this.diatarSource = '';
            this.$flux.modal('diatar-import').close();
            this.$nextTick(() => this.scheduleRender());
        },

        _abcRenderVersion: 0,
        // The text the preview's hit boxes point into.
        _abcRenderedContent: null,

        async renderAbcPreview() {
            const version = ++this._abcRenderVersion;
            const container = this.$refs.abcPreview;
            if (!container) { return; }
            const content = this.localContent;
            if (!content || !content.trim()) {
                container.innerHTML = '';
                this.hasPages = false;
                this.setAbcDiagnostics([]);
                return;
            }
            const settings = Object.fromEntries(this.abcFields.map(field => [field, this[field]]));
            await ensureAbcFontsLoaded(settings);
            if (version !== this._abcRenderVersion || this.$refs.abcPreview !== container
                || this.$wire?.format !== 'abc' || this.localContent !== content
                || this.abcFields.some(field => this[field] !== settings[field])) { return; }

            container.innerHTML = '';
            this._abcRenderedContent = content;
            this.hasPages = false;
            if (typeof abc2svg === 'undefined' || !abc2svg.Abc) {
                console.error('[score-editor] abc2svg not loaded');
                return;
            }
            const ratio = this.abcPageRatio;
            const isFixed = this.isFixedRatio(ratio);
            const isResponsive = this.isResponsiveRatio(ratio);
            const isPaper = this.isPaperRatio(ratio);
            const canvas = this.getVirtualCanvasSize('abc');
            const zoom = Number(this.abcZoom || 100) / 100;
            // The canvas is the printed page in CSS pixels, so at 100 % the
            // preview is life size on a 96 dpi display; it used to be drawn at
            // half of a canvas twice as wide, which came to the same picture on
            // screen but hid what the numbers meant.
            const zoomedPaperWidth = Math.round(canvas.width * zoom);
            const availableWidth = Math.max(200, Math.round((container.clientWidth || zoomedPaperWidth) - 4));
            const paperPageWidth = normalizeAbcPageWidth(this.abcPageWidth);
            // Responsive lays the music out at whatever width the container
            // offers, wider than the paper page included; the zoom then only
            // decides how large the notes come out inside that width.
            const renderWidth = isResponsive
                ? availableWidth
                : isPaper
                    ? Math.round(zoomedPaperWidth * paperPageWidth / canvas.width)
                    : zoomedPaperWidth;
            const renderScale = zoomedPaperWidth / canvas.width;
            const pageWidth = isResponsive
                ? Math.max(200, Math.round(renderWidth / renderScale))
                : isPaper
                    ? paperPageWidth
                    : canvas.width;
            const preamble = buildAbcPreamble(this, pageWidth);
            const pages = prepareAbcPreviewPages(content, ratio, this.abcNoClef);
            const report = { text: content, diagnostics: [] };
            if (isFixed) {
                // The slides are engraved by the one copy of that code a
                // projection also draws, so a page that does not fit comes to
                // the same slides here as it will on the wall.
                const slides = [];
                for (const pageContent of pages) {
                    try {
                        slides.push(...await renderAbcSlides(pageContent, this, canvas, report));
                    } catch (e) {
                        console.error('[score-editor] abc2svg error:', e);
                    }
                }
                this.placePreviewSlides(container, slides, 'abc', ratio);
            } else {
                for (const [idx, pageContent] of pages.entries()) {
                    const pageEl = document.createElement('div');
                    if (isResponsive) {
                        pageEl.className = 'score-preview-page overflow-auto rounded-lg border border-zinc-200 bg-white dark:border-zinc-700';
                        pageEl.style.width = '100%';
                        pageEl.style.maxWidth = '100%';
                        pageEl.style.minWidth = '0';
                    } else {
                        pageEl.className = 'score-preview-page score-preview-paper overflow-auto';
                    }
                    container.appendChild(pageEl);
                    try {
                        pageEl.innerHTML = renderAbcToSvgMarkup(pageContent, preamble, report);
                        const svgs = Array.from(pageEl.querySelectorAll('svg'));
                        svgs.forEach((svg) => ensureAbcSvgViewBox(svg, pageWidth));
                        svgs.forEach((svg, svgIdx) => {
                            applyAbcSvgStyle(svg, `abc-svg-${idx}-${svgIdx}-${Date.now()}`, this);
                            svg.setAttribute('width', '100%');
                            svg.removeAttribute('height');
                            svg.style.display = 'block';
                        });
                        if (svgs.length > 0) {
                            const zoomFrame = document.createElement('div');
                            zoomFrame.style.width = renderWidth + 'px';
                            zoomFrame.style.maxWidth = 'none';
                            pageEl.replaceChildren(zoomFrame);
                            svgs.forEach(svg => zoomFrame.appendChild(svg));
                        }
                        if (svgs.length > 0) { this.hasPages = true; }
                    } catch (e) {
                        console.error('[score-editor] abc2svg error:', e);
                    }
                    this.addPageControls(pageEl, idx + 1, pages.length, 'abc', { fullscreen: false, ratio });
                }
            }
            // A slide is awaited, and the text may have moved on meanwhile;
            // the render that follows it brings its own warnings.
            if (version === this._abcRenderVersion && this.localContent === content) {
                this.setAbcDiagnostics(report.diagnostics);
            }
            this.updateAbcHighlight();
        },

        _abcDiagnostics: [],

        /**
         * Underlines abc2svg's warnings in the editor. Every page repeats the
         * header, so a warning about it comes once per page and is kept once.
         */
        setAbcDiagnostics(diagnostics) {
            const seen = new Set();
            this._abcDiagnostics = diagnostics.filter((diagnostic) => {
                const key = `${diagnostic.from}:${diagnostic.to}:${diagnostic.message}`;
                if (seen.has(key)) { return false; }
                seen.add(key);
                return true;
            });
            const editor = this.$refs.abcEditor;
            if (editor && customElements.get('abc2svg-editor')) {
                editor.diagnostics = this._abcDiagnostics;
            }
        },

        /** Marks the symbol the editor's caret stands in. */
        updateAbcHighlight() {
            if (this.$wire.format !== 'abc') { return; }
            const container = this.$refs.abcPreview;
            const selection = this.$refs.abcEditor?.selection;
            if (!container || !selection) { return; }
            const boxes = container.querySelectorAll('.abcsym');
            boxes.forEach((box) => box.classList.remove('sel'));
            // While a render is pending the boxes still point into the text as
            // it was; marking one of them would point at the wrong note.
            if (this._abcRenderedContent !== this.localContent) { return; }
            hitBoxesAtOffset(boxes, selection.from).forEach((box) => box.classList.add('sel'));
        },

        /** Puts the editor's selection on the source of the clicked symbol. */
        handleAbcPreviewClick(event) {
            const box = event.target.closest?.('.abcsym');
            const editor = this.$refs.abcEditor;
            if (!box || typeof editor?.setSelection !== 'function') { return; }
            if (this._abcRenderedContent !== this.localContent) { return; }
            editor.focus();
            // Its selectionchange marks the note.
            editor.setSelection(Number(box.dataset.start), Number(box.dataset.stop));
        },
    };
}
