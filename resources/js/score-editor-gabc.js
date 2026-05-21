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
        pageRatio: 'paper',
        dropCaps: false,
        lyricFont: "'EB Garamond'",
        gabcFields: ['zoom', 'lyricSize', 'staffSize', 'dropCaps', 'lyricFont', 'minLyricWordSpacing', 'hyphenWidth', 'condensingTolerance', 'spaceBetweenSystems', 'minSpaceBelowStaff'],

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
            // Paper & fixed ratios lay out to the virtual canvas width; responsive
            // lays out to the live container width so content reflows on resize.
            const layoutWidth = isResponsive
                ? Math.max(200, Math.round((container.clientWidth || canvas.width) / zoom) - 4)
                : canvas.width;
            const pages = this.splitPages(content, 'gabc', ratio);
            const pageEls = pages.map((_, idx) => {
                const pageEl = document.createElement('div');
                if (isFixed) {
                    this.applyProjectorFrame(pageEl, ratio);
                    pageEl.className = 'overflow-auto bg-white';
                } else if (isResponsive) {
                    pageEl.className = 'overflow-auto rounded-lg border border-zinc-200 bg-white dark:border-zinc-700';
                } else {
                    pageEl.className = 'overflow-auto rounded-lg border border-zinc-200 bg-white dark:border-zinc-700';
                }
                pageEl.style.width = '100%';
                pageEl.style.maxWidth = '100%';
                pageEl.style.minWidth = '0';
                container.appendChild(pageEl);
                return pageEl;
            });
            pages.forEach((pageSource, idx) => {
                const pageEl = pageEls[idx];
                try {
                    const ctxt = new exsurge.ChantContext();
                    const z = 100 / 30;
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
                        score.layoutChantLines(ctxt, layoutWidth, () => {
                            let html = score.createSvg(ctxt);
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(html, 'image/svg+xml');
                            const svg = doc.querySelector('svg');
                            let contentH = 0;
                            let overflowScale = 1;
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
                                    overflowScale = zoom;
                                    svg.style.width = '100%';
                                    svg.style.height = '100%';
                                    svg.style.maxWidth = 'none';
                                } else if (!isResponsive) {
                                    overflowScale = zoom;
                                    svg.style.width = '100%';
                                    svg.style.maxWidth = 'none';
                                }
                                html = new XMLSerializer().serializeToString(svg);
                            }
                            pageEl.innerHTML = html;
                            if (!isResponsive && overflowScale !== 1) {
                                const svgEl = pageEl.querySelector('svg');
                                if (svgEl) {
                                    const scrollWidth = (overflowScale * 100) + '%';
                                    const zoomFrame = document.createElement('div');
                                    zoomFrame.style.width = scrollWidth;
                                    zoomFrame.style.maxWidth = 'none';
                                    if (isFixed) {
                                        zoomFrame.style.aspectRatio = ratio;
                                    }
                                    pageEl.replaceChildren(zoomFrame);
                                    zoomFrame.appendChild(svgEl);
                                }
                            }
                            if (isFixed && canvas.height && contentH > canvas.height + 2) {
                                this.appendClipWarning(pageEl);
                            }
                            this.hasPages = true;
                            this.addPageControls(pageEl, idx + 1, pages.length, 'gabc', { fullscreen: isFixed, ratio });
                        });
                    });
                } catch (e) {
                    console.error('[score-editor] exsurge error:', e);
                }
            });
        },
    };
}
