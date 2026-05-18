import { renderAretino } from './aretino/index.js';

export function aretinoMixin() {
    return {
        aretinoLyricFont: "'Palatino Linotype', 'Book Antiqua', Palatino, serif",
        aretinoLyricSize: 13,
        aretinoStaffSize: 100,
        aretinoZoom: 100,
        aretinoPageRatio: 'auto',
        aretinoFields: ['aretinoLyricFont', 'aretinoLyricSize', 'aretinoStaffSize', 'aretinoZoom'],

        renderAretinoPreview() {
            const container = this.$refs.aretinoPreview;
            if (!container) { return; }
            container.innerHTML = '';
            this.hasPages = false;
            const content = this.localContent;
            if (!content || !content.trim()) { return; }
            const pages = this.splitPages(content, 'aretino', this.aretinoPageRatio);
            const canvas = this.getVirtualCanvasSize('aretino');
            const zoom = Number(this.aretinoZoom) / 100;
            pages.forEach((pageSource, idx) => {
                const pageEl = document.createElement('div');
                pageEl.className = 'overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900';
                if (this.aretinoPageRatio !== 'auto') {
                    pageEl.style.aspectRatio = this.aretinoPageRatio;
                }
                container.appendChild(pageEl);
                try {
                    const svg = renderAretino(pageSource, {
                        canvasWidth: canvas.width,
                        canvasHeight: canvas.height,
                        staffSize: Number(this.aretinoStaffSize) * zoom,
                        lyricFont: this.aretinoLyricFont,
                        lyricSize: Number(this.aretinoLyricSize) * zoom,
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
        },
    };
}
