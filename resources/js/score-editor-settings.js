import { ABC_RATIO_DEFAULTS, abcMixin } from './score-editor-abc.js';
import { ARETINO_RATIO_DEFAULTS, aretinoMixin } from './score-editor-aretino.js';
import { chordproMixin } from './score-editor-chordpro.js';
import { GABC_SCREEN_DEFAULTS, gabcMixin } from './score-editor-gabc.js';

/**
 * The factory defaults of a format, and the fields that carry them.
 *
 * A mixin is the single place a format states what it looks like untouched, so
 * the defaults are read back out of a fresh one rather than restated here.
 */
const RATIO_FIELDS = { gabc: 'pageRatio', abc: 'abcPageRatio', aretino: 'aretinoPageRatio' };

export function formatDefaults(format, ratio = 'paper') {
    const factories = { gabc: gabcMixin, abc: abcMixin, chordpro: chordproMixin, aretino: aretinoMixin };
    const factory = factories[format];
    if (!factory) { return { fields: [], defaults: {} }; }

    const defaults = factory();
    if (['16/9', '4/3', '1/1'].includes(ratio)) {
        const screenDefaults = {
            abc: ABC_RATIO_DEFAULTS[ratio],
            aretino: ARETINO_RATIO_DEFAULTS[ratio],
            gabc: GABC_SCREEN_DEFAULTS,
        };
        Object.assign(defaults, screenDefaults[format]);
    }

    return { fields: defaults[`${format}Fields`], defaults };
}

/**
 * The settings an incipit is rendered with: always the format's factory
 * defaults, never the score's own.
 *
 * Settings are tuned for a purpose — oversized lyrics for a projector slide, a
 * condensed staff for a booklet page — and the incipit serves none of them. It
 * is the thumbnail every listing shows, so it stays the score at its plainest,
 * whatever the editor happens to be set to when the score is saved.
 */
export function incipitSettings(format) {
    return formatDefaults(format).defaults;
}

/** Restore the active layout without changing its ratio or other saved layouts. */
export function resetFormatSettings(component, format) {
    const { fields, defaults } = formatDefaults(format, component[RATIO_FIELDS[format]]);
    const ratioFields = new Set(Object.values(RATIO_FIELDS));

    fields.forEach(field => {
        if (field in defaults && !ratioFields.has(field)) {
            component[field] = defaults[field];
        }
    });
}
