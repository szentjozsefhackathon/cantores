import { canvasMeasurer, chordproRows } from './booklet-chordpro.js';
import { packColumns } from './booklet-flow.js';
import { DEFAULT_LYRIC_SIZE_PT, DEFAULT_PAGE_WIDTH_MM, mmToPx, opticalLyricSizePt, ptToPx } from './booklet-geometry.js';
import { markupRuns, runsText } from './chordpro-markup.js';
import { chordStringsOf, displayChord, displayChordsInHtml, displayChordsInText } from './chordpro-notation.js';
import { ensureFontsLoaded } from './svg-fonts.js';
import { stackSvgs } from './svg-stack.js';

let chordSheetJsPromise = null;
function loadChordSheetJS() {
    if (!chordSheetJsPromise) {
        chordSheetJsPromise = import('chordsheetjs').then(m => m.default);
    }
    return chordSheetJsPromise;
}

const DEFAULT_FONT_FAMILY = "'Merriweather'";

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

/**
 * The styles a lyric may carry, and the element each of them becomes.
 *
 * A run's `script` is already named for its own element, so it needs no entry
 * here — see markupElement below.
 *
 * @see chordpro-markup.js, which is where a lyric is cut into styled runs, and
 *      which the booklet's SVG renderer draws from the same way.
 */
const MARKUP_ELEMENT = { bold: 'b', italic: 'i', underline: 'u' };

/**
 * Close and reopen a lyric's inline markup at every chord.
 *
 * chordsheetjs cuts a line into one fragment per chord and formats each into a
 * `<div>` of its own, so `<i>Ave [G]Maria</i>` reaches the browser as an `<i>`
 * that opens in one div and a `</i>` that closes in another. HTML has no such
 * thing: the browser shuts the tag at the end of the first div and throws the
 * stray close away, and only the first half of the phrase comes out italic.
 *
 * Rewriting each fragment so it opens and closes its own tags says the same
 * thing in a form HTML can carry, and leaves the formatters untouched.
 */
function balanceMarkup(song) {
    return rewriteLyrics(song, (lyrics, style) => {
        const { runs, open } = markupRuns(lyrics, style);

        return { lyrics: runs.map(markupElement).join(''), open };
    });
}

/**
 * Drop the markup, leaving the words as they are sung.
 *
 * Plain text has nowhere to put an italic, and `<i>` printed in the middle of a
 * lyric is worse than the formatting being lost.
 */
function stripMarkup(song) {
    return rewriteLyrics(song, (lyrics) => ({ lyrics: runsText(markupRuns(lyrics).runs) }));
}

/**
 * Rewrite every lyric fragment of a parsed song in place.
 *
 * The style a fragment leaves open is handed to the next one on its line, so a
 * rewrite can see markup that runs across a chord; a new line starts afresh.
 *
 * @param {object} song a freshly parsed song, which this mutates
 * @param {(lyrics: string, open: object|undefined) => {lyrics: string, open?: object}} rewrite
 */
function rewriteLyrics(song, rewrite) {
    (song.lines ?? []).forEach((line) => {
        let style;

        (line.items ?? []).forEach((item) => {
            if (typeof item?.lyrics !== 'string' || item.lyrics === '') {
                return;
            }

            const rewritten = rewrite(item.lyrics, style);
            item.lyrics = rewritten.lyrics;
            style = rewritten.open;
        });
    });

    return song;
}

function markupElement(run) {
    const tags = Object.entries(MARKUP_ELEMENT)
        .filter(([style]) => run[style])
        .map(([, tag]) => tag);

    if (run.script) {
        tags.push(run.script);
    }

    return tags.map((tag) => `<${tag}>`).join('')
        + run.text
        + [...tags].reverse().map((tag) => `</${tag}>`).join('');
}

function sanitizeChordproContent(content) {
    return content.replace(/<\/?(sup|sub|i|b|u)>|[<>]/gi, (match) => {
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
 * @param {{german: boolean, transpose: number|string, sanitize?: boolean}} options
 *        `sanitize` guards the HTML paths, where everything but ChordPro's own
 *        `<i>`, `<b>` and `<u>` has to stop being markup before it reaches a
 *        browser. A caller that escapes what it draws — anything engraving to
 *        SVG — wants the text as written instead.
 */
export async function parseChordproSong(content, { german, transpose, sanitize = true }) {
    const ChordSheetJS = await loadChordSheetJS();
    const song = new ChordSheetJS.ChordProParser().parse(
        sanitize ? sanitizeChordproContent(content) : content,
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

    const song = await parseChordproSong(content, { german, transpose, sanitize: false });
    const family = safeFontFamily(fontFamily);

    const rows = chordproIncipitRows(song.bodyParagraphs ?? song.paragraphs ?? [], {
        fontSize: INCIPIT_FONT_SIZE,
        fontFamily: family,
        layoutWidth: INCIPIT_LAYOUT_WIDTH,
        measure: canvasMeasurer(family, INCIPIT_FONT_SIZE),
        spell: (chord) => displayChord(chord, german),
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

export { balanceMarkup, stripMarkup };

/**
 * How wide a chord sheet is engraved for export.
 *
 * The same text width every other format here is drawn to — A4 with the margins
 * a binder needs — so a chord sheet placed beside an engraved score in a layout
 * measures the same across.
 */
export const CHORDPRO_PAGE_WIDTH_PX = mmToPx(DEFAULT_PAGE_WIDTH_MM);

/** The gutter between two columns, as a multiple of the lyric size. */
const COLUMN_GAP = 2;

/** As many columns as the toolbar can ask for, and never fewer than one. */
function columnCount(columns) {
    const count = Math.round(Number(columns) || 1);

    return Math.min(Math.max(count, 1), 4);
}

/**
 * The measurements a chord sheet's page is built from.
 *
 * Wanted twice, and in this order: the column width decides how the rows wrap,
 * and the rows cannot be dealt into columns until they have been laid out.
 *
 * @param {{columns: number|string, fontSize: number, pageWidth?: number}} options
 * @returns {{count: number, gap: number, columnWidth: number, pageWidth: number}}
 */
export function chordproPageMetrics({ columns, fontSize, pageWidth = CHORDPRO_PAGE_WIDTH_PX }) {
    const count = columnCount(columns);
    const gap = fontSize * COLUMN_GAP;

    return { count, gap, pageWidth, columnWidth: (pageWidth - gap * (count - 1)) / count };
}

/**
 * Where each row of a laid-out chord sheet goes on the page.
 *
 * One page, as tall as the song needs, rather than a run of A4s: a sheet
 * exported into a layout is placed by hand, and a page break the exporter chose
 * would only be in the way. Columns are the sheet's own business, though — a
 * hymn set in two on screen is set in two here — so the rows are dealt into
 * that many, balanced by height.
 *
 * @param {Array<{height: number, spaceBefore?: number, keepWithNext?: boolean, svg: string}>} rows
 * @param {{count: number, gap: number, columnWidth: number, pageWidth: number}} metrics
 * @returns {{placements: Array<{row: object, x: number, y: number, scale: number}>, width: number, height: number}}
 */
export function chordproPageLayout(rows, metrics) {
    const { count, gap, columnWidth, pageWidth } = metrics;
    const placements = [];
    let height = 0;

    packColumns(rows, count).forEach((column, index) => {
        column.items.forEach(({ block, y }) => {
            placements.push({ row: block, x: index * (columnWidth + gap), y, scale: 1 });
        });

        height = Math.max(height, column.height);
    });

    return { placements, width: pageWidth, height };
}

/**
 * A whole chord sheet, drawn as one SVG page.
 *
 * The same engraver the booklet and the incipit already use, which is the
 * point: the picture that comes out is the sheet the reader was looking at,
 * with its markup, its transposition and its note names applied — and, being
 * vector text at a stated physical size, it opens in a layout program as type
 * rather than as a screenshot.
 *
 * The preview's zoom is deliberately not consulted. It magnifies the screen;
 * the page is life size.
 *
 * Returns null when there is nothing sung to draw.
 *
 * @param {string} content raw ChordPro
 * @param {{german: boolean, transpose: number|string, fontFamily: string, fontSize: number, columns?: number|string}} options
 * @returns {Promise<SVGElement|null>}
 */
export async function renderChordproPageSvg(content, { german, transpose, fontFamily, fontSize, columns = 1 }) {
    if (!content || !content.trim()) { return null; }

    const song = await parseChordproSong(content, { german, transpose, sanitize: false });
    const family = safeFontFamily(fontFamily);
    const metrics = chordproPageMetrics({ columns, fontSize });

    // Every column is placed at a measured width, and a face the browser has
    // not loaded yet measures as whatever it falls back to: the words would be
    // engraved at one set of widths and drawn at another.
    await ensureFontsLoaded([family], fontSize);

    const rows = chordproRows(song.bodyParagraphs ?? song.paragraphs ?? [], {
        fontSize,
        fontFamily: family,
        layoutWidth: metrics.columnWidth,
        measure: canvasMeasurer(family, fontSize),
        spell: (chord) => displayChord(chord, german),
    });

    if (rows.length === 0) { return null; }

    const { placements, width, height } = chordproPageLayout(rows, metrics);
    const fragments = placements.map(
        ({ row }) => new DOMParser().parseFromString(row.svg, 'image/svg+xml').documentElement,
    );

    const { svg } = stackSvgs(fragments, {
        placements: placements.map(({ x, y, scale }) => ({ x, y, scale })),
        intrinsicSize: true,
        viewBox: { x: 0, y: 0, w: width, h: height },
    });

    return svg;
}

/**
 * The face a chord sheet is set in unless someone says otherwise.
 *
 * The screen-first serif of the set rather than the book faces the engraved
 * formats use: a chord sheet is read off a phone or a tablet propped on a stand
 * as often as off paper, and Merriweather was drawn for exactly that.
 */
const CHORDPRO_DEFAULT_FONT = 'Merriweather';

function round(value, places) {
    const factor = 10 ** places;

    return Math.round(value * factor) / factor;
}

export function chordproMixin() {
    return {
        /**
         * A chord sheet is read off a music stand, so it starts at the size a
         * hymnal is printed in — 9 pt of Merriweather, which is the same height
         * of letter as the 11 pt of Alegreya every other editor opens at, in the
         * px the container is styled with. A booklet says nothing about this:
         * there the size comes from the booklet's own lyric size, in points, per
         * page.
         */
        chordproFontSize: round(ptToPx(opticalLyricSizePt(DEFAULT_LYRIC_SIZE_PT, CHORDPRO_DEFAULT_FONT)), 4),
        chordproFontFamily: `'${CHORDPRO_DEFAULT_FONT}'`,
        chordproColumns: 1,
        chordproTranspose: 0,
        chordproGermanNotation: true,
        /**
         * The preview is text in a box rather than an engraving on a page, so
         * nothing about it is life size to begin with — and 9 pt of it on a
         * screen read at arm's length is too small to work in. The zoom is a
         * magnifying glass over the preview alone: it never reaches the export,
         * the score views or a booklet page.
         */
        chordproZoom: 120,
        chordproFields: ['chordproFontSize', 'chordproFontFamily', 'chordproColumns', 'chordproTranspose', 'chordproGermanNotation', 'chordproZoom'],

        parseChordpro(content) {
            return parseChordproSong(content, {
                german: this.chordproGermanNotation,
                transpose: this.chordproTranspose,
            });
        },

        /** German renders B flat as `B`; this app spells it `Bb`. */
        spellChordsInHtml(html) {
            return displayChordsInHtml(html, this.chordproGermanNotation);
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
                const song = balanceMarkup(await this.parseChordpro(content));
                const html = this.spellChordsInHtml(new ChordSheetJS.HtmlDivFormatter({ normalizeChordSuffix: false }).format(song));
                const pageEl = document.createElement('div');
                pageEl.className = 'chordpro-preview overflow-auto rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900';
                pageEl.style.fontFamily = this.chordproFontFamily;
                // Everything inside is sized in em, so magnifying the root size
                // magnifies the whole sheet.
                const zoom = (Number(this.chordproZoom) || 100) / 100;
                pageEl.style.fontSize = Number(this.chordproFontSize) * zoom + 'px';
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

        /**
         * The sheet engraved, in a detached element the export helpers can read.
         *
         * Every one of them takes a page the same way — an element with an
         * `<svg>` in it — so producing one is all a chord sheet needs to reach
         * the exports the engraved formats have always had.
         */
        async chordproPageElement() {
            const svg = await renderChordproPageSvg(this.localContent, {
                german: this.chordproGermanNotation,
                transpose: this.chordproTranspose,
                fontFamily: this.chordproFontFamily,
                fontSize: Number(this.chordproFontSize),
                columns: this.chordproColumns,
            });

            if (!svg) { return null; }

            const page = document.createElement('div');
            page.appendChild(svg);

            return page;
        },

        async copyChordproImage() {
            try {
                const page = await this.chordproPageElement();
                if (page) {
                    await this.copyPageImage(page, 'chordpro', (message) => this.showCopyFeedback(message));
                }
            } catch (e) {
                console.error('[score-editor] chordpro copy image error:', e);
                this.showCopyFeedback(this.failedToCopy);
            }
        },

        async exportChordproPng() {
            const page = await this.chordproPageElement();

            if (page) {
                this.exportPagePng(page, 1, 1, 'chordpro', this.$wire.title);
            }
        },

        async exportChordproSvg() {
            const page = await this.chordproPageElement();

            if (page) {
                await this.exportPageSvg(page, 1, 1, 'chordpro', this.$wire.title);
            }
        },

        async exportChordproPdf() {
            const page = await this.chordproPageElement();

            if (page) {
                await this.exportDocumentPdf('chordpro', this.$wire.title, [page]);
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
                const song = stripMarkup(await this.parseChordpro(content));
                const rendered = new ChordSheetJS.TextFormatter({ normalizeChordSuffix: false }).format(song);
                const text = displayChordsInText(rendered, chordStringsOf(song), this.chordproGermanNotation);
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
                const song = balanceMarkup(await this.parseChordpro(content));
                const body = this.spellChordsInHtml(new ChordSheetJS.HtmlTableFormatter({ normalizeChordSuffix: false }).format(song));
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
td{vertical-align:bottom;padding:0;}
.chord{font-weight:bold;color:#1d4ed8;min-height:1.3em;white-space:nowrap;}
.annotation{font-weight:bold;color:#555;white-space:nowrap;}
.chord:not(:empty),.annotation{padding-right:0.4em;}
.lyrics{white-space:pre;}
sup,sub{font-size:0.7em;line-height:0;}
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
                const song = balanceMarkup(await this.parseChordpro(content));
                const body = this.spellChordsInHtml(new ChordSheetJS.HtmlDivFormatter({ normalizeChordSuffix: false }).format(song));
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
.column{display:flex;flex-direction:column;}
.chord{font-weight:bold;color:#1d4ed8;white-space:nowrap;}
.annotation{font-weight:bold;color:#555;white-space:nowrap;}
.chord:not(:empty),.annotation{padding-right:0.4em;}
.row:has(.chord:not(:empty), .annotation) .chord{min-height:1.3em;}
.lyrics{white-space:pre;}
sup,sub{font-size:0.7em;line-height:0;}
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
