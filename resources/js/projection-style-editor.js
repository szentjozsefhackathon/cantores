import { onAlpineInit } from './alpine-init.js';
import { movesSetting, steppedValue } from './booklet-settings.js';
import { styledDefaults } from './projection-settings.js';
import { formatDefaults } from './score-editor-settings.js';

/**
 * The per-format half of the slide style editor.
 *
 * A style is a complete stylesheet, so every knob shows a value — the style's
 * own where it has said something, and the format's factory default for the
 * style's shape where it has not. Only the browser knows those defaults (they
 * are read out of each format's own mixin, see score-editor-settings.js), which
 * is why this half lives here rather than in the Livewire component around it.
 *
 * Each change is sent on its own, a knob at a time, and the deck behind the
 * editor is drawn again by the save; the same debounce the slide panel uses
 * keeps a run of presses on one knob to one save.
 */

const SAVE_DEBOUNCE_MS = 500;

onAlpineInit(() => {
    Alpine.data('projectionStyleFormats', (config = {}) => {
        // Taken once, for the reason projection-editor.js gives: `$wire` from
        // a method resolves to whichever component the element sits in.
        let wire = null;
        const timers = {};

        return {
            ratio: config.ratio ?? '16/9',
            settings: plainSettings(config.settings),

            init() {
                wire = this.$wire;
            },

            /** What a knob reads: the style's value, or the factory default. */
            valueOf(format, key) {
                return styledDefaults(format, this.ratio, { settings: this.settings })[key];
            },

            /** Whether the style says something other than the factory default here. */
            isSet(format, key) {
                const bucket = this.settings[format] ?? {};

                return Object.prototype.hasOwnProperty.call(bucket, key)
                    && movesSetting(bucket[key], formatDefaults(format, this.ratio).defaults[key]);
            },

            set(format, key, value) {
                this.settings = { ...this.settings, [format]: { ...(this.settings[format] ?? {}), [key]: value } };

                const timer = `${format}.${key}`;
                clearTimeout(timers[timer]);
                timers[timer] = setTimeout(() => wire.saveSetting(format, key, value), SAVE_DEBOUNCE_MS);
            },

            nudge(format, field, direction) {
                this.set(format, field.key, steppedValue(this.valueOf(format, field.key), field, direction));
            },

            atLimit(format, field, direction) {
                const current = Number(this.valueOf(format, field.key));

                return Number.isFinite(current) && steppedValue(current, field, direction) === current;
            },

            /** Back to the factory default for this one knob. */
            reset(format, key) {
                const { [key]: _, ...rest } = this.settings[format] ?? {};
                this.settings = { ...this.settings, [format]: rest };

                clearTimeout(timers[`${format}.${key}`]);
                wire.saveSetting(format, key, null);
            },
        };
    });
});

function plainSettings(settings) {
    const plain = {};

    Object.entries(settings ?? {}).forEach(([format, bucket]) => {
        plain[format] = { ...(bucket ?? {}) };
    });

    return plain;
}
