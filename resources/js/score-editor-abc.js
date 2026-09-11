import { diatarToAbc } from './diatar-to-abc.js';
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
 * The tightest staff-to-first-lyric advance we let anyone ask for. The factor
 * is the lyric baseline's distance below the bottom staff line, counted in the
 * lyric face's own ascent, so 1 puts the ascender line on the staff line and
 * anything under it reaches up into the staff — which is the point of the knob,
 * but half an ascent is as far as it stays music. Anything under the floor is
 * treated as unset, which leaves abc2svg on its own 1.1.
 */
export const ABC_LYRIC_FIRST_SKIP_MIN = 0.5;

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
        abcLyricSize: 31,
        abcLyricBold: false,
        abcPageScale: 3.1,
        abcPageWidth: 1920,
        abcNoteSpacing: 1.1,
        abcStaffSep: 15,
        abcNoClef: true,
        abcStemWidth: 1.4,
        abcStaffLineWidth: 1,
        abcZoom: 100,
    },
    '4/3': {
        abcLyricFont: 'Barlow Condensed',
        abcLyricSize: 26,
        abcLyricBold: false,
        abcPageScale: 2.3,
        abcPageWidth: 1440,
        abcNoteSpacing: 1.1,
        abcStaffSep: 15,
        abcNoClef: true,
        abcStemWidth: 1.4,
        abcStaffLineWidth: 1,
        abcZoom: 100,
    },
    '1/1': {
        abcLyricFont: 'Barlow Condensed',
        abcLyricSize: 23,
        abcLyricBold: false,
        abcPageScale: 2.1,  
        abcPageWidth: 1080,
        abcNoteSpacing: 1.1,
        abcStaffSep: 15,
        abcNoClef: true,
        abcStemWidth: 1.4,
        abcStaffLineWidth: 1,
        abcZoom: 100,
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
    return source
        .split('\n')
        .map((line) => {
            if (line.startsWith('%') || /^[A-Za-z][:+]/.test(line)) {
                return line;
            }

            return line.replace(/"([^"]*)"/g, (whole, inner) => {
                if (!inner || /^[_^<>@]/.test(inner)) {
                    return whole;
                }

                const converted = inner
                    .split(/(;)/)
                    .map((part) => (part === ';' ? part : englishChordRoots(part)))
                    .join('');

                return `"${converted}"`;
            });
        })
        .join('\n');
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
 */
export function buildAbcPreamble(settings, pageWidth) {
    const rawFont = (settings.abcLyricFont || '').trim();
    const safeFont = /^[a-zA-Z0-9 .\-'&]+$/.test(rawFont) ? rawFont : DEFAULT_ABC_FONT;
    const fontName = /[ .\-'&]/.test(safeFont) ? `"${safeFont}"` : safeFont;
    const pageScale = Number(settings.abcPageScale) > 0 ? Number(settings.abcPageScale) : 1;
    const rawLyricSize = Number(settings.abcLyricSize) > 0 ? Number(settings.abcLyricSize) : 12;
    const lyricSize = Number((rawLyricSize / pageScale * 3).toFixed(3));
    const vocalfontLine = ['%%vocalfont', fontName, settings.abcLyricBold ? 'bold' : null, lyricSize].filter(Boolean).join(' ');
    const transposeSemitones = Number(settings.abcTranspose) || 0;
    const transposeLine = transposeSemitones !== 0 ? `%%transpose ${transposeSemitones}\n` : '';
    const lyricSkip = Number(settings.abcLyricSkip) || 0;
    const lyricSkipLine = lyricSkip >= ABC_LYRIC_SKIP_MIN ? `%%lyricskipfac ${lyricSkip}\n` : '';
    const lyricFirstSkip = Number(settings.abcLyricFirstSkip) || 0;
    const lyricFirstSkipLine = lyricFirstSkip >= ABC_LYRIC_FIRST_SKIP_MIN ? `%%lyricfirstskipfac ${lyricFirstSkip}\n` : '';

    return `%%fullsvg 1\n%%pagewidth ${pageWidth}px\n%%leftmargin 10px\n%%rightmargin 10px\n%%pagescale ${pageScale}\n${vocalfontLine}\n%%notespacingfactor ${settings.abcNoteSpacing}\n%%musicspace 0\n%%topspace 0\n%%staffsep ${settings.abcStaffSep}\n%%vocalspace 0\n${lyricFirstSkipLine}${lyricSkipLine}${transposeLine}`;
}

/** Engraves an ABC source (preamble included) into SVG markup. */
export function renderAbcToSvgMarkup(source) {
    if (typeof abc2svg === 'undefined' || !abc2svg.Abc) {
        console.error('[score-editor] abc2svg not loaded');

        return '';
    }

    const svgChunks = [];
    const errs = [];
    const user = {
        img_out: (str) => svgChunks.push(str),
        errmsg: (msg, l) => errs.push(`${msg} (line ${l})`),
        read_file: () => null,
    };
    const abc = new abc2svg.Abc(user);
    abc.tosvg('score', source);
    if (errs.length) {
        console.warn('[score-editor] abc2svg warnings:', errs);
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
 */
export function abcStrokeWidths(settings) {
    const isFixed = Object.prototype.hasOwnProperty.call(ABC_RATIO_DEFAULTS, settings.abcPageRatio);

    return {
        stem: isFixed ? settings.abcStemWidth : ABC_ENGINE_STROKE_WIDTH,
        staffLine: isFixed ? settings.abcStaffLineWidth : ABC_ENGINE_STROKE_WIDTH,
    };
}

/** Ink colour and the stroke widths of stems and staff lines, scoped by id. */
export function applyAbcSvgStyle(svg, svgId, settings) {
    svg.id = svgId;
    const { stem, staffLine } = abcStrokeWidths(settings);
    const style = document.createElementNS('http://www.w3.org/2000/svg', 'style');
    style.textContent = `#${svgId}{color:#000!important;fill:#000!important}#${svgId} .sW{stroke-width:${stem}!important}#${svgId} .slW{stroke-width:${staffLine}!important}`;
    svg.appendChild(style);
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
        abcFields: ['abcLyricFont', 'abcLyricSize', 'abcLyricBold', 'abcPageRatio', 'abcPageScale', 'abcPageWidth', 'abcNoteSpacing', 'abcStaffSep', 'abcLyricFirstSkip', 'abcLyricSkip', 'abcNoClef', 'abcStemWidth', 'abcStaffLineWidth', 'abcZoom', 'abcTranspose'],

        normalizeAbcPageWidth,

        convertDiatarToAbc() {
            const abc = diatarToAbc(this.diatarSource);
            if (!abc.trim()) { return; }
            this.isContentUserModified = true;
            this.$wire.format = 'abc';
            this.$wire.content = abc;
            this.localContent = abc;
            this.diatarSource = '';
            this.$flux.modal('diatar-import').close();
            this.$nextTick(() => this.scheduleRender());
        },

        renderAbcPreview() {
            const container = this.$refs.abcPreview;
            if (!container) { return; }
            container.innerHTML = '';
            this.hasPages = false;
            let content = this.localContent;
            if (!content || !content.trim()) { return; }
            if (typeof abc2svg === 'undefined' || !abc2svg.Abc) {
                console.error('[score-editor] abc2svg not loaded');
                return;
            }
            if (!/^X:/m.test(content)) {
                content = 'X:1\n' + content;
            }
            if (this.abcNoClef) {
                content = content.replace(/\|[|:\]]?/, '$&[K:clef=none]');
            }
            content = hungarianChordsToAbc(content);
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
            const renderWidth = isResponsive
                ? Math.min(zoomedPaperWidth, availableWidth)
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
            const pages = this.splitPages(content, 'abc', ratio);
            pages.forEach((pageContent, idx) => {
                const pageEl = document.createElement('div');
                if (isFixed) {
                    this.applyProjectorFrame(pageEl, ratio);
                } else if (isResponsive) {
                    pageEl.className = 'score-preview-page overflow-auto rounded-lg border border-zinc-200 bg-white dark:border-zinc-700';
                    pageEl.style.width = '100%';
                    pageEl.style.maxWidth = '100%';
                    pageEl.style.minWidth = '0';
                } else {
                    pageEl.className = 'score-preview-page score-preview-paper overflow-auto';
                }
                container.appendChild(pageEl);
                try {
                    pageEl.innerHTML = renderAbcToSvgMarkup(preamble + pageContent);
                    const svgs = Array.from(pageEl.querySelectorAll('svg'));
                    svgs.forEach((svg) => ensureAbcSvgViewBox(svg, pageWidth));
                    if (isFixed && svgs.length > 0) {
                        const { svg: merged, totalHeight } = this.mergeAbcSvgsToElement(svgs);
                        applyAbcSvgStyle(merged, `abc-svg-${idx}-${Date.now()}`, this);
                        merged.setAttribute('viewBox', `0 0 ${canvas.width} ${canvas.height}`);
                        merged.setAttribute('width', '100%');
                        merged.setAttribute('preserveAspectRatio', 'xMidYMin meet');
                        merged.style.display = 'block';
                        merged.style.width = '100%';
                        merged.style.height = '100%';
                        pageEl.innerHTML = '';
                        pageEl.appendChild(merged);
                        if (totalHeight > canvas.height + 2) {
                            this.appendClipWarning(pageEl);
                        }
                        this.hasPages = true;
                    } else {
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
                    }
                } catch (e) {
                    console.error('[score-editor] abc2svg error:', e);
                }
                this.addPageControls(pageEl, idx + 1, pages.length, 'abc', { fullscreen: isFixed, ratio });
            });
        },
    };
}
