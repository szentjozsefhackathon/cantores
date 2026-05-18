import { renderAretino } from './aretino/index.js';

document.addEventListener('alpine:init', () => {
    Alpine.data('aretinoMiniEditor', (initialContent = '') => ({
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

        render() {
            const preview = this.$refs.preview;
            if (!preview) { return; }
            preview.innerHTML = '';
            if (!this.content.trim()) { return; }
            try {
                const width = preview.clientWidth || 700;
                const svg = renderAretino(this.content, {
                    canvasWidth: width,
                    staffSize: 100,
                    lyricSize: 13,
                    lyricFont: "'Palatino Linotype', 'Book Antiqua', Palatino, serif",
                });
                preview.innerHTML = svg;
            } catch (e) {
                preview.innerHTML = `<p class="text-red-500 text-sm p-2 font-mono">${e.message}</p>`;
            }
        },

        reset() {
            this.content = this.originalContent;
        },
    }));
});
