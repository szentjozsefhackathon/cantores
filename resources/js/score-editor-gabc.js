import {
    DEFAULT_LYRIC_SIZE_PT,
    DEFAULT_PAGE_WIDTH_MM,
    DEFAULT_STAFF_HEIGHT_MM,
    DEFAULT_TEXT_FONT,
    gabcLyricSizeForPt,
    gabcStaffSizeForStaffHeight,
    mmToPx,
} from './booklet-geometry.js';

/**
 * The page a GABC score is laid out on, in the user units exsurge counts in —
 * CSS pixels at 96 dpi, so this is a real 170 mm sheet.
 *
 * The editor used to lay out on one fixed page and keep no width of its own,
 * which left the only way to rebreak a chant's lines being to change its staff
 * size. Two decimals of a unit, so a width typed in whole millimetres comes
 * back out of the toolbar whole; see the same in score-editor-abc.js.
 */
export const GABC_LAYOUT_WIDTH_DEFAULT = Math.round(mmToPx(DEFAULT_PAGE_WIDTH_MM) * 100) / 100;

/** Down to a business card and up to a poster, as in the ABC editor. */
const GABC_LAYOUT_WIDTH_MIN = 100;

const GABC_LAYOUT_WIDTH_MAX = 3000;

export function normalizeGabcLayoutWidth(value) {
    if (value === null || value === undefined || String(value).trim() === '') {
        return GABC_LAYOUT_WIDTH_DEFAULT;
    }

    const width = Number(value);
    if (!Number.isFinite(width)) {
        return GABC_LAYOUT_WIDTH_DEFAULT;
    }

    return Math.min(GABC_LAYOUT_WIDTH_MAX, Math.max(GABC_LAYOUT_WIDTH_MIN, Math.round(width * 100) / 100));
}

/** exsurge sizes everything off a 30-unit em; this is the conversion factor. */
const GABC_SIZE_UNIT = 100 / 30;

/**
 * Pours a settings bucket into a fresh exsurge context.
 *
 * Shared by the preview and the incipit, so a render at the format's factory
 * defaults differs from the on-screen one only in the settings handed in.
 */
export function configureChantContext(ctxt, settings) {
    ctxt.setFont(settings.lyricFont, Number(settings.lyricSize) * GABC_SIZE_UNIT * 1.3);
    ctxt.setGlyphScaling((Number(settings.staffSize) / 100) * GABC_SIZE_UNIT / 16);
    if (Number(settings.minLyricWordSpacing) > 0) {
        ctxt.minLyricWordSpacing = Number(settings.minLyricWordSpacing) * GABC_SIZE_UNIT;
    }
    if (Number(settings.hyphenWidth) > 0) {
        ctxt.hyphenWidth = Number(settings.hyphenWidth) * GABC_SIZE_UNIT;
    }
    ctxt.condensingTolerance = Number(settings.condensingTolerance);
    ctxt.spaceBetweenSystems = Number(settings.spaceBetweenSystems);
    ctxt.minSpaceBelowStaff = Number(settings.minSpaceBelowStaff);

    return ctxt;
}

/**
 * Engraves one GABC page into SVG markup. exsurge lays out in two asynchronous
 * steps, which this wraps into a single promise.
 */
export function renderGabcToSvgMarkup(source, settings, layoutWidth) {
    return new Promise((resolve, reject) => {
        try {
            const ctxt = configureChantContext(new exsurge.ChantContext(), settings);
            const mappings = exsurge.Gabc.createMappingsFromSource(ctxt, source);
            const score = new exsurge.ChantScore(ctxt, mappings, !!settings.dropCaps);
            score.performLayoutAsync(ctxt, () => {
                score.layoutChantLines(ctxt, layoutWidth, () => {
                    try {
                        resolve(score.createSvg(ctxt));
                    } catch (e) {
                        reject(e);
                    }
                });
            });
        } catch (e) {
            reject(e);
        }
    });
}

function round(value, places) {
    const factor = 10 ** places;

    return Math.round(value * factor) / factor;
}

/** Projector sizes retained for the unchanged 1920-pixel canvas. */
export const GABC_SCREEN_DEFAULTS = {
    lyricSize: 12,
    staffSize: 80,
    zoom: 90,
};

export function gabcMixin() {
    return {
        // The preview is the printed page at 96 dpi, so 100 is life size and a
        // zoom is what it says it is. The default sits above it because life
        // size on a monitor at arm's length is smaller than anyone wants to
        // work in; it magnifies the preview and nothing else.
        zoom: 120,
        // Stored in exsurge's own units, set from the millimetres and points the
        // toolbar states: see lyricSizePt and staffSizeMm on the component.
        lyricSize: round(gabcLyricSizeForPt(DEFAULT_LYRIC_SIZE_PT), 4),
        staffSize: round(gabcStaffSizeForStaffHeight(DEFAULT_STAFF_HEIGHT_MM), 4),
        minLyricWordSpacing: 0,
        hyphenWidth: 0,
        condensingTolerance: 0.9,
        spaceBetweenSystems: 0,
        minSpaceBelowStaff: 0,
        pageRatio: 'paper',
        // The width the chant's lines are broken at, in exsurge's user units;
        // the toolbar states it in millimetres. See gabcLayoutWidthMm.
        gabcLayoutWidth: GABC_LAYOUT_WIDTH_DEFAULT,
        dropCaps: false,
        lyricFont: `'${DEFAULT_TEXT_FONT}'`,
        gabcFields: ['zoom', 'lyricSize', 'staffSize', 'gabcLayoutWidth', 'dropCaps', 'lyricFont', 'minLyricWordSpacing', 'hyphenWidth', 'condensingTolerance', 'spaceBetweenSystems', 'minSpaceBelowStaff'],

        normalizeGabcLayoutWidth,

        renderGabcPreview() {
            const container = this.$refs.preview;
            if (!container) { return; }
            container.innerHTML = '';
            this.hasPages = false;
            if (!window.exsurge) { return; }
            const content = this.localContent;
            if (!content || !content.trim()) { return; }
            const ratio = this.pageRatio;
            const isFixed = this.isFixedRatio(ratio);
            const isResponsive = this.isResponsiveRatio(ratio);
            const canvas = this.getVirtualCanvasSize('gabc');
            const zoom = Number(this.zoom || 100) / 100;
            // The canvas is the printed page in CSS pixels, so at 100 % the
            // preview is life size on a 96 dpi display; it used to be drawn at
            // half of a canvas twice as wide, which came to the same picture on
            // screen but hid what the numbers meant.
            const zoomedPaperWidth = Math.round(canvas.width * zoom);
            const availableWidth = Math.max(200, Math.round((container.clientWidth || zoomedPaperWidth) - 4));
            const renderWidth = isResponsive
                ? Math.min(zoomedPaperWidth, availableWidth)
                : zoomedPaperWidth;
            const renderScale = zoomedPaperWidth / canvas.width;
            const layoutWidth = isResponsive
                ? Math.max(200, Math.round(renderWidth / renderScale))
                : canvas.width;
            const pages = this.splitPages(content, 'gabc', ratio);
            const pageEls = pages.map((_, idx) => {
                const pageEl = document.createElement('div');
                if (isFixed) {
                    this.applyProjectorFrame(pageEl, ratio);
                    pageEl.className = 'score-preview-page overflow-auto bg-white';
                    pageEl.style.width = '100%';
                    pageEl.style.maxWidth = '100%';
                    pageEl.style.minWidth = '0';
                } else if (isResponsive) {
                    pageEl.className = 'score-preview-page overflow-auto rounded-lg border border-zinc-200 bg-white dark:border-zinc-700';
                    pageEl.style.width = '100%';
                    pageEl.style.maxWidth = '100%';
                    pageEl.style.minWidth = '0';
                } else {
                    pageEl.className = 'score-preview-page score-preview-paper overflow-auto';
                }
                container.appendChild(pageEl);
                return pageEl;
            });
            pages.forEach((pageSource, idx) => {
                const pageEl = pageEls[idx];
                renderGabcToSvgMarkup(pageSource, this, layoutWidth).then((markup) => {
                    let html = markup;
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'image/svg+xml');
                    const svg = doc.querySelector('svg');
                    let contentH = 0;
                    if (svg) {
                        const h = svg.getAttribute('height');
                        contentH = h ? parseFloat(h) : 0;
                        const viewBoxHeight = isFixed ? canvas.height : contentH;
                        svg.setAttribute('viewBox', '0 0 ' + layoutWidth + ' ' + viewBoxHeight);
                        svg.setAttribute('width', '100%');
                        svg.removeAttribute('height');
                        svg.setAttribute('preserveAspectRatio', 'xMidYMin meet');
                        svg.style.display = 'block';
                        svg.style.overflow = 'hidden';
                        if (isFixed) {
                            svg.style.width = '100%';
                            svg.style.height = '100%';
                            svg.style.maxWidth = 'none';
                        } else {
                            svg.style.width = '100%';
                            svg.style.maxWidth = 'none';
                        }
                        html = new XMLSerializer().serializeToString(svg);
                    }
                    pageEl.innerHTML = html;
                    if (!isFixed) {
                        const svgEl = pageEl.querySelector('svg');
                        if (svgEl) {
                            const zoomFrame = document.createElement('div');
                            zoomFrame.style.width = renderWidth + 'px';
                            zoomFrame.style.maxWidth = 'none';
                            pageEl.replaceChildren(zoomFrame);
                            zoomFrame.appendChild(svgEl);
                        }
                    }
                    if (isFixed && canvas.height && contentH > canvas.height + 2) {
                        this.appendClipWarning(pageEl);
                    }
                    this.hasPages = true;
                    this.addPageControls(pageEl, idx + 1, pages.length, 'gabc', { fullscreen: isFixed, ratio });
                }).catch((e) => {
                    console.error('[score-editor] exsurge error:', e);
                });
            });
        },
    };
}
