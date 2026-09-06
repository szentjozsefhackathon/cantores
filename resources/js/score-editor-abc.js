import { diatarToAbc } from './diatar-to-abc.js';

const DEFAULT_ABC_FONT = 'EB Garamond';
const ABC_PAGE_WIDTH_MIN = 400;
const ABC_PAGE_WIDTH_MAX = 4000;
const ABC_PAGE_WIDTH_DEFAULT = 1700;

export function normalizeAbcPageWidth(value) {
    if (value === null || value === undefined || String(value).trim() === '') {
        return ABC_PAGE_WIDTH_DEFAULT;
    }

    const width = Number(value);
    if (!Number.isFinite(width)) {
        return ABC_PAGE_WIDTH_DEFAULT;
    }

    return Math.min(ABC_PAGE_WIDTH_MAX, Math.max(ABC_PAGE_WIDTH_MIN, Math.round(width)));
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
        abcVocalSpace: 0,
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
        abcVocalSpace: 0,
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
        abcVocalSpace: 0,
        abcNoClef: true,
        abcStemWidth: 1.4,
        abcStaffLineWidth: 1,
        abcZoom: 100,
    },
};

export { ABC_RATIO_DEFAULTS };

/**
 * The abc2svg preamble a settings bucket describes.
 *
 * Shared by the preview and the incipit so that a render at the format's
 * factory defaults differs from the on-screen one only in the settings handed
 * in, never in how they are turned into directives.
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

    return `%%fullsvg 1\n%%pagewidth ${pageWidth}px\n%%leftmargin 10px\n%%rightmargin 10px\n%%pagescale ${pageScale}\n${vocalfontLine}\n%%notespacingfactor ${settings.abcNoteSpacing}\n%%musicspace 0\n%%topspace 0\n%%staffsep ${settings.abcStaffSep}\n%%vocalspace ${settings.abcVocalSpace}\n${transposeLine}`;
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

/** Ink colour and the stroke widths of stems and staff lines, scoped by id. */
export function applyAbcSvgStyle(svg, svgId, settings) {
    svg.id = svgId;
    const style = document.createElementNS('http://www.w3.org/2000/svg', 'style');
    style.textContent = `#${svgId}{color:#000!important;fill:#000!important}#${svgId} .sW{stroke-width:${settings.abcStemWidth}!important}#${svgId} .slW{stroke-width:${settings.abcStaffLineWidth}!important}`;
    svg.appendChild(style);
}

export function abcMixin() {
    return {
        diatarSource: '',
        abcLyricFont: 'EB Garamond',
        abcLyricSize: 12,
        abcLyricBold: false,
        abcPageRatio: 'paper',
        abcPageScale: 2.3,
        abcPageWidth: 1700,
        abcNoteSpacing: 1.4,
        abcStaffSep: 46,
        abcVocalSpace: 10,
        abcNoClef: false,
        abcStemWidth: 0.7,
        abcStaffLineWidth: 0.7,
        abcZoom: 100,
        abcTranspose: 0,
        abcFields: ['abcLyricFont', 'abcLyricSize', 'abcLyricBold', 'abcPageRatio', 'abcPageScale', 'abcPageWidth', 'abcNoteSpacing', 'abcStaffSep', 'abcVocalSpace', 'abcNoClef', 'abcStemWidth', 'abcStaffLineWidth', 'abcZoom', 'abcTranspose'],

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
            const ratio = this.abcPageRatio;
            const isFixed = this.isFixedRatio(ratio);
            const isResponsive = this.isResponsiveRatio(ratio);
            const isPaper = this.isPaperRatio(ratio);
            const canvas = this.getVirtualCanvasSize('abc');
            const zoom = Number(this.abcZoom || 100) / 100;
            const paperWidth = canvas.width / 2;
            const zoomedPaperWidth = Math.round(paperWidth * zoom);
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
