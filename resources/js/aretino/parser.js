// Aretino source → AST.
//
// Returns:
// {
//     header: { [key: string]: string },
//     lines: Array<
//         | { type: 'music', tokens: Token[] }
//         | { type: 'lyrics', text: string }
//         | { type: 'blank' }
//     >
// }
//
// Token shapes:
//   { type: 'directive', value: string }              — anything inside ( )
//   { type: 'barline', kind: ',' | ';' | ':' | '::' }
//   { type: 'expander' }                              — `*`
//   { type: 'ligature', groups: Note[][] }            — one or more note groups; groups are separated by '/' cuts within the neume
//
// Note shape:
//   {
//       pitch: 'a'..'m',
//       virga: boolean,                  — uppercase letter
//       high: boolean,                   — trailing apostrophe (octave up)
//       shape: 'punctum' | 'virga' | 'quilisma' | 'tenor',
//       modifiers: Array<'episema'|'mora'|'liquescens'>,
//   }

export function parseAretino(source) {
    const src = source ?? '';
    const lines = src.replace(/\r\n/g, '\n').split('\n');
    // Absolute source offset of the first character of each line. Used to
    // translate per-line token positions into absolute positions that match
    // the textarea's selectionStart.
    const lineStarts = [];
    let off = 0;
    for (const line of lines) {
        lineStarts.push(off);
        off += line.length + 1;
    }
    const header = {};
    let bodyStart = 0;
    let sawHeaderEnd = false;
    for (let i = 0; i < lines.length; i++) {
        const line = lines[i];
        if (/^%%\s*$/.test(line)) {
            bodyStart = i + 1;
            sawHeaderEnd = true;
            break;
        }
        const m = line.match(/^;\s*([^:]+):\s*(.*)$/);
        if (m) {
            header[m[1].trim()] = m[2].trim();
            continue;
        }
        if (line.trim() === '') {
            continue;
        }
        bodyStart = i;
        break;
    }
    if (!sawHeaderEnd && Object.keys(header).length === 0) {
        bodyStart = 0;
    }
    const result = [];
    for (let li = bodyStart; li < lines.length; li++) {
        const raw = lines[li];
        const lineStart = lineStarts[li];
        if (raw.trim() === '') {
            result.push({ type: 'blank' });
            continue;
        }
        if (/^\s*w:/.test(raw)) {
            result.push({ type: 'lyrics', text: raw.replace(/^\s*w:\s?/, '') });
            continue;
        }
        result.push({ type: 'music', tokens: tokenizeMusicLine(raw, lineStart) });
    }
    return { header, lines: result };
}

function isPitchLetter(c) {
    return /[a-mA-M]/.test(c);
}

// Peek at position `pos` (which should be '(') to see if the parenthesized
// content is an accidental pattern like (ibx), (by), (c#), etc.
// Returns { pitch, symbol, end } where `end` is the index past ')' if it
// matches, or null if it doesn't.
function peekInlineAccidental(line, pos) {
    if (line[pos] !== '(') {
        return null;
    }
    const end = line.indexOf(')', pos);
    if (end < 0) {
        return null;
    }
    const inner = line.slice(pos + 1, end).trim();
    const m = inner.match(/^([a-mA-M]?)b([xy#])$/);
    if (!m) {
        return null;
    }
    return { pitch: (m[1] || 'b').toLowerCase(), symbol: m[2], end: end + 1 };
}

function tokenizeMusicLine(line, lineStart = 0) {
    const tokens = [];
    const len = line.length;
    let i = 0;
    while (i < len) {
        const ch = line[i];
        if (ch === ' ' || ch === '\t') {
            i++;
            continue;
        }
        const tokStart = i;
        if (ch === '(') {
            const end = line.indexOf(')', i);
            const value = end < 0 ? line.slice(i + 1) : line.slice(i + 1, end);
            i = end < 0 ? len : end + 1;
            const inner = value.trim();
            const srcStart = lineStart + tokStart;
            const srcEnd = lineStart + i;
            const bareBar = inner.match(/^([,;]|::?)$/);
            if (bareBar) {
                tokens.push({ type: 'barline', kind: bareBar[1], srcStart, srcEnd });
            } else if (/^sp([0-9]*\.?[0-9]*)$/i.test(inner)) {
                const m2 = inner.match(/^sp([0-9]*\.?[0-9]*)$/i);
                const multiplier = m2[1] ? parseFloat(m2[1]) : 1;
                tokens.push({ type: 'spacer', multiplier: isFinite(multiplier) && multiplier > 0 ? multiplier : 1, srcStart, srcEnd });
            } else {
                tokens.push({ type: 'directive', value: inner, srcStart, srcEnd });
            }
            continue;
        }
        if (ch === '*') {
            tokens.push({ type: 'expander', srcStart: lineStart + tokStart, srcEnd: lineStart + tokStart + 1 });
            i++;
            continue;
        }
        if (ch === ':' && line[i + 1] === ':') {
            tokens.push({ type: 'barline', kind: '::', srcStart: lineStart + tokStart, srcEnd: lineStart + tokStart + 2 });
            i += 2;
            continue;
        }
        if (ch === ',' || ch === ';' || ch === ':') {
            tokens.push({ type: 'barline', kind: ch, srcStart: lineStart + tokStart, srcEnd: lineStart + tokStart + 1 });
            i++;
            continue;
        }
        if (isPitchLetter(ch)) {
            const groups = [];
            const gaps = []; // 'neume' for each explicit '/' boundary between groups
            while (true) {
                const group = [];
                let pendingAcc = null;
                while (i < len && (isPitchLetter(line[i]) || (line[i] === '(' && peekInlineAccidental(line, i) !== null))) {
                    // Check for inline accidental before the next note.
                    if (line[i] === '(') {
                        pendingAcc = peekInlineAccidental(line, i);
                        // Advance past the directive including closing ')'.
                        i = pendingAcc.end;
                        continue;
                    }
                    const noteStart = i;
                    const pitchChar = line[i];
                    i++;
                    const note = {
                        pitch: pitchChar.toLowerCase(),
                        virga: pitchChar !== pitchChar.toLowerCase(),
                        high: false,
                        shape: pitchChar === pitchChar.toLowerCase() ? 'punctum' : 'virga',
                        modifiers: [],
                    };
                    if (pendingAcc) {
                        note.accidental = { pitch: pendingAcc.pitch, symbol: pendingAcc.symbol };
                        pendingAcc = null;
                    }
                    while (i < len) {
                        const m = line[i];
                        if (m === "'") {
                            note.high = true;
                            i++;
                            continue;
                        }
                        if (m === '_') {
                            note.modifiers.push('episema');
                            i++;
                            continue;
                        }
                        if (m === '.') {
                            note.modifiers.push('mora');
                            i++;
                            continue;
                        }
                        if (m === '~') {
                            note.modifiers.push('liquescens');
                            i++;
                            continue;
                        }
                        if (m === 'w') {
                            note.shape = 'quilisma';
                            i++;
                            continue;
                        }
                        if (m === 't') {
                            note.shape = 'tenor';
                            i++;
                            continue;
                        }
                        if (m === 's') {
                            note.modifiers.push('small');
                            i++;
                            continue;
                        }
                        break;
                    }
                    note.srcStart = lineStart + noteStart;
                    note.srcEnd = lineStart + i;
                    group.push(note);
                }
                if (group.length) {
                    groups.push(group);
                }
                // Check for '/' (skip surrounding whitespace) — '/' within a neume
                // connects the next note group to this ligature.
                let j = i;
                while (j < len && (line[j] === ' ' || line[j] === '\t')) { j++; }
                if (j < len && line[j] === '/') {
                    i = j + 1;
                    while (i < len && (line[i] === ' ' || line[i] === '\t')) { i++; }
                    // After '/', also allow an inline accidental before the next pitch.
                    if (i < len && (isPitchLetter(line[i]) || (line[i] === '(' && peekInlineAccidental(line, i) !== null))) {
                        gaps.push('neume');
                        continue;
                    }
                }
                break;
            }
            if (groups.length) {
                tokens.push({ type: 'ligature', groups, gaps, srcStart: lineStart + tokStart, srcEnd: lineStart + i });
            }
            continue;
        }
        // Unknown character — skip silently to keep editing forgiving.
        i++;
    }
    return tokens;
}
