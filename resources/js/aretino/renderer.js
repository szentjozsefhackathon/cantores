import { parseAretino } from './parser.js';
import {
    pitchToPos,
    pitchY,
    drawNoteHead,
    drawEpisema,
    drawMora,
    drawLiquescens,
    drawLigatureConnector,
    drawStaffLines,
    drawClef,
    drawAccidental,
    drawBarline,
    escapeText,
    escapeAttr,
} from './glyphs.js';

const DEFAULT_FONT = "'Palatino Linotype', 'Book Antiqua', Palatino, serif";

export function renderAretino(source, options = {}) {
    const ast = parseAretino(source);
    const canvasWidth = options.canvasWidth || 1920;
    const canvasHeight = options.canvasHeight || null;
    const staffScale = Math.max(0.1, (options.staffSize ?? 100) / 100);
    const noteSpacing = Math.max(0.5, options.noteSpacing ?? 1);
    const lyricFont = options.lyricFont || DEFAULT_FONT;
    const baseUnit = 8;

    const ctx = {
        unit: baseUnit * staffScale,
    };
    ctx.staffSpace = 2 * ctx.unit;
    ctx.staffHeight = 4 * ctx.staffSpace;
    ctx.noteW = 1.8 * ctx.unit;
    ctx.noteH = 1.5 * ctx.unit;
    ctx.defaultAdvance = 3.5 * ctx.unit * noteSpacing;
    ctx.ligatureAdvance = 1.9 * ctx.unit;
    ctx.lyricFont = lyricFont;
    ctx.lyricSize = Math.max(6, (options.lyricSize ?? 12) * staffScale);
    ctx.canvasWidth = canvasWidth;
    ctx.leftMargin = 10 * ctx.unit;
    ctx.rightMargin = 6 * ctx.unit;
    ctx.systemGap = 3 * ctx.unit;
    ctx.lyricToNextStaff = 5 * ctx.unit;

    const systems = groupSystems(ast.lines);

    const parts = [];
    let currentClef = { letter: 'g', line: 2 };
    let y = ctx.unit * 3;

    if (ast.header && Object.keys(ast.header).length) {
        const title = ast.header['cím'] || ast.header['title'];
        if (title) {
            const fontSize = ctx.lyricSize * 1.6;
            y += fontSize;
            parts.push(`<text x="${canvasWidth / 2}" y="${y}" font-family="${escapeAttr(lyricFont)}" font-size="${fontSize}" font-weight="bold" text-anchor="middle" fill="#000">${escapeText(title)}</text>`);
            y += fontSize * 0.4;
        }
    }

    for (const sys of systems) {
        const staffBottomY = y + ctx.staffHeight;
        parts.push(drawStaffLines(ctx, ctx.leftMargin, canvasWidth - ctx.rightMargin, staffBottomY));

        let cursor = { x: ctx.leftMargin, clef: currentClef, glyphs: [] };

        // Always emit a clef at the start of a system.
        const startClef = drawClef(ctx, cursor.clef, cursor.x, staffBottomY);
        parts.push(startClef.svg);
        cursor.x += startClef.advance + ctx.unit * 0.6;

        // Layout pass — collect glyph instructions, with expander spots for justification.
        const expanderIndices = [];
        const items = [];

        for (const tok of sys.music) {
            if (tok.type === 'directive') {
                const v = tok.value;
                const clefM = v.match(/^([gfcGFC])([0-9])$/);
                if (clefM) {
                    cursor.clef = { letter: clefM[1].toLowerCase(), line: parseInt(clefM[2], 10) };
                    const c = drawClef(ctx, cursor.clef, cursor.x, staffBottomY);
                    parts.push(c.svg);
                    cursor.x += c.advance + ctx.unit * 0.5;
                    continue;
                }
                const accM = v.match(/^([a-mA-M]?)b([xy#])$/);
                if (accM) {
                    const a = drawAccidental(ctx, (accM[1] || 'b').toLowerCase(), accM[2], cursor.x, staffBottomY);
                    parts.push(a.svg);
                    cursor.x += a.advance;
                    continue;
                }
                if (v === 'z') {
                    continue;
                }
                continue;
            }
            if (tok.type === 'expander') {
                items.push({ kind: 'expander' });
                expanderIndices.push(items.length - 1);
                continue;
            }
            if (tok.type === 'barline') {
                items.push({ kind: 'barline', value: tok.kind });
                continue;
            }
            if (tok.type === 'ligature') {
                items.push({ kind: 'ligature', notes: tok.notes });
            }
        }

        // Measure natural width (no expanders).
        let natural = 0;
        for (const it of items) {
            if (it.kind === 'expander') {
                natural += ctx.unit * 1.5;
            } else if (it.kind === 'barline') {
                natural += measureBarline(ctx, it.value);
            } else if (it.kind === 'ligature') {
                natural += measureLigature(ctx, it.notes);
            }
        }
        const available = (canvasWidth - ctx.rightMargin) - cursor.x;
        const extra = Math.max(0, available - natural);
        const extraPer = expanderIndices.length > 0 ? extra / expanderIndices.length : 0;

        // Emit items.
        for (let idx = 0; idx < items.length; idx++) {
            const it = items[idx];
            if (it.kind === 'expander') {
                cursor.x += ctx.unit * 1.5 + extraPer;
            } else if (it.kind === 'barline') {
                const b = drawBarline(ctx, it.value, cursor.x, staffBottomY);
                parts.push(b.svg);
                cursor.x += b.advance + ctx.unit * 0.4;
            } else if (it.kind === 'ligature') {
                const r = emitLigature(ctx, it.notes, cursor.x, staffBottomY);
                parts.push(r.svg);
                cursor.x += r.advance;
            }
        }

        currentClef = cursor.clef;

        // Lyric line(s).
        let lyricY = staffBottomY + ctx.systemGap + ctx.lyricSize;
        if (sys.lyrics.length > 0) {
            for (const lyric of sys.lyrics) {
                parts.push(`<text xml:space="preserve" x="${ctx.leftMargin}" y="${lyricY}" font-family="${escapeAttr(ctx.lyricFont)}" font-size="${ctx.lyricSize}" fill="#000">${escapeText(lyric)}</text>`);
                lyricY += ctx.lyricSize * 1.4;
            }
            y = lyricY + ctx.lyricToNextStaff;
        } else {
            y = staffBottomY + ctx.systemGap + ctx.lyricToNextStaff;
        }
    }

    const totalHeight = canvasHeight || Math.max(y + ctx.unit * 2, 100);
    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${canvasWidth} ${totalHeight}" preserveAspectRatio="xMidYMin meet" width="100%" style="display:block">${parts.join('')}</svg>`;
}

function groupSystems(lines) {
    const systems = [];
    let pending = null;
    for (const item of lines) {
        if (item.type === 'music') {
            if (pending) {
                systems.push(pending);
            }
            pending = { music: item.tokens, lyrics: [] };
        } else if (item.type === 'lyrics') {
            if (!pending) {
                pending = { music: [], lyrics: [] };
            }
            pending.lyrics.push(item.text);
        }
    }
    if (pending) {
        systems.push(pending);
    }
    return systems;
}

function measureBarline(ctx, kind) {
    if (kind === '::') {
        return ctx.unit * 3 + ctx.unit * 0.4;
    }
    return ctx.unit * 1.6 + ctx.unit * 0.4;
}

function measureLigature(ctx, notes) {
    if (notes.length <= 1) {
        return ctx.defaultAdvance;
    }
    return ctx.defaultAdvance + (notes.length - 1) * ctx.ligatureAdvance;
}

function emitLigature(ctx, notes, x, staffBottomY) {
    const parts = [];
    const positions = [];
    let cx = x + ctx.noteW * 0.5;
    for (let i = 0; i < notes.length; i++) {
        const note = notes[i];
        const cy = pitchY(ctx, note, staffBottomY);
        positions.push({ note, cx, cy });
        if (i < notes.length - 1) {
            cx += ctx.ligatureAdvance;
        }
    }

    // Draw ligature connectors first (under the heads).
    for (let i = 1; i < positions.length; i++) {
        const prev = positions[i - 1];
        const cur = positions[i];
        const prevPos = pitchToPos(prev.note);
        const curPos = pitchToPos(cur.note);
        if (curPos > prevPos) {
            parts.push(drawLigatureConnector(ctx, prev.cx + ctx.noteW * 0.45, prev.cy, cur.cx + ctx.noteW * 0.45, cur.cy, 'up'));
        } else if (curPos < prevPos) {
            parts.push(drawLigatureConnector(ctx, prev.cx, prev.cy, cur.cx, cur.cy, 'down'));
        }
    }

    // Draw note heads + modifiers.
    for (const p of positions) {
        parts.push(drawNoteHead(ctx, p.note, p.cx, p.cy, staffBottomY));
        for (const mod of p.note.modifiers) {
            if (mod === 'episema') {
                parts.push(drawEpisema(ctx, p.cx, p.cy));
            } else if (mod === 'mora') {
                parts.push(drawMora(ctx, p.cx, p.cy));
            } else if (mod === 'liquescens') {
                parts.push(drawLiquescens(ctx, p.cx, p.cy, 'down'));
            }
        }
    }

    const advance = notes.length > 1
        ? ctx.defaultAdvance + (notes.length - 1) * ctx.ligatureAdvance
        : ctx.defaultAdvance;
    return { svg: parts.join(''), advance };
}
