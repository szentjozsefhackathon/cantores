import { DEFAULT_LINE_HEIGHT } from './booklet-markdown.js';
import { formatDefaults } from './score-editor-settings.js';

/**
 * How a score gets its render settings inside a projection.
 *
 * Four layers, read in this order, each overruling the one before:
 *
 *   1. the format's factory defaults *for this ratio* — the screen defaults, not
 *      the paper ones
 *   2. the deck's style — how everything is set for this one screen in this
 *      one church (App\Models\ProjectionStyle)
 *   3. what the score's author chose for this ratio, in the score editor
 *   4. the slide's own override, which is whatever a person changed by hand in
 *      this deck
 *
 * The order is the opposite of a booklet's, and the difference is the point. A
 * booklet puts scores engraved for different nominal pages onto one real sheet,
 * so it has to impose its sizes over theirs. A score laid out for 16:9 in the
 * score editor was laid out against this very canvas, by someone deciding which
 * line goes on which slide and how large; the style answers for every score
 * nobody has decided that for — the borrowed one, the one never projected — and
 * never undoes a decision somebody made.
 *
 * Layer 3 can still be set aside, a slide at a time: a slide whose override says
 * `fromStyle` follows the style instead of the score's layout. That is how the
 * borrowed score tuned for somebody else's screen is brought into line.
 */

/** The key a slide's override carries when it follows the style. */
export const FROM_STYLE = 'fromStyle';

/**
 * The score's own settings for one projector ratio.
 *
 * The third sibling of the score editor's `readRatioBucket` and the booklet's
 * `paperBucket`, and the only one of the three that reads a fixed ratio.
 *
 * There is deliberately no fall-back to the paper bucket. A score that has never
 * been opened at 16:9 has no 16:9 bucket, and the right answer then is the
 * style, or the format's screen defaults — large condensed type on a wide
 * canvas — not the author's page layout, which would put 11-point lyrics on a
 * projector. This is the same rule the score editor itself follows when it opens
 * a ratio for the first time.
 */
export function ratioBucket(scoreSettings, format, ratio) {
    return { ...(scoreSettings?.[format]?.[ratio] ?? {}) };
}

/** What the deck's style says about one format; nothing for a deck in none. */
export function styleBucket(style, format) {
    return { ...(style?.settings?.[format] ?? {}) };
}

/**
 * What a slide of this format looks like before its score has a say: the
 * factory defaults with the style over them.
 */
export function styledDefaults(format, ratio, style = null) {
    return { ...formatDefaults(format, ratio).defaults, ...styleBucket(style, format) };
}

/** How far a slide in this style may be set smaller to save a slide. */
export function styleMinScale(style) {
    const scale = Number(style?.minScale);

    return Number.isFinite(scale) && scale > 0 ? Math.min(1, Math.max(0.5, scale)) : 1;
}

/**
 * The settings one slide is actually engraved with.
 *
 * `slideMinScale` rides along for the slide renderers, which may set a slide that
 * is just too tall a little smaller rather than cut it in two. It is not a knob
 * and nothing stores it: it is the style's, and 1 — never smaller — for a deck in
 * no style.
 *
 * @param {string} format gabc | abc | aretino | chordpro
 * @param {object} scoreSettings the score's whole settings column
 * @param {string} ratio 16/9 | 4/3 | 1/1
 * @param {object} [override] the slide's own bucket
 * @param {object|null} [style] the deck's style, as Projection::geometry() hands it over
 */
export function resolveSlideSettings(format, scoreSettings, ratio, override, style = null) {
    const { [FROM_STYLE]: fromStyle, ...changed } = override ?? {};

    return {
        ...styledDefaults(format, ratio, style),
        ...(fromStyle ? {} : ratioBucket(scoreSettings, format, ratio)),
        ...changed,
        slideMinScale: styleMinScale(style),
    };
}

/**
 * The same, with one key taken out — what the slide would show if that knob had
 * never been touched. This is how the panel tells "changed it" from "set it to
 * what it already was".
 */
export function inheritedSlideSetting(format, scoreSettings, ratio, override, key, style = null) {
    const without = { ...(override ?? {}) };
    delete without[key];

    return resolveSlideSettings(format, scoreSettings, ratio, without, style)[key];
}

/**
 * The keys where the score's own layout for this ratio says something other than
 * the style would — the second of the two ways a slide can differ from the rest
 * of the deck, beside the changes made to it by hand.
 *
 * Only the knobs a style speaks to are compared: a score's transposition is not
 * a disagreement with a screen.
 *
 * @param {string[]} keys the knobs a style holds for this format
 * @param {(a: *, b: *) => boolean} differs how two values are told apart
 */
export function divergingKeys(format, scoreSettings, ratio, style, keys, differs) {
    const bucket = ratioBucket(scoreSettings, format, ratio);
    const styled = styledDefaults(format, ratio, style);

    return (keys ?? []).filter((key) => (
        Object.prototype.hasOwnProperty.call(bucket, key) && differs(bucket[key], styled[key])
    ));
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
