import { diatarToAbc } from './diatar-to-abc.js';

const DEFAULT_ABC_FONT = 'EB Garamond';

export function abcMixin() {
    return {
        diatarSource: '',
        abcLyricFont: 'EB Garamond',
        abcLyricSize: 13,
        abcLyricBold: false,
        abcPageRatio: 'paper',
        abcPageScale: 3,
        abcPageWidth: 1800,
        abcNoteSpacing: 1.4,
        abcStaffSep: 46,
        abcVocalSpace: 10,
        abcNoClef: false,
        abcStemWidth: 0.7,
        abcStaffLineWidth: 0.7,
        abcZoom: 100,
        abcFields: ['abcLyricFont', 'abcLyricSize', 'abcLyricBold', 'abcPageRatio', 'abcPageScale', 'abcPageWidth', 'abcNoteSpacing', 'abcStaffSep', 'abcVocalSpace', 'abcNoClef', 'abcStemWidth', 'abcStaffLineWidth', 'abcZoom'],

        convertDiatarToAbc() {
            const abc = diatarToAbc(this.diatarSource);
            if (!abc.trim()) { return; }
            this.isContentUserModified = true;
            this.$wire.format = 'abc';
            this.$wire.content = abc;
            this.localContent = abc;
            this.diatarSource = '';
            this.$flux.modal('diatar-import').close();
            this.$nextTick(() => this.scheduleRender());
        },

        renderAbcPreview() {
            const container = this.$refs.abcPreview;
            if (!container) { return; }
            container.innerHTML = '';
            this.hasPages = false;
            let content = this.localContent;
            if (!content || !content.trim()) { return; }
            if (typeof abc2svg === 'undefined' || !abc2svg.Abc) {
                console.error('[score-editor] abc2svg not loaded');
                return;
            }
            if (!/^X:/m.test(content)) {
                content = 'X:1\n' + content;
            }
            if (this.abcNoClef) {
                content = content.replace(/\|[|:\]]?/, '$&[K:clef=none]');
            }
            const ratio = this.abcPageRatio;
            const isFixed = this.isFixedRatio(ratio);
            const isResponsive = this.isResponsiveRatio(ratio);
            const isPaper = this.isPaperRatio(ratio);
            const canvas = this.getVirtualCanvasSize('abc');
            const zoom = Number(this.abcZoom || 100) / 100;
            const paperWidth = canvas.width / 2;
            const zoomedPaperWidth = Math.round(paperWidth * zoom);
            const availableWidth = Math.max(200, Math.round((container.clientWidth || zoomedPaperWidth) - 4));
            const paperPageWidth = Number(this.abcPageWidth) > 0 ? Number(this.abcPageWidth) : canvas.width;
            const renderWidth = isResponsive
                ? Math.min(zoomedPaperWidth, availableWidth)
                : isPaper
                    ? Math.round(zoomedPaperWidth * paperPageWidth / canvas.width)
                    : zoomedPaperWidth;
            const renderScale = zoomedPaperWidth / canvas.width;
            const pageWidth = isResponsive
                ? Math.max(200, Math.round(renderWidth / renderScale))
                : isPaper
                    ? paperPageWidth
                : canvas.width;
            const rawFont = (this.abcLyricFont || '').trim();
            const safeFont = /^[a-zA-Z0-9 .\-'&]+$/.test(rawFont) ? rawFont : DEFAULT_ABC_FONT;
            const fontName = /[ .\-'&]/.test(safeFont) ? `"${safeFont}"` : safeFont;
            const pageScale = Number(this.abcPageScale) > 0 ? Number(this.abcPageScale) : 1;
            const rawLyricSize = Number(this.abcLyricSize) > 0 ? Number(this.abcLyricSize) : 12;
            const lyricSize = Number((rawLyricSize / pageScale).toFixed(3));
            const vocalfontLine = ['%%vocalfont', fontName, this.abcLyricBold ? 'bold' : null, lyricSize].filter(Boolean).join(' ');
            const preamble = `%%fullsvg 1\n%%pagewidth ${pageWidth}px\n%%leftmargin 15px\n%%rightmargin 50px\n%%pagescale ${pageScale}\n${vocalfontLine}\n%%notespacingfactor ${this.abcNoteSpacing}\n%%musicspace 0\n%%topspace 0\n%%staffsep ${this.abcStaffSep}\n%%vocalspace ${this.abcVocalSpace}\n`;
            const pages = this.splitPages(content, 'abc', ratio);
            pages.forEach((pageContent, idx) => {
                const pageEl = document.createElement('div');
                if (isFixed) {
                    this.applyProjectorFrame(pageEl, ratio);
                } else if (isResponsive) {
                    pageEl.className = 'score-preview-page overflow-auto rounded-lg border border-zinc-200 bg-white dark:border-zinc-700';
                } else {
                    pageEl.className = 'score-preview-page overflow-auto rounded-lg border border-zinc-200 bg-white dark:border-zinc-700';
                }
                if (!isFixed) {
                    pageEl.style.width = '100%';
                    pageEl.style.maxWidth = '100%';
                    pageEl.style.minWidth = '0';
                }
                container.appendChild(pageEl);
                try {
                    const source = preamble + pageContent;
                    const svgChunks = [];
                    const errs = [];
                    const user = {
                        img_out: (str) => svgChunks.push(str),
                        errmsg: (msg, l) => errs.push(`${msg} (line ${l})`),
                        read_file: () => null,
                    };
                    const abc = new abc2svg.Abc(user);
                    abc.tosvg('score', source);
                    pageEl.innerHTML = svgChunks.join('\n');
                    if (errs.length) {
                        console.warn('[score-editor] abc2svg warnings:', errs);
                    }
                    const svgs = Array.from(pageEl.querySelectorAll('svg'));
                    svgs.forEach((svg) => {
                        if (!svg.getAttribute('viewBox')) {
                            const w = parseFloat(svg.getAttribute('width')) || pageWidth;
                            const h = parseFloat(svg.getAttribute('height')) || 0;
                            if (h) {
                                svg.setAttribute('viewBox', `0 0 ${w} ${h}`);
                            }
                        }
                    });
                    if (isFixed && svgs.length > 0) {
                        const { svg: merged, totalHeight } = this.mergeAbcSvgsToElement(svgs);
                        const svgId = `abc-svg-${idx}-${Date.now()}`;
                        merged.id = svgId;
                        const style = document.createElementNS('http://www.w3.org/2000/svg', 'style');
                        style.textContent = `#${svgId}{color:#000!important;fill:#000!important}#${svgId} .sW{stroke-width:${this.abcStemWidth}!important}#${svgId} .slW{stroke-width:${this.abcStaffLineWidth}!important}`;
                        merged.appendChild(style);
                        merged.setAttribute('viewBox', `0 0 ${canvas.width} ${canvas.height}`);
                        merged.setAttribute('width', '100%');
                        merged.setAttribute('preserveAspectRatio', 'xMidYMin meet');
                        merged.style.display = 'block';
                        merged.style.width = '100%';
                        merged.style.height = '100%';
                        pageEl.innerHTML = '';
                        pageEl.appendChild(merged);
                        if (totalHeight > canvas.height + 2) {
                            this.appendClipWarning(pageEl);
                        }
                        this.hasPages = true;
                    } else {
                        svgs.forEach((svg, svgIdx) => {
                            const svgId = `abc-svg-${idx}-${svgIdx}-${Date.now()}`;
                            svg.id = svgId;
                            const style = document.createElementNS('http://www.w3.org/2000/svg', 'style');
                            style.textContent = `#${svgId}{color:#000!important;fill:#000!important}#${svgId} .sW{stroke-width:${this.abcStemWidth}!important}#${svgId} .slW{stroke-width:${this.abcStaffLineWidth}!important}`;
                            svg.appendChild(style);
                            svg.setAttribute('width', '100%');
                            svg.removeAttribute('height');
                            svg.style.display = 'block';
                        });
                        if (svgs.length > 0) {
                            const zoomFrame = document.createElement('div');
                            zoomFrame.style.width = renderWidth + 'px';
                            zoomFrame.style.maxWidth = 'none';
                            pageEl.replaceChildren(zoomFrame);
                            svgs.forEach(svg => zoomFrame.appendChild(svg));
                        }
                        if (svgs.length > 0) { this.hasPages = true; }
                    }
                } catch (e) {
                    console.error('[score-editor] abc2svg error:', e);
                }
                this.addPageControls(pageEl, idx + 1, pages.length, 'abc', { fullscreen: isFixed, ratio });
            });
        },
    };
}
