export function abcMixin() {
    return {
        abcLyricFont: 'Palatino Linotype',
        abcLyricSize: 13,
        abcLyricBold: false,
        abcPageRatio: 'auto',
        abcFields: ['abcLyricFont', 'abcLyricSize', 'abcLyricBold', 'abcPageRatio'],

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
            const pageEl = document.createElement('div');
            pageEl.className = 'overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900';
            if (this.abcPageRatio !== 'auto') {
                pageEl.style.aspectRatio = this.abcPageRatio;
            }
            container.appendChild(pageEl);
            try {
                const virtualWidth = this.getVirtualCanvasSize('abc').width;
                const fontName = this.abcLyricFont.includes(' ') ? `"${this.abcLyricFont}"` : this.abcLyricFont;
                const boldStr = this.abcLyricBold ? 'Bold' : '';
                const vocalfontLine = `%%vocalfont ${fontName}${boldStr} ${this.abcLyricSize}`;
                const source = `%%fullsvg 1\n%%pagewidth ${virtualWidth}px\n%%leftmargin 15px\n%%rightmargin 50px\n%%pagescale 3\n${vocalfontLine}\n` + content;
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
                svgs.forEach(svg => {
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
                this.hasPages = svgs.length > 0;
            } catch (e) {
                console.error('[score-editor] abc2svg error:', e);
            }
        },
    };
}
