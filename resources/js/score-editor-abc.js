const DEFAULT_ABC_FONT = 'Palatino Linotype';

export function abcMixin() {
    return {
        abcLyricFont: 'Palatino Linotype',
        abcLyricSize: 13,
        abcLyricBold: false,
        abcPageRatio: 'auto',
        abcPageScale: 3,
        abcNoteSpacing: 1.4,
        abcStaffSep: 46,
        abcVocalSpace: 10,
        abcNoClef: false,
        abcStemWidth: 0.7,
        abcStaffLineWidth: 0.7,
        abcFields: ['abcLyricFont', 'abcLyricSize', 'abcLyricBold', 'abcPageRatio', 'abcPageScale', 'abcNoteSpacing', 'abcStaffSep', 'abcVocalSpace', 'abcNoClef', 'abcStemWidth', 'abcStaffLineWidth'],

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
            const virtualWidth = this.getVirtualCanvasSize('abc').width;
            const safeFont = /^[a-zA-Z0-9 ]+$/.test(this.abcLyricFont) ? this.abcLyricFont : DEFAULT_ABC_FONT;
            const fontName = safeFont.includes(' ') ? `"${safeFont}"` : safeFont;
            const boldStr = this.abcLyricBold ? ' bold' : '';
            const lyricSize = this.abcLyricSize > 0 ? this.abcLyricSize : 12;
            const vocalfontLine = `%%vocalfont ${fontName}${boldStr} ${lyricSize}`;
            const preamble = `%%fullsvg 1\n%%pagewidth ${virtualWidth}px\n%%leftmargin 15px\n%%rightmargin 50px\n%%pagescale ${this.abcPageScale}\n${vocalfontLine}\n%%notespacingfactor ${this.abcNoteSpacing}\n%%musicspace 0\n%%topspace 0\n%%staffsep ${this.abcStaffSep}\n%%vocalspace ${this.abcVocalSpace}\n`;
            const pages = this.splitPages(content, 'abc', this.abcPageRatio);
            pages.forEach(pageContent => {
                const pageEl = document.createElement('div');
                pageEl.className = 'overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900';
                if (this.abcPageRatio !== 'auto') {
                    pageEl.style.aspectRatio = this.abcPageRatio;
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
                    const svgs = pageEl.querySelectorAll('svg');
                    svgs.forEach((svg, svgIdx) => {
                        const style = document.createElementNS('http://www.w3.org/2000/svg', 'style');
                        style.textContent = `.sW{stroke-width:${this.abcStemWidth}!important}.slW{stroke-width:${this.abcStaffLineWidth}!important}`;
                        svg.appendChild(style);
                        if (!svg.getAttribute('viewBox')) {
                            const w = parseFloat(svg.getAttribute('width')) || virtualWidth;
                            const h = parseFloat(svg.getAttribute('height')) || 0;
                            if (h) {
                                svg.setAttribute('viewBox', `0 0 ${w} ${h}`);
                            }
                        }
                        svg.setAttribute('width', '100%');
                        svg.removeAttribute('height');
                        svg.style.display = 'block';
                    });
                    if (svgs.length > 0) { this.hasPages = true; }
                } catch (e) {
                    console.error('[score-editor] abc2svg error:', e);
                }
            });
        },
    };
}
