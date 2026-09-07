/**
 * The B flat spelling, applied to what chordsheetjs has already rendered.
 *
 * German notation is the parser's own since 15.5, and it writes B flat as `B`
 * and B natural as `H`. This app keeps the English `Bb` for the flat instead, so
 * that neither of the two can be read as the other, and chordsheetjs has no
 * setting for that — the only `Notation` it knows is `'german'`, and mutating a
 * chord string on the parsed song does not help, because the formatters re-parse
 * it and normalise it straight back.
 *
 * So the change is made on the output. Unlike the substitutions this replaced,
 * it runs after the transposition rather than instead of it: it can only respell
 * a chord, never move it.
 */

/**
 * German `B` (B flat) written as `Bb`. `H` is left alone — it is B natural and
 * already unambiguous — and so is `B#`, which German mode does emit for some
 * enharmonic spellings and where `Bb#` would be nonsense.
 *
 * @param {string} chord
 * @returns {string}
 */
export function spellFlatB(chord) {
    return chord.replace(/B(?![b#])/g, 'Bb');
}

/**
 * The same, inside the chord cells of a rendered chord sheet.
 *
 * Both HTML formatters are covered: the div one used for the preview and the
 * export, and the table one used for the clipboard.
 *
 * @param {string} html
 * @returns {string}
 */
export function spellFlatBInHtml(html) {
    return html.replace(
        /(<(?:div|td) class="chord">)([^<]*)(<\/(?:div|td)>)/g,
        (_, open, chord, close) => open + spellFlatB(chord) + close,
    );
}

/**
 * The same, in a plain text rendering, where nothing marks a chord row.
 *
 * A row is taken for chords when every token on it is a chord this very song
 * contains, which no line of lyrics can satisfy without being made up entirely
 * of chord names. Guessing matters here: a blind substitution would turn the
 * Hungarian "Boldog" into "Bboldog".
 *
 * @param {string} text
 * @param {Set<string>|string[]} chords every chord string the song renders
 * @returns {string}
 */
export function spellFlatBInText(text, chords) {
    const known = chords instanceof Set ? chords : new Set(chords);

    return text
        .split('\n')
        .map((line) => {
            const tokens = line.trim().split(/\s+/).filter(Boolean);
            const isChordRow = tokens.length > 0 && tokens.every((token) => known.has(token));

            return isChordRow ? spellFlatB(line) : line;
        })
        .join('\n');
}

/**
 * Every chord a parsed song carries, as it will be rendered.
 *
 * @param {{bodyParagraphs?: Array}} song
 * @returns {string[]}
 */
export function chordStringsOf(song) {
    return (song.bodyParagraphs ?? song.paragraphs ?? [])
        .flatMap((paragraph) => paragraph.lines ?? [])
        .flatMap((line) => line.items ?? [])
        .map((item) => item.chords)
        .filter((chords) => typeof chords === 'string' && chords !== '');
}
