const NOTE_LETTER = { g: 'G', a: 'A', h: 'B', c: 'C', d: 'D', e: 'E', f: 'F' };

const SHARP_KEYS = ['C', 'G', 'D', 'A', 'E', 'B', 'F#', 'C#'];
const FLAT_KEYS = ['C', 'F', 'Bb', 'Eb', 'Ab', 'Db', 'Gb', 'Cb'];

const CLEF_MAP = {
    G: 'treble',
    F: 'bass',
    1: 'soprano',
    2: 'mezzosoprano',
    3: 'alto',
    4: 'tenor',
    5: 'baritone',
    a: 'soprano',
    c: 'mezzosoprano',
    e: 'alto',
    g: 'tenor',
    i: 'baritone',
};

/**
 * Diatonic shift (in scale steps) applied to a note's treble-anchored pitch so
 * that, under the given clef, it lands on the staff position Diatar encoded.
 * Diatar note letters describe staff lines/spaces (treble-anchored), not pitches,
 * so every non-treble clef offsets the absolute pitch by a fixed number of steps.
 */
const CLEF_SHIFT = {
    treble: 0,
    soprano: -2,
    mezzosoprano: -4,
    alto: -6,
    tenor: -8,
    baritone: -10,
    bass: -12,
};

const ACCIDENTAL = { 0: '=', k: '^', K: '^^', b: '_', B: '__' };

const DECORATION = {
    '-': '!tenuto!',
    '.': '.',
    '>': '!accent!',
    '^': '!marcato!',
    K: '!fermata!',
    m: '!mordent!',
    M: '!lowermordent!',
    t: '!trill!',
    T: '!trill!',
};

const DURATION_EIGHTHS = { 1: 8, 2: 4, 4: 2, 8: 1, 6: 0.5, l: 32, b: 16, s: 8 };

const METER = { 2: '2/4', 3: '3/4', 4: '4/4', 5: '5/4', 6: '6/4' };
const SPECIAL_METER = { 2: '2/2', 3: '3/2', 6: '6/8', 8: '3/8' };

const DIATONIC_INDEX = { C: 0, D: 1, E: 2, F: 3, G: 4, A: 5, B: 6 };
const DIATONIC_NAME = ['C', 'D', 'E', 'F', 'G', 'A', 'B'];

const HUNGARIAN_VOWELS = 'aáeéiíoóöőuúüű';
const HUNGARIAN_DIGRAPHS = ['cs', 'dz', 'gy', 'ly', 'ny', 'sz', 'ty', 'zs'];

/**
 * Trailing consonant unit of a Hungarian syllable, treating digraphs (cs, sz,
 * gy, …) and the trigraph dzs as a single consonant. Returns null when the
 * syllable does not end on a consonant letter.
 *
 * @param {string} syllable
 * @return {?{unit: string, geminated: boolean}}
 */
function trailingConsonant(syllable) {
    const lower = syllable.toLowerCase();
    const last = lower.slice(-1);
    if (!/[a-záéíóöőúüű]/.test(last)) { return null; }
    if (HUNGARIAN_VOWELS.includes(last)) { return null; }
    if (lower.endsWith('dzs')) { return { unit: syllable.slice(-3), geminated: false }; }
    for (const digraph of HUNGARIAN_DIGRAPHS) {
        if (lower.endsWith(digraph)) {
            const geminated = lower.slice(-3, -2) === digraph[0];
            return { unit: syllable.slice(-2), geminated };
        }
    }
    return { unit: syllable.slice(-1), geminated: false };
}

/**
 * Diatar aligns lyrics to vowels manually, so an interior syllable may keep a
 * consonant that Hungarian phonotactics attaches to the following syllable
 * (e.g. "vétk-em" → "vét-kem"). When a syllable starts with a vowel and the
 * preceding same-word syllable ends with a consonant, the consonant is moved
 * across the boundary. Geminated digraphs ("ssz") split into full digraphs
 * either side ("asz-szony").
 *
 * @param {string[]} sylls
 * @param {string[]} seps Separator preceding each syllable ('-' joins one word)
 * @return {void}
 */
function fixHungarianSyllabification(sylls, seps) {
    for (let i = 1; i < sylls.length; i++) {
        if (seps[i] !== '-') { continue; }
        const prev = sylls[i - 1];
        const cur = sylls[i];
        if (prev === '' || cur === '') { continue; }
        const firstLetter = cur[0].toLowerCase();
        if (!HUNGARIAN_VOWELS.includes(firstLetter)) { continue; }
        const consonant = trailingConsonant(prev);
        if (!consonant) { continue; }

        let stripped;
        let moved;
        if (consonant.geminated) {
            stripped = prev.slice(0, -3) + consonant.unit;
            moved = consonant.unit;
        } else {
            stripped = prev.slice(0, -consonant.unit.length);
            moved = consonant.unit;
        }
        if (!/[aáeéiíoóöőuúüű]/i.test(stripped)) { continue; }

        sylls[i - 1] = stripped;
        sylls[i] = moved + cur;
    }
}

/**
 * @param {string} digit Diatar register digit
 * @param {string} letter Diatar note letter (staff position, treble-anchored)
 * @param {number} clefShift Diatonic shift for the active clef (see CLEF_SHIFT)
 * @return {?string} ABC pitch token, or null for an unknown letter
 */
function pitchToAbc(digit, letter, clefShift) {
    const lower = letter.toLowerCase();
    const name = NOTE_LETTER[lower];
    if (!name) { return null; }
    const reg = parseInt(digit, 10);
    const octave = (lower === 'g' || lower === 'a' || lower === 'h') ? reg + 2 : reg + 3;
    const diatonic = (octave - 4) * 7 + DIATONIC_INDEX[name] + clefShift;
    const finalName = DIATONIC_NAME[((diatonic % 7) + 7) % 7];
    const finalOctave = 4 + Math.floor(diatonic / 7);
    if (finalOctave >= 5) {
        return finalName.toLowerCase() + "'".repeat(finalOctave - 5);
    }
    if (finalOctave === 4) {
        return finalName;
    }
    return finalName + ','.repeat(4 - finalOctave);
}

function lengthSuffix(eighths) {
    if (eighths === 1) { return ''; }
    if (Number.isInteger(eighths)) { return String(eighths); }
    let num = eighths;
    let den = 1;
    while (!Number.isInteger(num)) {
        num *= 2;
        den *= 2;
    }
    const divisor = gcd(num, den);
    num /= divisor;
    den /= divisor;
    if (num === 1 && den === 2) { return '/'; }
    return den === 1 ? String(num) : `${num}/${den}`;
}

function gcd(a, b) {
    return b === 0 ? a : gcd(b, a % b);
}

function barline(param) {
    switch (param) {
        case '1': return '|';
        case '|': return '||';
        case '.': return '|]';
        case "'": return '|';
        case '!': return '||';
        case '>': return '|:';
        case ':': return '::';
        case '<': return ':|';
        default: return '|';
    }
}

/**
 * @param {string} line
 * @return {{leading: string, segments: Array<{notation: string, lyric: string}>}}
 */
function parseLine(line) {
    const segments = [];
    let i = 0;
    let leading = '';
    let firstSeen = false;

    while (i < line.length) {
        const c = line[i];
        if (c === '\\' && i + 1 < line.length && 'K?G'.includes(line[i + 1])) {
            firstSeen = true;
            i += 2;
            let notation = '';
            while (i < line.length && line[i] !== ';') {
                notation += line[i];
                i++;
            }
            i++;
            let lyric = '';
            while (i < line.length) {
                if (line[i] === '\\' && i + 1 < line.length) {
                    const next = line[i + 1];
                    if ('K?G'.includes(next)) { break; }
                    if (next === '\\') { lyric += '\\'; i += 2; continue; }
                    if (next === ' ') { lyric += ' '; i += 2; continue; }
                    if (next === '.') { lyric += ' '; i += 2; continue; }
                    i += 1;
                    continue;
                }
                lyric += line[i];
                i++;
            }
            segments.push({ notation, lyric });
        } else {
            if (!firstSeen) { leading += c; }
            i++;
        }
    }

    return { leading, segments };
}

/**
 * Convert a Diatar score (DTX inline notation) to ABC notation.
 *
 * @param {string} diatar
 * @return {string}
 */
export function diatarToAbc(diatar) {
    if (!diatar || !diatar.trim()) { return ''; }

    const rawLines = diatar.replace(/\r\n?/g, '\n').split('\n');

    let clef = 'treble';
    let clefShift = CLEF_SHIFT.treble;
    let key = 'C';
    let headerMeter = null;
    let bodyStarted = false;
    let currentEighths = 2;
    let stemless = false;
    let staffLines = null;

    const bodyLines = [];

    for (const rawLine of rawLines) {
        const { leading, segments } = parseLine(rawLine);
        if (segments.length === 0) { continue; }

        let music = '';
        let beaming = false;
        let beamFirst = true;
        let pendingPrefix = '';

        const sylls = [];
        const seps = [];
        let carryPrefix = '';
        let nextSep = '';
        let firstNoteOnLine = true;

        const pushNote = (str) => {
            if (music === '') {
                music = str;
            } else if (beaming && !beamFirst) {
                music += str;
            } else {
                music += ' ' + str;
            }
            if (beaming) { beamFirst = false; }
        };

        const pushOther = (str) => {
            music += (music === '' ? '' : ' ') + str;
            beamFirst = true;
        };

        const assignLyric = (run) => {
            let text = carryPrefix + run;
            carryPrefix = '';
            const hasLeadingSpace = /^\s/.test(text);
            const trimmed = text.replace(/^\s+/, '');
            const hasTrailingSpace = /\s$/.test(text);
            const tokens = trimmed.split(/\s+/).filter((token) => token !== '');
            let syllable;
            if (tokens.length === 0) {
                syllable = '';
                nextSep = ' ';
            } else if (hasTrailingSpace) {
                syllable = tokens.join('~');
                nextSep = ' ';
            } else if (tokens.length === 1) {
                syllable = tokens[0];
                nextSep = '-';
            } else {
                syllable = tokens.slice(0, -1).join('~');
                carryPrefix = tokens[tokens.length - 1];
                nextSep = ' ';
            }

            if (firstNoteOnLine) {
                const coreRaw = leading;
                if (coreRaw.trim() !== '') {
                    const core = coreRaw.trim().replace(/\s+/g, '~');
                    const glue = /\s$/.test(coreRaw) ? '~' : '';
                    syllable = core + glue + syllable;
                }
            }

            seps.push(firstNoteOnLine ? '' : (hasLeadingSpace ? ' ' : nextSepBefore));
            sylls.push(syllable);
            firstNoteOnLine = false;
        };

        let nextSepBefore = '';

        for (const segment of segments) {
            const block = segment.notation;
            let noteAssigned = false;

            for (let p = 0; p + 1 < block.length; p += 2) {
                const type = block[p];
                const param = block[p + 1];

                if (type === '-') {
                    if (!bodyStarted) {
                        const n = parseInt(param, 10);
                        if (!Number.isNaN(n)) { staffLines = n; }
                    }
                    continue;
                }
                if (type === 'k') {
                    const mapped = CLEF_MAP[param];
                    if (mapped) {
                        clefShift = CLEF_SHIFT[mapped] ?? 0;
                        if (bodyStarted) {
                            pushOther(`[K:clef=${mapped}]`);
                        } else {
                            clef = mapped;
                        }
                    }
                    continue;
                }
                if (type === 'E' || type === 'e') {
                    const n = parseInt(param, 10);
                    const table = type === 'E' ? SHARP_KEYS : FLAT_KEYS;
                    const k = table[n] ?? 'C';
                    if (bodyStarted) {
                        pushOther(`[K:${k}]`);
                    } else {
                        key = k;
                    }
                    continue;
                }
                if (type === 'u' || type === 'U') {
                    const table = type === 'u' ? METER : SPECIAL_METER;
                    const m = table[param];
                    if (m) {
                        if (bodyStarted) {
                            pushOther(`[M:${m}]`);
                        } else {
                            headerMeter = m;
                        }
                    }
                    continue;
                }
                if (type === '|') {
                    pushOther(barline(param));
                    bodyStarted = true;
                    continue;
                }
                if (type === 'm') {
                    const acc = ACCIDENTAL[param];
                    if (acc) { pendingPrefix += acc; }
                    continue;
                }
                if (type === 's' || type === 'S') {
                    let eighths = DURATION_EIGHTHS[param] ?? 2;
                    if (type === 'S') { eighths *= 1.5; }
                    pushOther('z' + lengthSuffix(eighths));
                    bodyStarted = true;
                    continue;
                }
                if (type === 'r' || type === 'R') {
                    if (param === 't') { continue; }
                    let eighths = DURATION_EIGHTHS[param];
                    if (eighths === undefined) { continue; }
                    if (type === 'R') { eighths *= 1.5; }
                    currentEighths = eighths;
                    continue;
                }
                if (type === '[') {
                    if (param === '?') {
                        beaming = true;
                        beamFirst = true;
                    } else if (param === '0') {
                        stemless = true;
                    } else if (param === '1') {
                        stemless = false;
                    } else if (param === '3') {
                        pendingPrefix += '(3';
                    } else if (param === '5') {
                        pendingPrefix += '(5';
                    }
                    continue;
                }
                if (type === ']') {
                    if (param === '?') {
                        beaming = false;
                    }
                    continue;
                }
                if (type === 'a') {
                    const dec = DECORATION[param];
                    if (dec) { pendingPrefix += dec; }
                    continue;
                }
                if (type === '(') {
                    pendingPrefix += '(';
                    continue;
                }
                if (type === ')') {
                    music += ')';
                    continue;
                }
                if (type === '1' || type === '2' || type === '3') {
                    const abcPitch = pitchToAbc(type, param, clefShift);
                    if (abcPitch) {
                        nextSepBefore = firstNoteOnLine ? '' : nextSep;
                        const noteStr = pendingPrefix + abcPitch + (stemless ? '0' : '') + lengthSuffix(currentEighths);
                        pendingPrefix = '';
                        pushNote(noteStr);
                        bodyStarted = true;
                        if (!noteAssigned) {
                            assignLyric(segment.lyric);
                            noteAssigned = true;
                        } else {
                            const savedSep = nextSep;
                            assignLyric('');
                            nextSep = savedSep;
                        }
                    }
                    continue;
                }
            }

            if (!noteAssigned && segment.lyric) {
                carryPrefix += segment.lyric;
            }
        }

        if (music.trim() === '') { continue; }

        fixHungarianSyllabification(sylls, seps);

        let wLine = '';
        for (let n = 0; n < sylls.length; n++) {
            let text = sylls[n] === '' ? '*' : sylls[n];
            if (n === 0) {
                wLine = text;
            } else {
                let sep = seps[n] || ' ';
                if (text === '*' || sylls[n - 1] === '') { sep = ' '; }
                wLine += sep + text;
            }
        }

        bodyLines.push({ music, lyrics: wLine.trim() });
    }

    if (bodyLines.length === 0) { return ''; }

    const header = ['X:1'];
    header.push('M:' + (headerMeter ?? 'none'));
    header.push('L:1/8');
    const clefParams = [];
    if (clef !== 'treble') { clefParams.push(`clef=${clef}`); }
    if (staffLines !== null && staffLines !== 5) { clefParams.push(`stafflines=${staffLines}`); }
    header.push('K:' + key + (clefParams.length ? ' ' + clefParams.join(' ') : ''));

    const out = [header.join('\n')];
    for (const bl of bodyLines) {
        out.push(bl.music);
        if (bl.lyrics) {
            out.push('w: ' + bl.lyrics);
        }
    }

    return out.join('\n') + '\n';
}
