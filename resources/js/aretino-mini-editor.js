import { renderAretino } from '@aretino-chant/core';

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
                const ZOOM = 1.4;
                const containerWidth = preview.clientWidth || 800;
                const width = Math.max(120, Math.round(containerWidth / ZOOM));
                const svg = renderAretino(this.content, {
                    width,
                    zoom: ZOOM,
                    textFont: "'Palatino Linotype', 'Book Antiqua', Palatino, serif",
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
