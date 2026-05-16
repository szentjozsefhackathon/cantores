// SVG glyph builders for the Aretino renderer.
// Each drawing function returns an SVG string; layout-relevant helpers
// return { svg, advance }.
//
// =========================================================================
// SIZING CONVENTION
// =========================================================================
// All lengths below are expressed in **staff spaces (SS)** — the standard
// engraving unit (cf. SMuFL / Bravura).
//
//   1 SS  = vertical distance between two adjacent staff lines
//   1 step (one diatonic pitch) = 0.5 SS
//
// `ctx.staffSpace` is the pixel size of one staff space at the current
// scale, and `ctx.pitchStep` = ctx.staffSpace / 2.
//
// Stroke widths use `stroke(ctx, ssFraction, minPx)` so thin strokes stay
// visible when the staff is rendered very small.
//
// Coordinate convention: callers pass `staffBottomY` = y of the bottom
// staff line (line 1). Higher pitches → smaller y values.

export const METRICS = {
    // --- Notehead (rotated filled oval) -----------------------------------
    noteheadRx: 0.57,                  // pre-rotation horizontal radius
    noteheadRy: 0.45,                  // pre-rotation vertical radius
    noteheadRotationDeg: -20,
    noteBoxWidth: 0.9,                 // layout/bounding-box width
    noteBoxHeight: 1.0,                // layout/bounding-box height

    // --- Horizontal advances ----------------------------------------------
    singleNoteAdvance: 1.75,           // base spacing per glyph (× noteSpacing)
    ligatureStepAdvance: 1.05,         // added per extra note in a ligature
    expanderWidth: 0.75,               // intrinsic width of '*' expander
    neumeGapAdvance: 0.9,              // extra space inserted by '/' between neume groups

    // --- Staff lines ------------------------------------------------------
    staffLineCount: 5,
    staffLineStroke: 0.09,
    staffLineStrokeMinPx: 0.6,

    // --- Ledger lines -----------------------------------------------------
    ledgerHalfExtent: 0.81,            // extent on each side of notehead center
    ledgerLineSpacing: 1.0,            // distance between successive ledgers
    ledgerStroke: 0.09,
    ledgerStrokeMinPx: 0.6,

    // --- Stems (virga & tenor side strokes) -------------------------------
    stemStroke: 0.11,
    stemStrokeMinPx: 0.8,
    virgaStemLength: 1.75,              // default descent of virga stem
    virgaStemDescentBelowPrev: 1.25,    // descent past a lower preceding note

    // --- Tenor notehead (open oval with two side strokes) -----------------
    tenorOutlineStroke: 0.1,
    tenorOutlineStrokeMinPx: 0.8,
    tenorSideStrokeOffset: 0.075,      // gap between head edge and side stroke
    tenorSideStrokeHalfHeight: 0.9,

    // --- Mora dot ---------------------------------------------------------
    moraOffsetX: 0.765,                // horizontal distance from notehead center
    moraRadius: 0.175,

    // --- Episema (horizontal mark above note) -----------------------------
    episemaWidth: 0.9,
    episemaOffsetY: 1.075,             // distance above notehead center
    episemaStroke: 0.12,
    episemaStrokeMinPx: 0.8,

    // --- Liquescens (small tail off upper-right of head) ------------------
    liquescensAnchorX: 0.405,
    liquescensAnchorY: 0.4,            // above center
    liquescensTailDX: 0.3,
    liquescensTailDY: 0.55,
    liquescensControlDX: 0.1,
    liquescensStroke: 0.1,
    liquescensStrokeMinPx: 0.7,

    // --- Ligature connectors ----------------------------------------------
    ligatureConnectorStroke: 0.11,
    ligatureConnectorStrokeMinPx: 0.7,

    // --- Quilisma (saw-tooth notehead) ------------------------------------
    quilismaTeeth: 3,
    quilismaPeakUp: 0.55,              // × noteBoxHeight
    quilismaLowerY: 0.4,
    quilismaTrough: 0.85,

    // --- Clefs ------------------------------------------------------------
    clefGSize: 3.2,                    // glyph height
    clefGAdvanceScale: 1.1,
    clefFSize: 2.6,
    clefFAdvanceScale: 1.15,
    clefFDotRadius: 0.225,
    clefFDotOffsetY: 0.5,              // dot y-offset from clef line
    clefCHeight: 1.8,
    clefCWidth: 0.7,
    clefCStroke: 0.14,
    clefCStrokeMinPx: 0.9,
    clefCLeftPadding: 0.15,            // gap before C-clef body
    clefCRightPadding: 0.6,            // gap after C-clef body
    clefPostGap: 0.3,                  // gap after start-of-system clef
    clefInlinePostGap: 0.25,           // gap after mid-system clef change

    // --- Accidentals (flat / natural / sharp) -----------------------------
    accidentalSize: 0.9,
    accidentalAdvance: 1.08,

    // --- Barlines ---------------------------------------------------------
    barlineStroke: 0.12,
    barlineStrokeMinPx: 0.8,
    barlineOffsetX: 0.3,               // gap before line
    barlineAdvance: 0.8,
    barlinePostGap: 0.2,               // gap after barline
    barlineDoubleSecondOffsetX: 1.0,   // second line offset for '::'
    barlineDoubleAdvance: 1.5,

    // --- Page layout ------------------------------------------------------
    leftMargin: 5,
    rightMargin: 3,
    systemGap: 1.5,
    lyricToNextStaff: 2.5,
    titleTopPadding: 1.5,
};

const PITCH_BASE = { a: -4, b: -3, c: -2, d: -1, e: 0, f: 1, g: 2, h: 3, i: 4, j: 5, k: 6, l: 7, m: 8 };

function stroke(ctx, ssFraction, minPx) {
    return Math.max(minPx, ssFraction * ctx.staffSpace);
}

function ss(ctx, n) {
    return n * ctx.staffSpace;
}

export function pitchToPos(note) {
    const base = PITCH_BASE[note.pitch] ?? 0;
    return base + (note.high ? 7 : 0);
}

export function pitchY(ctx, note, staffBottomY) {
    return staffBottomY - pitchToPos(note) * ctx.pitchStep;
}

function attr(s) {
    return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
}

// Filled oval note head. Centered at (cx, cy). Rotated slightly CCW.
function ovalHead(ctx, cx, cy, opts = {}) {
    const rx = opts.rx ?? ss(ctx, METRICS.noteheadRx);
    const ry = opts.ry ?? ss(ctx, METRICS.noteheadRy);
    const fill = opts.fill ?? '#000';
    const strokeColor = opts.stroke ?? 'none';
    const sw = opts.strokeWidth ?? 0;
    return `<ellipse cx="${cx}" cy="${cy}" rx="${rx}" ry="${ry}" fill="${fill}" stroke="${strokeColor}" stroke-width="${sw}" transform="rotate(${METRICS.noteheadRotationDeg}, ${cx}, ${cy})"/>`;
}

function ledgerLines(ctx, cx, cy, staffBottomY) {
    // Draw ledger lines above/below the staff for notes outside line 1..line 5.
    const top = staffBottomY - (METRICS.staffLineCount - 1) * ctx.staffSpace;
    const bottom = staffBottomY;
    const halfW = ss(ctx, METRICS.ledgerHalfExtent);
    const spacing = ss(ctx, METRICS.ledgerLineSpacing);
    const tolerance = ctx.pitchStep;
    const sw = stroke(ctx, METRICS.ledgerStroke, METRICS.ledgerStrokeMinPx);
    const parts = [];
    if (cy < top - tolerance) {
        let yLine = top - spacing;
        while (yLine >= cy - tolerance) {
            parts.push(`<line x1="${cx - halfW}" y1="${yLine}" x2="${cx + halfW}" y2="${yLine}" stroke="#000" stroke-width="${sw}"/>`);
            yLine -= spacing;
        }
    } else if (cy > bottom + tolerance) {
        let yLine = bottom + spacing;
        while (yLine <= cy + tolerance) {
            parts.push(`<line x1="${cx - halfW}" y1="${yLine}" x2="${cx + halfW}" y2="${yLine}" stroke="#000" stroke-width="${sw}"/>`);
            yLine += spacing;
        }
    }
    return parts.join('');
}

export function drawNoteHead(ctx, note, cx, cy, staffBottomY, prevCy = null) {
    const parts = [];
    parts.push(ledgerLines(ctx, cx, cy, staffBottomY));

    const noteW = ss(ctx, METRICS.noteBoxWidth);
    const noteH = ss(ctx, METRICS.noteBoxHeight);

    if (note.shape === 'quilisma') {
        const teeth = METRICS.quilismaTeeth;
        const dx = noteW / teeth;
        let path = `M ${cx - noteW / 2} ${cy} `;
        for (let t = 0; t < teeth; t++) {
            const xa = cx - noteW / 2 + t * dx;
            const xb = xa + dx / 2;
            const xc = xa + dx;
            path += `L ${xb} ${cy - noteH * METRICS.quilismaPeakUp} L ${xc} ${cy} `;
        }
        path += `L ${cx + noteW / 2} ${cy + noteH * METRICS.quilismaLowerY} `;
        for (let t = teeth - 1; t >= 0; t--) {
            const xa = cx - noteW / 2 + t * dx + dx;
            const xb = xa - dx / 2;
            const xc = xa - dx;
            path += `L ${xb} ${cy + noteH * METRICS.quilismaTrough} L ${xc} ${cy + noteH * METRICS.quilismaLowerY} `;
        }
        path += 'Z';
        parts.push(`<path d="${path}" fill="#000"/>`);
    } else if (note.shape === 'tenor') {
        const outlineSW = stroke(ctx, METRICS.tenorOutlineStroke, METRICS.tenorOutlineStrokeMinPx);
        parts.push(ovalHead(ctx, cx, cy, { fill: 'none', stroke: '#000', strokeWidth: outlineSW }));
        const sideSW = stroke(ctx, METRICS.stemStroke, METRICS.stemStrokeMinPx);
        const sideX = noteW / 2 + ss(ctx, METRICS.tenorSideStrokeOffset);
        const halfH = ss(ctx, METRICS.tenorSideStrokeHalfHeight);
        parts.push(`<line x1="${cx - sideX}" y1="${cy - halfH}" x2="${cx - sideX}" y2="${cy + halfH}" stroke="#000" stroke-width="${sideSW}"/>`);
        parts.push(`<line x1="${cx + sideX}" y1="${cy - halfH}" x2="${cx + sideX}" y2="${cy + halfH}" stroke="#000" stroke-width="${sideSW}"/>`);
    } else {
        parts.push(ovalHead(ctx, cx, cy));
    }

    if (note.shape === 'virga' || note.virga) {
        // Stem going down from the left edge of the head.
        const sw = stroke(ctx, METRICS.stemStroke, METRICS.stemStrokeMinPx);
        const stemX = cx - noteW / 2 - sw / 2;
        const stemLength = prevCy !== null && prevCy > cy
            ? (prevCy - cy) + ss(ctx, METRICS.virgaStemDescentBelowPrev)
            : ss(ctx, METRICS.virgaStemLength);
        parts.push(`<line x1="${stemX}" y1="${cy}" x2="${stemX}" y2="${cy + stemLength}" stroke="#000" stroke-width="${sw}"/>`);
    }
    return parts.join('');
}

export function drawEpisema(ctx, cx, cy) {
    const w = ss(ctx, METRICS.episemaWidth);
    const y = cy - ss(ctx, METRICS.episemaOffsetY);
    const sw = stroke(ctx, METRICS.episemaStroke, METRICS.episemaStrokeMinPx);
    return `<line x1="${cx - w / 2}" y1="${y}" x2="${cx + w / 2}" y2="${y}" stroke="#000" stroke-width="${sw}" stroke-linecap="round"/>`;
}

export function drawMora(ctx, cx, cy) {
    const dotX = cx + ss(ctx, METRICS.moraOffsetX);
    const r = ss(ctx, METRICS.moraRadius);
    return `<circle cx="${dotX}" cy="${cy}" r="${r}" fill="#000"/>`;
}

export function drawLiquescens(ctx, cx, cy, direction = 'down') {
    const x1 = cx + ss(ctx, METRICS.liquescensAnchorX);
    const y1 = cy - ss(ctx, METRICS.liquescensAnchorY);
    const x2 = x1 + ss(ctx, METRICS.liquescensTailDX);
    const dy = ss(ctx, METRICS.liquescensTailDY);
    const y2 = direction === 'down' ? y1 + dy : y1 - dy;
    const cxControl = x1 + ss(ctx, METRICS.liquescensControlDX);
    const sw = stroke(ctx, METRICS.liquescensStroke, METRICS.liquescensStrokeMinPx);
    return `<path d="M ${x1} ${y1} Q ${cxControl} ${(y1 + y2) / 2} ${x2} ${y2}" fill="none" stroke="#000" stroke-width="${sw}" stroke-linecap="round"/>`;
}

function noteheadEdgeOffset(ctx) {
    // Geometric horizontal extent of the rotated notehead ellipse — used to
    // attach ligature connectors at the true left/right tip of each oval.
    const rx = ss(ctx, METRICS.noteheadRx);
    const ry = ss(ctx, METRICS.noteheadRy);
    const θ = METRICS.noteheadRotationDeg * Math.PI / 180;
    const cosθ = Math.cos(θ);
    const sinθ = Math.sin(θ);
    const d = Math.sqrt(rx * rx * cosθ * cosθ + ry * ry * sinθ * sinθ);
    const yShift = sinθ * cosθ * (rx * rx - ry * ry) / d;
    return { dx: d, dy: yShift };
}

export function noteheadRightPoint(ctx, cx, cy) {
    const { dx, dy } = noteheadEdgeOffset(ctx);
    return { x: cx + dx, y: cy + dy };
}

export function noteheadLeftPoint(ctx, cx, cy) {
    const { dx, dy } = noteheadEdgeOffset(ctx);
    return { x: cx - dx, y: cy - dy };
}

export function drawLigatureConnector(ctx, fromX, fromY, toX, toY, kind) {
    const sw = stroke(ctx, METRICS.ligatureConnectorStroke, METRICS.ligatureConnectorStrokeMinPx);
    if (kind === 'up') {
        return `<line x1="${fromX}" y1="${fromY}" x2="${toX}" y2="${toY}" stroke="#000" stroke-width="${sw}"/>`;
    }
    if (kind === 'down') {
        return `<line x1="${fromX}" y1="${fromY}" x2="${toX}" y2="${toY}" stroke="#000" stroke-width="${sw}" stroke-linecap="round"/>`;
    }
    return '';
}

export function ligatureConnectorHalfStroke(ctx) {
    return stroke(ctx, METRICS.ligatureConnectorStroke, METRICS.ligatureConnectorStrokeMinPx) / 2;
}

export function drawStaffLines(ctx, x1, x2, staffBottomY) {
    const sw = stroke(ctx, METRICS.staffLineStroke, METRICS.staffLineStrokeMinPx);
    const parts = [];
    for (let i = 0; i < METRICS.staffLineCount; i++) {
        const y = staffBottomY - i * ctx.staffSpace;
        parts.push(`<line x1="${x1}" y1="${y}" x2="${x2}" y2="${y}" stroke="#000" stroke-width="${sw}"/>`);
    }
    return parts.join('');
}

export function drawClef(ctx, clef, x, staffBottomY) {
    const lineY = staffBottomY - (clef.line - 1) * ctx.staffSpace;
    const letter = clef.letter.toLowerCase();
    if (letter === 'g') {
        const size = ss(ctx, METRICS.clefGSize);
        const tx = x + size * 0.45;
        const ty = lineY + size * 0.32;
        const stemSW = stroke(ctx, METRICS.stemStroke, METRICS.stemStrokeMinPx);
        const svg = `<text x="${tx}" y="${ty}" font-family="serif" font-style="italic" font-weight="bold" font-size="${size}" text-anchor="middle" fill="#000">G</text>`
            + `<line x1="${tx}" y1="${ty - size * 0.95}" x2="${tx}" y2="${lineY + size * 0.9}" stroke="#000" stroke-width="${stemSW}"/>`;
        return { svg, advance: size * METRICS.clefGAdvanceScale };
    }
    if (letter === 'f') {
        const size = ss(ctx, METRICS.clefFSize);
        const tx = x + size * 0.45;
        const ty = lineY + size * 0.4;
        const dotR = ss(ctx, METRICS.clefFDotRadius);
        const dotOffsetY = ss(ctx, METRICS.clefFDotOffsetY);
        const svg = `<text x="${tx}" y="${ty}" font-family="serif" font-style="italic" font-weight="bold" font-size="${size}" text-anchor="middle" fill="#000">F</text>`
            + `<circle cx="${tx + size * 0.55}" cy="${lineY - dotOffsetY}" r="${dotR}" fill="#000"/>`
            + `<circle cx="${tx + size * 0.55}" cy="${lineY + dotOffsetY}" r="${dotR}" fill="#000"/>`;
        return { svg, advance: size * METRICS.clefFAdvanceScale };
    }
    if (letter === 'c') {
        // Square C-clef (bracket-style "C" centered on its line).
        const h = ss(ctx, METRICS.clefCHeight);
        const w = ss(ctx, METRICS.clefCWidth);
        const sw = stroke(ctx, METRICS.clefCStroke, METRICS.clefCStrokeMinPx);
        const left = x + ss(ctx, METRICS.clefCLeftPadding);
        const top = lineY - h / 2;
        const bottom = lineY + h / 2;
        const right = left + w;
        const svg = `<path d="M ${right} ${top} L ${left} ${top} L ${left} ${bottom} L ${right} ${bottom}" fill="none" stroke="#000" stroke-width="${sw}"/>`
            + `<rect x="${left - sw / 2}" y="${top - sw / 2}" width="${sw}" height="${h + sw}" fill="#000"/>`;
        return { svg, advance: w + ss(ctx, METRICS.clefCRightPadding) };
    }
    return { svg: '', advance: 0 };
}

export function drawAccidental(ctx, pitchLetter, kind, x, staffBottomY) {
    const pos = PITCH_BASE[pitchLetter] ?? 3;
    const cy = staffBottomY - pos * ctx.pitchStep;
    const sz = ss(ctx, METRICS.accidentalSize);
    let svg = '';
    if (kind === 'x') {
        // flat
        const stemSW = stroke(ctx, METRICS.stemStroke, METRICS.stemStrokeMinPx);
        const stemX = x + sz * 0.25;
        svg = `<line x1="${stemX}" y1="${cy - sz * 1.1}" x2="${stemX}" y2="${cy + sz * 0.35}" stroke="#000" stroke-width="${stemSW}"/>`
            + `<path d="M ${stemX} ${cy - sz * 0.1} Q ${stemX + sz * 0.9} ${cy - sz * 0.55} ${stemX + sz * 0.05} ${cy + sz * 0.4}" fill="#000" stroke="#000" stroke-width="${stemSW}"/>`;
    } else if (kind === 'y') {
        // natural
        const w = sz * 0.55;
        const h = sz * 1.4;
        const sw = stroke(ctx, METRICS.liquescensStroke, METRICS.liquescensStrokeMinPx);
        const cxA = x + sz * 0.45;
        svg = `<line x1="${cxA - w / 2}" y1="${cy - h / 2}" x2="${cxA - w / 2}" y2="${cy + h / 2 - sz * 0.2}" stroke="#000" stroke-width="${sw}"/>`
            + `<line x1="${cxA + w / 2}" y1="${cy - h / 2 + sz * 0.2}" x2="${cxA + w / 2}" y2="${cy + h / 2}" stroke="#000" stroke-width="${sw}"/>`
            + `<line x1="${cxA - w / 2}" y1="${cy - sz * 0.15}" x2="${cxA + w / 2}" y2="${cy - sz * 0.4}" stroke="#000" stroke-width="${sw * 1.4}"/>`
            + `<line x1="${cxA - w / 2}" y1="${cy + sz * 0.4}" x2="${cxA + w / 2}" y2="${cy + sz * 0.15}" stroke="#000" stroke-width="${sw * 1.4}"/>`;
    } else if (kind === '#') {
        // sharp
        const w = sz * 0.7;
        const h = sz * 1.3;
        const sw = stroke(ctx, METRICS.liquescensStroke, METRICS.liquescensStrokeMinPx);
        const cxA = x + sz * 0.45;
        svg = `<line x1="${cxA - w * 0.3}" y1="${cy - h / 2}" x2="${cxA - w * 0.3}" y2="${cy + h / 2}" stroke="#000" stroke-width="${sw}"/>`
            + `<line x1="${cxA + w * 0.3}" y1="${cy - h / 2}" x2="${cxA + w * 0.3}" y2="${cy + h / 2}" stroke="#000" stroke-width="${sw}"/>`
            + `<line x1="${cxA - w / 2}" y1="${cy - sz * 0.1}" x2="${cxA + w / 2}" y2="${cy - sz * 0.35}" stroke="#000" stroke-width="${sw * 1.4}"/>`
            + `<line x1="${cxA - w / 2}" y1="${cy + sz * 0.35}" x2="${cxA + w / 2}" y2="${cy + sz * 0.1}" stroke="#000" stroke-width="${sw * 1.4}"/>`;
    }
    return { svg, advance: ss(ctx, METRICS.accidentalAdvance) };
}

export function drawBarline(ctx, kind, x, staffBottomY) {
    const top5 = staffBottomY - 4 * ctx.staffSpace;
    const top3 = staffBottomY - 2 * ctx.staffSpace;
    const sw = stroke(ctx, METRICS.barlineStroke, METRICS.barlineStrokeMinPx);
    const lineX = x + ss(ctx, METRICS.barlineOffsetX);
    let svg = '';
    let advance = ss(ctx, METRICS.barlineAdvance);
    if (kind === ',') {
        const y1 = top5 - ctx.staffSpace * 0.5;
        const y2 = top5 + ctx.staffSpace * 0.5;
        svg = `<line x1="${lineX}" y1="${y1}" x2="${lineX}" y2="${y2}" stroke="#000" stroke-width="${sw}"/>`;
    } else if (kind === ';') {
        svg = `<line x1="${lineX}" y1="${top5}" x2="${lineX}" y2="${top3}" stroke="#000" stroke-width="${sw}"/>`;
    } else if (kind === ':') {
        svg = `<line x1="${lineX}" y1="${top5}" x2="${lineX}" y2="${staffBottomY}" stroke="#000" stroke-width="${sw}"/>`;
    } else if (kind === '::') {
        const lineX2 = x + ss(ctx, METRICS.barlineDoubleSecondOffsetX);
        svg = `<line x1="${lineX}" y1="${top5}" x2="${lineX}" y2="${staffBottomY}" stroke="#000" stroke-width="${sw}"/>`
            + `<line x1="${lineX2}" y1="${top5}" x2="${lineX2}" y2="${staffBottomY}" stroke="#000" stroke-width="${sw}"/>`;
        advance = ss(ctx, METRICS.barlineDoubleAdvance);
    }
    return { svg, advance };
}

export function escapeText(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

export function escapeAttr(s) {
    return attr(s);
}
