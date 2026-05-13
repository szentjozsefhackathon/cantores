import * as ABCJS from 'abcjs';

const ABC_LYRIC_FONT_MAP = {
    Palatino: "'Palatino Linotype', 'Book Antiqua', Palatino, serif",
    Garamond: "Garamond, 'EB Garamond', serif",
    Times: "'Times New Roman', Times, serif",
    Franklin: "'Franklin Gothic Book', 'Franklin Gothic Medium', Arial, sans-serif",
};

export function abcMixin() {
    return {
        abcScale: 1,
        abcTranspose: 0,
        abcPageRatio: 'auto',
        abcHideRepeatClef: false,
        abcLyricSize: 0,
        abcLyricFont: '',
        abcFields: ['abcScale', 'abcTranspose', 'abcHideRepeatClef', 'abcLyricSize', 'abcLyricFont'],

        fixAbcLyrics(pageEl) {
            const noteXMap = new Map();
            pageEl.querySelectorAll('.abcjs-note .abcjs-notehead').forEach(nh => {
                const bbox = nh.getBBox();
                const noteCenter = bbox.x + bbox.width / 2;
                const noteGroup = nh.closest('.abcjs-note');
                if (noteGroup) {
                    const lyric = noteGroup.querySelector('text.abcjs-lyric');
                    if (lyric) {
                        noteXMap.set(lyric, noteCenter);
                    }
                }
            });
            pageEl.querySelectorAll('text.abcjs-lyric').forEach(t => {
                const txt = t.textContent || '';
                const tspans = t.querySelectorAll('tspan');
                if (txt.startsWith('​')) {
                    t.textContent = txt.replace('​', '');
                    t.setAttribute('text-anchor', 'start');
                } else {
                    const noteCenter = noteXMap.get(t);
                    if (noteCenter !== undefined) {
                        const cx = String(noteCenter + (txt.includes('-') ? 4 : 0));
                        t.setAttribute('text-anchor', 'middle');
                        t.setAttribute('x', cx);
                        tspans.forEach(ts => ts.setAttribute('x', cx));
                    }
                }
            });
        },

        applyAbcLyricFont(pageEl) {
            const font = this.abcLyricFont;
            if (!font) { return; }
            const cssFont = ABC_LYRIC_FONT_MAP[font] || font;
            pageEl.querySelectorAll('text.abcjs-lyric').forEach(t => {
                t.setAttribute('font-family', cssFont);
            });
        },

        renderAbcPreview() {
            const container = this.$refs.abcPreview;
            if (!container) { return; }
            container.innerHTML = '';
            this.hasPages = false;
            const content = this.localContent;
            if (!content || !content.trim()) { return; }
            const pages = this.splitPages(content, 'abc', this.abcPageRatio);
            const canvas = this.getVirtualCanvasSize('abc');
            const paddingLeft = 15;
            const paddingRight = 50;
            const paddingTop = 15;
            const paddingBottom = 30;
            const options = {
                scale: Number(this.abcScale) * 3,
                staffwidth: canvas.width - paddingLeft - paddingRight,
                visualTranspose: Number(this.abcTranspose),
                add_classes: true,
                paddingtop: paddingTop,
                paddingbottom: paddingBottom,
                paddingleft: paddingLeft,
                paddingright: paddingRight,
                initialClef: !!this.abcHideRepeatClef,
            };
            const pageEls = pages.map(() => {
                const pageEl = document.createElement('div');
                pageEl.className = 'overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900';
                if (this.abcPageRatio !== 'auto') {
                    pageEl.style.aspectRatio = this.abcPageRatio;
                }
                container.appendChild(pageEl);
                return pageEl;
            });
            this.hasPages = pages.length > 0;
            pages.forEach((pageSource, idx) => {
                const pageEl = pageEls[idx];
                try {
                    let rendered = pageSource.replace(/^(w:\s*)(.*)$/gm, (match, prefix, lyrics) => {
                        return prefix + lyrics.replace(/<([^ |*~_-]+)/g, '​$1');
                    });
                    const lyricSize = Number(this.abcLyricSize);
                    if (lyricSize > 0) {
                        const fontName = this.abcLyricFont || 'Times';
                        rendered = `%%vocalfont ${fontName} ${lyricSize}\n` + rendered;
                    }
                    ABCJS.renderAbc(pageEl, rendered, options);
                    pageEl.style.height = '';
                    this.fixAbcLyrics(pageEl);
                    this.applyAbcLyricFont(pageEl);
                    const svg = pageEl.querySelector('svg');
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
                    }
                    if (canvas.height && contentH > canvas.height + 2) {
                        this.appendClipWarning(pageEl);
                    }
                } catch (e) {
                    console.error('[score-editor] abcjs error:', e);
                }
            });
        },
    };
}
