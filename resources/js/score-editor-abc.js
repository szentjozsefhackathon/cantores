import * as ABCJS from 'abcjs';

export function abcMixin() {
    return {
        abcScale: 1,
        abcStaffWidth: 740,
        abcTranspose: 0,
        abcPageRatio: 'auto',
        abcFields: ['abcScale', 'abcStaffWidth', 'abcTranspose'],

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

        renderAbcPreview() {
            const container = this.$refs.abcPreview;
            if (!container) { return; }
            container.innerHTML = '';
            const content = this.localContent;
            if (!content || !content.trim()) {
                this.hasPages = false;
                return;
            }
            const pages = this.splitPages(content, 'abc', this.abcPageRatio);
            const options = {
                scale: Number(this.abcScale),
                staffwidth: Number(this.abcStaffWidth),
                visualTranspose: Number(this.abcTranspose),
                add_classes: true,
                paddingtop: 15,
                paddingbottom: 30,
                paddingleft: 15,
                paddingright: 50,
            };
            pages.forEach((pageSource) => {
                const pageEl = document.createElement('div');
                pageEl.className = 'overflow-hidden rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900 [&_svg]:max-w-full [&_svg]:h-auto';
                if (this.abcPageRatio !== 'auto') {
                    pageEl.style.aspectRatio = this.abcPageRatio;
                }
                container.appendChild(pageEl);
                try {
                    const rendered = pageSource.replace(/^(w:\s*)(.*)$/gm, (match, prefix, lyrics) => {
                        return prefix + lyrics.replace(/<([^ |*~_-]+)/g, '​$1');
                    });
                    ABCJS.renderAbc(pageEl, rendered, options);
                    this.fixAbcLyrics(pageEl);
                    if (this.abcPageRatio !== 'auto' && pageEl.scrollHeight > pageEl.clientHeight + 2) {
                        this.appendClipWarning(pageEl);
                    }
                } catch (e) {
                    console.error('[score-editor] abcjs error:', e);
                }
            });
            this.hasPages = pages.length > 0;
        },
    };
}
