import { pageGeometry } from './booklet-geometry.js';
import { renderBooklet, serializeBookletPages } from './booklet-render.js';
import { resolveSettings } from './booklet-settings.js';
import { abcMixin } from './score-editor-abc.js';
import { aretinoMixin } from './score-editor-aretino.js';
import { chordproMixin } from './score-editor-chordpro.js';
import { gabcMixin } from './score-editor-gabc.js';

/**
 * The booklet editor's browser half.
 *
 * Livewire owns what is in the booklet; this owns what it looks like. Every
 * change — a page size, a lyric size, one score nudged wider — redraws the whole
 * thing, because a booklet flows and there is no such thing as re-rendering only
 * the score you touched: making one score two lines shorter moves everything
 * after it.
 */

const RENDER_DEBOUNCE_MS = 250;

document.addEventListener('alpine:init', () => {
    Alpine.data('bookletEditor', (config = {}) => ({
        geometry: config.geometry ?? {},
        entries: config.entries ?? [],
        exportUrl: config.exportUrl ?? '',
        csrfToken: config.csrfToken ?? '',
        exportFailedText: config.exportFailedText ?? '',

        pages: [],
        pageCount: 0,
        rendering: false,
        exporting: false,
        message: '',

        /** The entry whose settings panel is open, and its working values. */
        panelEntryId: null,
        panelValues: {},
        panelOverride: {},

        _renderTimer: null,
        _renderToken: 0,

        init() {
            this.$nextTick(() => this.scheduleRender());
        },

        destroy() {
            clearTimeout(this._renderTimer);
        },

        /**
         * A change came back from the server. The payload is pushed rather than
         * read, because it is a computed property with no client-side existence.
         */
        applyUpdate(detail = {}) {
            if (detail.payload) { this.entries = detail.payload; }
            if (detail.geometry) { this.geometry = detail.geometry; }

            // An entry may have been removed while its panel was open.
            if (this.panelEntryId !== null
                && !this.entries.some((entry) => entry.id === this.panelEntryId)) {
                this.panelEntryId = null;
            }

            this.scheduleRender();
        },

        scheduleRender() {
            clearTimeout(this._renderTimer);
            this._renderTimer = setTimeout(() => this.render(), RENDER_DEBOUNCE_MS);
        },

        async render() {
            // A slow render must not overwrite a newer one that finished first.
            const token = ++this._renderToken;
            this.rendering = true;

            try {
                const { pages, fonts } = await renderBooklet(this.entries, this.geometry, this.$refs.measure);

                if (token !== this._renderToken) { return; }

                this._fonts = fonts;
                this.pages = pages;
                this.pageCount = pages.length;
                this.paint();
            } catch (e) {
                console.error('[booklet] render failed', e);
            } finally {
                if (token === this._renderToken) { this.rendering = false; }
            }
        },

        paint() {
            const container = this.$refs.pages;
            if (!container) { return; }

            container.replaceChildren();

            this.pages.forEach((page) => {
                const sheet = document.createElement('div');
                sheet.className = 'booklet-page';
                const svg = page.cloneNode(true);
                svg.removeAttribute('width');
                svg.removeAttribute('height');
                svg.style.width = '100%';
                svg.style.height = 'auto';
                svg.style.display = 'block';
                sheet.appendChild(svg);
                container.appendChild(sheet);
            });
        },

        /**
         * Open the settings panel for one score, pre-filled with the values it is
         * actually being drawn at — the booklet's, unless something was already
         * changed by hand.
         */
        openPanel(entryId) {
            const entry = this.entries.find((candidate) => candidate.id === entryId);

            if (!entry || entry.kind === 'text') {
                this.panelEntryId = null;

                return;
            }

            this.panelEntryId = entryId;
            this.panelOverride = { ...(entry.override ?? {}) };
            this.panelValues = resolveSettings(
                entry.format,
                formatDefaults(entry.format),
                entry.settings ?? {},
                pageGeometry(this.geometry),
                this.panelOverride,
            );
        },

        closePanel() {
            this.panelEntryId = null;
        },

        /**
         * A knob moved. Only the keys someone actually touched are stored, so a
         * booklet resized later still re-unifies everything nobody pinned.
         */
        setOverride(key, value) {
            if (this.panelEntryId === null) { return; }

            this.panelValues[key] = value;
            this.panelOverride[key] = value;

            const entry = this.entries.find((candidate) => candidate.id === this.panelEntryId);
            if (entry) { entry.override = { ...this.panelOverride }; }

            this.scheduleRender();
            this.$wire.saveOverride(this.panelEntryId, this.panelOverride);
        },

        resetPanel() {
            if (this.panelEntryId === null) { return; }

            const entryId = this.panelEntryId;
            this.panelOverride = {};

            const entry = this.entries.find((candidate) => candidate.id === entryId);
            if (entry) { entry.override = {}; }

            this.openPanel(entryId);
            this.scheduleRender();
            this.$wire.resetOverride(entryId);
        },

        isOverridden(key) {
            return Object.prototype.hasOwnProperty.call(this.panelOverride, key);
        },

        async exportPdf() {
            if (this.exporting || this.pages.length === 0) { return; }

            this.exporting = true;
            this.message = '';

            try {
                const svgs = await serializeBookletPages(this.pages, this._fonts ?? []);

                const response = await fetch(this.exportUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        Accept: 'application/pdf',
                    },
                    body: JSON.stringify({ pages: svgs }),
                });

                if (!response.ok) {
                    throw new Error(`export failed with ${response.status}`);
                }

                const blob = await response.blob();
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = (this.$wire.get('title') || 'fuzet') + '.cantores.hu.pdf';
                link.click();
                URL.revokeObjectURL(url);
            } catch (e) {
                console.error('[booklet] export failed', e);
                this.message = this.exportFailedText;
            } finally {
                this.exporting = false;
            }
        },
    }));
});

function formatDefaults(format) {
    if (format === 'gabc') { return gabcMixin(); }
    if (format === 'abc') { return abcMixin(); }
    if (format === 'chordpro') { return chordproMixin(); }
    if (format === 'aretino') { return aretinoMixin(); }

    return {};
}
