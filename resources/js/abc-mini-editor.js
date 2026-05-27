const ABC2SVG_SRC = '/js/abc2svg-1.js';
const DEFAULT_PAGE_WIDTH = 760;

let abc2svgLoadPromise = null;

export function clampAbcGuidePageWidth(value) {
    const width = Number(value);

    if (!Number.isFinite(width)) {
        return DEFAULT_PAGE_WIDTH;
    }

    return Math.min(1200, Math.max(420, Math.round(width)));
}

export function prepareAbcGuideSource(content, pageWidth = DEFAULT_PAGE_WIDTH) {
    let source = String(content || '').trim();

    if (!/^X:/m.test(source)) {
        source = `X:1\n${source}`;
    }

    return [
        '%%fullsvg 1',
        `%%pagewidth ${clampAbcGuidePageWidth(pageWidth)}px`,
        '%%leftmargin 12px',
        '%%rightmargin 12px',
        '%%topspace 0',
        '%%musicspace 4',
        '%%staffsep 42',
        '%%vocalspace 8',
        '%%vocalfont "EB Garamond" 15',
        source,
    ].join('\n');
}

function ensureAbc2SvgElement() {
    window.abc2svg = window.abc2svg || {};

    if (window.abc2svg.el) {
        return;
    }

    const el = document.createElement('span');
    el.style.cssText = 'position:absolute;top:-9999px;left:-9999px;visibility:hidden;white-space:nowrap;';
    document.body.appendChild(el);
    window.abc2svg.el = el;
}

function loadAbc2Svg() {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return Promise.reject(new Error('abc2svg requires a browser environment.'));
    }

    ensureAbc2SvgElement();

    if (window.abc2svg?.Abc) {
        return Promise.resolve();
    }

    if (abc2svgLoadPromise) {
        return abc2svgLoadPromise;
    }

    abc2svgLoadPromise = new Promise((resolve, reject) => {
        const existingScript = Array.from(document.scripts)
            .find((script) => script.src.endsWith(ABC2SVG_SRC));

        if (existingScript) {
            existingScript.addEventListener('load', () => resolve(), { once: true });
            existingScript.addEventListener('error', () => reject(new Error('Az abc2svg betöltése nem sikerült.')), { once: true });

            return;
        }

        const script = document.createElement('script');
        script.src = ABC2SVG_SRC;
        script.async = true;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Az abc2svg betöltése nem sikerült.'));
        document.head.appendChild(script);
    });

    return abc2svgLoadPromise;
}

function normalizeRenderedSvgs(container) {
    Array.from(container.querySelectorAll('svg')).forEach((svg) => {
        if (!svg.getAttribute('viewBox')) {
            const width = parseFloat(svg.getAttribute('width')) || DEFAULT_PAGE_WIDTH;
            const height = parseFloat(svg.getAttribute('height')) || 0;

            if (height) {
                svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
            }
        }

        const style = document.createElementNS('http://www.w3.org/2000/svg', 'style');
        style.textContent = '.sW{stroke-width:.8!important}.slW{stroke-width:.7!important}';
        svg.appendChild(style);
        svg.setAttribute('width', '100%');
        svg.removeAttribute('height');
        svg.style.display = 'block';
    });
}

if (typeof document !== 'undefined') {
    document.addEventListener('alpine:init', () => {
        Alpine.data('abcMiniEditor', (initialContent = '') => ({
            content: initialContent,
            originalContent: initialContent,
            renderTimer: null,

            init() {
                this.$nextTick(() => this.render());
                this.$watch('content', () => this.scheduleRender());
            },

            scheduleRender() {
                clearTimeout(this.renderTimer);
                this.renderTimer = setTimeout(() => this.render(), 250);
            },

            async render() {
                const preview = this.$refs.preview;
                if (!preview) { return; }

                preview.innerHTML = '';
                if (!this.content.trim()) { return; }

                try {
                    await loadAbc2Svg();

                    const pageWidth = clampAbcGuidePageWidth((preview.clientWidth || DEFAULT_PAGE_WIDTH) - 24);
                    const source = prepareAbcGuideSource(this.content, pageWidth);
                    const svgChunks = [];
                    const errors = [];
                    const abc = new window.abc2svg.Abc({
                        img_out: (str) => svgChunks.push(str),
                        errmsg: (msg, line) => errors.push(`${msg} (sor: ${line})`),
                        read_file: () => null,
                    });

                    abc.tosvg('abc-guide', source);
                    preview.innerHTML = svgChunks.join('\n');
                    normalizeRenderedSvgs(preview);

                    if (errors.length) {
                        const warning = document.createElement('div');
                        warning.className = 'border-t border-amber-200 bg-amber-50 px-3 py-2 font-mono text-xs text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200';
                        warning.textContent = errors.join(' | ');
                        preview.appendChild(warning);
                    }
                } catch (error) {
                    preview.innerHTML = `<p class="p-3 font-mono text-sm text-red-600 dark:text-red-400">${error.message}</p>`;
                }
            },

            reset() {
                this.content = this.originalContent;
            },
        }));
    });
}
