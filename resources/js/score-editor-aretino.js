import { renderAretino } from '@aretino-chant/core';
import { SLIDE_FIT_TOLERANCE, emptySlide, fitSlide, parseSvg, viewBoxOf } from './slide-frame.js';
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
 * Aretino is the one engine that is told the box it is drawing into, so it
 * reports having run out of room differently from the other two: handed a
 * canvas height it holds that height and *widens* the viewBox instead — 960
 * becomes 1236 and the slide is no longer 16:9. Nothing is lost, but the music
 * is then letterboxed down to fit the screen, which is the same bad news the
 * other engines deliver by clipping. So the overflow test here is the width,
 * and the engine's own viewBox is kept rather than restated: rewriting it to
 * the canvas would crop the very music that grew.
 *
 * The lyric face is waited for first, for the reason ensureFontsLoaded()
 * explains: Aretino places every syllable at a measured width, and a face the
 * browser has not fetched yet measures as the fallback.
 */
export async function renderAretinoSlide(pageSource, settings, canvas, ratio) {
    await ensureFontsLoaded([settings.aretinoTextFont], Number(settings.aretinoLyricSize));

    const zoom = Number(settings.aretinoZoom) > 0 ? Number(settings.aretinoZoom) / 100 : 1;

    const svg = parseSvg(renderAretino(pageSource, {
        ...aretinoProjectorOptions(ratio),
        zoom,
        staffSpaceMm: Number(settings.aretinoStaffSize) / 4.0,
        lyricSize: Number(settings.aretinoLyricSize),
        textFont: settings.aretinoTextFont,
        staffGap: Number(settings.aretinoStaffGap),
        hideRepeatClef: !!settings.aretinoHideRepeatClef,
    }));

    if (svg === null) {
        return { svg: emptySlide(canvas), overflows: false };
    }

    return {
        svg: fitSlide(svg),
        overflows: viewBoxOf(svg).width > canvas.width + SLIDE_FIT_TOLERANCE,
    };
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

            const pages = this.splitPages(content, 'aretino', ratio);
            const virtualCanvas = this.getVirtualCanvasSize('aretino');
            const zoom = Number(this.aretinoZoom) / 100;

            for (const [idx, pageSource] of pages.entries()) {
                const pageEl = document.createElement('div');

                if (isFixedRatio) {
                    // Projector-screen frame: fixed aspect ratio, scales to container.
                    pageEl.className = 'overflow-hidden bg-white';
                    pageEl.style.aspectRatio = ratio;
                    pageEl.style.width = '100%';
                    pageEl.style.border = '8px solid #374151';
                    pageEl.style.borderRadius = '4px';
                    pageEl.style.boxShadow = '0 8px 32px rgba(0,0,0,0.45)';
                } else if (isPaper) {
                    pageEl.className = 'score-preview-page score-preview-paper overflow-auto';
                } else {
                    pageEl.className = 'score-preview-page overflow-auto rounded-lg border border-zinc-200 bg-white dark:border-zinc-700';
                }

                container.appendChild(pageEl);

                try {
                    if (isFixedRatio) {
                        // The slide is engraved by the one copy of that code a
                        // projection also draws.
                        const { svg, overflows } = await renderAretinoSlide(pageSource, this, virtualCanvas, ratio);
                        pageEl.replaceChildren(svg);
                        if (overflows) {
                            this.appendClipWarning(pageEl);
                        }
                    } else {
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
                    }
                } catch (e) {
                    console.error('[score-editor] aretino render error:', e);
                }
                this.addPageControls(pageEl, idx + 1, pages.length, 'aretino', { fullscreen: isFixedRatio, ratio });
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
