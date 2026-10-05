import { ABC_RATIO_DEFAULTS, abcMixin } from './score-editor-abc.js';
import { ARETINO_RATIO_DEFAULTS, aretinoMixin } from './score-editor-aretino.js';
import { CHORDPRO_RATIO_DEFAULTS, chordproMixin } from './score-editor-chordpro.js';
import { GABC_SCREEN_DEFAULTS, gabcMixin } from './score-editor-gabc.js';

/**
 * The factory defaults of a format, and the fields that carry them.
 *
 * A mixin is the single place a format states what it looks like untouched, so
 * the defaults are read back out of a fresh one rather than restated here.
 */
const RATIO_FIELDS = { gabc: 'pageRatio', abc: 'abcPageRatio', aretino: 'aretinoPageRatio', chordpro: 'chordproPageRatio' };

export function formatDefaults(format, ratio = 'paper') {
    const factories = { gabc: gabcMixin, abc: abcMixin, chordpro: chordproMixin, aretino: aretinoMixin };
    const factory = factories[format];
    if (!factory) { return { fields: [], defaults: {} }; }

    const defaults = factory();
    if (['16/9', '4/3', '1/1'].includes(ratio)) {
        const screenDefaults = {
            abc: ABC_RATIO_DEFAULTS[ratio],
            aretino: ARETINO_RATIO_DEFAULTS[ratio],
            gabc: GABC_SCREEN_DEFAULTS[ratio],
            chordpro: CHORDPRO_RATIO_DEFAULTS[ratio],
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

/** Preview magnifications: they say how large the editor shows a layout, not what it is. */
const ZOOM_FIELDS = new Set(['zoom', 'abcZoom', 'aretinoZoom', 'chordproZoom']);

/**
 * What a layout is reset to: the format's factory defaults for the ratio, and
 * over them the person's own default for it where they saved one.
 *
 * @param {object|null} myBucket the person's saved default for this format and ratio
 */
export function defaultLayout(format, ratio, myBucket = null) {
    const { fields, defaults } = formatDefaults(format, ratio);
    const ratioFields = new Set(Object.values(RATIO_FIELDS));
    const layout = {};

    (fields ?? []).forEach(field => {
        if (ratioFields.has(field)) { return; }
        if (myBucket && field in myBucket) {
            layout[field] = myBucket[field];
        } else if (field in defaults) {
            layout[field] = defaults[field];
        }
    });

    return layout;
}

/** Restore the active layout without changing its ratio or other saved layouts. */
export function resetFormatSettings(component, format, myBucket = null) {
    Object.assign(component, defaultLayout(format, component[RATIO_FIELDS[format]], myBucket));
}

/**
 * Which default a layout is sitting on: the person's own ('mine'), the
 * factory's ('factory'), or neither ('custom').
 *
 * A new score opens on its author's saved default and an old one on whatever it
 * was saved with, and the two defaults can look nearly alike, so the editor has
 * to say which it is showing rather than leave it to be guessed from the numbers.
 * Their own default wins a tie: one saved at the factory's values is still theirs.
 *
 * @param {object} current the layout's settings as the editor would save them
 * @param {object|null} myBucket the person's saved default for this format and ratio
 * @returns {'mine'|'factory'|'custom'}
 */
export function layoutSource(format, ratio, current, myBucket = null) {
    const matches = layout => Object.entries(current ?? {}).every(([key, value]) => (
        ZOOM_FIELDS.has(key) || !(key in layout) || sameSetting(value, layout[key])
    ));

    if (myBucket && matches(defaultLayout(format, ratio, myBucket))) { return 'mine'; }
    if (matches(defaultLayout(format, ratio))) { return 'factory'; }

    return 'custom';
}

/**
 * Two values of one knob told apart as a person would: sizes converted from
 * points come back with a different last decimal, and a face is the same face
 * with or without the quotes one engine wants around it.
 */
function sameSetting(a, b) {
    if (typeof a === 'boolean' || typeof b === 'boolean') { return !!a === !!b; }

    const x = Number(a);
    const y = Number(b);
    if (a !== '' && b !== '' && a !== null && b !== null && Number.isFinite(x) && Number.isFinite(y)) {
        return Math.abs(x - y) <= 1e-3 * Math.max(1, Math.abs(x), Math.abs(y));
    }

    const unquoted = value => String(value ?? '').replace(/^['"]|['"]$/g, '');

    return unquoted(a) === unquoted(b);
}
