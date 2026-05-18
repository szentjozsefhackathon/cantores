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

function wrapSrc(item, svg, cls) {
    if (item.srcStart === undefined || item.srcEnd === undefined) {
        return svg;
    }
    return `<g class="${cls}" data-src-start="${item.srcStart}" data-src-end="${item.srcEnd}">${svg}</g>`;
}

// CSS rules embedded in the SVG so a cursor-tracking script can toggle a
// single class to highlight the active note/token.
const HIGHLIGHT_STYLE = `<style>.aretino-active [fill]:not([fill="none"]){fill:#ea580c}.aretino-active [stroke]:not([stroke="none"]){stroke:#ea580c}</style>`;

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
    // lyricSize is in typographic points; convert to SVG user units for the virtual canvas.
    // Assumes the SVG is displayed at ~750px CSS width (96/72 pt→px, scaled by canvas/display ratio).
    const lyricPt = Math.max(6, options.lyricSize ?? 12);
    ctx.lyricSize = lyricPt * (96 / 72) * (canvasWidth / 750);
    ctx.canvasWidth = canvasWidth;

    const sections = groupSections(ast.lines);

    const parts = [];
    let currentClef = { letter: 'g', line: 2 };
    let hasSeenClef = false;
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

    const staffRightX = canvasWidth - ctx.rightMargin;

    for (const sec of sections) {
        const items = flattenItems(sec.tokens);
        const sectionHasClef = items.some(it => it.kind === 'clef');
        if (sectionHasClef) {
            hasSeenClef = true;
        }
        const drawClefForRows = hasSeenClef;

        const verseSyllables = sec.lyrics.map(parseSyllables);
        const verseCount = sec.lyrics.length;
        const totalLigatures = items.reduce((n, it) => n + (it.kind === 'ligature' ? 1 : 0), 0);
        const alignSyllables = totalLigatures > 0 && verseCount > 0;

        // Reserve extra advance after a neume whose syllable is wider than the
        // neume's natural trailing slack, so the next neume isn't overlapped.
        if (alignSyllables) {
            const minGap = ctx.lyricSize * 0.18;
            let li = 0;
            for (const it of items) {
                if (it.kind !== 'ligature') {
                    continue;
                }
                let maxSylW = 0;
                for (const sylls of verseSyllables) {
                    if (li < sylls.length) {
                        const w = measureTextWidth(sylls[li].text, ctx.lyricSize, ctx.lyricFont);
                        if (w > maxSylW) {
                            maxSylW = w;
                        }
                    }
                }
                const baseAdv = measureLigature(ctx, it.groups);
                it.syllableExtra = Math.max(0, maxSylW + minGap - baseAdv);
                li++;
            }
        }

        const rows = layoutRows(items, ctx, currentClef, staffRightX, drawClefForRows);

        // For lyric-only sections (no music), still emit one empty row so lyrics render.
        if (rows.length === 0 && sec.lyrics.length > 0) {
            rows.push({
                items: [],
                itemsWidth: 0,
                justify: false,
                startClef: currentClef,
                drawStartClef: drawClefForRows,
            });
        }

        let ligOffset = 0;

        rows.forEach((row, rowIdx) => {
            const staffBottomY = y + ctx.staffHeight;
            parts.push(drawStaffLines(ctx, ctx.leftMargin, staffRightX, staffBottomY));

            let cursorX = ctx.leftMargin;
            const rowLigatures = [];

            if (row.drawStartClef) {
                const c = drawClef(ctx, row.startClef, cursorX, staffBottomY);
                parts.push(c.svg);
                cursorX += c.advance + ss(ctx, METRICS.clefInlinePostGap);
            }

            const remaining = staffRightX - cursorX;
            const extra = Math.max(0, remaining - row.itemsWidth);
            const expanderCount = row.items.reduce((n, it) => n + (it.kind === 'expander' ? 1 : 0), 0);
            const gapCount = Math.max(0, row.items.length - 1);
            let extraPerExpander = 0;
            let extraPerGap = 0;
            if (row.justify && extra > 0) {
                if (expanderCount > 0) {
                    extraPerExpander = extra / expanderCount;
                } else if (gapCount > 0) {
                    extraPerGap = extra / gapCount;
                }
            }

            for (let idx = 0; idx < row.items.length; idx++) {
                const it = row.items[idx];
                if (it.kind === 'clef') {
                    const c = drawClef(ctx, it.clef, cursorX, staffBottomY);
                    parts.push(wrapSrc(it, c.svg, 'aretino-token aretino-clef'));
                    cursorX += c.advance + ss(ctx, METRICS.clefInlinePostGap);
                } else if (it.kind === 'accidental') {
                    const a = drawAccidental(ctx, it.pitch, it.symbol, cursorX, staffBottomY);
                    parts.push(wrapSrc(it, a.svg, 'aretino-token aretino-accidental'));
                    cursorX += a.advance;
                } else if (it.kind === 'expander') {
                    cursorX += ctx.expanderWidth + extraPerExpander;
                } else if (it.kind === 'barline') {
                    const b = drawBarline(ctx, it.value, cursorX, staffBottomY);
                    parts.push(wrapSrc(it, b.svg, 'aretino-token aretino-barline'));
                    cursorX += b.advance + ss(ctx, METRICS.barlinePostGap);
                } else if (it.kind === 'spacer') {
                    cursorX += ss(ctx, METRICS.spacerAdvance) * it.multiplier;
                } else if (it.kind === 'ligature') {
                    const r = emitLigature(ctx, it.groups, cursorX, staffBottomY);
                    parts.push(wrapSrc(it, r.svg, 'aretino-token aretino-ligature'));
                    rowLigatures.push({ centerX: r.centerX, leftX: r.leftX });
                    cursorX += r.advance + (it.syllableExtra || 0);
                }
                if (idx < row.items.length - 1 && extraPerGap > 0) {
                    cursorX += extraPerGap;
                }
            }

            const isLastRow = rowIdx === rows.length - 1;
            const rowLigCount = rowLigatures.length;
            let lyricY = staffBottomY + ctx.systemGap + ctx.lyricSize;

            if (alignSyllables) {
                for (let v = 0; v < verseCount; v++) {
                    const sylls = verseSyllables[v];
                    const start = ligOffset;
                    const end = isLastRow
                        ? Math.max(sylls.length, ligOffset + rowLigCount)
                        : ligOffset + rowLigCount;
                    const rowSyllables = sylls.slice(start, end);
                    parts.push(emitAlignedSyllables(ctx, rowSyllables, rowLigatures, lyricY));
                    lyricY += ctx.lyricSize * 1.4;
                }
                ligOffset += rowLigCount;
                y = lyricY + ctx.lyricToNextStaff;
            } else if (isLastRow && verseCount > 0) {
                for (const lyric of sec.lyrics) {
                    parts.push(`<text xml:space="preserve" x="${ctx.leftMargin}" y="${lyricY}" font-family="${escapeAttr(ctx.lyricFont)}" font-size="${ctx.lyricSize}" fill="#000">${escapeText(lyric)}</text>`);
                    lyricY += ctx.lyricSize * 1.4;
                }
                y = lyricY + ctx.lyricToNextStaff;
            } else {
                y = staffBottomY + ctx.systemGap + ctx.lyricToNextStaff;
            }
        });

        currentClef = trailingClef(items, currentClef);
    }

    const totalHeight = canvasHeight || Math.max(y + ctx.staffSpace, 100);
    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${canvasWidth} ${totalHeight}" preserveAspectRatio="xMidYMin meet" width="100%" style="display:block">${HIGHLIGHT_STYLE}${parts.join('')}</svg>`;
}

function trailingClef(items, fallback) {
    for (let i = items.length - 1; i >= 0; i--) {
        if (items[i].kind === 'clef') {
            return items[i].clef;
        }
    }
    return fallback;
}

// A section bundles a run of music tokens (concatenated across input music
// lines) with the lyric lines that immediately follow it. A new section starts
// when a music line appears after lyrics, mirroring the natural "verse" unit
// in a score.
function groupSections(lines) {
    const sections = [];
    let pending = null;
    let lyricStarted = false;
    for (const item of lines) {
        if (item.type === 'music') {
            if (!pending || lyricStarted) {
                if (pending) {
                    sections.push(pending);
                }
                pending = { tokens: [], lyrics: [] };
                lyricStarted = false;
            }
            pending.tokens.push(...item.tokens);
        } else if (item.type === 'lyrics') {
            if (!pending) {
                pending = { tokens: [], lyrics: [] };
            }
            pending.lyrics.push(item.text);
            lyricStarted = true;
        }
    }
    if (pending) {
        sections.push(pending);
    }
    return sections;
}

function flattenItems(tokens) {
    const items = [];
    for (const tok of tokens) {
        const src = { srcStart: tok.srcStart, srcEnd: tok.srcEnd };
        if (tok.type === 'directive') {
            const v = tok.value;
            const clefM = v.match(/^([gfcGFC])([0-9])$/);
            if (clefM) {
                items.push({ kind: 'clef', clef: { letter: clefM[1].toLowerCase(), line: parseInt(clefM[2], 10) }, ...src });
                continue;
            }
            const accM = v.match(/^([a-mA-M]?)b([xy#])$/);
            if (accM) {
                items.push({ kind: 'accidental', pitch: (accM[1] || 'b').toLowerCase(), symbol: accM[2], ...src });
                continue;
            }
            if (v === 'z') {
                items.push({ kind: 'break', justify: true, ...src });
                continue;
            }
            if (v === 'Z') {
                items.push({ kind: 'break', justify: false, ...src });
                continue;
            }
            continue;
        }
        if (tok.type === 'expander') {
            items.push({ kind: 'expander', ...src });
            continue;
        }
        if (tok.type === 'barline') {
            items.push({ kind: 'barline', value: tok.kind, ...src });
            continue;
        }
        if (tok.type === 'spacer') {
            items.push({ kind: 'spacer', multiplier: tok.multiplier, ...src });
            continue;
        }
        if (tok.type === 'ligature') {
            items.push({ kind: 'ligature', groups: tok.groups, ...src });
            continue;
        }
    }
    return items;
}

// Mirrors the advance returned by drawClef in glyphs.js. We need it during
// the line-fit pass before any drawing happens.
function clefAdvance(ctx, clef) {
    const letter = (clef.letter || 'g').toLowerCase();
    const k = ctx.staffSpace / 591;
    if (letter === 'g') {
        return (2621 - 1186) * k + ss(ctx, METRICS.clefPostGap);
    }
    if (letter === 'f') {
        return (2889 - 1239) * k + ss(ctx, METRICS.clefPostGap);
    }
    if (letter === 'c') {
        return ss(ctx, METRICS.clefCWidth) + ss(ctx, METRICS.clefCRightPadding);
    }
    return 0;
}

function measureItem(ctx, item) {
    if (item.kind === 'clef') {
        return clefAdvance(ctx, item.clef) + ss(ctx, METRICS.clefInlinePostGap);
    }
    if (item.kind === 'accidental') {
        return ss(ctx, METRICS.accidentalAdvance);
    }
    if (item.kind === 'barline') {
        return measureBarline(ctx, item.value);
    }
    if (item.kind === 'spacer') {
        return ss(ctx, METRICS.spacerAdvance) * item.multiplier;
    }
    if (item.kind === 'expander') {
        return ctx.expanderWidth;
    }
    if (item.kind === 'ligature') {
        return measureLigature(ctx, item.groups) + (item.syllableExtra || 0);
    }
    return 0;
}

// Greedy line-fit. Walks items, accumulating widths, breaking before any
// item that would push the row past the right margin. Explicit (z)/(Z)
// directives appear as `break` items and force a row finalization.
function layoutRows(items, ctx, initialClef, staffRightX, drawStartClef) {
    const rows = [];
    let cur = [];
    let curWidth = 0;
    let rowStartClef = initialClef;
    let runningClef = initialClef;

    function rowItemsAvailable() {
        const clefSlot = drawStartClef
            ? clefAdvance(ctx, rowStartClef) + ss(ctx, METRICS.clefInlinePostGap)
            : 0;
        return staffRightX - ctx.leftMargin - clefSlot;
    }

    function finalize(justify) {
        if (cur.length === 0) {
            return;
        }
        rows.push({
            items: cur,
            itemsWidth: curWidth,
            justify,
            startClef: rowStartClef,
            drawStartClef,
        });
        cur = [];
        curWidth = 0;
        rowStartClef = runningClef;
    }

    for (const item of items) {
        if (item.kind === 'break') {
            finalize(item.justify);
            continue;
        }
        if (item.kind === 'clef') {
            runningClef = item.clef;
            if (cur.length === 0) {
                rowStartClef = item.clef;
                continue;
            }
        }
        const w = measureItem(ctx, item);
        if (cur.length > 0 && curWidth + w > rowItemsAvailable()) {
            finalize(true);
            if (item.kind === 'clef') {
                rowStartClef = item.clef;
                continue;
            }
        }
        cur.push(item);
        curWidth += w;
    }
    finalize(false);
    return rows;
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
    let firstNoteCx = null;
    let lastNoteCx = null;

    for (let g = 0; g < groups.length; g++) {
        const notes = groups[g];
        const positions = [];
        let cx = groupStartX + ss(ctx, METRICS.noteBoxWidth) * 0.5;

        for (let i = 0; i < notes.length; i++) {
            const note = notes[i];
            const cy = pitchY(ctx, note, staffBottomY);
            positions.push({ note, cx, cy });
            if (firstNoteCx === null) {
                firstNoteCx = cx;
            }
            lastNoteCx = cx;
            if (i < notes.length - 1) {
                cx += ctx.ligatureStepAdvance;
            }
        }

        // Auto-virga per group: every local pitch peak gets a downward stem on the left.
        // Left side non-strict (>=), right side strict (>) so only the last note of a
        // plateau is marked (e.g. "ggf" → virga on the second g).
        const autoVirga = new Array(notes.length).fill(false);
        if (notes.length >= 2) {
            const pitchPositions = notes.map(n => pitchToPos(n));
            const hasVariation = Math.max(...pitchPositions) > Math.min(...pitchPositions);
            if (hasVariation) {
                for (let i = 0; i < notes.length; i++) {
                    const atLeastAsHighAsLeft = i === 0 || pitchPositions[i] >= pitchPositions[i - 1];
                    const higherThanRight = i === notes.length - 1 || pitchPositions[i] > pitchPositions[i + 1];
                    if (atLeastAsHighAsLeft && higherThanRight) {
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

        // Draw note heads + modifiers, wrapped per-note so each note can be
        // highlighted independently when the cursor sits on it.
        for (let i = 0; i < positions.length; i++) {
            const p = positions[i];
            const prevCy = i > 0 ? positions[i - 1].cy : null;
            const drawnNote = autoVirga[i] ? { ...p.note, virga: true } : p.note;
            const noteParts = [drawNoteHead(ctx, drawnNote, p.cx, p.cy, staffBottomY, prevCy)];
            for (const mod of p.note.modifiers) {
                if (mod === 'episema') {
                    const onLine = pitchToPos(p.note) % 2 === 0;
                    noteParts.push(drawEpisema(ctx, p.cx, p.cy, onLine));
                } else if (mod === 'mora') {
                    const onLine = pitchToPos(p.note) % 2 === 0;
                    noteParts.push(drawMora(ctx, p.cx, p.cy, onLine));
                } else if (mod === 'liquescens') {
                    noteParts.push(drawLiquescens(ctx, p.cx, p.cy, 'down'));
                }
            }
            parts.push(wrapSrc(p.note, noteParts.join(''), 'aretino-note'));
        }

        if (g < groups.length - 1) {
            groupStartX += ss(ctx, METRICS.noteBoxWidth) + (notes.length - 1) * ctx.ligatureStepAdvance + ctx.neumeGapAdvance;
        }
    }

    const centerX = firstNoteCx !== null
        ? (firstNoteCx + lastNoteCx) / 2
        : x + measureLigature(ctx, groups) / 2;
    const leftX = firstNoteCx !== null
        ? firstNoteCx - ss(ctx, METRICS.noteBoxWidth) * 0.5
        : x;
    return { svg: parts.join(''), advance: measureLigature(ctx, groups), centerX, leftX };
}

let _measureCanvas = null;

function measureTextWidth(text, fontSize, fontFamily) {
    if (text === '') {
        return 0;
    }
    if (typeof document !== 'undefined') {
        try {
            if (!_measureCanvas) {
                _measureCanvas = document.createElement('canvas');
            }
            const c2d = _measureCanvas.getContext('2d');
            c2d.font = `${fontSize}px ${fontFamily}`;
            return c2d.measureText(text).width;
        } catch (_e) {
            // fall through to estimation
        }
    }
    return text.length * fontSize * 0.55;
}

// "San-ctus, Do-mi-nus" → [
//   {text:'San', hyphenAfter:true},
//   {text:'ctus,', hyphenAfter:false},
//   {text:'Do', hyphenAfter:true},
//   {text:'mi', hyphenAfter:true},
//   {text:'nus', hyphenAfter:false},
// ]
function parseSyllables(text) {
    const result = [];
    const words = (text || '').match(/\S+/g) || [];
    for (const word of words) {
        const parts = word.split('-');
        const nonEmpty = parts.filter(p => p !== '');
        for (let i = 0; i < nonEmpty.length; i++) {
            result.push({
                text: nonEmpty[i],
                hyphenAfter: i < nonEmpty.length - 1,
            });
        }
    }
    return result;
}

// Lays out a row's worth of syllables centered under their corresponding
// ligature centers. Adjusts for collisions and emits hyphens between
// syllables of the same word when there's room.
function emitAlignedSyllables(ctx, syllables, ligatures, lyricY) {
    if (syllables.length === 0) {
        return '';
    }
    const fontSize = ctx.lyricSize;
    const fontFamily = ctx.lyricFont;
    const minGap = fontSize * 0.18;
    // A hyphen occupies the width of an 'n' character; if the gap between
    // syllables is smaller than that, there is no room to render it.
    const hyphenSpaceW = measureTextWidth('-', fontSize, fontFamily);
    const trailingAdvance = fontSize * 0.6;

    const parts = [];
    let prevRight = -Infinity;
    let lastRight = null;

    for (let i = 0; i < syllables.length; i++) {
        const syl = syllables[i];
        const w = measureTextWidth(syl.text, fontSize, fontFamily);
        let center;
        if (i < ligatures.length) {
            const lig = ligatures[i];
            const neumeWidth = (lig.centerX - lig.leftX) * 2;
            if (w < neumeWidth) {
                // Neume is wider than the syllable: align the syllable's left
                // edge with the neume's left edge instead of centering.
                center = lig.leftX + w / 2;
            } else {
                center = lig.centerX;
            }
        } else {
            // More syllables than ligatures: lay them out after the last one
            // with default spacing.
            center = prevRight + trailingAdvance + w / 2;
        }
        let left = center - w / 2;
        let hyphenX = null;
        if (i > 0) {
            const needsHyphen = syllables[i - 1].hyphenAfter;
            if (needsHyphen) {
                if (left - prevRight >= hyphenSpaceW) {
                    hyphenX = (left + prevRight) / 2;
                } else {
                    left = prevRight;
                    center = left + w / 2;
                }
            } else if (left < prevRight + minGap) {
                left = prevRight + minGap;
                center = left + w / 2;
            }
        }
        const right = left + w;

        parts.push(`<text xml:space="preserve" x="${center}" y="${lyricY}" font-family="${escapeAttr(fontFamily)}" font-size="${fontSize}" text-anchor="middle" fill="#000">${escapeText(syl.text)}</text>`);
        if (hyphenX !== null) {
            parts.push(`<text x="${hyphenX}" y="${lyricY}" font-family="${escapeAttr(fontFamily)}" font-size="${fontSize}" text-anchor="middle" fill="#000">-</text>`);
        }
        prevRight = right;
        lastRight = right;
    }

    // Word broken at the row boundary: render a trailing hyphen so the reader
    // knows the syllable continues on the next row.
    const lastSyl = syllables[syllables.length - 1];
    if (lastSyl && lastSyl.hyphenAfter && lastRight !== null) {
        const hyphenX = lastRight + hyphenSpaceW / 2;
        parts.push(`<text x="${hyphenX}" y="${lyricY}" font-family="${escapeAttr(fontFamily)}" font-size="${fontSize}" text-anchor="middle" fill="#000">-</text>`);
    }
    return parts.join('');
}
