/**
 * The ink a projector slide is set in.
 *
 * The JavaScript half of App\Enums\ProjectionTextTheme, key for key, and the
 * fall-back for a payload that predates it — a deck drawn by a client that
 * arrived before the server did still has to be some colour.
 *
 * It is its own module rather than a constant in projection-deck.js because two
 * unrelated renderers now need it: the deck, which colours a screen of words,
 * and the chord-sheet engraver, which the score editor calls directly and which
 * must not have to import a projection to know what black is.
 *
 * Only what is words. The three engines that engrave music draw ink on paper,
 * and a staff reversed out of black is harder to read across a nave rather than
 * easier — but a chord sheet is words with chords standing over them, so it is
 * coloured here with everything else that is read rather than played.
 */

/**
 * @typedef {object} SlidePalette
 * @property {string} background
 * @property {string} text the words as they are sung, and a rubric's body
 * @property {string} chord a chord symbol over them
 * @property {string} label a section label, a `{comment}`, an annotation
 * @property {string} quote
 * @property {string} rule
 * @property {string} accent what `<red>` comes out as
 */

/** @type {Object<string, SlidePalette>} */
export const SLIDE_PALETTES = {
    dark: {
        background: '#000000',
        text: '#ffffff',
        // Missal red and a royal blue both die on black; each answers with the
        // brightness the colour has to have to survive the room.
        chord: '#7dd3fc',
        label: '#b4b4b4',
        quote: '#b4b4b4',
        rule: '#666666',
        accent: '#ff6b6b',
    },
    light: {
        background: '#ffffff',
        text: '#000000',
        chord: '#1d4ed8',
        label: '#555555',
        quote: '#555555',
        rule: '#999999',
        accent: '#cc0000',
    },
};

/**
 * White on black: what a projector in a darkened church is expected to do, and
 * what anything drawn for a screen without a deck around it gets.
 */
export const DEFAULT_SLIDE_THEME = 'dark';

/**
 * The colours one deck sets its words in.
 *
 * The server states the whole palette in the geometry, so a colour is decided in
 * one place and travels; the name is what is left when it did not.
 *
 * @param {{textTheme?: string, textPalette?: Partial<SlidePalette>}} [geometry]
 * @return {SlidePalette}
 */
export function slidePalette(geometry) {
    const named = SLIDE_PALETTES[geometry?.textTheme] ?? SLIDE_PALETTES[DEFAULT_SLIDE_THEME];

    return { ...named, ...(geometry?.textPalette ?? {}) };
}
