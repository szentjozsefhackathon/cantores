import { renderAretino } from '@aretino-chant/core';

const ARETINO_STAFF_SIZE_CALIBRATION = 1.3;

// Fixed pixel canvas for each fixed-ratio (projector screen) mode.
// All share the same height so switching ratio keeps the screen size constant.
const ARETINO_SCREEN_CANVAS = {
    '16/9': { width: 960, height: 540 },
    '4/3':  { width: 720, height: 540 },
    '1/1':  { width: 540, height: 540 },
};

export function aretinoMixin() {
    return {
        aretinoLyricFont: "'EB Garamond'",
        aretinoLyricSize: 10,
        aretinoStaffSize: 7,
        aretinoZoom: 120,
        aretinoPageRatio: 'paper',
        aretinoStaffWidth: 170,
        aretinoStaffGap: 2.5,
        aretinoHideRepeatClef: false,
        aretinoFields: ['aretinoLyricFont', 'aretinoLyricSize', 'aretinoStaffSize', 'aretinoZoom', 'aretinoPageRatio', 'aretinoStaffWidth', 'aretinoStaffGap', 'aretinoHideRepeatClef'],
        _aretinoResizeObserver: null,
        _aretinoResizeTimer: null,

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
            if (!content || !content.trim()) { return; }

            if (document.fonts) {
                const primaryFamily = this.aretinoLyricFont.split(',')[0].trim().replace(/['"]/g, '');
                try {
                    await document.fonts.load(`${this.aretinoLyricSize}px "${primaryFamily}"`);
                } catch (_e) {
                    // proceed anyway if font load fails
                }
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
                        lyricFont: this.aretinoLyricFont,
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
            this.updateAretinoHighlight();
        },

        updateAretinoHighlight() {
            if (this.$wire.format !== 'aretino') { return; }
            const container = this.$refs.aretinoPreview;
            if (!container || !this._aretinoHighlightAtCaret) { return; }
            const editor = this.$refs.aretinoEditor;
            const textarea = this.$refs.contentTextarea;
            const caret = editor?.caret ?? textarea?.selectionStart;
            if (caret === null || caret === undefined) { return; }
            this._aretinoHighlightAtCaret(container, caret);
        },

        handleAretinoPreviewClick(event) {
            if (!this._aretinoSourceSpanFromPreviewClick) { return; }
            const container = this.$refs.aretinoPreview;
            const span = this._aretinoSourceSpanFromPreviewClick(event, container);
            if (!span) { return; }
            const editor = this.$refs.aretinoEditor;
            if (editor) {
                editor.caret = span.srcEnd;
            }
        },
    };
}
