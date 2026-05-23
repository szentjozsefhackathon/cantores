import { abcMixin, ABC_RATIO_DEFAULTS } from './score-editor-abc.js';
import { gabcMixin } from './score-editor-gabc.js';
import { chordproMixin } from './score-editor-chordpro.js';
import { aretinoMixin } from './score-editor-aretino.js';

const LATIN_EXT = 'U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF';
const LATIN = 'U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD';

const WEB_FONTS = {
    'EB Garamond': [
        { style: 'normal', weight: '400', unicodeRange: LATIN_EXT, url: '/fonts/eb-garamond-latin-ext-400.woff2' },
        { style: 'normal', weight: '400', unicodeRange: LATIN,     url: '/fonts/eb-garamond-latin-400.woff2' },
        { style: 'italic', weight: '400', unicodeRange: LATIN_EXT, url: '/fonts/eb-garamond-latin-ext-400i.woff2' },
        { style: 'italic', weight: '400', unicodeRange: LATIN,     url: '/fonts/eb-garamond-latin-400i.woff2' },
        { style: 'normal', weight: '700', unicodeRange: LATIN_EXT, url: '/fonts/eb-garamond-latin-ext-700.woff2' },
        { style: 'normal', weight: '700', unicodeRange: LATIN,     url: '/fonts/eb-garamond-latin-700.woff2' },
        { style: 'italic', weight: '700', unicodeRange: LATIN_EXT, url: '/fonts/eb-garamond-latin-ext-700i.woff2' },
        { style: 'italic', weight: '700', unicodeRange: LATIN,     url: '/fonts/eb-garamond-latin-700i.woff2' },
    ],
    'Lora': [
        { style: 'normal', weight: '400', unicodeRange: LATIN_EXT, url: '/fonts/lora-latin-ext-400.woff2' },
        { style: 'normal', weight: '400', unicodeRange: LATIN,     url: '/fonts/lora-latin-400.woff2' },
        { style: 'italic', weight: '400', unicodeRange: LATIN_EXT, url: '/fonts/lora-latin-ext-400i.woff2' },
        { style: 'italic', weight: '400', unicodeRange: LATIN,     url: '/fonts/lora-latin-400i.woff2' },
        { style: 'normal', weight: '700', unicodeRange: LATIN_EXT, url: '/fonts/lora-latin-ext-700.woff2' },
        { style: 'normal', weight: '700', unicodeRange: LATIN,     url: '/fonts/lora-latin-700.woff2' },
        { style: 'italic', weight: '700', unicodeRange: LATIN_EXT, url: '/fonts/lora-latin-ext-700i.woff2' },
        { style: 'italic', weight: '700', unicodeRange: LATIN,     url: '/fonts/lora-latin-700i.woff2' },
    ],
    'Inter': [
        { style: 'normal', weight: '100 900', unicodeRange: LATIN_EXT, url: '/fonts/inter-latin-ext.woff2' },
        { style: 'normal', weight: '100 900', unicodeRange: LATIN,     url: '/fonts/inter-latin.woff2' },
    ],
    'Barlow Condensed': [
        { style: 'normal', weight: '500', unicodeRange: LATIN_EXT, url: '/fonts/barlow-condensed-latin-ext-500.woff2' },
        { style: 'normal', weight: '500', unicodeRange: LATIN,     url: '/fonts/barlow-condensed-latin-500.woff2' },
        { style: 'normal', weight: '700', unicodeRange: LATIN_EXT, url: '/fonts/barlow-condensed-latin-ext-700.woff2' },
        { style: 'normal', weight: '700', unicodeRange: LATIN,     url: '/fonts/barlow-condensed-latin-700.woff2' },
    ],
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
    const seenFamilies = new Set();
    for (const value of fontValues) {
        const family = parsePrimaryFontFamily(value);
        if (seenFamilies.has(family)) { continue; }
        const descriptors = WEB_FONTS[family];
        if (!descriptors) { continue; }
        seenFamilies.add(family);
        for (const d of descriptors) {
            try {
                const b64 = await fetchFontBase64(d.url);
                rules.push(
                    `@font-face{font-family:'${family}';font-style:${d.style};font-weight:${d.weight};` +
                    `unicode-range:${d.unicodeRange};src:url('data:font/woff2;base64,${b64}')format('woff2');}`
                );
            } catch (e) {
                console.warn('[score-editor] could not embed font:', family, d.url, e);
            }
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
        isContentUserModified: false,
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
        exportSvgText: config.exportSvgText ?? '',
        fullscreenText: config.fullscreenText ?? '',
        renderTimer: null,
        scoreSettings: config.scoreSettings ?? {},
        userDefaults: config.userDefaults ?? {},
        tempSettings: {},
        copyFeedback: '',
        copyFeedbackTimer: null,
        shareUrl: '',
        shareUrlLoading: false,
        shareModalCopied: false,

        ...abcMixin(),
        ...gabcMixin(),
        ...chordproMixin(),
        ...aretinoMixin(),

        minimalExamples: {
            abc: 'K:C\nL:1/4\nC D E|]\nw: Glo-ri-a',
            gabc: '(c3) Glo(f)ri(g)a.(h.) (::)\n',
            aretino: '(g2) g h i ||\nw: Glo-ri-a\n',
            chordpro: '{title: }\n[C]Glo-ri-[G]a [C]Deo\n',
        },

        fillMinimalExample() {
            const example = this.minimalExamples[this.$wire.format] ?? '';
            if (!example) { return; }
            this.$wire.content = example;
            this.localContent = example;
            this.scheduleRender();
        },

        init() {
            console.log('[score-editor] init, exsurge available:', !!window.exsurge, 'format:', this.$wire.format);
            this.applyInitialSettings();
            this.localContent = this.$wire.content;
            if (this.localContent.trim() === '') {
                this.isContentUserModified = false;
                this.fillMinimalExample();
            } else {
                this.isContentUserModified = true;
            }
            this.$watch('$wire.content', (val) => {
                this.localContent = val;
                this.scheduleRender();
            });
            this.$watch('$wire.format', (val) => {
                if (!this.isContentUserModified) {
                    this.fillMinimalExample();
                }
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
            this.$watch('pageRatio', (val, old) => { this.captureCurrentSettings('gabc', old); this.applyRatioSettings('gabc', val); this.$nextTick(() => this.scheduleRender()); });
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
            this.$watch('abcPageWidth', () => this.scheduleRender());
            this.$watch('abcStemWidth', () => this.scheduleRender());
            this.$watch('abcStaffLineWidth', () => this.scheduleRender());
            this.$watch('abcZoom', () => this.scheduleRender());
            this.$watch('abcTranspose', () => this.scheduleRender());
            this.$watch('abcPageRatio', (val, old) => { this.captureCurrentSettings('abc', old); this.applyRatioSettings('abc', val); this.$nextTick(() => this.scheduleRender()); });
            this.$watch('chordproFontSize', () => this.scheduleRender());
            this.$watch('chordproFontFamily', () => this.scheduleRender());
            this.$watch('chordproColumns', () => this.scheduleRender());
            this.$watch('chordproTranspose', () => this.scheduleRender());
            this.$watch('chordproGermanNotation', () => this.scheduleRender());
            this.$watch('aretinoLyricFont', () => this.scheduleRender());
            this.$watch('aretinoLyricSize', () => this.scheduleRender());
            this.$watch('aretinoStaffSize', () => this.scheduleRender());
            this.$watch('aretinoZoom', () => this.scheduleRender());
            this.$watch('aretinoStaffWidth', () => this.scheduleRender());
            this.$watch('aretinoStaffGap', () => this.scheduleRender());
            this.$watch('aretinoHideRepeatClef', () => this.scheduleRender());
            this.$watch('aretinoPageRatio', (val, old) => { this.captureCurrentSettings('aretino', old); this.applyRatioSettings('aretino', val); this.$nextTick(() => this.scheduleRender()); });
            this.$nextTick(() => {
                this.scheduleRender();
                this.initResponsiveResizeObservers();
            });
        },

        // Re-render the active format when its preview container resizes, but
        // only in 'responsive' mode (where layout width tracks the container).
        _resizeObservers: [],
        _resizeTimer: null,
        initResponsiveResizeObservers() {
            if (!window.ResizeObserver) { return; }
            const refs = [this.$refs.preview, this.$refs.abcPreview, this.$refs.aretinoPreview];
            this._resizeObservers = [];
            refs.forEach((container) => {
                if (!container) { return; }
                const ro = new ResizeObserver((entries) => {
                    if (!this.isResponsiveRatio(this.ratioForFormat(this.$wire.format))) { return; }
                    const width = entries[0]?.contentRect?.width ?? 0;
                    if (width === 0) { return; }
                    clearTimeout(this._resizeTimer);
                    this._resizeTimer = setTimeout(() => this.renderPreview(), 300);
                });
                ro.observe(container);
                this._resizeObservers.push(ro);
            });
        },

        destroy() {
            this._resizeObservers.forEach(ro => ro.disconnect());
            this._resizeObservers = [];
            clearTimeout(this._resizeTimer);
            this._aretinoResizeObserver?.disconnect();
            this._aretinoResizeObserver = null;
            clearTimeout(this._aretinoResizeTimer);
        },

        // Paper & Responsive share one stored settings bucket; legacy scores
        // stored the paper-equivalent under 'auto'. Reads fall back across both
        // keys (see applyRatioSettings); writes always use the canonical 'paper'.
        effectiveRatioKey(ratio) {
            if (ratio === 'responsive' || ratio === 'auto') { return 'paper'; }
            return ratio;
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
                    ratio: this.effectiveRatioKey(this.pageRatio),
                };
            }
            if (this.$wire.format === 'abc') {
                return {
                    settings: {
                        abcLyricFont: this.abcLyricFont,
                        abcLyricSize: Number(this.abcLyricSize),
                        abcLyricBold: !!this.abcLyricBold,
                        abcPageScale: Number(this.abcPageScale),
                        abcPageWidth: Number(this.abcPageWidth),
                        abcNoteSpacing: Number(this.abcNoteSpacing),
                        abcStaffSep: Number(this.abcStaffSep),
                        abcVocalSpace: Number(this.abcVocalSpace),
                        abcNoClef: !!this.abcNoClef,
                        abcStemWidth: Number(this.abcStemWidth),
                        abcStaffLineWidth: Number(this.abcStaffLineWidth),
                        abcZoom: Number(this.abcZoom),
                    },
                    ratio: this.effectiveRatioKey(this.abcPageRatio),
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
                        aretinoStaffWidth: Number(this.aretinoStaffWidth),
                        aretinoStaffGap: Number(this.aretinoStaffGap),
                        aretinoHideRepeatClef: !!this.aretinoHideRepeatClef,
                    },
                    ratio: this.effectiveRatioKey(this.aretinoPageRatio),
                };
            }
            return { settings: {}, ratio: 'auto' };
        },

        ratioForFormat(format) {
            if (format === 'abc') { return this.abcPageRatio; }
            if (format === 'aretino') { return this.aretinoPageRatio; }
            return this.pageRatio;
        },

        isFixedRatio(ratio) {
            return ratio === '16/9' || ratio === '4/3' || ratio === '1/1';
        },

        isResponsiveRatio(ratio) {
            return ratio === 'responsive';
        },

        isPaperRatio(ratio) {
            return ratio === 'paper' || ratio === 'auto';
        },

        getVirtualCanvasSize(format) {
            const ratio = this.ratioForFormat(format);
            if (format === 'aretino') {
                // Projector screens: constant height, width varies by ratio.
                const screens = {
                    '16/9': { width: 960, height: 540 },
                    '4/3': { width: 720, height: 540 },
                    '1/1': { width: 540, height: 540 },
                };
                return screens[ratio] ?? { width: 1920, height: null };
            } else if (format === 'abc') {
                const screens = {
                    '16/9': { width: 1920, height: 1080 },
                    '4/3': { width: 1440, height: 1080 },
                    '1/1': { width: 1080, height: 1080 },
                };
                return screens[ratio] ?? { width: 1920, height: null };

            }

            // GABC: constant width, height varies by ratio.
            const width = 1920;
            const heights = { '16/9': 1080, '4/3': 1440, '1/1': 1920 };
            return { width, height: heights[ratio] ?? null };
        },

        getRenderWidth() {
            return this.getVirtualCanvasSize(this.$wire.format).width;
        },

        // Projector-screen frame shared by every fixed-ratio (16/9, 4/3, 1/1)
        // preview across formats: dark bezel, rounded corners, drop shadow.
        applyProjectorFrame(pageEl, ratio) {
            pageEl.className = 'overflow-hidden bg-white';
            pageEl.style.aspectRatio = ratio;
            pageEl.style.width = '100%';
            pageEl.style.border = '8px solid #374151';
            pageEl.style.borderRadius = '4px';
            pageEl.style.boxShadow = '0 8px 32px rgba(0,0,0,0.45)';
        },

        getFormatDefaults(format) {
            if (format === 'gabc') { const m = gabcMixin(); return { fields: m.gabcFields, defaults: m }; }
            if (format === 'abc') { const m = abcMixin(); return { fields: m.abcFields, defaults: m }; }
            if (format === 'chordpro') { const m = chordproMixin(); return { fields: m.chordproFields, defaults: m }; }
            if (format === 'aretino') { const m = aretinoMixin(); return { fields: m.aretinoFields, defaults: m }; }
            return { fields: [], defaults: {} };
        },

        captureCurrentSettings(format, ratio) {
            const effectiveRatio = this.effectiveRatioKey(ratio);
            const ratioFields = new Set(['pageRatio', 'abcPageRatio', 'aretinoPageRatio']);
            const { fields } = this.getFormatDefaults(format);
            const snap = {};
            fields.forEach(f => { if (!ratioFields.has(f) && f in this) { snap[f] = this[f]; } });
            if (!this.tempSettings[format]) { this.tempSettings[format] = {}; }
            this.tempSettings[format][effectiveRatio] = snap;
        },

        // Returns the stored settings bucket for a format+ratio. The paper
        // bucket is read from both 'paper' and the legacy 'auto' key so older
        // GABC/ABC scores keep loading their saved values.
        readRatioBucket(store, format, effectiveRatio) {
            if (!store || !store[format]) { return null; }
            const fmt = store[format];
            if (effectiveRatio === 'paper') {
                if (fmt.auto || fmt.paper) { return { ...(fmt.auto || {}), ...(fmt.paper || {}) }; }
                return null;
            }
            return fmt[effectiveRatio] || null;
        },

        applyRatioSettings(format, ratio) {
            const effectiveRatio = this.effectiveRatioKey(ratio);
            const score = this.readRatioBucket(this.scoreSettings, format, effectiveRatio);
            const user = this.readRatioBucket(this.userDefaults, format, effectiveRatio);
            const temp = this.readRatioBucket(this.tempSettings, format, effectiveRatio);
            const ratioFields = new Set(['pageRatio', 'abcPageRatio', 'aretinoPageRatio']);
            const { fields, defaults } = this.getFormatDefaults(format);
            const merged = {};
            fields.forEach(f => { if (f in defaults && !ratioFields.has(f)) { merged[f] = defaults[f]; } });
            if (format === 'abc' && ABC_RATIO_DEFAULTS[effectiveRatio]) {
                Object.assign(merged, ABC_RATIO_DEFAULTS[effectiveRatio]);
            }
            Object.assign(merged, user || {});
            Object.assign(merged, score || {});
            Object.assign(merged, temp || {});
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

        resetToDefaults() {
            const format = this.$wire.format;
            let defaults = {};
            let fields = [];

            if (format === 'gabc') {
                const m = gabcMixin();
                fields = m.gabcFields;
                defaults = m;
            } else if (format === 'abc') {
                const m = abcMixin();
                fields = m.abcFields;
                defaults = m;
            } else if (format === 'chordpro') {
                const m = chordproMixin();
                fields = m.chordproFields;
                defaults = m;
            } else if (format === 'aretino') {
                const m = aretinoMixin();
                fields = m.aretinoFields;
                defaults = m;
            }

            const applyDefaults = () => {
                fields.forEach(field => {
                    if (field in defaults) { this[field] = defaults[field]; }
                });
                if (format === 'abc') {
                    const effectiveRatio = this.effectiveRatioKey(this.abcPageRatio);
                    const ratioOverrides = ABC_RATIO_DEFAULTS[effectiveRatio];
                    if (ratioOverrides) {
                        Object.keys(ratioOverrides).forEach(k => { if (k in this) { this[k] = ratioOverrides[k]; } });
                    }
                }
            };

            applyDefaults();
            this.$nextTick(() => {
                applyDefaults();
                this.scheduleRender();
            });
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

        addPageControls(pageEl, pageIdx, totalPages, format, opts = {}) {
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

            const svgIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" style="flex-shrink:0"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>';
            const exportSvgBtn = document.createElement('button');
            exportSvgBtn.type = 'button';
            exportSvgBtn.className = btnClass;
            exportSvgBtn.innerHTML = svgIcon + this.exportSvgText;
            exportSvgBtn.addEventListener('click', () => this.exportPageSvg(pageEl, pageIdx, totalPages, format, this.$wire.title));

            bar.appendChild(feedbackSpan);
            bar.appendChild(copyBtn);
            bar.appendChild(exportBtn);
            bar.appendChild(exportSvgBtn);

            if (opts.fullscreen && document.documentElement.requestFullscreen) {
                const fsIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" style="flex-shrink:0"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15"/></svg>';
                const fsBtn = document.createElement('button');
                fsBtn.type = 'button';
                fsBtn.className = btnClass;
                fsBtn.innerHTML = fsIcon + this.fullscreenText;

                const ratio = opts.ratio;
                const svgEl = pageEl.querySelector('svg');

                let fsWrapper = null;

                const onFullscreenChange = () => {
                    if (document.fullscreenElement === pageEl) {
                        pageEl.style.border = 'none';
                        pageEl.style.borderRadius = '0';
                        pageEl.style.boxShadow = 'none';
                        pageEl.style.aspectRatio = '';
                        pageEl.style.width = '100%';
                        pageEl.style.height = '100%';
                        pageEl.style.background = '#000';
                        pageEl.style.display = 'flex';
                        pageEl.style.alignItems = 'center';
                        pageEl.style.justifyContent = 'center';
                        if (svgEl) {
                            fsWrapper = document.createElement('div');
                            fsWrapper.style.aspectRatio = ratio;
                            fsWrapper.style.height = '100%';
                            fsWrapper.style.maxWidth = '100%';
                            fsWrapper.style.background = 'white';
                            svgEl.parentNode.insertBefore(fsWrapper, svgEl);
                            fsWrapper.appendChild(svgEl);
                            svgEl.style.width = '100%';
                            svgEl.style.height = '100%';
                            svgEl.style.maxWidth = '';
                            svgEl.style.maxHeight = '';
                            svgEl.style.background = '';
                        }
                    } else {
                        pageEl.style.border = '8px solid #374151';
                        pageEl.style.borderRadius = '4px';
                        pageEl.style.boxShadow = '0 8px 32px rgba(0,0,0,0.45)';
                        pageEl.style.aspectRatio = ratio;
                        pageEl.style.width = '100%';
                        pageEl.style.height = '';
                        pageEl.style.background = '';
                        pageEl.style.display = '';
                        pageEl.style.alignItems = '';
                        pageEl.style.justifyContent = '';
                        if (svgEl && fsWrapper) {
                            fsWrapper.parentNode.insertBefore(svgEl, fsWrapper);
                            fsWrapper.remove();
                            fsWrapper = null;
                            svgEl.style.width = '100%';
                            svgEl.style.height = '100%';
                            svgEl.style.maxWidth = '';
                            svgEl.style.maxHeight = '';
                            svgEl.style.background = '';
                        }
                        document.removeEventListener('fullscreenchange', onFullscreenChange);
                    }
                };

                fsBtn.addEventListener('click', () => {
                    if (document.fullscreenElement) { return; }
                    document.addEventListener('fullscreenchange', onFullscreenChange);
                    pageEl.requestFullscreen().catch(err => {
                        console.error('[score-editor] fullscreen error:', err);
                        document.removeEventListener('fullscreenchange', onFullscreenChange);
                    });
                });

                bar.appendChild(fsBtn);
            }

            pageEl.insertAdjacentElement('afterend', bar);
        },

        scheduleRender() {
            clearTimeout(this.renderTimer);
            this.renderTimer = setTimeout(() => this.renderPreview(), 600);
        },

        renderPreview() {
            const scrollY = window.scrollY;
            if (this.$wire.format === 'abc') {
                this.renderAbcPreview();
            } else if (this.$wire.format === 'chordpro') {
                this.renderChordproPreview();
            } else if (this.$wire.format === 'aretino') {
                this.renderAretinoPreview();
            } else {
                this.renderGabcPreview();
            }
            window.scrollTo(0, scrollY);
        },

        showCopyFeedback(msg) {
            this.copyFeedback = msg;
            clearTimeout(this.copyFeedbackTimer);
            this.copyFeedbackTimer = setTimeout(() => { this.copyFeedback = ''; }, 2500);
        },

        async svgToCanvas(svgEl) {
            const renderWidth = this.getRenderWidth();
            const isPaperAretino = this.$wire.format === 'aretino' &&
                (this.aretinoPageRatio === 'paper' || this.aretinoPageRatio === 'auto');
            const scale = isPaperAretino ? 600 / 96 : 2;
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

        async exportPageSvg(pageEl, pageIdx, totalPages, format, title) {
            const svgs = Array.from(pageEl.querySelectorAll('svg'));
            if (!svgs.length) { return; }
            const base = title ? title.trim().replace(/[^\p{L}\p{N}\s-]/gu, '').replace(/\s+/g, '-').replace(/-+/g, '-').replace(/^-+|-+$/g, '') || 'score' : 'score';
            const filename = totalPages > 1 ? base + '-page-' + pageIdx + '.cantores.hu.svg' : base + '.cantores.hu.svg';
            try {
                let svgData;
                if (format === 'abc' && svgs.length > 1) {
                    svgData = await this.buildMergedSvg(svgs);
                } else {
                    const clone = svgs[0].cloneNode(true);
                    let lyricFont;
                    if (format === 'aretino') { lyricFont = this.aretinoLyricFont; }
                    else if (format === 'abc') { lyricFont = this.abcLyricFont; }
                    else { lyricFont = this.lyricFont; }
                    await injectWebFontsIntoSvg(clone, [lyricFont]);
                    svgData = new XMLSerializer().serializeToString(clone);
                }
                const blob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.download = filename;
                a.href = url;
                a.click();
                URL.revokeObjectURL(url);
            } catch (e) {
                console.error('[score-editor] svg export error:', e);
            }
        },

        async buildMergedSvg(svgs) {
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
            const ns = 'http://www.w3.org/2000/svg';
            const wrapper = document.createElementNS(ns, 'svg');
            wrapper.setAttribute('xmlns', ns);
            wrapper.setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xlink', 'http://www.w3.org/1999/xlink');
            wrapper.setAttribute('viewBox', `0 0 ${maxW} ${totalH}`);
            wrapper.setAttribute('width', String(maxW));
            wrapper.setAttribute('height', String(totalH));
            wrapper.setAttribute('color', '#000');
            wrapper.setAttribute('fill', 'currentColor');

            // Hoist <style> and <defs> to the wrapper root so @font-face rules
            // and glyph definitions are at document scope, not buried inside <g>
            // elements where some SVG renderers won't process them.
            let combinedStyle = `.sW{stroke-width:${this.abcStemWidth}!important}.slW{stroke-width:${this.abcStaffLineWidth}!important}\n`;
            const mergedDefs = document.createElementNS(ns, 'defs');
            const seenIds = new Set();

            let yOffset = 0;
            svgs.forEach((svg, i) => {
                const d = dims[i];
                const clone = svg.cloneNode(true);
                const g = document.createElementNS(ns, 'g');
                g.setAttribute('transform', `translate(${-d.x} ${yOffset - d.y})`);
                ['class', 'fill', 'stroke-width', 'color'].forEach(attr => {
                    const val = clone.getAttribute(attr);
                    if (val) { g.setAttribute(attr, val); }
                });
                Array.from(clone.childNodes).forEach(child => {
                    const tag = child.nodeName.toLowerCase();
                    if (tag === 'style') {
                        combinedStyle += child.textContent + '\n';
                    } else if (tag === 'defs') {
                        Array.from(child.childNodes).forEach(def => {
                            if (def.nodeType !== 1) { return; }
                            const id = def.getAttribute && def.getAttribute('id');
                            if (id) {
                                if (seenIds.has(id)) { return; }
                                seenIds.add(id);
                            }
                            mergedDefs.appendChild(def.cloneNode(true));
                        });
                    } else {
                        g.appendChild(child);
                    }
                });
                wrapper.appendChild(g);
                yOffset += d.h;
            });

            const styleEl = document.createElementNS(ns, 'style');
            styleEl.textContent = combinedStyle;
            wrapper.insertBefore(styleEl, wrapper.firstChild);
            if (mergedDefs.childNodes.length) {
                wrapper.insertBefore(mergedDefs, wrapper.firstChild);
            }

            await injectWebFontsIntoSvg(wrapper, [this.abcLyricFont]);
            return new XMLSerializer().serializeToString(wrapper);
        },

        // Stack multiple abc2svg output chunks into a single <svg> element for
        // the fixed-ratio preview, so projector-frame scaling, clipping and
        // fullscreen treat the page as one unit. Returns { svg, totalHeight }.
        mergeAbcSvgsToElement(svgs) {
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
            const ns = 'http://www.w3.org/2000/svg';
            const wrapper = document.createElementNS(ns, 'svg');
            wrapper.setAttribute('xmlns', ns);
            wrapper.setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xlink', 'http://www.w3.org/1999/xlink');
            wrapper.setAttribute('viewBox', `0 0 ${maxW} ${totalH}`);
            wrapper.setAttribute('color', '#000');
            wrapper.setAttribute('fill', 'currentColor');
            let combinedStyle = `.sW{stroke-width:${this.abcStemWidth}!important}.slW{stroke-width:${this.abcStaffLineWidth}!important}\n`;
            const mergedDefs = document.createElementNS(ns, 'defs');
            const seenIds = new Set();
            let yOffset = 0;
            svgs.forEach((svg, i) => {
                const d = dims[i];
                const clone = svg.cloneNode(true);
                const g = document.createElementNS(ns, 'g');
                g.setAttribute('transform', `translate(${-d.x} ${yOffset - d.y})`);
                ['class', 'fill', 'stroke-width', 'color'].forEach(attr => {
                    const val = clone.getAttribute(attr);
                    if (val) { g.setAttribute(attr, val); }
                });
                Array.from(clone.childNodes).forEach(child => {
                    const tag = child.nodeName.toLowerCase();
                    if (tag === 'style') {
                        combinedStyle += child.textContent + '\n';
                    } else if (tag === 'defs') {
                        Array.from(child.childNodes).forEach(def => {
                            if (def.nodeType !== 1) { return; }
                            const id = def.getAttribute && def.getAttribute('id');
                            if (id) {
                                if (seenIds.has(id)) { return; }
                                seenIds.add(id);
                            }
                            mergedDefs.appendChild(def.cloneNode(true));
                        });
                    } else {
                        g.appendChild(child);
                    }
                });
                wrapper.appendChild(g);
                yOffset += d.h;
            });
            const styleEl = document.createElementNS(ns, 'style');
            styleEl.textContent = combinedStyle;
            wrapper.insertBefore(styleEl, wrapper.firstChild);
            if (mergedDefs.childNodes.length) {
                wrapper.insertBefore(mergedDefs, wrapper.firstChild);
            }
            return { svg: wrapper, totalHeight: totalH, width: maxW };
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
                const scale = 2;
                const canvas = document.createElement('canvas');
                canvas.width = (maxW + margin * 2) * scale;
                canvas.height = (totalH + margin * 2) * scale;
                const ctx = canvas.getContext('2d');
                ctx.scale(scale, scale);
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, maxW + margin * 2, totalH + margin * 2);
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
                aretino: `(g2) g A B g. AB A g e_d_ , g AB Ag g. ||
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
