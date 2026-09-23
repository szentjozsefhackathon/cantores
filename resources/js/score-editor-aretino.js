import { renderAretino, splitRowSVGs } from '@aretino-chant/core';
import { softSegmentSources, splitPages as splitRatioPages } from './score-editor-pages.js';
import { SLIDE_FIT_TOLERANCE, emptySlide, fitSlide, parseSvg, viewBoxOf } from './slide-frame.js';
import { systemSlides } from './slide-systems.js';
import { svgHeight } from './svg-slice.js';
import { gabcToAretino } from '@aretino-chant/gabc2aretino';
import { guidoToAretino, guidoTextToAretino } from '@aretino-chant/guido2aretino';

import { ensureFontsLoaded } from './svg-fonts.js';
import { DEFAULT_LYRIC_SIZE_PT, DEFAULT_PAGE_WIDTH_MM, DEFAULT_STAFF_HEIGHT_MM, DEFAULT_TEXT_FONT } from './booklet-geometry.js';

const ARETINO_STAFF_SIZE_CALIBRATION = 1.3;

/**
 * Builds an Aretino source string from Guido TTF notes and lyrics, which the
 * guido2aretino package exposes as two separate converters. The lyrics are
 * appended as a `w:` line when present.
 */
export function buildAretinoFromGuido(notesSource, textSource) {
    const notes = guidoToAretino(notesSource ?? '');
    const text = guidoTextToAretino(textSource ?? '');
    if (notes.trim() === '' && text.trim() === '') {
        return '';
    }

    return text.trim() === '' ? `${notes}\n` : `${notes}\nw: ${text}\n`;
}

// Fixed pixel canvas for each fixed-ratio (projector screen) mode.
// All share the same height so switching ratio keeps the screen size constant.
const ARETINO_SCREEN_CANVAS = {
    '16/9': { width: 960, height: 540 },
    '4/3':  { width: 720, height: 540 },
    '1/1':  { width: 540, height: 540 },
};

/** The same readable type, staff size and spacing on every projector ratio. */
export const ARETINO_RATIO_DEFAULTS = Object.fromEntries(
    Object.keys(ARETINO_SCREEN_CANVAS).map(ratio => [ratio, {
        aretinoTextFont: "'Barlow Condensed'",
        aretinoLyricSize: 45,
        aretinoStaffSize: 13,
        aretinoStaffGap: 1,
        aretinoZoom: 100,
        aretinoHideRepeatClef: true,
    }]),
);

export function aretinoProjectorOptions(ratio) {
    const canvas = ARETINO_SCREEN_CANVAS[ratio];

    return { width: canvas.width, canvasHeight: canvas.height, dpi: 96 };
}

/**
 * One page of an Aretino chant engraved onto one projector slide.
 *
 * Aretino is the one engine that is told the box it is drawing into, and it
 * answers running out of room in two ways. Handed a canvas height it holds that
 * height: a chant taller than the canvas is cut off at the bottom by its own
 * viewBox, and that is measured here by engraving it once more without the
 * height, which is the only way to see how tall it would have been. A word
 * running past the right edge *widens* the viewBox instead — 960 becomes 1236
 * and the slide is no longer 16:9 — so the music is letterboxed down to fit the
 * screen, and the engine's own viewBox is kept rather than restated: rewriting
 * it to the canvas would crop the very music that grew. Either is `overflows`.
 *
 * renderAretinoSlides is what a projection asks, and cuts a page that is too
 * tall between its staff rows instead.
 *
 * The lyric face is waited for first, for the reason ensureFontsLoaded()
 * explains: Aretino places every syllable at a measured width, and a face the
 * browser has not fetched yet measures as the fallback.
 */
export async function renderAretinoSlide(pageSource, settings, canvas, ratio) {
    await ensureFontsLoaded([settings.aretinoTextFont], Number(settings.aretinoLyricSize));

    const height = aretinoContentHeight(engraveAretinoSlide(pageSource, settings, ratio, false));

    return aretinoWholeSlide(pageSource, settings, canvas, ratio, height);
}

/**
 * One page of an Aretino chant engraved onto the projector slides it needs.
 *
 * A page that fits is the one slide renderAretinoSlide draws. One too tall for
 * it is engraved again with a clef on every row, and cut at its `%pagebreak?`
 * suggestions and then between its staff rows, which splitRowSVGs hands over
 * one document apiece — see slide-systems.js.
 *
 * @param {string} pageSource one page, suggestions left in
 * @return {Promise<Array<{svg: SVGElement, overflows: boolean, autoSplit: boolean}>>} never empty
 */
export async function renderAretinoSlides(pageSource, settings, canvas, ratio) {
    await ensureFontsLoaded([settings.aretinoTextFont], Number(settings.aretinoLyricSize));

    const cut = softSegmentSources(pageSource, 'aretino');
    const free = engraveAretinoSlide(cut.whole, settings, ratio, false);
    const height = aretinoContentHeight(free);

    if (height <= canvas.height + SLIDE_FIT_TOLERANCE) {
        return [aretinoWholeSlide(cut.whole, settings, canvas, ratio, height)];
    }

    // Cut between rows, any row may open a slide, and a slide that opens on a
    // staff with no clef cannot be sung from. The clef the projector hides on a
    // repeated row is therefore drawn on every row of a page that is cut, as
    // abc2svg and exsurge draw theirs.
    const everyClef = { ...settings, aretinoHideRepeatClef: false };
    const markups = cut.segments.map((segment) => engraveAretinoSlide(segment, everyClef, ratio, false));

    return systemSlides(markups.map((markup) => aretinoRows(markup)), canvas);
}

/**
 * How tall an engraving made without a canvas height came to — what the rows
 * need, before any canvas pads them out.
 *
 * Read off the marker the renderer leaves after its last row where there is
 * one, since the viewBox also counts whatever it grew upwards by.
 *
 * @param {string} markup from renderAretino, without `canvasHeight`
 */
export function aretinoContentHeight(markup) {
    const end = String(markup ?? '').match(/<!--\s*aretino-rows-end\s+(-?[\d.]+)\s*-->/);

    if (end) { return parseFloat(end[1]); }

    return svgHeight(String(markup ?? ''));
}

/**
 * The Aretino engraving of one source at one projector ratio.
 *
 * @param {boolean} fixedHeight hold the canvas height, as a slide is drawn; or
 *        let the chant be as tall as it is, as it is measured and cut
 */
export function engraveAretinoSlide(source, settings, ratio, fixedHeight) {
    const zoom = Number(settings.aretinoZoom) > 0 ? Number(settings.aretinoZoom) / 100 : 1;
    const { canvasHeight, ...projector } = aretinoProjectorOptions(ratio);

    return renderAretino(source, {
        ...projector,
        ...(fixedHeight ? { canvasHeight } : {}),
        zoom,
        staffSpaceMm: Number(settings.aretinoStaffSize) / 4.0,
        lyricSize: Number(settings.aretinoLyricSize),
        textFont: settings.aretinoTextFont,
        staffGap: Number(settings.aretinoStaffGap),
        hideRepeatClef: !!settings.aretinoHideRepeatClef,
    });
}

function aretinoWholeSlide(pageSource, settings, canvas, ratio, contentHeight) {
    const svg = parseSvg(engraveAretinoSlide(pageSource, settings, ratio, true));

    if (svg === null) {
        return { svg: emptySlide(canvas), overflows: false, autoSplit: false };
    }

    return {
        svg: fitSlide(svg),
        overflows: viewBoxOf(svg).width > canvas.width + SLIDE_FIT_TOLERANCE
            || contentHeight > canvas.height + SLIDE_FIT_TOLERANCE,
        autoSplit: false,
    };
}

/** An engraving's staff rows, as systems a slide can be cut between. */
function aretinoRows(markup) {
    const rows = splitRowSVGs(markup) ?? [markup];

    return rows.map((row) => ({ svg: row, height: svgHeight(row) }));
}

export function aretinoMixin() {
    return {
        aretinoTextFont: `'${DEFAULT_TEXT_FONT}'`,
        aretinoLyricSize: DEFAULT_LYRIC_SIZE_PT,
        aretinoStaffSize: DEFAULT_STAFF_HEIGHT_MM,
        // The preview is the printed size, so this is a magnifying glass over
        // it rather than the size the score is engraved at — and life size on a
        // monitor at arm's length is smaller than anyone wants to work in.
        aretinoZoom: 120,
        aretinoPageRatio: 'paper',
        aretinoStaffWidth: DEFAULT_PAGE_WIDTH_MM,
        aretinoStaffGap: 2.5,
        aretinoHideRepeatClef: false,
        aretinoFields: ['aretinoTextFont', 'aretinoLyricSize', 'aretinoStaffSize', 'aretinoZoom', 'aretinoPageRatio', 'aretinoStaffWidth', 'aretinoStaffGap', 'aretinoHideRepeatClef'],
        gabcSource: '',
        guidoNotesSource: '',
        guidoTextSource: '',
        _aretinoResizeObserver: null,
        _aretinoResizeTimer: null,
        _aretinoPreviewDirty: false,

        convertGabcToAretino() {
            const aretino = gabcToAretino(this.gabcSource);
            if (aretino.trim() === '') { return; }
            this.$wire.format = 'aretino';
            this.setEditorContent(aretino, { modified: true });
            this.gabcSource = '';
            this.$flux.modal('gabc-import').close();
        },

        convertGuidoToAretino() {
            const aretino = buildAretinoFromGuido(this.guidoNotesSource, this.guidoTextSource);
            if (aretino.trim() === '') { return; }
            this.$wire.format = 'aretino';
            this.setEditorContent(aretino, { modified: true });
            this.guidoNotesSource = '';
            this.guidoTextSource = '';
            this.$flux.modal('guido-import').close();
        },

        initAretinoResizeObserver() {
            const container = this.$refs.aretinoPreview;
            if (!container || !window.ResizeObserver) { return; }
            this._aretinoResizeObserver = new ResizeObserver((entries) => {
                if (this.aretinoPageRatio !== 'responsive') { return; }
                const width = entries[0]?.contentRect?.width ?? 0;
                if (width === 0) { return; }
                clearTimeout(this._aretinoResizeTimer);
                this._aretinoResizeTimer = setTimeout(() => this.renderAretinoPreview(), 300);
            });
            this._aretinoResizeObserver.observe(container);
        },

        async renderAretinoPreview() {
            const container = this.$refs.aretinoPreview;
            if (!container) { return; }
            container.innerHTML = '';
            this.hasPages = false;
            const content = this.localContent;
            if (!content || !content.trim()) { this._aretinoPreviewDirty = false; return; }

            await ensureFontsLoaded([this.aretinoTextFont], Number(this.aretinoLyricSize));

            const ratio = this.aretinoPageRatio;
            // 'auto' is a legacy alias for 'paper' from before the rename.
            const isPaper = ratio === 'paper' || ratio === 'auto';
            const isResponsive = ratio === 'responsive';
            const isFixedRatio = !isPaper && !isResponsive; // '16/9', '4/3', '1/1'

            const virtualCanvas = this.getVirtualCanvasSize('aretino');
            const zoom = Number(this.aretinoZoom) / 100;

            if (isFixedRatio) {
                // The slides are engraved by the one copy of that code a
                // projection also draws, so a page that does not fit comes to
                // the same slides here as it will on the wall.
                const slides = [];
                for (const pageSource of splitRatioPages(content, 'aretino', ratio, true)) {
                    try {
                        slides.push(...await renderAretinoSlides(pageSource, this, virtualCanvas, ratio));
                    } catch (e) {
                        console.error('[score-editor] aretino render error:', e);
                    }
                }
                this.placePreviewSlides(container, slides, 'aretino', ratio);
                this._aretinoPreviewDirty = false;
                this.updateAretinoHighlight();
                return;
            }

            const pages = this.splitPages(content, 'aretino', ratio);

            for (const [idx, pageSource] of pages.entries()) {
                const pageEl = document.createElement('div');

                if (isPaper) {
                    pageEl.className = 'score-preview-page score-preview-paper overflow-auto';
                } else {
                    pageEl.className = 'score-preview-page overflow-auto rounded-lg border border-zinc-200 bg-white dark:border-zinc-700';
                }

                container.appendChild(pageEl);

                try {
                    const renderOpts = isPaper
                        ? { widthMm: Number(this.aretinoStaffWidth) }
                        : { width: container.clientWidth / zoom - 12 };

                    pageEl.innerHTML = renderAretino(pageSource, {
                        ...renderOpts,
                        zoom: zoom,
                        staffSpaceMm: Number(this.aretinoStaffSize) / 4.0,
                        lyricSize: Number(this.aretinoLyricSize),
                        textFont: this.aretinoTextFont,
                        staffGap: Number(this.aretinoStaffGap),
                        hideRepeatClef: !!this.aretinoHideRepeatClef,
                    });
                } catch (e) {
                    console.error('[score-editor] aretino render error:', e);
                }
                this.addPageControls(pageEl, idx + 1, pages.length, 'aretino', { fullscreen: false, ratio });
            }
            this.hasPages = true;
            this._aretinoPreviewDirty = false;
            this.updateAretinoHighlight();
        },

        updateAretinoHighlight() {
            if (this.$wire.format !== 'aretino') { return; }
            // While a re-render is pending the SVG still carries the previous
            // content's source-position mapping. Repositioning the caret/tooltip
            // against it makes the marker jump to the wrong row (e.g. onto the
            // lyric line) until renderAretinoPreview catches up. Leave the last
            // correct highlight in place; renderAretinoPreview re-runs this once
            // the fresh SVG exists.
            if (this._aretinoPreviewDirty) { return; }
            const container = this.$refs.aretinoPreview;
            if (!container || !this._aretinoHighlightAtSelection) { return; }
            const editor = this.$refs.aretinoEditor;
            const textarea = this.$refs.contentTextarea;
            const selection = editor?.selection ?? (textarea ? { from: textarea.selectionStart, to: textarea.selectionEnd } : null);
            if (selection === null || selection === undefined) { return; }
            this._aretinoHighlightAtSelection(container, selection);
            this._updateSvgTooltip(container);
        },

        handleAretinoPreviewClick(event) {
            if (!this._aretinoSourceSpanFromPreviewClick) { return; }
            const container = this.$refs.aretinoPreview;
            const span = this._aretinoSourceSpanFromPreviewClick(event, container);
            if (!span) { return; }
            const editor = this.$refs.aretinoEditor;
            if (editor) {
                editor.caret = span.srcEnd;
                // caret setter restores focus synchronously; re-run highlight so
                // the tooltip check sees view.hasFocus = true
                this.updateAretinoHighlight();
            }
        },
    };
}
