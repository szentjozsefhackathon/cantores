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

        // Highlights the SVG element whose source range contains the textarea
        // cursor position. Re-run on cursor moves and after each re-render.
        updateAretinoHighlight() {
            if (this.$wire.format !== 'aretino') { return; }
            const container = this.$refs.aretinoPreview;
            const textarea = this.$refs.contentTextarea;
            if (!container || !textarea) { return; }
            const pos = textarea.selectionStart;
            container.querySelectorAll('.aretino-active').forEach(el => el.classList.remove('aretino-active'));
            container.querySelectorAll('.aretino-cursor-bg').forEach(el => el.remove());
            if (pos === null || pos === undefined) { return; }

            // Walk all source-tagged elements; pick the smallest range that
            // contains the cursor (notes nest inside ligatures).
            let best = null;
            let bestSize = Infinity;
            container.querySelectorAll('[data-src-start]').forEach(el => {
                const s = Number(el.dataset.srcStart);
                const e = Number(el.dataset.srcEnd);
                if (pos < s || pos > e) { return; }
                const size = e - s;
                if (size < bestSize) {
                    best = el;
                    bestSize = size;
                }
            });
            if (best) {
                best.classList.add('aretino-active');
                this.drawAretinoCursorBg(best);
            }
        },

        // Insert a translucent rect behind the active element so the cursor
        // location reads at a glance, even when the underlying glyph is small.
        drawAretinoCursorBg(el) {
            let bbox;
            try {
                bbox = el.getBBox();
            } catch (e) {
                return;
            }
            if (!bbox || (bbox.width === 0 && bbox.height === 0)) { return; }
            const pad = 4;
            const rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
            rect.setAttribute('class', 'aretino-cursor-bg');
            rect.setAttribute('x', bbox.x - pad);
            rect.setAttribute('y', bbox.y - pad);
            rect.setAttribute('width', bbox.width + pad * 2);
            rect.setAttribute('height', bbox.height + pad * 2);
            rect.setAttribute('rx', 3);
            rect.setAttribute('ry', 3);
            rect.setAttribute('fill', 'rgba(0, 122, 204, 0.25)');
            rect.setAttribute('stroke', '#007acc');
            rect.setAttribute('stroke-width', 2);
            rect.setAttribute('pointer-events', 'none');
            el.insertBefore(rect, el.firstChild);
        },
    };
}
