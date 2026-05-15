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
//   { type: 'ligature', notes: Note[] }               — one or more glued notes
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
    const lines = (source ?? '').replace(/\r\n/g, '\n').split('\n');
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
    const body = lines.slice(bodyStart);
    const result = [];
    for (const raw of body) {
        if (raw.trim() === '') {
            result.push({ type: 'blank' });
            continue;
        }
        if (/^\s*w:/.test(raw)) {
            result.push({ type: 'lyrics', text: raw.replace(/^\s*w:\s?/, '') });
            continue;
        }
        result.push({ type: 'music', tokens: tokenizeMusicLine(raw) });
    }
    return { header, lines: result };
}

function isPitchLetter(c) {
    return /[a-mA-M]/.test(c);
}

function tokenizeMusicLine(line) {
    const tokens = [];
    const len = line.length;
    let i = 0;
    while (i < len) {
        const ch = line[i];
        if (ch === ' ' || ch === '\t') {
            i++;
            continue;
        }
        if (ch === '(') {
            const end = line.indexOf(')', i);
            const value = end < 0 ? line.slice(i + 1) : line.slice(i + 1, end);
            i = end < 0 ? len : end + 1;
            const inner = value.trim();
            const bareBar = inner.match(/^([,;]|::?)$/);
            if (bareBar) {
                tokens.push({ type: 'barline', kind: bareBar[1] });
            } else {
                tokens.push({ type: 'directive', value: inner });
            }
            continue;
        }
        if (ch === '*') {
            tokens.push({ type: 'expander' });
            i++;
            continue;
        }
        if (ch === ':' && line[i + 1] === ':') {
            tokens.push({ type: 'barline', kind: '::' });
            i += 2;
            continue;
        }
        if (ch === ',' || ch === ';' || ch === ':') {
            tokens.push({ type: 'barline', kind: ch });
            i++;
            continue;
        }
        if (isPitchLetter(ch)) {
            const group = [];
            while (i < len && isPitchLetter(line[i])) {
                const pitchChar = line[i];
                i++;
                const note = {
                    pitch: pitchChar.toLowerCase(),
                    virga: pitchChar !== pitchChar.toLowerCase(),
                    high: false,
                    shape: pitchChar === pitchChar.toLowerCase() ? 'punctum' : 'virga',
                    modifiers: [],
                };
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
                    break;
                }
                group.push(note);
            }
            if (group.length) {
                tokens.push({ type: 'ligature', notes: group });
            }
            continue;
        }
        // Unknown character — skip silently to keep editing forgiving.
        i++;
    }
    return tokens;
}
