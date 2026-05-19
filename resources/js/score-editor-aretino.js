import { renderAretino } from '@aretino-chant/core';

const ARETINO_STAFF_SIZE_CALIBRATION = 1.3;

export function aretinoMixin() {
    return {
        aretinoLyricFont: "'EB Garamond'",
        aretinoLyricSize: 12,
        aretinoStaffSize: 100,
        aretinoZoom: 100,
        aretinoPageRatio: 'auto',
        aretinoStaffGap: 2.5,
        aretinoHideRepeatClef: false,
        aretinoFields: ['aretinoLyricFont', 'aretinoLyricSize', 'aretinoStaffSize', 'aretinoZoom', 'aretinoPageRatio', 'aretinoStaffGap', 'aretinoHideRepeatClef'],

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

            const pages = this.splitPages(content, 'aretino', this.aretinoPageRatio);
            const canvas = this.getVirtualCanvasSize('aretino');
            const zoom = Number(this.aretinoZoom) / 100;
            pages.forEach((pageSource, idx) => {
                const pageEl = document.createElement('div');
                pageEl.className = 'overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-700';
                if (this.aretinoPageRatio !== 'auto') {
                    pageEl.style.aspectRatio = this.aretinoPageRatio;
                }
                container.appendChild(pageEl);
                try {
                    const svg = renderAretino(pageSource, {
                        canvasWidth: canvas.width,
                        canvasHeight: canvas.height,
                        staffSize: Number(this.aretinoStaffSize) * ARETINO_STAFF_SIZE_CALIBRATION * zoom,
                        lyricFont: this.aretinoLyricFont,
                        lyricSize: Number(this.aretinoLyricSize) * zoom,
                        staffGap: Number(this.aretinoStaffGap),
                        hideRepeatClef: !!this.aretinoHideRepeatClef,
                    });
                    pageEl.innerHTML = svg;
                    const svgEl = pageEl.querySelector('svg');
                    if (svgEl) {
                        if (canvas.height && this.aretinoPageRatio !== 'auto') {
                            const naturalH = parseFloat(svgEl.getAttribute('viewBox').split(/\s+/)[3]);
                            if (naturalH > canvas.height + 2) {
                                this.appendClipWarning(pageEl);
                            }
                        }
                    }
                } catch (e) {
                    console.error('[score-editor] aretino render error:', e);
                }
                this.addPageControls(pageEl, idx + 1, pages.length, 'aretino');
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
