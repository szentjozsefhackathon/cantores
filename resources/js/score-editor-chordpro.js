import { canvasMeasurer, chordproRows } from './booklet-chordpro.js';
import { chordStringsOf, spellFlatB, spellFlatBInHtml, spellFlatBInText } from './chordpro-notation.js';
import { stackSvgs } from './svg-stack.js';

let chordSheetJsPromise = null;
function loadChordSheetJS() {
    if (!chordSheetJsPromise) {
        chordSheetJsPromise = import('chordsheetjs').then(m => m.default);
    }
    return chordSheetJsPromise;
}

const DEFAULT_FONT_FAMILY = "'Lora'";

function safeFontFamily(value) {
    return /^[a-zA-Z0-9 ',\-]+$/.test(value) ? value : DEFAULT_FONT_FAMILY;
}

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function sanitizeChordproContent(content) {
    return content.replace(/<\/?(i|b|u)>|[<>]/gi, (match) => {
        if (match.length > 1) { return match; }
        return match === '<' ? '&lt;' : '&gt;';
    });
}


/**
 * Parse a sheet and apply the transpose, in the note names the user reads in.
 *
 * chordsheetjs speaks German natively: with `notation: 'german'` a `B` is B flat
 * and an `H` is B natural, both on the way in and in everything it renders —
 * transposition included, which is the part a text substitution on the output
 * could never get right, since it never knew that A + 1 is B and not A#.
 *
 * @param {string} content raw ChordPro
 * @param {{german: boolean, transpose: number|string}} options
 */
export async function parseChordproSong(content, { german, transpose }) {
    const ChordSheetJS = await loadChordSheetJS();
    const song = new ChordSheetJS.ChordProParser().parse(
        sanitizeChordproContent(content),
        german ? { notation: 'german' } : {},
    );

    const steps = Number(transpose) || 0;

    return steps === 0 ? song : song.transpose(steps);
}

/** How many lines of a sheet stand in for it as a thumbnail. */
const INCIPIT_ROWS = 3;

/** The size the incipit is engraved at, in the units of its own viewBox. */
const INCIPIT_FONT_SIZE = 40;

/**
 * How wide the sheet is laid out, in those same units.
 *
 * The incipit pipeline keeps the left half of what it renders — for engraved
 * formats that is the opening of the first staff — so the drawing is twice this
 * wide and the right half is left empty on purpose: what survives the crop is
 * then exactly the lines laid out here, at a size chosen for them rather than
 * whatever width the longest line happened to have.
 */
const INCIPIT_LAYOUT_WIDTH = 900;

/**
 * The rows a chord sheet's incipit is drawn from: its opening lines, chords
 * over the syllables they belong to.
 *
 * Section labels ("Verse 1") and `{comment}` lines are dropped rather than
 * counted, because a thumbnail three lines tall cannot spend one of them on a
 * heading — what tells two arrangements apart is the words and the chords.
 *
 * @param {Array<{lines: Array<{items: Array}>}>} paragraphs chordsheetjs Paragraphs
 * @param {object} options as chordproRows takes them
 * @returns {Array<{height: number, svg: string}>}
 */
export function chordproIncipitRows(paragraphs, options) {
    const sung = (paragraphs ?? [])
        .map((paragraph) => ({ lines: (paragraph.lines ?? []).filter(isSungLine) }))
        .filter((paragraph) => paragraph.lines.length > 0);

    return chordproRows(sung, options).slice(0, INCIPIT_ROWS);
}

/** Whether a line carries anything sung, as opposed to a directive alone. */
function isSungLine(line) {
    return (line.items ?? []).some((item) => (
        (typeof item?.lyrics === 'string' && item.lyrics.trim() !== '')
        || (typeof item?.chords === 'string' && item.chords.trim() !== '')
    ));
}

/**
 * The opening of a chord sheet, drawn as one SVG element for the incipit.
 *
 * Returns null when there is nothing sung to draw — an empty editor, or a file
 * of directives — so the caller leaves the score without a thumbnail rather
 * than storing a blank picture.
 *
 * @param {string} content raw ChordPro
 * @param {{german: boolean, transpose: number|string, fontFamily: string}} options
 * @returns {Promise<SVGElement|null>}
 */
export async function renderChordproIncipitSvg(content, { german, transpose, fontFamily }) {
    if (!content || !content.trim()) { return null; }

    const song = await parseChordproSong(content, { german, transpose });
    const family = safeFontFamily(fontFamily);

    const rows = chordproIncipitRows(song.bodyParagraphs ?? song.paragraphs ?? [], {
        fontSize: INCIPIT_FONT_SIZE,
        fontFamily: family,
        layoutWidth: INCIPIT_LAYOUT_WIDTH,
        measure: canvasMeasurer(family, INCIPIT_FONT_SIZE),
        spell: german ? spellFlatB : undefined,
    });

    if (rows.length === 0) { return null; }

    const height = rows.reduce((total, row) => total + row.height, 0);
    const fragments = rows.map((row) => new DOMParser().parseFromString(row.svg, 'image/svg+xml').documentElement);

    const { svg } = stackSvgs(fragments, {
        intrinsicSize: true,
        viewBox: { x: 0, y: 0, w: INCIPIT_LAYOUT_WIDTH * 2, h: height },
    });

    return svg;
}

export function chordproMixin() {
    return {
        chordproFontSize: 14,
        chordproFontFamily: "'Lora'",
        chordproColumns: 1,
        chordproTranspose: 0,
        chordproGermanNotation: true,
        chordproFields: ['chordproFontSize', 'chordproFontFamily', 'chordproColumns', 'chordproTranspose', 'chordproGermanNotation'],

        parseChordpro(content) {
            return parseChordproSong(content, {
                german: this.chordproGermanNotation,
                transpose: this.chordproTranspose,
            });
        },

        /** German renders B flat as `B`; this app spells it `Bb`. */
        spellChordsInHtml(html) {
            return this.chordproGermanNotation ? spellFlatBInHtml(html) : html;
        },

        async renderChordproPreview() {
            const container = this.$refs.chordproPreview;
            if (!container) { return; }
            container.innerHTML = '';
            this.hasPages = false;
            const content = this.localContent;
            if (!content || !content.trim()) { return; }
            try {
                const ChordSheetJS = await loadChordSheetJS();
                const song = await this.parseChordpro(content);
                const html = this.spellChordsInHtml(new ChordSheetJS.HtmlDivFormatter().format(song));
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

        async copyChordproPlainText() {
            if (!navigator.clipboard) {
                this.showCopyFeedback(this.clipboardNotSupported);
                return;
            }
            const content = this.localContent;
            if (!content || !content.trim()) { return; }
            try {
                const ChordSheetJS = await loadChordSheetJS();
                const song = await this.parseChordpro(content);
                const rendered = new ChordSheetJS.TextFormatter().format(song);
                const text = this.chordproGermanNotation
                    ? spellFlatBInText(rendered, chordStringsOf(song))
                    : rendered;
                navigator.clipboard.writeText(text)
                    .then(() => this.showCopyFeedback(this.plainTextCopied))
                    .catch(() => this.showCopyFeedback(this.failedToCopy));
            } catch (e) {
                console.error('[score-editor] copy plain text error:', e);
                this.showCopyFeedback(this.failedToCopy);
            }
        },

        async copyChordproHtml() {
            if (!navigator.clipboard || !window.ClipboardItem) {
                this.showCopyFeedback(this.clipboardNotSupported);
                return;
            }
            const content = this.localContent;
            if (!content || !content.trim()) { return; }
            try {
                const ChordSheetJS = await loadChordSheetJS();
                const song = await this.parseChordpro(content);
                const body = this.spellChordsInHtml(new ChordSheetJS.HtmlTableFormatter().format(song));
                const fontFamily = safeFontFamily(this.chordproFontFamily);
                const fontSize = Number(this.chordproFontSize);
                const cols = Number(this.chordproColumns);
                const colStyle = cols > 1 ? `column-count:${cols};column-gap:2rem;` : '';
                const html = `<!DOCTYPE html>
<html><head><meta charset="UTF-8"><style>
body{font-family:${fontFamily};font-size:${fontSize}px;margin:0;line-height:1.5;color:#111;}
.chord-sheet{${colStyle}}
h1.title{font-size:1.8em;font-weight:bold;margin:0 0 0.2em;}
h2.subtitle{font-size:1.1em;font-weight:normal;margin:0 0 0.2em;}
p.artist{color:#555;margin:0 0 1.5em;}
.paragraph{margin-bottom:1.5rem;break-inside:avoid;}
.paragraph-header{font-weight:bold;font-style:italic;color:#555;margin-bottom:0.4rem;}
table{border-collapse:collapse;margin-bottom:0.25rem;}
td.column{vertical-align:bottom;padding-right:0.1em;}
.chord{font-weight:bold;color:#1d4ed8;min-height:1.3em;white-space:nowrap;}
.lyrics{white-space:pre;}
</style></head><body>${body}</body></html>`;
                const blob = new Blob([html], { type: 'text/html' });
                navigator.clipboard.write([new ClipboardItem({ 'text/html': blob })])
                    .then(() => this.showCopyFeedback(this.htmlCopied))
                    .catch(() => this.showCopyFeedback(this.failedToCopy));
            } catch (e) {
                console.error('[score-editor] copy html error:', e);
                this.showCopyFeedback(this.failedToCopy);
            }
        },

        async exportChordproHtml() {
            const content = this.localContent;
            if (!content || !content.trim()) { return; }
            try {
                const ChordSheetJS = await loadChordSheetJS();
                const song = await this.parseChordpro(content);
                const body = this.spellChordsInHtml(new ChordSheetJS.HtmlDivFormatter().format(song));
                const title = this.$wire.title || 'score';
                const fontFamily = safeFontFamily(this.chordproFontFamily);
                const fontSize = Number(this.chordproFontSize);
                const cols = Number(this.chordproColumns);
                const colStyle = cols > 1 ? `column-count:${cols};column-gap:2rem;` : '';
                const html = `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>${escapeHtml(title)}</title>
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
.chord{font-weight:bold;color:#1d4ed8;white-space:nowrap;}
.row:has(.chord:not(:empty)) .chord{min-height:1.3em;}
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
