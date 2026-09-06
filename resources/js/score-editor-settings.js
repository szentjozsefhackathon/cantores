import { abcMixin } from './score-editor-abc.js';
import { aretinoMixin } from './score-editor-aretino.js';
import { chordproMixin } from './score-editor-chordpro.js';
import { gabcMixin } from './score-editor-gabc.js';

/**
 * The factory defaults of a format, and the fields that carry them.
 *
 * A mixin is the single place a format states what it looks like untouched, so
 * the defaults are read back out of a fresh one rather than restated here.
 */
export function formatDefaults(format) {
    if (format === 'gabc') { const m = gabcMixin(); return { fields: m.gabcFields, defaults: m }; }
    if (format === 'abc') { const m = abcMixin(); return { fields: m.abcFields, defaults: m }; }
    if (format === 'chordpro') { const m = chordproMixin(); return { fields: m.chordproFields, defaults: m }; }
    if (format === 'aretino') { const m = aretinoMixin(); return { fields: m.aretinoFields, defaults: m }; }

    return { fields: [], defaults: {} };
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
