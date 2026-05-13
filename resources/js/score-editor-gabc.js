export function gabcMixin() {
    return {
        zoom: 100,
        lyricSize: 12,
        staffSize: 100,
        dropCapSize: 64,
        minLyricWordSpacing: 0,
        hyphenWidth: 0,
        condensingTolerance: 0.9,
        pageRatio: 'auto',
        dropCaps: false,
        lyricFont: "'Palatino Linotype', 'Book Antiqua', Palatino, serif",
        gabcFields: ['zoom', 'lyricSize', 'staffSize', 'dropCapSize', 'dropCaps', 'lyricFont', 'minLyricWordSpacing', 'hyphenWidth', 'condensingTolerance'],

        getRenderWidth() {
            return this.getVirtualCanvasSize().width;
        },

        getVirtualCanvasSize() {
            const sizes = {
                '16/9': { width: 1920, height: 1080 },
                '4/3': { width: 1440, height: 1080 },
                '1/1': { width: 1080, height: 1080 },
            };
            return sizes[this.pageRatio] || { width: 1920, height: null };
        },

        renderGabcPreview() {
            const container = this.$refs.preview;
            if (!container) { return; }
            container.innerHTML = '';
            this.hasPages = false;
            if (!window.exsurge) { return; }
            const content = this.localContent;
            if (!content || !content.trim()) { return; }
            const pages = this.splitPages(content, 'gabc', this.pageRatio);
            const canvas = this.getVirtualCanvasSize();
            const pageEls = pages.map(() => {
                const pageEl = document.createElement('div');
                pageEl.className = 'overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900';
                if (this.pageRatio !== 'auto') {
                    pageEl.style.aspectRatio = this.pageRatio;
                }
                container.appendChild(pageEl);
                return pageEl;
            });
            this.hasPages = true;
            pages.forEach((pageSource, idx) => {
                const pageEl = pageEls[idx];
                try {
                    const ctxt = new exsurge.ChantContext();
                    const z = Number(this.zoom) / 100;
                    const ptToPx = 4;
                    ctxt.lyricTextSize = Number(this.lyricSize) * z * ptToPx;
                    ctxt.lyricTextFont = this.lyricFont;
                    ctxt.dropCapTextFont = this.lyricFont;
                    ctxt.annotationTextFont = this.lyricFont;
                    ctxt.dropCapTextSize = Number(this.dropCapSize) * z * ptToPx;
                    ctxt.glyphScaling = (1.0 / 16.0) * (Number(this.staffSize) / 100) * z * ptToPx;
                    ctxt.staffInterval = ctxt.glyphPunctumWidth * ctxt.glyphScaling;
                    ctxt.staffLineWeight = Math.round(ctxt.glyphPunctumWidth * ctxt.glyphScaling / 8);
                    ctxt.neumeLineWeight = ctxt.staffLineWeight;
                    ctxt.dividerLineWeight = ctxt.neumeLineWeight;
                    ctxt.episemaLineWeight = ctxt.neumeLineWeight;
                    if (Number(this.minLyricWordSpacing) > 0) {
                        ctxt.minLyricWordSpacing = Number(this.minLyricWordSpacing) * z * ptToPx;
                    }
                    if (Number(this.hyphenWidth) > 0) {
                        ctxt.hyphenWidth = Number(this.hyphenWidth) * z * ptToPx;
                    }
                    ctxt.condensingTolerance = Number(this.condensingTolerance);
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
