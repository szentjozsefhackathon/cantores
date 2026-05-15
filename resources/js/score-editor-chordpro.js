import ChordSheetJS from 'chordsheetjs';

export function chordproMixin() {
    return {
        chordproFontSize: 14,
        chordproFontFamily: "'Palatino Linotype', 'Book Antiqua', Palatino, serif",
        chordproColumns: 1,
        chordproTranspose: 0,
        chordproFields: ['chordproFontSize', 'chordproFontFamily', 'chordproColumns', 'chordproTranspose'],

        renderChordproPreview() {
            const container = this.$refs.chordproPreview;
            if (!container) { return; }
            container.innerHTML = '';
            this.hasPages = false;
            const content = this.localContent;
            if (!content || !content.trim()) { return; }
            try {
                const parser = new ChordSheetJS.ChordProParser();
                const formatter = new ChordSheetJS.HtmlDivFormatter();
                let song = parser.parse(content);
                const transpose = Number(this.chordproTranspose);
                if (transpose !== 0) {
                    song = song.transpose(transpose);
                }
                const html = formatter.format(song);
                const pageEl = document.createElement('div');
                pageEl.className = 'chordpro-preview overflow-auto rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900';
                pageEl.style.fontFamily = this.chordproFontFamily;
                pageEl.style.fontSize = Number(this.chordproFontSize) + 'px';
                const cols = Number(this.chordproColumns);
                if (cols > 1) {
                    pageEl.style.columnCount = cols;
                    pageEl.style.columnGap = '2rem';
                }
                pageEl.innerHTML = html;
                container.appendChild(pageEl);
                this.hasPages = true;
            } catch (e) {
                console.error('[score-editor] chordsheetjs error:', e);
            }
        },

        syncChordproTitle(title) {
            const directive = `{title: ${title}}`;
            const titleRe = /^\{title:[^}]*\}/m;
            let content = this.localContent;
            if (titleRe.test(content)) {
                content = content.replace(titleRe, directive);
            } else if (title) {
                content = content ? directive + '\n' + content : directive;
            }
            if (content !== this.localContent) {
                this.localContent = content;
                this.$wire.content = content;
                this.scheduleRender();
            }
        },

        exportChordproHtml() {
            const content = this.localContent;
            if (!content || !content.trim()) { return; }
            try {
                const parser = new ChordSheetJS.ChordProParser();
                const formatter = new ChordSheetJS.HtmlDivFormatter();
                let song = parser.parse(content);
                const transpose = Number(this.chordproTranspose);
                if (transpose !== 0) {
                    song = song.transpose(transpose);
                }
                const body = formatter.format(song);
                const title = this.$wire.title || 'score';
                const fontFamily = this.chordproFontFamily;
                const fontSize = Number(this.chordproFontSize);
                const cols = Number(this.chordproColumns);
                const colStyle = cols > 1 ? `column-count:${cols};column-gap:2rem;` : '';
                const html = `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>${title}</title>
<style>
body{font-family:${fontFamily};font-size:${fontSize}px;margin:2rem;line-height:1.5;color:#111;}
.chord-sheet{max-width:900px;margin:0 auto;${colStyle}}
h1.title{font-size:1.8em;font-weight:bold;margin:0 0 0.2em;}
h2.subtitle{font-size:1.1em;font-weight:normal;margin:0 0 0.2em;}
p.artist{color:#555;margin:0 0 1.5em;}
.paragraph{margin-bottom:1.5rem;break-inside:avoid;}
.paragraph-header{font-weight:bold;font-style:italic;color:#555;margin-bottom:0.4rem;}
.row{display:flex;flex-wrap:wrap;margin-bottom:0.25rem;align-items:flex-end;}
.column{display:flex;flex-direction:column;margin-right:0.1em;}
.chord{font-weight:bold;color:#1d4ed8;min-height:1.3em;white-space:nowrap;}
.lyrics{white-space:pre;}
</style>
</head>
<body>
${body}
</body>
</html>`;
                const blob = new Blob([html], { type: 'text/html;charset=utf-8' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.download = title.replace(/[^a-z0-9]/gi, '_').toLowerCase() + '.html';
                a.href = url;
                a.click();
                URL.revokeObjectURL(url);
            } catch (e) {
                console.error('[score-editor] export error:', e);
            }
        },
    };
}
