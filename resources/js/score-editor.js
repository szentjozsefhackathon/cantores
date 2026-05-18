import { abcMixin } from './score-editor-abc.js';
import { gabcMixin } from './score-editor-gabc.js';
import { chordproMixin } from './score-editor-chordpro.js';
import { aretinoMixin } from './score-editor-aretino.js';

const WEB_FONT_URLS = {
    'Barlow Condensed': 'https://fonts.bunny.net/barlow-condensed/files/barlow-condensed-latin-500-normal.woff2',
};
const fontBase64Cache = {};

async function fetchFontBase64(url) {
    if (fontBase64Cache[url]) { return fontBase64Cache[url]; }
    const res = await fetch(url);
    const buf = await res.arrayBuffer();
    let binary = '';
    new Uint8Array(buf).forEach(b => { binary += String.fromCharCode(b); });
    const b64 = btoa(binary);
    fontBase64Cache[url] = b64;
    return b64;
}

function parsePrimaryFontFamily(fontValue) {
    return (fontValue ?? '').split(',')[0].trim().replace(/['"]/g, '');
}

async function injectWebFontsIntoSvg(svgEl, fontValues) {
    const rules = [];
    for (const value of fontValues) {
        const family = parsePrimaryFontFamily(value);
        const url = WEB_FONT_URLS[family];
        if (!url) { continue; }
        try {
            const b64 = await fetchFontBase64(url);
            rules.push(`@font-face{font-family:'${family}';src:url('data:font/woff2;base64,${b64}')format('woff2');}`);
        } catch (e) {
            console.warn('[score-editor] could not embed font:', family, e);
        }
    }
    if (!rules.length) { return; }
    const style = document.createElementNS('http://www.w3.org/2000/svg', 'style');
    style.textContent = rules.join('');
    let defs = svgEl.querySelector('defs');
    if (!defs) {
        defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
        svgEl.insertBefore(defs, svgEl.firstChild);
    }
    defs.insertBefore(style, defs.firstChild);
}

document.addEventListener('alpine:init', () => {
    Alpine.data('scoreEditor', (config = {}) => ({
        hasPages: false,
        localContent: '',
        clippedWarningText: config.clippedWarningText ?? '',
        clipboardNotSupported: config.clipboardNotSupported ?? '',
        imageCopied: config.imageCopied ?? '',
        failedToCopy: config.failedToCopy ?? '',
        shareLinkCopied: config.shareLinkCopied ?? '',
        linkCopyFailed: config.linkCopyFailed ?? '',
        htmlCopied: config.htmlCopied ?? '',
        plainTextCopied: config.plainTextCopied ?? '',
        copyAsImageText: config.copyAsImageText ?? '',
        exportPngText: config.exportPngText ?? '',
        renderTimer: null,
        scoreSettings: config.scoreSettings ?? {},
        userDefaults: config.userDefaults ?? {},
        copyFeedback: '',
        copyFeedbackTimer: null,
        shareUrl: '',
        shareUrlLoading: false,
        shareModalCopied: false,

        ...abcMixin(),
        ...gabcMixin(),
        ...chordproMixin(),
        ...aretinoMixin(),

        init() {
            console.log('[score-editor] init, exsurge available:', !!window.exsurge, 'format:', this.$wire.format);
            this.applyInitialSettings();
            this.localContent = this.$wire.content;
            this.$watch('$wire.content', (val) => {
                console.log('[score-editor] $wire.content changed, len:', val?.length);
                this.localContent = val;
                this.scheduleRender();
            });
            this.$watch('$wire.format', (val) => {
                console.log('[score-editor] $wire.format changed:', val);
                if (val === 'chordpro') {
                    this.syncChordproTitle(this.$wire.title);
                }
                this.scheduleRender();
            });
            this.$watch('$wire.title', (val) => {
                if (this.$wire.format === 'chordpro') {
                    this.syncChordproTitle(val);
                }
            });
            this.$watch('lyricSize', () => this.scheduleRender());
            this.$watch('staffSize', () => this.scheduleRender());
            this.$watch('lyricFont', () => this.scheduleRender());
            this.$watch('pageRatio', (val) => { this.applyRatioSettings('gabc', val); this.$nextTick(() => this.scheduleRender()); });
            this.$watch('dropCaps', () => this.scheduleRender());
            this.$watch('minLyricWordSpacing', () => this.scheduleRender());
            this.$watch('hyphenWidth', () => this.scheduleRender());
            this.$watch('condensingTolerance', () => this.scheduleRender());
            this.$watch('spaceBetweenSystems', () => this.scheduleRender());
            this.$watch('minSpaceBelowStaff', () => this.scheduleRender());
            this.$watch('zoom', () => this.scheduleRender());
            this.$watch('abcLyricFont', () => this.scheduleRender());
            this.$watch('abcLyricSize', () => this.scheduleRender());
            this.$watch('abcLyricBold', () => this.scheduleRender());
            this.$watch('abcNoteSpacing', () => this.scheduleRender());
            this.$watch('abcStaffSep', () => this.scheduleRender());
            this.$watch('abcVocalSpace', () => this.scheduleRender());
            this.$watch('abcNoClef', () => this.scheduleRender());
            this.$watch('abcPageScale', () => this.scheduleRender());
            this.$watch('abcStemWidth', () => this.scheduleRender());
            this.$watch('abcStaffLineWidth', () => this.scheduleRender());
            this.$watch('abcPageRatio', (val) => { this.applyRatioSettings('abc', val); this.$nextTick(() => this.scheduleRender()); });
            this.$watch('chordproFontSize', () => this.scheduleRender());
            this.$watch('chordproFontFamily', () => this.scheduleRender());
            this.$watch('chordproColumns', () => this.scheduleRender());
            this.$watch('chordproTranspose', () => this.scheduleRender());
            this.$watch('chordproGermanNotation', () => this.scheduleRender());
            this.$watch('aretinoLyricFont', () => this.scheduleRender());
            this.$watch('aretinoLyricSize', () => this.scheduleRender());
            this.$watch('aretinoStaffSize', () => this.scheduleRender());
            this.$watch('aretinoZoom', () => this.scheduleRender());
            this.$watch('aretinoPageRatio', (val) => { this.applyRatioSettings('aretino', val); this.$nextTick(() => this.scheduleRender()); });
            this.$nextTick(() => {
                console.log('[score-editor] nextTick, exsurge available:', !!window.exsurge);
                this.scheduleRender();
            });
        },

        collectSettings() {
            if (this.$wire.format === 'gabc') {
                return {
                    settings: {
                        zoom: Number(this.zoom),
                        lyricSize: Number(this.lyricSize),
                        staffSize: Number(this.staffSize),
                        dropCaps: !!this.dropCaps,
                        lyricFont: this.lyricFont,
                        minLyricWordSpacing: Number(this.minLyricWordSpacing),
                        hyphenWidth: Number(this.hyphenWidth),
                        condensingTolerance: Number(this.condensingTolerance),
                        spaceBetweenSystems: Number(this.spaceBetweenSystems),
                        minSpaceBelowStaff: Number(this.minSpaceBelowStaff),
                    },
                    ratio: this.pageRatio,
                };
            }
            if (this.$wire.format === 'abc') {
                return {
                    settings: {
                        abcLyricFont: this.abcLyricFont,
                        abcLyricSize: Number(this.abcLyricSize),
                        abcLyricBold: !!this.abcLyricBold,
                        abcNoteSpacing: Number(this.abcNoteSpacing),
                        abcStaffSep: Number(this.abcStaffSep),
                        abcVocalSpace: Number(this.abcVocalSpace),
                        abcNoClef: !!this.abcNoClef,
                        abcStemWidth: Number(this.abcStemWidth),
                        abcStaffLineWidth: Number(this.abcStaffLineWidth),
                    },
                    ratio: this.abcPageRatio,
                };
            }
            if (this.$wire.format === 'chordpro') {
                return {
                    settings: {
                        chordproFontSize: Number(this.chordproFontSize),
                        chordproFontFamily: this.chordproFontFamily,
                        chordproColumns: Number(this.chordproColumns),
                        chordproTranspose: Number(this.chordproTranspose),
                        chordproGermanNotation: !!this.chordproGermanNotation,
                    },
                    ratio: 'auto',
                };
            }
            if (this.$wire.format === 'aretino') {
                return {
                    settings: {
                        aretinoLyricFont: this.aretinoLyricFont,
                        aretinoLyricSize: Number(this.aretinoLyricSize),
                        aretinoStaffSize: Number(this.aretinoStaffSize),
                        aretinoZoom: Number(this.aretinoZoom),
                    },
                    ratio: this.aretinoPageRatio,
                };
            }
            return { settings: {}, ratio: 'auto' };
        },

        getVirtualCanvasSize(format) {
            if (format === 'abc') { return { width: 1920, height: null }; }
            const width = 1920;
            const heights = { '16/9': 1080, '4/3': 1440, '1/1': 1920 };
            if (format === 'aretino') {
                return { width, height: heights[this.aretinoPageRatio] ?? null };
            }
            return { width, height: heights[this.pageRatio] ?? null };
        },

        getRenderWidth() {
            return this.getVirtualCanvasSize(this.$wire.format).width;
        },

        applyRatioSettings(format, ratio) {
            const score = (this.scoreSettings && this.scoreSettings[format] && this.scoreSettings[format][ratio]) || null;
            const user = (this.userDefaults && this.userDefaults[format] && this.userDefaults[format][ratio]) || null;
            const merged = { ...(user || {}), ...(score || {}) };
            Object.keys(merged).forEach(k => { if (k in this) { this[k] = merged[k]; } });
        },

        applyInitialSettings() {
            this.applyRatioSettings('gabc', this.pageRatio);
            this.applyRatioSettings('abc', this.abcPageRatio);
            this.applyRatioSettings('chordpro', 'auto');
            this.applyRatioSettings('aretino', this.aretinoPageRatio);
        },

        saveScore() {
            const c = this.collectSettings();
            this.$wire.call('save', c.settings, c.ratio);
        },

        getShareData() {
            const { settings, ratio } = this.collectSettings();
            const format = this.$wire.format;
            const formatSettings = JSON.parse(JSON.stringify((this.scoreSettings || {})[format] || {}));
            formatSettings[ratio] = settings;
            return {
                title: this.$wire.title,
                format,
                content: this.localContent,
                settings: { [format]: formatSettings },
            };
        },

        async openShareModal() {
            this.shareUrl = '';
            this.shareModalCopied = false;
            this.shareUrlLoading = true;
            this.$flux.modal('share-link-modal').show();
            try {
                const data = this.getShareData();
                this.shareUrl = await this.$wire.createShareUrl(data);
            } catch (e) {
                this.showCopyFeedback(this.linkCopyFailed);
            } finally {
                this.shareUrlLoading = false;
            }
        },

        async copyShareLink() {
            if (!this.shareUrl) { return; }
            try {
                await navigator.clipboard.writeText(this.shareUrl);
                this.shareModalCopied = true;
            } catch (e) {
                this.showCopyFeedback(this.linkCopyFailed);
                this.$flux.modal('share-link-modal').close();
            }
        },

        saveAsDefault() {
            const c = this.collectSettings();
            this.$wire.call('saveAsDefault', c.settings, c.ratio, this.$wire.format);
        },

        splitPages(content, format, ratio) {
            const ratioSuffix = { '16/9': '169', '4/3': '43', '1/1': '11' };
            const targetSuffix = ratioSuffix[ratio];
            const isAuto = !targetSuffix;
            const lines = content.split('\n');
            let headerEnd = -1;
            if (format === 'gabc' || format === 'aretino') {
                for (let i = 0; i < lines.length; i++) {
                    if (/^%%\s*$/.test(lines[i])) { headerEnd = i; break; }
                }
            } else {
                for (let i = 0; i < lines.length; i++) {
                    if (/^K:/.test(lines[i])) { headerEnd = i; break; }
                }
            }
            const header = headerEnd >= 0 ? lines.slice(0, headerEnd + 1).join('\n') + '\n' : '';
            const bodyLines = headerEnd >= 0 ? lines.slice(headerEnd + 1) : lines.slice();
            const breakRe = /^\s*%pagebreak(\d*)\s*$/;
            if (isAuto) {
                const stripped = bodyLines.filter(l => !breakRe.test(l)).join('\n');
                return [header + stripped];
            }
            const pages = [];
            let current = [];
            for (const line of bodyLines) {
                const m = line.match(breakRe);
                if (m) {
                    const suffix = m[1];
                    if (suffix === '' || suffix === targetSuffix) {
                        pages.push(current.join('\n'));
                        current = [];
                    }
                    continue;
                }
                current.push(line);
            }
            pages.push(current.join('\n'));
            return pages.map(p => header + p);
        },

        appendClipWarning(pageEl) {
            const warn = document.createElement('div');
            warn.className = 'mt-2 flex justify-center';
            const span = document.createElement('span');
            span.className = 'rounded bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900 dark:text-red-300';
            span.textContent = this.clippedWarningText;
            warn.appendChild(span);
            pageEl.insertAdjacentElement('afterend', warn);
        },

        addPageControls(pageEl, pageIdx, totalPages, format) {
            const bar = document.createElement('div');
            bar.className = 'mt-1 mb-2 flex flex-wrap items-center justify-end gap-2';

            const feedbackSpan = document.createElement('span');
            feedbackSpan.className = 'text-sm text-zinc-600 dark:text-zinc-300';
            feedbackSpan.style.display = 'none';

            const showFeedback = (msg) => {
                feedbackSpan.textContent = msg;
                feedbackSpan.style.display = '';
                clearTimeout(feedbackSpan._timer);
                feedbackSpan._timer = setTimeout(() => { feedbackSpan.style.display = 'none'; }, 2500);
            };

            const clipIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" style="flex-shrink:0"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184"/></svg>';
            const dlIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" style="flex-shrink:0"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>';
            const btnClass = 'inline-flex cursor-pointer items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm font-medium text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800';

            const copyBtn = document.createElement('button');
            copyBtn.type = 'button';
            copyBtn.className = btnClass;
            copyBtn.innerHTML = clipIcon + this.copyAsImageText;
            copyBtn.addEventListener('click', () => this.copyPageImage(pageEl, format, showFeedback));

            const exportBtn = document.createElement('button');
            exportBtn.type = 'button';
            exportBtn.className = btnClass;
            exportBtn.innerHTML = dlIcon + this.exportPngText;
            exportBtn.addEventListener('click', () => this.exportPagePng(pageEl, pageIdx, totalPages, format, this.$wire.title));

            bar.appendChild(feedbackSpan);
            bar.appendChild(copyBtn);
            bar.appendChild(exportBtn);
            pageEl.insertAdjacentElement('afterend', bar);
        },

        scheduleRender() {
            clearTimeout(this.renderTimer);
            this.renderTimer = setTimeout(() => this.renderPreview(), 600);
        },

        renderPreview() {
            if (this.$wire.format === 'abc') {
                this.renderAbcPreview();
                return;
            }
            if (this.$wire.format === 'chordpro') {
                this.renderChordproPreview();
                return;
            }
            if (this.$wire.format === 'aretino') {
                this.renderAretinoPreview();
                return;
            }
            this.renderGabcPreview();
        },

        showCopyFeedback(msg) {
            this.copyFeedback = msg;
            clearTimeout(this.copyFeedbackTimer);
            this.copyFeedbackTimer = setTimeout(() => { this.copyFeedback = ''; }, 2500);
        },

        async svgToCanvas(svgEl) {
            const renderWidth = this.getRenderWidth();
            const scale = 1;
            const margin = 20;
            const viewBox = svgEl.getAttribute('viewBox');
            let svgWidth = renderWidth;
            let svgHeight = renderWidth * 9 / 16;
            let paddedViewBox = null;
            if (viewBox) {
                const parts = viewBox.split(/\s+/).map(Number);
                if (parts.length === 4) {
                    svgWidth = parts[2];
                    svgHeight = parts[3];
                    paddedViewBox = `${parts[0] - margin} ${parts[1] - margin} ${parts[2] + margin * 2} ${parts[3] + margin * 2}`;
                }
            } else {
                const bbox = svgEl.getBBox();
                const w = svgEl.getAttribute('width');
                const h = svgEl.getAttribute('height');
                svgWidth = w ? parseFloat(w) : bbox.width + bbox.x;
                svgHeight = h ? parseFloat(h) : bbox.height + bbox.y;
            }
            const paddedWidth = svgWidth + margin * 2;
            const paddedHeight = svgHeight + margin * 2;
            const clonedSvg = svgEl.cloneNode(true);
            clonedSvg.setAttribute('width', String(paddedWidth));
            clonedSvg.setAttribute('height', String(paddedHeight));
            if (paddedViewBox) {
                clonedSvg.setAttribute('viewBox', paddedViewBox);
            }
            const lyricFont = this.$wire.format === 'aretino' ? this.aretinoLyricFont : this.lyricFont;
            await injectWebFontsIntoSvg(clonedSvg, [lyricFont]);
            const svgData = new XMLSerializer().serializeToString(clonedSvg);
            const svgBlob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
            const url = URL.createObjectURL(svgBlob);
            return new Promise((resolve, reject) => {
                const img = new Image();
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    canvas.width = paddedWidth * scale;
                    canvas.height = paddedHeight * scale;
                    const ctx = canvas.getContext('2d');
                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(0, 0, canvas.width, canvas.height);
                    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                    URL.revokeObjectURL(url);
                    resolve(canvas);
                };
                img.onerror = (e) => { URL.revokeObjectURL(url); reject(e); };
                img.src = url;
            });
        },

        exportPagePng(pageEl, pageIdx, totalPages, format, title) {
            const svgs = Array.from(pageEl.querySelectorAll('svg'));
            if (!svgs.length) { return; }
            const base = title ? title.trim().replace(/[^\p{L}\p{N}\s-]/gu, '').replace(/\s+/g, '-').replace(/-+/g, '-').replace(/^-+|-+$/g, '') || 'score' : 'score';
            const filename = totalPages > 1 ? base + '-page-' + pageIdx + '.cantores.hu.png' : base + '.cantores.hu.png';
            const canvasPromise = format === 'abc' ? this.buildAbcCanvas(svgs) : this.svgToCanvas(svgs[0]);
            canvasPromise.then(canvas => {
                const a = document.createElement('a');
                a.download = filename;
                a.href = canvas.toDataURL('image/png');
                a.click();
            }).catch(e => console.error('[score-editor] export error:', e));
        },

        async copyPageImage(pageEl, format, showFeedback) {
            const svgs = Array.from(pageEl.querySelectorAll('svg'));
            if (!svgs.length) { return; }
            if (!navigator.clipboard || !window.ClipboardItem) {
                showFeedback(this.clipboardNotSupported);
                return;
            }
            try {
                const canvasPromise = format === 'abc' ? this.buildAbcCanvas(svgs) : this.svgToCanvas(svgs[0]);
                const blobPromise = canvasPromise.then(canvas =>
                    new Promise(resolve => canvas.toBlob(resolve, 'image/png'))
                );
                await navigator.clipboard.write([new ClipboardItem({ 'image/png': blobPromise })]);
                showFeedback(this.imageCopied);
            } catch (e) {
                console.error('[score-editor] copy image error:', e);
                showFeedback(this.failedToCopy);
            }
        },

        buildAbcCanvas(svgs) {
            const margin = 20;
            const dims = svgs.map(svg => {
                const vb = svg.getAttribute('viewBox');
                if (vb) {
                    const [x, y, w, h] = vb.split(/\s+/).map(Number);
                    return { x, y, w, h };
                }
                return { x: 0, y: 0, w: 1920, h: 200 };
            });
            const maxW = Math.max(...dims.map(d => d.w));
            const totalH = dims.reduce((sum, d) => sum + d.h, 0);
            const promises = svgs.map((svg, i) => {
                const d = dims[i];
                const clone = svg.cloneNode(true);
                clone.setAttribute('width', String(d.w));
                clone.setAttribute('height', String(d.h));
                clone.setAttribute('viewBox', `${d.x} ${d.y} ${d.w} ${d.h}`);
                return injectWebFontsIntoSvg(clone, [this.abcLyricFont]).then(() => {
                    const blob = new Blob([new XMLSerializer().serializeToString(clone)], { type: 'image/svg+xml;charset=utf-8' });
                    const url = URL.createObjectURL(blob);
                    return new Promise((resolve, reject) => {
                        const img = new Image();
                        img.onload = () => { URL.revokeObjectURL(url); resolve({ img, w: d.w, h: d.h }); };
                        img.onerror = e => { URL.revokeObjectURL(url); reject(e); };
                        img.src = url;
                    });
                });
            });
            return Promise.all(promises).then(results => {
                const canvas = document.createElement('canvas');
                canvas.width = maxW + margin * 2;
                canvas.height = totalH + margin * 2;
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                let y = margin;
                results.forEach(r => {
                    ctx.drawImage(r.img, margin, y, r.w, r.h);
                    y += r.h;
                });
                return canvas;
            });
        },

        fillExample() {
            const examples = {
                abc: `K:D minor
L:1/4
ABAG | A G2 z | A A G (A1/2G1/2) | F2 E2 | ABAG | A G2 z | A A G (A1/2G1/2) | F2 E2 | D E F E | D4 | F F G/ G3/2 | A4 | A A G A | B2 A2 | DEFG | E2 D2 |]
w: Bol-dog-asz-szony a-nyánk, ré-gi nagy pát-_ró-nánk! Nagy ín-ség-ben lé-vén így szó-lít meg_ ha-zánk: Ma-gyar-or-szág-ról, é-des ha-zánk-ról, ne fe-lejt-kez-zél el sze-gény ma-gya-rok-ról!`,
                gabc: `(c3) KY(d)ri(gxfgh)e(h.ivHGh.) *(kvIH'Ghih.) (,) e(gxhvFE'Dgf)lé(e')i(e)son.(d.) (::)
`,
                aretino: `(g2) g h i g. hi h g e_d_ , g hi a'g g. ||
w: Al-le-lu-ja, al-le-lu-ja, al-le-lu-ja.
`,
                chordpro: `{title: Minden, mi él}
{subtitle: K 272}
{soc}
[D]Minden, mi él, csak Téged hirdet, [Hm]Minden dicsér, mert mind a műved.
[G]Azzal, hogy él, ezt zengi Néked: [A]dicsérlek, én, [A7]dicsérlek Téged!
{eoc}
1. Dicsér az ég, Nap, Hold és csillagok, fény és sötét, nap, éj és hajnalok,
dicsér a szél, felhő és hóvihar, a víz s a tűz, megannyi tiszta dallal
Refr.
...
<i>Coda:</i> [D]Dicsérlek én!
`,
            };
            const example = examples[this.$wire.format] ?? '';
            if (!example) { return; }
            this.$wire.content = example;
            this.localContent = example;
            this.scheduleRender();
        },

    }));
});
