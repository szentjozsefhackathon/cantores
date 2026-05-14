import { abcMixin } from './score-editor-abc.js';
import { gabcMixin } from './score-editor-gabc.js';

document.addEventListener('alpine:init', () => {
    Alpine.data('scoreEditor', (config = {}) => ({
        hasPages: false,
        localContent: '',
        clippedWarningText: config.clippedWarningText ?? '',
        clipboardNotSupported: config.clipboardNotSupported ?? '',
        firstPageCopied: config.firstPageCopied ?? '',
        imageCopied: config.imageCopied ?? '',
        failedToCopy: config.failedToCopy ?? '',
        renderTimer: null,
        scoreSettings: config.scoreSettings ?? {},
        userDefaults: config.userDefaults ?? {},
        copyFeedback: '',
        copyFeedbackTimer: null,

        ...abcMixin(),
        ...gabcMixin(),

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
                this.scheduleRender();
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
                    },
                    ratio: 'auto',
                };
            }
            return { settings: {}, ratio: 'auto' };
        },

        getVirtualCanvasSize(format) {
            if (format === 'abc') { return { width: 1920, height: null }; }
            const width = 1920;
            const heights = { '16/9': 1080, '4/3': 1440, '1/1': 1920 };
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
            this.applyRatioSettings('abc', 'auto');
        },

        saveScore() {
            const c = this.collectSettings();
            this.$wire.call('save', c.settings, c.ratio);
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
            if (format === 'gabc') {
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

        scheduleRender() {
            clearTimeout(this.renderTimer);
            this.renderTimer = setTimeout(() => this.renderPreview(), 600);
        },

        renderPreview() {
            if (this.$wire.format === 'abc') {
                this.renderAbcPreview();
                return;
            }
            this.renderGabcPreview();
        },

        showCopyFeedback(msg) {
            this.copyFeedback = msg;
            clearTimeout(this.copyFeedbackTimer);
            this.copyFeedbackTimer = setTimeout(() => { this.copyFeedback = ''; }, 2500);
        },

        svgToCanvas(svgEl) {
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

        exportPng() {
            const previewEl = this.$wire.format === 'abc' ? this.$refs.abcPreview : this.$refs.preview;
            if (!previewEl) { return; }
            const svgs = Array.from(previewEl.querySelectorAll('svg'));
            if (!svgs.length) { return; }
            if (this.$wire.format === 'abc') {
                this.exportAbcPng(svgs);
                return;
            }
            const total = svgs.length;
            svgs.forEach((svgEl, idx) => {
                this.svgToCanvas(svgEl).then(canvas => {
                    const a = document.createElement('a');
                    a.download = total > 1 ? 'score-page-' + (idx + 1) + '.png' : 'score.png';
                    a.href = canvas.toDataURL('image/png');
                    a.click();
                }).catch(e => console.error('[score-editor] export error:', e));
            });
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
                const blob = new Blob([new XMLSerializer().serializeToString(clone)], { type: 'image/svg+xml;charset=utf-8' });
                const url = URL.createObjectURL(blob);
                return new Promise((resolve, reject) => {
                    const img = new Image();
                    img.onload = () => { URL.revokeObjectURL(url); resolve({ img, w: d.w, h: d.h }); };
                    img.onerror = e => { URL.revokeObjectURL(url); reject(e); };
                    img.src = url;
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

        exportAbcPng(svgs) {
            this.buildAbcCanvas(svgs).then(canvas => {
                const a = document.createElement('a');
                a.download = 'score.png';
                a.href = canvas.toDataURL('image/png');
                a.click();
            }).catch(e => console.error('[score-editor] export error:', e));
        },

        async copyImage() {
            const previewEl = this.$wire.format === 'abc' ? this.$refs.abcPreview : this.$refs.preview;
            if (!previewEl) { return; }
            const svgs = Array.from(previewEl.querySelectorAll('svg'));
            if (!svgs.length) { return; }
            if (!navigator.clipboard || !window.ClipboardItem) {
                this.showCopyFeedback(this.clipboardNotSupported);
                return;
            }
            try {
                const canvasPromise = this.$wire.format === 'abc'
                    ? this.buildAbcCanvas(svgs)
                    : this.svgToCanvas(svgs[0]);
                const blobPromise = canvasPromise.then(canvas =>
                    new Promise(resolve => canvas.toBlob(resolve, 'image/png'))
                );
                await navigator.clipboard.write([new ClipboardItem({ 'image/png': blobPromise })]);
                const msg = svgs.length > 1 ? this.firstPageCopied : this.imageCopied;
                this.showCopyFeedback(msg);
            } catch (e) {
                console.error('[score-editor] copy image error:', e);
                this.showCopyFeedback(this.failedToCopy);
            }
        },
    }));
});
