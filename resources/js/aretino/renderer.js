import { parseAretino } from './parser.js';
import {
    METRICS,
    pitchToPos,
    pitchY,
    drawNoteHead,
    drawEpisema,
    drawMora,
    drawLiquescens,
    drawLigatureConnector,
    ligatureConnectorHalfStroke,
    noteheadRightPoint,
    noteheadLeftPoint,
    drawStaffLines,
    drawClef,
    drawAccidental,
    drawBarline,
    escapeText,
    escapeAttr,
} from './glyphs.js';

const DEFAULT_FONT = "'Palatino Linotype', 'Book Antiqua', Palatino, serif";

// One staff space in pixels at 100% staffSize.
const BASE_STAFF_SPACE_PX = 16;

function ss(ctx, n) {
    return n * ctx.staffSpace;
}

export function renderAretino(source, options = {}) {
    const ast = parseAretino(source);
    const canvasWidth = options.canvasWidth || 1920;
    const canvasHeight = options.canvasHeight || null;
    const staffScale = Math.max(0.1, (options.staffSize ?? 100) / 100);
    const noteSpacing = Math.max(0.5, options.noteSpacing ?? 1);
    const lyricFont = options.lyricFont || DEFAULT_FONT;

    // The whole engraving is parameterised by a single pixel-size: staffSpace.
    // Everything else (margins, advances, glyph dimensions) is a multiple of
    // it via METRICS.
    const ctx = {
        staffSpace: BASE_STAFF_SPACE_PX * staffScale,
    };
    ctx.pitchStep = ctx.staffSpace / 2;
    ctx.staffHeight = (METRICS.staffLineCount - 1) * ctx.staffSpace;
    ctx.singleNoteAdvance = ss(ctx, METRICS.singleNoteAdvance) * noteSpacing;
    ctx.ligatureStepAdvance = ss(ctx, METRICS.ligatureStepAdvance);
    ctx.expanderWidth = ss(ctx, METRICS.expanderWidth);
    ctx.neumeGapAdvance = ss(ctx, METRICS.neumeGapAdvance);
    ctx.leftMargin = ss(ctx, METRICS.leftMargin);
    ctx.rightMargin = ss(ctx, METRICS.rightMargin);
    ctx.systemGap = ss(ctx, METRICS.systemGap);
    ctx.lyricToNextStaff = ss(ctx, METRICS.lyricToNextStaff);
    ctx.lyricFont = lyricFont;
    ctx.lyricSize = Math.max(6, (options.lyricSize ?? 12) * staffScale);
    ctx.canvasWidth = canvasWidth;

    const systems = groupSystems(ast.lines);

    const parts = [];
    let currentClef = { letter: 'g', line: 2 };
    let y = ss(ctx, METRICS.titleTopPadding);

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
                    cursor.x += c.advance + ss(ctx, METRICS.clefInlinePostGap);
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
            if (tok.type === 'spacer') {
                items.push({ kind: 'spacer', multiplier: tok.multiplier });
                continue;
            }
            if (tok.type === 'ligature') {
                items.push({ kind: 'ligature', groups: tok.groups });
            }
        }

        // Measure natural width (no expanders).
        let natural = 0;
        for (let idx = 0; idx < items.length; idx++) {
            const it = items[idx];
            if (it.kind === 'expander') {
                natural += ctx.expanderWidth;
            } else if (it.kind === 'barline') {
                natural += measureBarline(ctx, it.value);
            } else if (it.kind === 'spacer') {
                natural += ss(ctx, METRICS.spacerAdvance) * it.multiplier;
            } else if (it.kind === 'ligature') {
                natural += measureLigature(ctx, it.groups);
            }
        }
        const available = (canvasWidth - ctx.rightMargin) - cursor.x;
        const extra = Math.max(0, available - natural);
        const extraPer = expanderIndices.length > 0 ? extra / expanderIndices.length : 0;

        for (let idx = 0; idx < items.length; idx++) {
            const it = items[idx];
            if (it.kind === 'expander') {
                cursor.x += ctx.expanderWidth + extraPer;
            } else if (it.kind === 'barline') {
                const b = drawBarline(ctx, it.value, cursor.x, staffBottomY);
                parts.push(b.svg);
                cursor.x += b.advance + ss(ctx, METRICS.barlinePostGap);
            } else if (it.kind === 'spacer') {
                cursor.x += ss(ctx, METRICS.spacerAdvance) * it.multiplier;
            } else if (it.kind === 'ligature') {
                const r = emitLigature(ctx, it.groups, cursor.x, staffBottomY);
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

    const totalHeight = canvasHeight || Math.max(y + ctx.staffSpace, 100);
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
    const base = kind === '::'
        ? ss(ctx, METRICS.barlineDoubleAdvance)
        : ss(ctx, METRICS.barlineAdvance);
    return base + ss(ctx, METRICS.barlinePostGap);
}

// groups: Note[][] — each group is a run of notes; groups are separated by neumatic cuts ('/').
// All groups except the last contribute noteBoxWidth + steps + neumeGapAdvance;
// the last group contributes the normal singleNoteAdvance + steps (trailing slack included).
function measureLigature(ctx, groups) {
    let total = 0;
    for (let g = 0; g < groups.length; g++) {
        const notes = groups[g];
        const n = notes.length;
        if (g < groups.length - 1) {
            total += ss(ctx, METRICS.noteBoxWidth) + (n - 1) * ctx.ligatureStepAdvance + ctx.neumeGapAdvance;
        } else {
            const lastNote = notes[n - 1];
            const hasMora = lastNote.modifiers && lastNote.modifiers.includes('mora');
            const moraExtra = hasMora ? ss(ctx, METRICS.moraOffsetX + METRICS.moraRadius) : 0;
            total += ctx.singleNoteAdvance + (n - 1) * ctx.ligatureStepAdvance + moraExtra;
        }
    }
    return total;
}

function emitLigature(ctx, groups, x, staffBottomY) {
    const parts = [];
    const halfSW = ligatureConnectorHalfStroke(ctx);
    let groupStartX = x;

    for (let g = 0; g < groups.length; g++) {
        const notes = groups[g];
        const positions = [];
        let cx = groupStartX + ss(ctx, METRICS.noteBoxWidth) * 0.5;

        for (let i = 0; i < notes.length; i++) {
            const note = notes[i];
            const cy = pitchY(ctx, note, staffBottomY);
            positions.push({ note, cx, cy });
            if (i < notes.length - 1) {
                cx += ctx.ligatureStepAdvance;
            }
        }

        // Auto-virga per group: every local pitch peak gets a downward stem on the left.
        // Left side strict (>), right side non-strict (>=) so only the first note of a
        // plateau is marked.
        const autoVirga = new Array(notes.length).fill(false);
        if (notes.length >= 2) {
            const pitchPositions = notes.map(n => pitchToPos(n));
            const hasVariation = Math.max(...pitchPositions) > Math.min(...pitchPositions);
            if (hasVariation) {
                for (let i = 0; i < notes.length; i++) {
                    const higherThanLeft = i === 0 || pitchPositions[i] > pitchPositions[i - 1];
                    const atLeastAsHighAsRight = i === notes.length - 1 || pitchPositions[i] >= pitchPositions[i + 1];
                    if (higherThanLeft && atLeastAsHighAsRight) {
                        autoVirga[i] = true;
                    }
                }
            }
        }

        // Draw ligature connectors first (under the heads).
        for (let i = 1; i < positions.length; i++) {
            const prev = positions[i - 1];
            const cur = positions[i];
            if (cur.note.shape === 'virga' || cur.note.virga) {
                continue;
            }
            const prevPos = pitchToPos(prev.note);
            const curPos = pitchToPos(cur.note);
            if (curPos === prevPos) {
                continue;
            }
            const from = noteheadRightPoint(ctx, prev.cx, prev.cy);
            const to = noteheadLeftPoint(ctx, cur.cx, cur.cy);
            const kind = curPos > prevPos ? 'up' : 'down';
            parts.push(drawLigatureConnector(ctx, from.x - halfSW, from.y, to.x + halfSW, to.y, kind));
        }

        // Draw note heads + modifiers.
        for (let i = 0; i < positions.length; i++) {
            const p = positions[i];
            const prevCy = i > 0 ? positions[i - 1].cy : null;
            const drawnNote = autoVirga[i] ? { ...p.note, virga: true } : p.note;
            parts.push(drawNoteHead(ctx, drawnNote, p.cx, p.cy, staffBottomY, prevCy));
            for (const mod of p.note.modifiers) {
                if (mod === 'episema') {
                    const onLine = pitchToPos(p.note) % 2 === 0;
                    parts.push(drawEpisema(ctx, p.cx, p.cy, onLine));
                } else if (mod === 'mora') {
                    const onLine = pitchToPos(p.note) % 2 === 0;
                    parts.push(drawMora(ctx, p.cx, p.cy, onLine));
                } else if (mod === 'liquescens') {
                    parts.push(drawLiquescens(ctx, p.cx, p.cy, 'down'));
                }
            }
        }

        if (g < groups.length - 1) {
            groupStartX += ss(ctx, METRICS.noteBoxWidth) + (notes.length - 1) * ctx.ligatureStepAdvance + ctx.neumeGapAdvance;
        }
    }

    return { svg: parts.join(''), advance: measureLigature(ctx, groups) };
}
