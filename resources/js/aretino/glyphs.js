// SVG glyph builders for the Aretino renderer.
// Each function returns { svg: string, advance: number }.
//
// Coordinate convention: callers pass `staffBottomY` = y of the bottom
// staff line (line 1). Higher pitches → smaller y values. `unit` = one
// diatonic step in pixels (half the line gap).

const PITCH_BASE = { a: -4, b: -3, c: -2, d: -1, e: 0, f: 1, g: 2, h: 3, i: 4, j: 5, k: 6, l: 7, m: 8 };

export function pitchToPos(note) {
    const base = PITCH_BASE[note.pitch] ?? 0;
    return base + (note.high ? 7 : 0);
}

export function pitchY(ctx, note, staffBottomY) {
    return staffBottomY - pitchToPos(note) * ctx.unit;
}

function attr(s) {
    return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
}

function notesNeedLedger(ctx, pos, staffBottomY) {
    const out = [];
    const lineX1 = (cx) => cx - ctx.noteW * 0.9;
    const lineX2 = (cx) => cx + ctx.noteW * 0.9;
    return { lineX1, lineX2, out };
}

// Filled oval note head. Centered at (cx, cy). Rotated slightly CCW.
function ovalHead(ctx, cx, cy, opts = {}) {
    const rx = (opts.rx ?? ctx.unit * 1.9 * 0.6);
    const ry = (opts.ry ?? ctx.unit * 0.9);
    const fill = opts.fill ?? '#000';
    const stroke = opts.stroke ?? 'none';
    const sw = opts.strokeWidth ?? 0;
    return `<ellipse cx="${cx}" cy="${cy}" rx="${rx}" ry="${ry}" fill="${fill}" stroke="${stroke}" stroke-width="${sw}" transform="rotate(-20, ${cx}, ${cy})"/>`;
}

function ledgerLines(ctx, cx, cy, staffBottomY) {
    // Draw ledger lines above/below the staff for notes outside line 1..line 5.
    const top = staffBottomY - 8 * ctx.unit; // line 5
    const bottom = staffBottomY; // line 1
    const halfW = ctx.noteW * 0.9;
    const parts = [];
    const sw = Math.max(0.6, ctx.unit * 0.18);
    if (cy < top - ctx.unit / 2) {
        // above staff: ledger lines at line 5 + n * (2*unit) until reaching cy
        let yLine = top - 2 * ctx.unit;
        while (yLine >= cy - ctx.unit / 2) {
            parts.push(`<line x1="${cx - halfW}" y1="${yLine}" x2="${cx + halfW}" y2="${yLine}" stroke="#000" stroke-width="${sw}"/>`);
            yLine -= 2 * ctx.unit;
        }
    } else if (cy > bottom + ctx.unit / 2) {
        let yLine = bottom + 2 * ctx.unit;
        while (yLine <= cy + ctx.unit / 2) {
            parts.push(`<line x1="${cx - halfW}" y1="${yLine}" x2="${cx + halfW}" y2="${yLine}" stroke="#000" stroke-width="${sw}"/>`);
            yLine += 2 * ctx.unit;
        }
    }
    return parts.join('');
}

export function drawNoteHead(ctx, note, cx, cy, staffBottomY, prevCy = null) {
    const parts = [];
    parts.push(ledgerLines(ctx, cx, cy, staffBottomY));
    if (note.shape === 'quilisma') {
        // Zig-zag contoured oval. Approximated as a small saw-tooth path.
        const w = ctx.noteW;
        const h = ctx.noteH;
        const teeth = 3;
        const dx = w / teeth;
        let path = `M ${cx - w / 2} ${cy} `;
        for (let t = 0; t < teeth; t++) {
            const xa = cx - w / 2 + t * dx;
            const xb = xa + dx / 2;
            const xc = xa + dx;
            path += `L ${xb} ${cy - h * 0.55} L ${xc} ${cy} `;
        }
        path += `L ${cx + w / 2} ${cy + h * 0.4} `;
        for (let t = teeth - 1; t >= 0; t--) {
            const xa = cx - w / 2 + t * dx + dx;
            const xb = xa - dx / 2;
            const xc = xa - dx;
            path += `L ${xb} ${cy + h * 0.85} L ${xc} ${cy + h * 0.4} `;
        }
        path += 'Z';
        parts.push(`<path d="${path}" fill="#000"/>`);
    } else if (note.shape === 'tenor') {
        parts.push(ovalHead(ctx, cx, cy, { fill: 'none', stroke: '#000', strokeWidth: Math.max(0.8, ctx.unit * 0.2) }));
        const sw = Math.max(0.8, ctx.unit * 0.22);
        const halfH = ctx.noteH * 0.9;
        parts.push(`<line x1="${cx - ctx.noteW / 2 - ctx.unit * 0.15}" y1="${cy - halfH}" x2="${cx - ctx.noteW / 2 - ctx.unit * 0.15}" y2="${cy + halfH}" stroke="#000" stroke-width="${sw}"/>`);
        parts.push(`<line x1="${cx + ctx.noteW / 2 + ctx.unit * 0.15}" y1="${cy - halfH}" x2="${cx + ctx.noteW / 2 + ctx.unit * 0.15}" y2="${cy + halfH}" stroke="#000" stroke-width="${sw}"/>`);
    } else {
        parts.push(ovalHead(ctx, cx, cy));
    }
    if (note.shape === 'virga' || note.virga) {
        // Stem going down from the left side of the head.
        const sw = Math.max(0.8, ctx.unit * 0.22);
        const stemX = cx - ctx.noteW / 2.0 - sw/2.0;        
        // When a preceding note is lower (higher cy), extend stem to reach below it.
        const stemLength = prevCy !== null && prevCy > cy
            ? (prevCy - cy) + 2 * ctx.staffSpace
            : ctx.staffSpace * 1.5;
        parts.push(`<line x1="${stemX}" y1="${cy}" x2="${stemX}" y2="${cy + stemLength}" stroke="#000" stroke-width="${sw}"/>`);
    }
    return parts.join('');
}

export function drawEpisema(ctx, cx, cy) {
    const w = ctx.noteW * 1;
    const y = cy - ctx.noteH * 0.9 - ctx.unit * 0.35;
    const sw = Math.max(0.8, ctx.unit * 0.24);
    return `<line x1="${cx - w / 2}" y1="${y}" x2="${cx + w / 2}" y2="${y}" stroke="#000" stroke-width="${sw}" stroke-linecap="round"/>`;
}

export function drawMora(ctx, cx, cy) {
    const dotX = cx + ctx.noteW * 0.85;
    const r = ctx.unit * 0.35;
    return `<circle cx="${dotX}" cy="${cy}" r="${r}" fill="#000"/>`;
}

export function drawLiquescens(ctx, cx, cy, direction = 'down') {
    // Little tail hanging off the upper-right of the head.
    const x1 = cx + ctx.noteW * 0.45;
    const y1 = cy - ctx.noteH * 0.4;
    const x2 = x1 + ctx.unit * 0.6;
    const y2 = direction === 'down' ? y1 + ctx.unit * 1.1 : y1 - ctx.unit * 1.1;
    const sw = Math.max(0.7, ctx.unit * 0.2);
    return `<path d="M ${x1} ${y1} Q ${x1 + ctx.unit * 0.2} ${(y1 + y2) / 2} ${x2} ${y2}" fill="none" stroke="#000" stroke-width="${sw}" stroke-linecap="round"/>`;
}

// Returns the geometric rightmost point of a notehead ellipse at (cx, cy).
export function noteheadRightPoint(ctx, cx, cy) {
    const rx = ctx.unit * 1.9 * 0.6;
    const ry = ctx.unit * 0.9;
    const θ = -20 * Math.PI / 180;
    const cosθ = Math.cos(θ);
    const sinθ = Math.sin(θ);
    const d = Math.sqrt(rx * rx * cosθ * cosθ + ry * ry * sinθ * sinθ);
    return { x: cx + d, y: cy + sinθ * cosθ * (rx * rx - ry * ry) / d };
}

// Returns the geometric leftmost point of a notehead ellipse at (cx, cy).
export function noteheadLeftPoint(ctx, cx, cy) {
    const rx = ctx.unit * 1.9 * 0.6;
    const ry = ctx.unit * 0.9;
    const θ = -20 * Math.PI / 180;
    const cosθ = Math.cos(θ);
    const sinθ = Math.sin(θ);
    const d = Math.sqrt(rx * rx * cosθ * cosθ + ry * ry * sinθ * sinθ);
    return { x: cx - d, y: cy - sinθ * cosθ * (rx * rx - ry * ry) / d };
}

export function drawLigatureConnector(ctx, fromX, fromY, toX, toY, kind) {
    const sw = Math.max(0.7, ctx.unit * 0.22);
    if (kind === 'up') {
        // Vertical line on the left side of the upper note connecting ascending heads.
        return `<line x1="${fromX}" y1="${fromY}" x2="${toX}" y2="${toY}" stroke="#000" stroke-width="${sw}"/>`;
    }
    if (kind === 'down') {
        // Straight line from rightmost point of first ellipse to leftmost point of second ellipse.
        // Callers pass the already-computed attachment points directly.
        return `<line x1="${fromX}" y1="${fromY}" x2="${toX}" y2="${toY}" stroke="#000" stroke-width="${sw}" stroke-linecap="round"/>`;
    }
    return '';
}

export function drawStaffLines(ctx, x1, x2, staffBottomY) {
    const parts = [];
    const sw = Math.max(0.6, ctx.unit * 0.18);
    for (let i = 0; i < 5; i++) {
        const y = staffBottomY - i * ctx.staffSpace;
        parts.push(`<line x1="${x1}" y1="${y}" x2="${x2}" y2="${y}" stroke="#000" stroke-width="${sw}"/>`);
    }
    return parts.join('');
}

export function drawClef(ctx, clef, x, staffBottomY) {
    const lineY = staffBottomY - (clef.line - 1) * ctx.staffSpace;
    const letter = clef.letter.toLowerCase();
    if (letter === 'g') {
        const size = ctx.staffSpace * 3.2;
        const tx = x + size * 0.45;
        const ty = lineY + size * 0.32;
        const svg = `<text x="${tx}" y="${ty}" font-family="serif" font-style="italic" font-weight="bold" font-size="${size}" text-anchor="middle" fill="#000">G</text>`
            + `<line x1="${tx}" y1="${ty - size * 0.95}" x2="${tx}" y2="${lineY + size * 0.9}" stroke="#000" stroke-width="${Math.max(0.8, ctx.unit * 0.22)}"/>`;
        return { svg, advance: size * 1.1 };
    }
    if (letter === 'f') {
        const size = ctx.staffSpace * 2.6;
        const tx = x + size * 0.45;
        const ty = lineY + size * 0.4;
        const dotR = ctx.unit * 0.45;
        const svg = `<text x="${tx}" y="${ty}" font-family="serif" font-style="italic" font-weight="bold" font-size="${size}" text-anchor="middle" fill="#000">F</text>`
            + `<circle cx="${tx + size * 0.55}" cy="${lineY - ctx.staffSpace * 0.5}" r="${dotR}" fill="#000"/>`
            + `<circle cx="${tx + size * 0.55}" cy="${lineY + ctx.staffSpace * 0.5}" r="${dotR}" fill="#000"/>`;
        return { svg, advance: size * 1.15 };
    }
    if (letter === 'c') {
        // Square C-clef (per spec — a small bracket-style "C" centered on its line).
        const h = ctx.staffSpace * 1.8;
        const w = ctx.staffSpace * 0.7;
        const sw = Math.max(0.9, ctx.unit * 0.28);
        const left = x + ctx.unit * 0.3;
        const top = lineY - h / 2;
        const bottom = lineY + h / 2;
        const right = left + w;
        const svg = `<path d="M ${right} ${top} L ${left} ${top} L ${left} ${bottom} L ${right} ${bottom}" fill="none" stroke="#000" stroke-width="${sw}"/>`
            + `<rect x="${left - sw / 2}" y="${top - sw / 2}" width="${sw}" height="${h + sw}" fill="#000"/>`;
        return { svg, advance: w + ctx.unit * 1.2 };
    }
    return { svg: '', advance: 0 };
}

export function drawAccidental(ctx, pitchLetter, kind, x, staffBottomY) {
    const pos = PITCH_BASE[pitchLetter] ?? 3; // default to middle B-line
    const cy = staffBottomY - pos * ctx.unit;
    const sz = ctx.staffSpace * 0.9;
    let svg = '';
    if (kind === 'x') {
        // flat
        const stemSW = Math.max(0.8, ctx.unit * 0.22);
        const stemX = x + sz * 0.25;
        svg = `<line x1="${stemX}" y1="${cy - sz * 1.1}" x2="${stemX}" y2="${cy + sz * 0.35}" stroke="#000" stroke-width="${stemSW}"/>`
            + `<path d="M ${stemX} ${cy - sz * 0.1} Q ${stemX + sz * 0.9} ${cy - sz * 0.55} ${stemX + sz * 0.05} ${cy + sz * 0.4}" fill="#000" stroke="#000" stroke-width="${stemSW}"/>`;
    } else if (kind === 'y') {
        // natural
        const w = sz * 0.55;
        const h = sz * 1.4;
        const sw = Math.max(0.7, ctx.unit * 0.2);
        const cxA = x + sz * 0.45;
        svg = `<line x1="${cxA - w / 2}" y1="${cy - h / 2}" x2="${cxA - w / 2}" y2="${cy + h / 2 - sz * 0.2}" stroke="#000" stroke-width="${sw}"/>`
            + `<line x1="${cxA + w / 2}" y1="${cy - h / 2 + sz * 0.2}" x2="${cxA + w / 2}" y2="${cy + h / 2}" stroke="#000" stroke-width="${sw}"/>`
            + `<line x1="${cxA - w / 2}" y1="${cy - sz * 0.15}" x2="${cxA + w / 2}" y2="${cy - sz * 0.4}" stroke="#000" stroke-width="${sw * 1.4}"/>`
            + `<line x1="${cxA - w / 2}" y1="${cy + sz * 0.4}" x2="${cxA + w / 2}" y2="${cy + sz * 0.15}" stroke="#000" stroke-width="${sw * 1.4}"/>`;
    } else if (kind === '#') {
        const w = sz * 0.7;
        const h = sz * 1.3;
        const sw = Math.max(0.7, ctx.unit * 0.2);
        const cxA = x + sz * 0.45;
        svg = `<line x1="${cxA - w * 0.3}" y1="${cy - h / 2}" x2="${cxA - w * 0.3}" y2="${cy + h / 2}" stroke="#000" stroke-width="${sw}"/>`
            + `<line x1="${cxA + w * 0.3}" y1="${cy - h / 2}" x2="${cxA + w * 0.3}" y2="${cy + h / 2}" stroke="#000" stroke-width="${sw}"/>`
            + `<line x1="${cxA - w / 2}" y1="${cy - sz * 0.1}" x2="${cxA + w / 2}" y2="${cy - sz * 0.35}" stroke="#000" stroke-width="${sw * 1.4}"/>`
            + `<line x1="${cxA - w / 2}" y1="${cy + sz * 0.35}" x2="${cxA + w / 2}" y2="${cy + sz * 0.1}" stroke="#000" stroke-width="${sw * 1.4}"/>`;
    }
    return { svg, advance: sz * 1.2 };
}

export function drawBarline(ctx, kind, x, staffBottomY) {
    const top5 = staffBottomY - 4 * ctx.staffSpace;
    const top3 = staffBottomY - 2 * ctx.staffSpace;
    const sw = Math.max(0.8, ctx.unit * 0.24);
    let svg = '';
    let advance = ctx.unit * 1.6;
    if (kind === ',') {
        const y1 = top5 - ctx.staffSpace * 0.5;
        const y2 = top5 + ctx.staffSpace * 0.5;
        svg = `<line x1="${x + ctx.unit * 0.6}" y1="${y1}" x2="${x + ctx.unit * 0.6}" y2="${y2}" stroke="#000" stroke-width="${sw}"/>`;
    } else if (kind === ';') {
        svg = `<line x1="${x + ctx.unit * 0.6}" y1="${top5}" x2="${x + ctx.unit * 0.6}" y2="${top3}" stroke="#000" stroke-width="${sw}"/>`;
    } else if (kind === ':') {
        svg = `<line x1="${x + ctx.unit * 0.6}" y1="${top5}" x2="${x + ctx.unit * 0.6}" y2="${staffBottomY}" stroke="#000" stroke-width="${sw}"/>`;
    } else if (kind === '::') {
        svg = `<line x1="${x + ctx.unit * 0.6}" y1="${top5}" x2="${x + ctx.unit * 0.6}" y2="${staffBottomY}" stroke="#000" stroke-width="${sw}"/>`
            + `<line x1="${x + ctx.unit * 2}" y1="${top5}" x2="${x + ctx.unit * 2}" y2="${staffBottomY}" stroke="#000" stroke-width="${sw}"/>`;
        advance = ctx.unit * 3;
    }
    return { svg, advance };
}

export function escapeText(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

export function escapeAttr(s) {
    return attr(s);
}
