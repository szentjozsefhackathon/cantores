import { onAlpineInit } from './alpine-init.js';
import { movesSetting, physicalKnob, steppedValue } from './booklet-settings.js';
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
            knobValue(format, key) {
                return styledDefaults(format, this.ratio, { settings: this.settings })[key];
            },

            /**
             * The same, as a number field shows it.
             *
             * A size is shown in the unit the score editor shows it in — points
             * of type, millimetres of staff — and never in its engine's own: a
             * ChordPro size is stored in px, so the 62 pt a score opens at on a
             * 16:9 screen read 82.88 here, and 85 typed into the score editor
             * was a different size from 85 typed here. Snapped to the half that
             * a press moves it by. Anything else is an engine unit nobody reads
             * past the second place of.
             */
            shownValue(format, key) {
                const value = Number(this.knobValue(format, key));
                if (!Number.isFinite(value)) { return ''; }

                const knob = physicalKnob(key);

                return knob ? snap(knob.toPhysical(value), knob.step) : Math.round(value * 100) / 100;
            },

            /** A knob's limit in the unit shownValue() reads it in. */
            shownLimit(field, limit) {
                const knob = physicalKnob(field.key);

                return knob ? snap(knob.toPhysical(Number(field[limit])), knob.step) : field[limit];
            },

            /** A number typed into a knob, taken in the unit it is shown in. */
            type(format, key, value) {
                const typed = Number(value);
                if (!Number.isFinite(typed)) { return; }

                const knob = physicalKnob(key);

                this.set(format, key, knob ? Math.round(knob.fromPhysical(typed) * 1e4) / 1e4 : typed);
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

            /**
             * One press, counted in the unit the knob is shown in, so a size
             * moves from 62 pt to 68 rather than from 82.88 px to whatever that
             * makes in points.
             */
            nudge(format, field, direction) {
                this.set(format, field.key, this.stepped(format, field, direction));
            },

            atLimit(format, field, direction) {
                const current = Number(this.knobValue(format, field.key));

                return Number.isFinite(current) && this.stepped(format, field, direction) === current;
            },

            stepped(format, field, direction) {
                const current = this.knobValue(format, field.key);
                const knob = physicalKnob(field.key);
                if (!knob) { return steppedValue(current, field, direction); }

                const shown = knob.toPhysical(Number(current));
                const physical = steppedValue(shown, {
                    ...field,
                    step: knob.step,
                    min: knob.toPhysical(Number(field.min)),
                    max: knob.toPhysical(Number(field.max)),
                }, direction);

                return physical === shown ? Number(current) : Math.round(knob.fromPhysical(physical) * 1e4) / 1e4;
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

function snap(value, step) {
    return Math.round(value / step) * step;
}

function plainSettings(settings) {
    const plain = {};

    Object.entries(settings ?? {}).forEach(([format, bucket]) => {
        plain[format] = { ...(bucket ?? {}) };
    });

    return plain;
}
