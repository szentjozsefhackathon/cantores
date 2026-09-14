import { DEFAULT_LINE_HEIGHT } from './booklet-markdown.js';
import { formatDefaults } from './score-editor-settings.js';

/**
 * How a score gets its render settings inside a projection.
 *
 * Three layers, one fewer than a booklet has, and the missing one is the point:
 *
 *   1. the format's factory defaults *for this ratio* — the screen defaults, not
 *      the paper ones
 *   2. what the score's author chose for this ratio, in the score editor
 *   3. the slide's own override, which is whatever a person changed by hand
 *
 * A booklet has a third layer between 2 and 3 where it imposes its own size,
 * width and face on every score, because it puts scores engraved for different
 * nominal pages onto one real sheet and they would otherwise come out at
 * different sizes. A projection has no such layer and must not grow one. Its
 * scores were each tuned by their author against this very canvas — the score
 * editor's 16:9 preview is engraved at exactly the size this renders at — so
 * imposing a deck-wide size would overrule the only person who has actually
 * looked at the thing on a screen.
 *
 * What is left for layer 3 is the narrow, real case the booklet's layer 4 also
 * serves: the borrowed score that does not quite fit, adjusted here because the
 * score is not this cantor's to edit, and because next month's deck at another
 * ratio needs different numbers anyway.
 */

/**
 * The score's own settings for one projector ratio.
 *
 * The third sibling of the score editor's `readRatioBucket` and the booklet's
 * `paperBucket`, and the only one of the three that reads a fixed ratio.
 *
 * There is deliberately no fall-back to the paper bucket. A score that has never
 * been opened at 16:9 has no 16:9 bucket, and the right answer then is the
 * format's screen defaults — large condensed type on a wide canvas — not the
 * author's page layout, which would put 11-point lyrics on a projector. This is
 * the same rule the score editor itself follows when it opens a ratio for the
 * first time.
 */
export function ratioBucket(scoreSettings, format, ratio) {
    return { ...(scoreSettings?.[format]?.[ratio] ?? {}) };
}

/**
 * The settings one slide is actually engraved with.
 *
 * @param {string} format gabc | abc | aretino | chordpro
 * @param {object} scoreSettings the score's whole settings column
 * @param {string} ratio 16/9 | 4/3 | 1/1
 * @param {object} [override] the slide's own bucket
 */
export function resolveSlideSettings(format, scoreSettings, ratio, override) {
    return {
        ...formatDefaults(format, ratio).defaults,
        ...ratioBucket(scoreSettings, format, ratio),
        ...(override ?? {}),
    };
}

/**
 * The same, with one key taken out — what the slide would show if that knob had
 * never been touched. This is how the panel tells "changed it" from "set it to
 * what it already was".
 */
export function inheritedSlideSetting(format, scoreSettings, ratio, override, key) {
    const without = { ...(override ?? {}) };
    delete without[key];

    return resolveSlideSettings(format, scoreSettings, ratio, without)[key];
}

/**
 * An uploaded score has no settings of its own — it is a picture of a page by
 * the time it reaches a screen — so the only thing to resolve is how large it is
 * drawn, and the only answer is the one the slide carries.
 */
export function fileSlideSettings(override) {
    return { fileZoom: 1, ...(override ?? {}) };
}

/**
 * A screen of words: the deck's own text size and leading, unless this row has
 * said otherwise.
 *
 * Two layers rather than three, because a rubric has no engine defaults and no
 * author — the words were typed into the row itself. Both numbers are factors of
 * what the slide computes from its own height, so a deck keeps its proportions
 * at every ratio.
 *
 * @param {object|null} override the slide's own bucket for this ratio
 * @param {object} geometry the payload's geometry — Projection::geometry()
 * @returns {{textSizeScale: number, textLineHeight: number}}
 */
export function textSlideSettings(override, geometry) {
    const scale = Number(geometry?.textSizeScale);
    const lineHeight = Number(geometry?.textLineHeight);

    return {
        textSizeScale: scale > 0 ? scale : 1,
        textLineHeight: lineHeight > 0 ? lineHeight : DEFAULT_LINE_HEIGHT,
        ...(override ?? {}),
    };
}
