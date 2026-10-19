import { onAlpineInit } from './alpine-init.js';
import { AretinoPlayer, renderAretino } from '@aretino-chant/core';

onAlpineInit(() => {
    Alpine.data('aretinoMiniEditor', (initialContent = '') => {
        // Kept out of Alpine's reactive proxy: EventTarget methods refuse a proxied `this`.
        let player = null;

        return {
            content: initialContent,
            originalContent: initialContent,
            renderTimer: null,
            playing: false,

            init() {
                this.$nextTick(() => this.render());
                this.$watch('content', () => {
                    this.scheduleRender();
                    player?.load(this.content);
                });
            },

            destroy() {
                player?.dispose();
                player = null;
            },

            /** Plays the score, or stops it when it is playing; follows the sounding note in the preview. */
            async togglePlayback() {
                if (!player) {
                    player = new AretinoPlayer();
                    player.load(this.content);
                    player.addEventListener('statechange', (event) => {
                        this.playing = event.detail.state === 'playing';
                        if (event.detail.state === 'stopped') { this.clearHighlight(); }
                    });
                    player.addEventListener('noteon', (event) => this.highlightNote(event.detail.srcStart, true));
                    player.addEventListener('noteoff', (event) => this.highlightNote(event.detail.srcStart, false));
                    player.addEventListener('end', () => this.clearHighlight());
                }
                if (player.state === 'playing') {
                    player.stop();
                } else {
                    await player.play();
                }
            },

            highlightNote(srcStart, active) {
                this.$refs.preview
                    ?.querySelectorAll(`.aretino-note[data-src-start="${srcStart}"]`)
                    .forEach((note) => note.classList.toggle('aretino-active', active));
            },

            clearHighlight() {
                this.$refs.preview
                    ?.querySelectorAll('.aretino-active')
                    .forEach((element) => element.classList.remove('aretino-active'));
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
        };
    });
});
