export function gabcMixin() {
    return {
        zoom: 100,
        lyricSize: 12,
        staffSize: 100,
        minLyricWordSpacing: 0,
        hyphenWidth: 0,
        condensingTolerance: 0.9,
        spaceBetweenSystems: 0,
        minSpaceBelowStaff: 0,
        pageRatio: 'auto',
        dropCaps: false,
        lyricFont: "'Palatino Linotype', 'Book Antiqua', Palatino, serif",
        gabcFields: ['zoom', 'lyricSize', 'staffSize', 'dropCaps', 'lyricFont', 'minLyricWordSpacing', 'hyphenWidth', 'condensingTolerance', 'spaceBetweenSystems', 'minSpaceBelowStaff'],

        renderGabcPreview() {
            const container = this.$refs.preview;
            if (!container) { return; }
            container.innerHTML = '';
            this.hasPages = false;
            if (!window.exsurge) { return; }
            const content = this.localContent;
            if (!content || !content.trim()) { return; }
            const pages = this.splitPages(content, 'gabc', this.pageRatio);
            const canvas = this.getVirtualCanvasSize('gabc');
            const pageEls = pages.map((_, idx) => {
                const pageEl = document.createElement('div');
                pageEl.className = 'overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900';
                if (this.pageRatio !== 'auto') {
                    pageEl.style.aspectRatio = this.pageRatio;
                }
                container.appendChild(pageEl);
                this.addPageControls(pageEl, idx + 1, pages.length, 'gabc');
                return pageEl;
            });
            this.hasPages = true;
            pages.forEach((pageSource, idx) => {
                const pageEl = pageEls[idx];
                try {
                    const ctxt = new exsurge.ChantContext();
                    const z = Number(this.zoom) / 30;
                    ctxt.setFont(this.lyricFont, Number(this.lyricSize) * z * 1.3);
                    ctxt.setGlyphScaling((Number(this.staffSize) / 100) * z / 16);
                    if (Number(this.minLyricWordSpacing) > 0) {
                        ctxt.minLyricWordSpacing = Number(this.minLyricWordSpacing) * z;
                    }
                    if (Number(this.hyphenWidth) > 0) {
                        ctxt.hyphenWidth = Number(this.hyphenWidth) * z;
                    }
                    ctxt.condensingTolerance = Number(this.condensingTolerance);
                    ctxt.spaceBetweenSystems = Number(this.spaceBetweenSystems);
                    ctxt.minSpaceBelowStaff = Number(this.minSpaceBelowStaff);
                    const mappings = exsurge.Gabc.createMappingsFromSource(ctxt, pageSource);
                    const score = new exsurge.ChantScore(ctxt, mappings, this.dropCaps);
                    score.performLayoutAsync(ctxt, () => {
                        score.layoutChantLines(ctxt, canvas.width, () => {
                            let html = score.createSvg(ctxt);
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(html, 'image/svg+xml');
                            const svg = doc.querySelector('svg');
                            let contentH = 0;
                            if (svg) {
                                const h = svg.getAttribute('height');
                                contentH = h ? parseFloat(h) : 0;
                                const viewBoxHeight = canvas.height ?? contentH;
                                svg.setAttribute('viewBox', '0 0 ' + canvas.width + ' ' + viewBoxHeight);
                                svg.setAttribute('width', '100%');
                                svg.removeAttribute('height');
                                svg.setAttribute('preserveAspectRatio', 'xMidYMin meet');
                                svg.style.display = 'block';
                                svg.style.overflow = 'hidden';
                                html = new XMLSerializer().serializeToString(svg);
                            }
                            pageEl.innerHTML = html;
                            if (canvas.height && contentH > canvas.height + 2) {
                                this.appendClipWarning(pageEl);
                            }
                        });
                    });
                } catch (e) {
                    console.error('[score-editor] exsurge error:', e);
                }
            });
        },
    };
}
