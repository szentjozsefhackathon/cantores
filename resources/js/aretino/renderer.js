import { parseAretino } from './parser.js';
import {
    METRICS,
    pitchToPos,
    pitchY,
    drawNoteHead,
    drawEpisema,
    drawEpisemaSpan,
    drawIctus,
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
const HIGHLIGHT_STYLE = `<style>.aretino-active [fill]:not([fill="none"]):not(.aretino-cursor-bg){fill:#ea580c}.aretino-active [stroke]:not([stroke="none"]):not(.aretino-cursor-bg){stroke:#ea580c}</style>`;

export function renderAretino(source, options = {}) {
    const ast = parseAretino(source);
    const canvasWidth = options.canvasWidth || 1920;
    const canvasHeight = options.canvasHeight || null;
    const staffScale = Math.max(0.1, (options.staffSize ?? 100) / 100);
    const noteSpacing = Math.max(0.5, options.noteSpacing ?? 1);
    const lyricFont = options.lyricFont || DEFAULT_FONT;
    const hideRepeatClef = !!options.hideRepeatClef;

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
    ctx.staffGap = Math.max(0, ss(ctx, options.staffGap ?? METRICS.staffGap));
    ctx.lyricDistance = ss(ctx, METRICS.lyricDistance);
    ctx.lyricFont = lyricFont;
    // lyricSize is in typographic points; convert to SVG user units for the virtual canvas.
    // Scale proportionally to canvas width but clamp the ratio so lyrics stay readable on small screens.
    const lyricPt = Math.max(6, options.lyricSize ?? 12);
    const lyricScale = Math.max(0.75, canvasWidth / 750);
    ctx.lyricSize = lyricPt * (96 / 72) * lyricScale;
    ctx.canvasWidth = canvasWidth;

    const sections = groupSections(ast.lines);

    const parts = [];
    let currentClef = { letter: 'g', line: 2 };
    let currentKeySig = [];
    let hasSeenClef = false;
    let clefRowsBudget = hideRepeatClef ? 1 : Infinity;
    let y = ss(ctx, METRICS.titleTopPadding);
    let contentBottom = y;

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
        const verseNotes = verseSyllables.map(arr => arr.filter(s => s.kind === 'note'));
        const verseBarlines = verseSyllables.map(arr => arr.filter(s => s.kind === 'barline'));
        const verseCount = sec.lyrics.length;
        const totalLigatures = items.reduce((n, it) => n + (it.kind === 'ligature' ? 1 : 0), 0);
        const alignSyllables = totalLigatures > 0 && verseCount > 0;
        const hasBarlineLabels = verseBarlines.some(arr => arr.length > 0);

        // For each barline item in the music sequence, record how many ligatures
        // precede it. Used to match lyric barline labels (which carry notesBefore)
        // to the correct actual barline rather than by parallel index.
        let _lc = 0;
        const barlineLigsBefore = [];
        for (const it of items) {
            if (it.kind === 'ligature') { _lc++; }
            else if (it.kind === 'barline') { barlineLigsBefore.push(_lc); }
        }

        // Build per-verse maps: globalBarlineIdx → label.
        // A label with notesBefore=K targets the first barline where ligsBefore >= K.
        const verseBarlineMaps = verseBarlines.map(lbls => {
            const m = new Map();
            for (const lbl of lbls) {
                const K = lbl.notesBefore ?? 0;
                for (let bi = 0; bi < barlineLigsBefore.length; bi++) {
                    if (barlineLigsBefore[bi] >= K && !m.has(bi)) {
                        m.set(bi, lbl);
                        break;
                    }
                }
            }
            return m;
        });

        // Reserve extra advance after a neume whose syllable is wider than the
        // neume's natural trailing slack, so the next neume isn't overlapped.
        if (alignSyllables) {
            const minGap = ctx.lyricSize * 0.18;
            const halfNoteW = ss(ctx, METRICS.noteBoxWidth) * 0.5;
            const ligInfo = [];
            let li = 0;
            for (const it of items) {
                if (it.kind !== 'ligature') {
                    continue;
                }
                let maxSylW = 0;
                for (const notes of verseNotes) {
                    if (li < notes.length) {
                        const w = measureTextWidth(notes[li].alignText || notes[li].text, ctx.lyricSize, ctx.lyricFont);
                        if (w > maxSylW) {
                            maxSylW = w;
                        }
                    }
                }
                const totalNotes = it.groups.reduce((sum, g) => sum + g.length, 0);
                const lastG = it.groups[it.groups.length - 1];
                const lastN = lastG?.[lastG.length - 1];
                const isCentered = totalNotes === 1
                    && !(lastN?.modifiers?.includes('mora'))
                    && !it.groups.some(g => g.some(n => n.shape === 'tenor'));
                ligInfo.push({ item: it, maxSylW, isCentered });
                li++;
            }
            for (let i = 0; i < ligInfo.length; i++) {
                const { item, maxSylW, isCentered } = ligInfo[i];
                const baseAdv = measureLigature(ctx, item.groups, item.gaps ?? []);
                // Current syllable's right-edge offset from the ligature's left.
                const currRight = isCentered ? halfNoteW + maxSylW / 2 : maxSylW;
                // How far the next syllable extends to the left of the next ligature's
                // start (positive = intrudes). Only centered syllables intrude leftward.
                let nextLeftIntrusion = 0;
                if (i + 1 < ligInfo.length) {
                    const next = ligInfo[i + 1];
                    if (next.isCentered) {
                        nextLeftIntrusion = Math.max(0, next.maxSylW / 2 - halfNoteW);
                    }
                }
                // Hyphen-joined syllables ("Ki-rá-lyok") butt up against each other;
                // a gap (filled with a hyphen) only appears when the neumes are
                // naturally wider than the syllables. Separate words ("Ki rá lyok")
                // still need a minimum gap between them.
                let pairConnected = false;
                if (i + 1 < ligInfo.length) {
                    let pairExists = false;
                    let allHyphenated = true;
                    for (const notes of verseNotes) {
                        if (i < notes.length && i + 1 < notes.length) {
                            pairExists = true;
                            if (!notes[i].hyphenAfter) {
                                allHyphenated = false;
                                break;
                            }
                        }
                    }
                    pairConnected = pairExists && allHyphenated;
                }
                const gap = pairConnected ? 0 : minGap;
                item.syllableExtra = Math.max(0, currRight + nextLeftIntrusion + gap - baseAdv);
            }
        }

        // Reserve extra space around barlines that carry a label, so the
        // centered label doesn't overlap neighboring ligature syllables.
        if (hasBarlineLabels) {
            const minGap = ctx.lyricSize * 0.18;
            let bi = 0;
            for (const it of items) {
                if (it.kind !== 'barline') {
                    continue;
                }
                let maxW = 0;
                for (const barlineMap of verseBarlineMaps) {
                    const lbl = barlineMap.get(bi);
                    if (lbl) {
                        const w = measureTextWidth(lbl.text, ctx.lyricSize, ctx.lyricFont);
                        if (w > maxW) { maxW = w; }
                    }
                }
                if (maxW > 0) {
                    const baseAdv = measureBarline(ctx, it.value);
                    it.barlineExtra = Math.max(0, maxW + minGap - baseAdv);
                }
                bi++;
            }
        }

        const allowedClefRows = drawClefForRows ? clefRowsBudget : 0;
        const rows = layoutRows(items, ctx, currentClef, staffRightX, drawClefForRows, currentKeySig, allowedClefRows);

        if (hideRepeatClef) {
            const clefRowsUsed = rows.filter(r => r.drawStartClef).length;
            clefRowsBudget = Math.max(0, clefRowsBudget - clefRowsUsed);
        }

        // For lyric-only sections (no music), still emit one empty row so lyrics render.
        if (rows.length === 0 && sec.lyrics.length > 0) {
            const emptyRowDrawClef = drawClefForRows && clefRowsBudget > 0;
            if (hideRepeatClef && emptyRowDrawClef) {
                clefRowsBudget = 0;
            }
            rows.push({
                items: [],
                itemsWidth: 0,
                justify: false,
                startClef: currentClef,
                startKeySig: currentKeySig,
                drawStartClef: emptyRowDrawClef,
            });
        }

        let ligOffset = 0;
        let globalBarlineIdx = 0;

        rows.forEach((row, rowIdx) => {
            const staffBottomY = y + ctx.staffHeight;
            parts.push(drawStaffLines(ctx, ctx.leftMargin, staffRightX, staffBottomY));

            let cursorX = ctx.leftMargin;
            const rowLigatures = [];
            const rowBarlines = [];

            if (row.drawStartClef) {
                const c = drawClef(ctx, row.startClef, cursorX, staffBottomY);
                parts.push(c.svg);
                cursorX += c.advance - ss(ctx, METRICS.clefPostGap) + ss(ctx, METRICS.clefInlinePostGap);
            }

            const startKeySig = row.startKeySig ?? [];
            for (const acc of startKeySig) {
                const a = drawAccidental(ctx, acc.pitch, acc.symbol, cursorX, staffBottomY);
                parts.push(a.svg);
                cursorX += a.advance;
            }

            // Add proper spacing after clef/key sig and before notes
            if (row.drawStartClef || startKeySig.length > 0) {
                if (startKeySig.length === 0) {
                    cursorX += ss(ctx, METRICS.clefPostGap);
                } else {
                    cursorX += ss(ctx, 1);
                }
            } else {
                // No clef and no key sig: still add one staff space of emptiness
                cursorX += ctx.staffSpace;
            }

            const remaining = staffRightX - cursorX;
            const extra = Math.max(0, remaining - row.itemsWidth);
            const expanderCount = row.items.reduce((n, it) => n + (it.kind === 'expander' ? 1 : 0), 0);
            // Accidental+ligature pairs are glued — don't count them as a gap.
            const gluedPairs = row.items.reduce((n, it, i) => n + (it.kind === 'accidental' && i + 1 < row.items.length && row.items[i + 1].kind === 'ligature' ? 1 : 0), 0);
            const gapCount = Math.max(0, row.items.length - 1 - gluedPairs);
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
                } else if (it.kind === 'keysig') {
                    const startX = cursorX;
                    const pieces = [];
                    for (const acc of it.accidentals) {
                        const a = drawAccidental(ctx, acc.pitch, acc.symbol, cursorX, staffBottomY);
                        pieces.push(a.svg);
                        cursorX += a.advance;
                    }
                    if (pieces.length) {
                        parts.push(wrapSrc(it, pieces.join(''), 'aretino-token aretino-keysig'));
                    } else {
                        // Empty (K:) — clears signature; nothing to draw.
                        cursorX = startX;
                    }
                } else if (it.kind === 'expander') {
                    cursorX += ctx.expanderWidth + extraPerExpander;
                } else if (it.kind === 'barline') {
                    const extra = it.barlineExtra || 0;
                    cursorX += extra / 2;
                    const b = drawBarline(ctx, it.value, cursorX, staffBottomY);
                    parts.push(wrapSrc(it, b.svg, 'aretino-token aretino-barline'));
                    const offsetX = (it.value === '||' || it.value === ':|' || it.value === '|:' || it.value === ':|:' || it.value === '|||')
                        ? (METRICS.barlineOffsetX + METRICS.barlineDoubleSecondOffsetX) / 2
                        : METRICS.barlineOffsetX;
                    rowBarlines.push({ centerX: cursorX + ss(ctx, offsetX), value: it.value, globalIdx: globalBarlineIdx });
                    globalBarlineIdx++;
                    cursorX += b.advance + ss(ctx, METRICS.barlinePostGap) + extra / 2;
                } else if (it.kind === 'spacer') {
                    cursorX += ss(ctx, METRICS.spacerAdvance) * it.multiplier;
                } else if (it.kind === 'ligature') {
                    const r = emitLigature(ctx, it.groups, cursorX, staffBottomY, it.gaps ?? []);
                    parts.push(wrapSrc(it, r.svg, 'aretino-token aretino-ligature'));
                    rowLigatures.push({ centerX: r.centerX, leftX: r.leftX, shouldAlignLeft: r.shouldAlignLeft });
                    cursorX += r.advance + (it.syllableExtra || 0);
                }
                if (idx < row.items.length - 1 && extraPerGap > 0) {
                    // Don't insert justification gap between an accidental and its neume.
                    const nextIt = row.items[idx + 1];
                    if (!(it.kind === 'accidental' && nextIt.kind === 'ligature')) {
                        cursorX += extraPerGap;
                    }
                }
            }

            const isLastRow = rowIdx === rows.length - 1;
            const rowLigCount = rowLigatures.length;
            const lowestNoteY = rowLowestNoteY(ctx, row, staffBottomY);
            const lyricTopY = lowestNoteY > staffBottomY
                ? lowestNoteY + ctx.lyricDistance
                : staffBottomY + ctx.lyricDistance;
            let lyricY = lyricTopY + ctx.lyricSize;

            if (alignSyllables) {
                for (let v = 0; v < verseCount; v++) {
                    const notes = verseNotes[v];
                    const start = ligOffset;
                    const end = isLastRow
                        ? Math.max(notes.length, ligOffset + rowLigCount)
                        : ligOffset + rowLigCount;
                    const rowSyllables = notes.slice(start, end);
                    parts.push(emitAlignedSyllables(ctx, rowSyllables, rowLigatures, lyricY));
                    const barlineMap = verseBarlineMaps[v];
                    const matchedLabels = [];
                    const matchedBarlines = [];
                    for (const rb of rowBarlines) {
                        const lbl = barlineMap.get(rb.globalIdx);
                        if (lbl) {
                            matchedLabels.push(lbl);
                            matchedBarlines.push(rb);
                        }
                    }
                    if (matchedLabels.length > 0) {
                        parts.push(emitBarlineLabels(ctx, matchedLabels, matchedBarlines, lyricY));
                    }
                    lyricY += ctx.lyricSize * 1.4;
                }
                ligOffset += rowLigCount;
                // lyricY has advanced one full line past the last rendered baseline;
                // only add descender clearance, not another full line height.
                const lastLyricBottom = lyricY - ctx.lyricSize * 1.4 + ctx.lyricSize * 0.3;
                contentBottom = Math.max(contentBottom, lastLyricBottom);
                y = lastLyricBottom + ctx.staffGap;
            } else if (isLastRow && verseCount > 0) {
                for (const lyric of sec.lyrics) {
                    parts.push(`<text xml:space="preserve" x="${ctx.leftMargin}" y="${lyricY}" font-family="${escapeAttr(ctx.lyricFont)}" font-size="${ctx.lyricSize}" fill="#000">${formatLyricLine(lyric)}</text>`);
                    lyricY += ctx.lyricSize * 1.4;
                }
                const lastLyricBottom = lyricY - ctx.lyricSize * 1.4 + ctx.lyricSize * 0.3;
                contentBottom = Math.max(contentBottom, lastLyricBottom);
                y = lastLyricBottom + ctx.staffGap;
            } else {
                y = staffBottomY + ctx.staffGap;
                contentBottom = Math.max(contentBottom, y);
            }
        });

        currentClef = trailingClef(items, currentClef);
        currentKeySig = trailingKeySig(items, currentKeySig);
    }

    const totalHeight = canvasHeight || Math.max(contentBottom, y + ctx.staffSpace, 100);
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

function trailingKeySig(items, fallback) {
    for (let i = items.length - 1; i >= 0; i--) {
        if (items[i].kind === 'keysig') {
            return items[i].accidentals;
        }
    }
    return fallback;
}

// A section bundles music tokens and lyric lines separated from other sections
// by a blank line (empty line). A new section starts only on a blank line —
// single linebreaks in either the notation or w: parts do not break sections.
function groupSections(lines) {
    const sections = [];
    let pending = null;

    function flushPending() {
        if (pending && (pending.tokens.length > 0 || pending.lyrics.length > 0)) {
            sections.push(pending);
        }
        pending = null;
    }

    for (const item of lines) {
        if (item.type === 'blank') {
            flushPending();
            continue;
        }
        if (!pending) {
            pending = { tokens: [], lyrics: [] };
        }
        if (item.type === 'music') {
            pending.tokens.push(...item.tokens);
        } else if (item.type === 'lyrics') {
            pending.lyrics.push(item.text);
        }
    }
    flushPending();
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
                items.push({ kind: 'accidental', pitch: (accM[1] || 'i').toLowerCase(), symbol: accM[2], ...src });
                continue;
            }
            const keyM = v.match(/^K:\s*(.*)$/);
            if (keyM) {
                const inner = keyM[1].trim();
                const accidentals = [];
                if (inner) {
                    for (const part of inner.split(/\s+/)) {
                        const m = part.match(/^([a-mA-M]?)b([xy#])$/);
                        if (m) {
                            accidentals.push({ pitch: (m[1] || 'i').toLowerCase(), symbol: m[2] });
                        }
                    }
                }
                items.push({ kind: 'keysig', accidentals, ...src });
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
            items.push({ kind: 'ligature', groups: tok.groups, gaps: tok.gaps ?? [], ...src });
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

function keySigAdvance(ctx, accidentals) {
    return (accidentals?.length ?? 0) * ss(ctx, METRICS.accidentalAdvance);
}

function measureItem(ctx, item) {
    if (item.kind === 'clef') {
        return clefAdvance(ctx, item.clef) + ss(ctx, METRICS.clefInlinePostGap);
    }
    if (item.kind === 'accidental') {
        return ss(ctx, METRICS.accidentalAdvance);
    }
    if (item.kind === 'keysig') {
        return keySigAdvance(ctx, item.accidentals);
    }
    if (item.kind === 'barline') {
        return measureBarline(ctx, item.value) + (item.barlineExtra || 0);
    }
    if (item.kind === 'spacer') {
        return ss(ctx, METRICS.spacerAdvance) * item.multiplier;
    }
    if (item.kind === 'expander') {
        return ctx.expanderWidth;
    }
    if (item.kind === 'ligature') {
        return measureLigature(ctx, item.groups, item.gaps ?? []) + (item.syllableExtra || 0);
    }
    return 0;
}

// Greedy line-fit. Walks items, accumulating widths, breaking before any
// item that would push the row past the right margin. Explicit (z)/(Z)
// directives appear as `break` items and force a row finalization.
function layoutRows(items, ctx, initialClef, staffRightX, drawStartClef, initialKeySig, allowedClefRows = Infinity) {
    const rows = [];
    let cur = [];
    let curWidth = 0;
    let rowStartClef = initialClef;
    let runningClef = initialClef;
    let rowStartKeySig = initialKeySig ?? [];
    let runningKeySig = initialKeySig ?? [];
    let clefRowsDrawn = 0;

    function currentRowDrawsClef() {
        return drawStartClef && clefRowsDrawn < allowedClefRows;
    }

    function rowItemsAvailable() {
        const showClef = currentRowDrawsClef();
        let reserved = 0;
        const hasKeySig = rowStartKeySig.length > 0;
        if (showClef) {
            const clefSlot = hasKeySig
                ? clefAdvance(ctx, rowStartClef) - ss(ctx, METRICS.clefPostGap) + ss(ctx, METRICS.clefInlinePostGap)
                : clefAdvance(ctx, rowStartClef) + ss(ctx, METRICS.clefInlinePostGap);
            reserved += clefSlot;
        }
        if (hasKeySig) {
            reserved += keySigAdvance(ctx, rowStartKeySig);
            if (!showClef) {
                reserved += ss(ctx, METRICS.clefPostGap);
            } else {
                reserved += ss(ctx, 1);
            }
        }
        if (!showClef && !hasKeySig) {
            reserved += ctx.staffSpace;
        }
        return staffRightX - ctx.leftMargin - reserved;
    }

    function finalize(justify) {
        if (cur.length === 0) {
            return;
        }
        const showClef = currentRowDrawsClef();
        rows.push({
            items: cur,
            itemsWidth: curWidth,
            justify,
            startClef: rowStartClef,
            startKeySig: rowStartKeySig,
            drawStartClef: showClef,
        });
        if (showClef) {
            clefRowsDrawn++;
        }
        cur = [];
        curWidth = 0;
        rowStartClef = runningClef;
        rowStartKeySig = runningKeySig;
    }

    for (let ii = 0; ii < items.length; ii++) {
        const item = items[ii];
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
        if (item.kind === 'keysig') {
            runningKeySig = item.accidentals;
            if (cur.length === 0) {
                rowStartKeySig = item.accidentals;
                continue;
            }
        }
        // Accidentals are glued to the following neume — measure them as a
        // single atomic unit for line-breaking purposes.
        let w = measureItem(ctx, item);
        if (item.kind === 'accidental' && ii + 1 < items.length && items[ii + 1].kind === 'ligature') {
            w += measureItem(ctx, items[ii + 1]);
        }
        // If the previous item was an accidental glued to this item, skip the
        // overflow check (it was already accounted for).
        const gluedToPrev = ii > 0 && items[ii - 1].kind === 'accidental' && item.kind === 'ligature';
        if (!gluedToPrev && cur.length > 0 && curWidth + w > rowItemsAvailable()) {
            if (item.kind === 'barline') {
                // Barlines must not start a row — carry the preceding note/neume
                // unit (optionally with its leading accidental) to the new row.
                let splitIdx = -1;
                for (let k = cur.length - 1; k >= 0; k--) {
                    if (cur[k].kind === 'ligature') {
                        splitIdx = (k > 0 && cur[k - 1].kind === 'accidental') ? k - 1 : k;
                        break;
                    }
                }
                if (splitIdx >= 0) {
                    const carried = cur.splice(splitIdx);
                    curWidth -= carried.reduce((sum, it) => sum + measureItem(ctx, it), 0);
                    finalize(true);
                    for (const it of carried) {
                        cur.push(it);
                        curWidth += measureItem(ctx, it);
                    }
                } else {
                    finalize(true);
                }
            } else {
                finalize(true);
                if (item.kind === 'clef') {
                    rowStartClef = item.clef;
                    continue;
                }
                if (item.kind === 'keysig') {
                    rowStartKeySig = item.accidentals;
                    continue;
                }
            }
        }
        cur.push(item);
        curWidth += measureItem(ctx, item);
    }
    finalize(false);
    return rows;
}

function measureBarline(ctx, kind) {
    if (kind === ':|:') {
        return ss(ctx, METRICS.barlineDoubleAdvance) * 1.5 + ss(ctx, METRICS.barlinePostGap);
    }
    const base = (kind === '||' || kind === ':|' || kind === '|:' || kind === '|||')
        ? ss(ctx, METRICS.barlineDoubleAdvance)
        : ss(ctx, METRICS.barlineAdvance);
    return base + ss(ctx, METRICS.barlinePostGap);
}

// A mora on a non-final note within a group acts like an implicit '/' cut:
// the group is split after that note so the remaining notes form a new group.
// Returns { groups, gaps } where gaps[i] is the gap type after groups[i]:
//   'mora'  — implicit split from an internal mora (compact spacing)
//   'neume' — explicit '/' separator (standard neumeGapAdvance)
function splitGroupsAtInternalMora(groups, gaps = []) {
    const resultGroups = [];
    const resultGaps = [];
    for (let gi = 0; gi < groups.length; gi++) {
        const group = groups[gi];
        let current = [];
        for (let i = 0; i < group.length; i++) {
            current.push(group[i]);
            if (i < group.length - 1 && group[i].modifiers && group[i].modifiers.includes('mora')) {
                resultGroups.push(current);
                resultGaps.push('mora');
                current = [];
            }
        }
        if (current.length > 0) {
            resultGroups.push(current);
            if (gi < groups.length - 1) {
                resultGaps.push(gaps[gi] ?? 'neume');
            }
        }
    }
    return { groups: resultGroups, gaps: resultGaps };
}

// groups: Note[][] — each group is a run of notes; groups are separated by neumatic cuts ('/').
// All groups except the last contribute a gap advance; the last group contributes singleNoteAdvance.
// Gap types: 'neume' = standard neumeGapAdvance; 'mora' = compact spacing just past the mora dot.
function measureLigature(ctx, groups, gaps = []) {
    const split = splitGroupsAtInternalMora(groups, gaps);
    return measureSplitLigature(ctx, split.groups, split.gaps);
}

function measureSplitLigature(ctx, groups, gaps) {
    let total = 0;
    for (let g = 0; g < groups.length; g++) {
        const notes = groups[g];
        const n = notes.length;
        // Add advance for any inline accidentals on notes in this group.
        const accExtra = notes.reduce((sum, note) => sum + (note.accidental ? ss(ctx, METRICS.accidentalAdvance) : 0), 0);
        if (g < groups.length - 1) {
            const gapType = gaps[g] ?? 'neume';
            const lastNote = notes[n - 1];
            const hasMora = lastNote.modifiers && lastNote.modifiers.includes('mora');
            // For an explicit '/' after a mora, the neume gap starts from the mora dot's right
            // edge rather than the note box edge, so add the mora's overhang.
            const moraOverhang = (gapType === 'neume' && hasMora)
                ? ss(ctx, METRICS.moraOffsetX + METRICS.moraRadius - METRICS.noteBoxWidth * 0.5)
                : 0;
            total += ss(ctx, METRICS.noteBoxWidth) + (n - 1) * ctx.ligatureStepAdvance + ctx.neumeGapAdvance + moraOverhang + accExtra;
        } else {
            const lastNote = notes[n - 1];
            const hasMora = lastNote.modifiers && lastNote.modifiers.includes('mora');
            const moraExtra = hasMora ? ss(ctx, METRICS.moraOffsetX + METRICS.moraRadius) : 0;
            total += ctx.singleNoteAdvance + (n - 1) * ctx.ligatureStepAdvance + moraExtra + accExtra;
        }
    }
    return total;
}

function rowLowestNoteY(ctx, row, staffBottomY) {
    let maxY = staffBottomY;
    const halfNoteH = ss(ctx, METRICS.noteBoxHeight) * 0.5;
    for (const it of row.items) {
        if (it.kind !== 'ligature') {
            continue;
        }
        for (const group of it.groups) {
            for (const note of group) {
                const cy = pitchY(ctx, note, staffBottomY);
                if (cy > maxY) {
                    maxY = cy + halfNoteH;
                }
            }
        }
    }
    return maxY;
}

function emitLigature(ctx, groups, x, staffBottomY, gaps = []) {
    const splitResult = splitGroupsAtInternalMora(groups, gaps);
    groups = splitResult.groups;
    gaps = splitResult.gaps;
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
            // Draw inline accidental before this note if present.
            if (note.accidental) {
                const accX = cx - ss(ctx, METRICS.noteBoxWidth) * 0.5;
                const a = drawAccidental(ctx, note.accidental.pitch, note.accidental.symbol, accX, staffBottomY);
                parts.push(a.svg);
                cx += ss(ctx, METRICS.accidentalAdvance);
            }
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
            // Skip connector into a note preceded by an inline accidental —
            // the accidental glyph occupies the space where the connector would be.
            if (cur.note.accidental) {
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

        // Detect runs of consecutive notes that all carry an episema.
        // For each run of 2+ notes, draw one spanning episema at the highest
        // note's vertical position so all segments touch.
        const halfEW = ss(ctx, METRICS.episemaWidth) / 2;
        const episemaInGroup = new Set(); // indices covered by a group episema
        {
            let runStart = null;
            const flushRun = (end) => {
                if (runStart === null) {
                    return;
                }
                if (end - runStart >= 2) {
                    const run = positions.slice(runStart, end);
                    const highest = run.reduce((best, p) => (p.cy < best.cy ? p : best), run[0]);
                    const onLine = pitchToPos(highest.note) % 2 === 0;
                    const x1 = run[0].cx - halfEW;
                    const x2 = run[run.length - 1].cx + halfEW;
                    parts.push(drawEpisemaSpan(ctx, x1, x2, highest.cy, onLine));
                    for (let j = runStart; j < end; j++) {
                        episemaInGroup.add(j);
                    }
                }
                runStart = null;
            };
            for (let i = 0; i <= positions.length; i++) {
                const hasEpisema = i < positions.length && positions[i].note.modifiers.includes('episema');
                if (hasEpisema) {
                    if (runStart === null) {
                        runStart = i;
                    }
                } else {
                    flushRun(i);
                }
            }
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
                    if (!episemaInGroup.has(i)) {
                        const onLine = pitchToPos(p.note) % 2 === 0;
                        noteParts.push(drawEpisema(ctx, p.cx, p.cy, onLine));
                    }
                } else if (mod === 'mora') {
                    const onLine = pitchToPos(p.note) % 2 === 0;
                    noteParts.push(drawMora(ctx, p.cx, p.cy, onLine));
                } else if (mod === 'ictus') {
                    const onLine = pitchToPos(p.note) % 2 === 0;
                    noteParts.push(drawIctus(ctx, p.cx, p.cy, onLine));
                } else if (mod === 'liquescens') {
                    noteParts.push(drawLiquescens(ctx, p.cx, p.cy, 'down'));
                }
            }
            parts.push(wrapSrc(p.note, noteParts.join(''), 'aretino-note'));
        }

        if (g < groups.length - 1) {
            const gapType = gaps[g] ?? 'neume';
            const lastNote = notes[notes.length - 1];
            const hasMora = lastNote.modifiers && lastNote.modifiers.includes('mora');
            const moraOverhang = (gapType === 'neume' && hasMora)
                ? ss(ctx, METRICS.moraOffsetX + METRICS.moraRadius - METRICS.noteBoxWidth * 0.5)
                : 0;
            const accExtra = notes.reduce((sum, note) => sum + (note.accidental ? ss(ctx, METRICS.accidentalAdvance) : 0), 0);
            groupStartX += ss(ctx, METRICS.noteBoxWidth) + (notes.length - 1) * ctx.ligatureStepAdvance + ctx.neumeGapAdvance + moraOverhang + accExtra;
        }
    }

    const advance = measureSplitLigature(ctx, groups, gaps);
    const centerX = firstNoteCx !== null
        ? (firstNoteCx + lastNoteCx) / 2
        : x + advance / 2;
    const leftX = firstNoteCx !== null
        ? firstNoteCx - ss(ctx, METRICS.noteBoxWidth) * 0.5
        : x;

    // Determine if syllable should align to left edge
    const totalNotes = groups.reduce((sum, g) => sum + g.length, 0);
    const lastNote = groups[groups.length - 1]?.[groups[groups.length - 1].length - 1];
    const hasMora = lastNote?.modifiers?.includes('mora');
    const isTenor = groups.some(g => g.some(n => n.shape === 'tenor'));
    const shouldAlignLeft = totalNotes > 1 || hasMora || isTenor;

    return { svg: parts.join(''), advance, centerX, leftX, shouldAlignLeft };
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

// "San-ctus, (M.:) Do-mi-nus" → [
//   {text:'San', hyphenAfter:true,  kind:'note'},
//   {text:'ctus,', hyphenAfter:false, kind:'note'},
//   {text:'M.:', hyphenAfter:false, kind:'barline'},
//   {text:'Do', hyphenAfter:true,  kind:'note'},
//   {text:'mi', hyphenAfter:true,  kind:'note'},
//   {text:'nus', hyphenAfter:false, kind:'note'},
// ]
// Parenthesized tokens are barline labels: rendered centered under the next
// barline rather than the next ligature.
function parseSyllables(text) {
    const result = [];
    const src = text || '';
    // Strip formatting tags and track bold/italic state per character position.
    const tagRe = /<\/?[bi]>/gi;
    let cleaned = '';
    const formatMap = []; // for each char in cleaned: { bold, italic }
    let bold = false;
    let italic = false;
    let lastIdx = 0;
    let m;
    while ((m = tagRe.exec(src)) !== null) {
        const before = src.slice(lastIdx, m.index);
        for (const c of before) {
            cleaned += c;
            formatMap.push({ bold, italic });
        }
        const tag = m[0].toLowerCase();
        if (tag === '<b>') { bold = true; }
        else if (tag === '</b>') { bold = false; }
        else if (tag === '<i>') { italic = true; }
        else if (tag === '</i>') { italic = false; }
        lastIdx = m.index + m[0].length;
    }
    const tail = src.slice(lastIdx);
    for (const c of tail) {
        cleaned += c;
        formatMap.push({ bold, italic });
    }

    // Build segments for a range [start, end) in cleaned, collapsing runs of
    // same formatting. The displayText function transforms the raw slice
    // (e.g. replacing ~ with space).
    function buildSegments(start, end, displayFn) {
        const segments = [];
        let runStart = start;
        let runBold = (formatMap[start] || {}).bold || false;
        let runItalic = (formatMap[start] || {}).italic || false;
        for (let p = start + 1; p < end; p++) {
            const f = formatMap[p] || { bold: false, italic: false };
            if (f.bold !== runBold || f.italic !== runItalic) {
                const rawSlice = cleaned.slice(runStart, p);
                segments.push({ text: displayFn(rawSlice), bold: runBold, italic: runItalic });
                runStart = p;
                runBold = f.bold;
                runItalic = f.italic;
            }
        }
        const rawSlice = cleaned.slice(runStart, end);
        segments.push({ text: displayFn(rawSlice), bold: runBold, italic: runItalic });
        return segments;
    }

    let i = 0;
    let noteCount = 0;
    while (i < cleaned.length) {
        const ch = cleaned[i];
        if (ch === ' ' || ch === '\t') {
            i++;
            continue;
        }
        if (ch === '(') {
            const end = cleaned.indexOf(')', i);
            const innerStart = i + 1;
            const innerEnd = end < 0 ? cleaned.length : end;
            const segments = buildSegments(innerStart, innerEnd, s => s.replace(/~/g, ' '));
            i = end < 0 ? cleaned.length : end + 1;
            result.push({
                text: cleaned.slice(innerStart, innerEnd).replace(/~/g, ' '),
                segments,
                hyphenAfter: false,
                kind: 'barline',
                notesBefore: noteCount,
            });
            continue;
        }
        let j = i;
        while (j < cleaned.length && cleaned[j] !== ' ' && cleaned[j] !== '\t' && cleaned[j] !== '(') {
            j++;
        }
        const word = cleaned.slice(i, j);
        const wordStart = i;
        i = j;
        const parts = word.split('-').filter(p => p !== '');
        let posInWord = 0;
        for (let k = 0; k < parts.length; k++) {
            const raw = parts[k];
            const sylPos = word.indexOf(raw, posInWord);
            const absStart = wordStart + sylPos;
            const absEnd = absStart + raw.length;
            posInWord = sylPos + raw.length + 1; // +1 for the hyphen
            const tildeIdx = raw.indexOf('~~');
            let text, alignText;
            if (tildeIdx !== -1) {
                text = raw.slice(0, tildeIdx).replace(/~/g, ' ') + ' ' + raw.slice(tildeIdx + 2).replace(/~/g, ' ');
                alignText = raw.slice(tildeIdx + 2).replace(/~/g, ' ');
            } else {
                text = raw.replace(/~/g, ' ');
                alignText = text;
            }
            const segments = buildSegments(absStart, absEnd, s => s.replace(/~~/g, ' ').replace(/~/g, ' '));
            result.push({
                text,
                alignText,
                segments,
                hyphenAfter: k < parts.length - 1,
                kind: 'note',
            });
            noteCount++;
        }
    }
    return result;
}

// Renders a syllable's segments array as SVG text content (plain or with tspans).
function renderSegments(segments) {
    if (!segments || segments.length === 0) {
        return '';
    }
    if (segments.every(s => !s.bold && !s.italic)) {
        return escapeText(segments.map(s => s.text).join(''));
    }
    return segments.map(s => {
        const attrs = (s.bold ? ' font-weight="bold"' : '') + (s.italic ? ' font-style="italic"' : '');
        if (!attrs) {
            return escapeText(s.text);
        }
        return `<tspan${attrs}>${escapeText(s.text)}</tspan>`;
    }).join('');
}

// Converts a lyric line with <b>/<i> formatting tags into SVG tspan elements.
function formatLyricLine(text) {
    const tagRe = /<\/?[bi]>/gi;
    const segments = [];
    let bold = false;
    let italic = false;
    let lastIdx = 0;
    let m;
    while ((m = tagRe.exec(text)) !== null) {
        const before = text.slice(lastIdx, m.index);
        if (before) {
            segments.push({ text: before, bold, italic });
        }
        const tag = m[0].toLowerCase();
        if (tag === '<b>') { bold = true; }
        else if (tag === '</b>') { bold = false; }
        else if (tag === '<i>') { italic = true; }
        else if (tag === '</i>') { italic = false; }
        lastIdx = m.index + m[0].length;
    }
    const tail = text.slice(lastIdx);
    if (tail) {
        segments.push({ text: tail, bold, italic });
    }
    if (segments.length === 0) {
        return '';
    }
    // If no formatting at all, return plain escaped text
    if (segments.every(s => !s.bold && !s.italic)) {
        return escapeText(text);
    }
    return segments.map(s => {
        const attrs = (s.bold ? ' font-weight="bold"' : '') + (s.italic ? ' font-style="italic"' : '');
        if (!attrs) {
            return escapeText(s.text);
        }
        return `<tspan${attrs}>${escapeText(s.text)}</tspan>`;
    }).join('');
}

// Renders parenthesized lyric tokens centered under their corresponding
// barlines. Each label pairs in order with the barlines that appeared in this
// row; extra labels beyond the row's barline count are skipped.
function emitBarlineLabels(ctx, labels, barlines, lyricY) {
    if (labels.length === 0 || barlines.length === 0) {
        return '';
    }
    const fontSize = ctx.lyricSize;
    const fontFamily = ctx.lyricFont;
    const parts = [];
    const n = Math.min(labels.length, barlines.length);
    for (let i = 0; i < n; i++) {
        const text = labels[i].text;
        if (text === '') {
            continue;
        }
        const cx = barlines[i].centerX;
        const label = labels[i];
        parts.push(`<text xml:space="preserve" x="${cx}" y="${lyricY}" font-family="${escapeAttr(fontFamily)}" font-size="${fontSize}" text-anchor="middle" fill="#000">${renderSegments(label.segments)}</text>`);
    }
    return parts.join('');
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
    const hyphenSpaceW = measureTextWidth('.', fontSize, fontFamily);
    const trailingAdvance = fontSize * 0.6;

    const parts = [];
    let prevRight = -Infinity;
    let lastRight = null;

    for (let i = 0; i < syllables.length; i++) {
        const syl = syllables[i];
        const alignStr = syl.alignText || syl.text;
        const fullW = measureTextWidth(syl.text, fontSize, fontFamily);
        const alignW = measureTextWidth(alignStr, fontSize, fontFamily);
        // Offset from the left edge of fullW to the left edge of alignText portion
        const prefixW = fullW - alignW;
        let center;
        if (i < ligatures.length) {
            const lig = ligatures[i];
            if (lig.shouldAlignLeft) {
                // Align left edge: syllable with mora, multi-note neume, or tenor note
                center = lig.leftX + alignW / 2 - ctx.staffSpace * 0.1;
            } else {
                // Center syllable: single note without mora (default)
                center = lig.centerX;
            }
        } else {
            // More syllables than ligatures: lay them out after the last one
            // with default spacing.
            center = prevRight + trailingAdvance + alignW / 2;
        }
        // left edge of full text: align portion starts at (center - alignW/2),
        // prefix sits to the left of it
        let left = center - alignW / 2 - prefixW;
        let hyphenX = null;
        if (i > 0) {
            const needsHyphen = syllables[i - 1].hyphenAfter;
            if (needsHyphen) {
                if (left - prevRight >= hyphenSpaceW) {
                    hyphenX = (left + prevRight) / 2;
                } else {
                    left = prevRight;
                    center = left + prefixW + alignW / 2;
                }
            } else if (left < prevRight + minGap) {
                left = prevRight + minGap;
                center = left + prefixW + alignW / 2;
            }
        }
        const right = left + fullW;
        const textCenter = left + fullW / 2;

        parts.push(`<text xml:space="preserve" x="${textCenter}" y="${lyricY}" font-family="${escapeAttr(fontFamily)}" font-size="${fontSize}" text-anchor="middle" fill="#000">${renderSegments(syl.segments)}</text>`);
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
