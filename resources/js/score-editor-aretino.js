import { renderAretino } from '@aretino-chant/core';
import { gabcToAretino } from '@aretino-chant/gabc2aretino';
import { guidoToAretino, guidoTextToAretino } from '@aretino-chant/guido2aretino';

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

export function aretinoMixin() {
    return {
        aretinoTextFont: "'EB Garamond'",
        aretinoLyricSize: 10,
        aretinoStaffSize: 7,
        aretinoZoom: 120,
        aretinoPageRatio: 'paper',
        aretinoStaffWidth: 170,
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

            if (document.fonts) {
                const primaryFamily = this.aretinoTextFont.split(',')[0].trim().replace(/['"]/g, '');
                const sz = `${this.aretinoLyricSize}px "${primaryFamily}"`;
                await Promise.allSettled([
                    document.fonts.load(sz),
                    document.fonts.load(`italic ${sz}`),
                    document.fonts.load(`bold ${sz}`),
                    document.fonts.load(`italic bold ${sz}`),
                ]);
            }

            const ratio = this.aretinoPageRatio;
            // 'auto' is a legacy alias for 'paper' from before the rename.
            const isPaper = ratio === 'paper' || ratio === 'auto';
            const isResponsive = ratio === 'responsive';
            const isFixedRatio = !isPaper && !isResponsive; // '16/9', '4/3', '1/1'

            const pages = this.splitPages(content, 'aretino', ratio);
            const virtualCanvas = this.getVirtualCanvasSize('aretino');
            const zoom = Number(this.aretinoZoom) / 100;

            pages.forEach((pageSource, idx) => {
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

                let renderOpts;
                if (isPaper) {
                    renderOpts = { widthMm: Number(this.aretinoStaffWidth) };
                } else if (isResponsive) {
                    renderOpts = { width: container.clientWidth / zoom - 12 };
                } else {
                    // Fixed ratio: render at a predefined px width so all ratios share
                    // the same 540 px height; CSS scaling handles smaller containers.
                    const sc = ARETINO_SCREEN_CANVAS[ratio] || { width: 960, height: 540 };
                    renderOpts = { width: sc.width };
                }

                try {
                    const svg = renderAretino(pageSource, {
                        ...renderOpts,
                        zoom: zoom,
                        staffSpaceMm: Number(this.aretinoStaffSize) / 4.0,
                        lyricSize: Number(this.aretinoLyricSize),
                        textFont: this.aretinoTextFont,
                        staffGap: Number(this.aretinoStaffGap),
                        hideRepeatClef: !!this.aretinoHideRepeatClef,
                    });
                    pageEl.innerHTML = svg;
                    const svgEl = pageEl.querySelector('svg');
                    if (svgEl) {
                        if (isFixedRatio) {
                            // Scale SVG to fill the projector-screen container via CSS.
                            svgEl.style.width = '100%';
                            svgEl.style.height = '100%';
                            svgEl.style.display = 'block';
                        } else if (virtualCanvas.height) {
                            const naturalH = parseFloat(svgEl.getAttribute('viewBox').split(/\s+/)[3]);
                            if (naturalH > virtualCanvas.height + 2) {
                                this.appendClipWarning(pageEl);
                            }
                        }
                    }
                } catch (e) {
                    console.error('[score-editor] aretino render error:', e);
                }
                this.addPageControls(pageEl, idx + 1, pages.length, 'aretino', { fullscreen: isFixedRatio, ratio });
            });
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
