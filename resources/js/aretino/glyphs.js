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
    moraOffsetX: 0.85,                // horizontal distance from notehead center
    moraRadius: 0.125,

    // --- Episema (horizontal mark above note) -----------------------------
    episemaWidth: 0.9,
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
    clefCHeight: 0.9,
    clefCWidth: 0.7,
    clefCStroke: 0.34,
    clefCStrokeMinPx: 0.9,
    clefCLeftPadding: 0.15,            // gap before C-clef body
    clefCRightPadding: 0.6,            // gap after C-clef body
    clefPostGap: 1,                  // gap after start-of-system clef
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

    // --- Spacer -----------------------------------------------------------
    spacerAdvance: 1,                   // default width of one (sp) spacer unit

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

export function drawEpisema(ctx, cx, cy, onLine = false) {
    const w = ss(ctx, METRICS.episemaWidth);
    const y = cy - (onLine ? ctx.staffSpace * 1.5 : ctx.staffSpace);
    const sw = stroke(ctx, METRICS.episemaStroke, METRICS.episemaStrokeMinPx);
    return `<line x1="${cx - w / 2}" y1="${y}" x2="${cx + w / 2}" y2="${y}" stroke="#000" stroke-width="${sw}" stroke-linecap="round"/>`;
}

export function drawMora(ctx, cx, cy, onLine = false) {
    const dotX = cx + ss(ctx, METRICS.moraOffsetX);
    const r = ss(ctx, METRICS.moraRadius);
    const dotY = onLine ? cy - ctx.staffSpace / 2 : cy;
    return `<circle cx="${dotX}" cy="${dotY}" r="${r}" fill="#000"/>`;
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
        // Treble clef path from https://commons.wikimedia.org/wiki/File:Treble_clef.svg
        // (staff lines removed). Wiki coordinate system: staff line spacing = 591 units,
        // G line (line 2) at y = 8149, clef left extent at x ≈ 1186, right at x ≈ 2621.
        const k = ctx.staffSpace / 591;
        const tx = x - 1186 * k;
        const ty = lineY - 8149 * k;
        const pathD = 'M 2002,7851 C 1941,7868 1886,7906 1835,7964 C 1784,8023 1759,8088 1759,8158'
            + ' C 1759,8202 1774,8252 1803,8305 C 1832,8359 1876,8398 1933,8423'
            + ' C 1952,8427 1961,8437 1961,8451 C 1961,8456 1954,8461 1937,8465'
            + ' C 1846,8442 1771,8393 1713,8320 C 1655,8246 1625,8162 1623,8066'
            + ' C 1626,7963 1657,7867 1716,7779 C 1776,7690 1853,7627 1947,7590'
            + ' L 1878,7235'
            + ' C 1724,7363 1599,7496 1502,7636 C 1405,7775 1355,7926 1351,8089'
            + ' C 1353,8162 1368,8233 1396,8301 C 1424,8370 1466,8432 1522,8489'
            + ' C 1635,8602 1782,8661 1961,8667 C 2022,8663 2087,8652 2157,8634'
            + ' L 2002,7851 z'
            + ' M 2074,7841 L 2230,8610'
            + ' C 2384,8548 2461,8413 2461,8207'
            + ' C 2452,8138 2432,8076 2398,8021 C 2365,7965 2321,7921 2265,7889'
            + ' C 2209,7857 2146,7841 2074,7841 z'
            + ' M 1869,6801'
            + ' C 1902,6781 1940,6746 1981,6697 C 2022,6649 2062,6592 2100,6528'
            + ' C 2139,6463 2170,6397 2193,6330 C 2216,6264 2227,6201 2227,6143'
            + ' C 2227,6118 2225,6093 2220,6071 C 2216,6035 2205,6007 2186,5988'
            + ' C 2167,5970 2143,5960 2113,5960'
            + ' C 2053,5960 1999,5997 1951,6071'
            + ' C 1914,6135 1883,6211 1861,6297 C 1838,6384 1825,6470 1823,6557'
            + ' C 1828,6656 1844,6737 1869,6801 z'
            + ' M 1806,6859'
            + ' C 1761,6697 1736,6532 1731,6364'
            + ' C 1732,6256 1743,6155 1764,6061 C 1784,5967 1813,5886 1851,5816'
            + ' C 1888,5746 1931,5693 1979,5657 C 2022,5625 2053,5608 2070,5608'
            + ' C 2083,5608 2094,5613 2104,5622 C 2114,5631 2127,5646 2143,5666'
            + ' C 2262,5835 2322,6039 2322,6277'
            + ' C 2322,6390 2307,6500 2277,6610 C 2248,6719 2205,6823 2148,6920'
            + ' C 2090,7018 2022,7103 1943,7176'
            + ' L 2024,7570'
            + ' C 2068,7565 2098,7561 2115,7561'
            + ' C 2191,7561 2259,7577 2322,7609 C 2385,7641 2439,7684 2483,7739'
            + ' C 2527,7793 2561,7855 2585,7925 C 2608,7995 2621,8068 2621,8144'
            + ' C 2621,8262 2590,8370 2528,8467 C 2466,8564 2373,8635 2248,8681'
            + ' C 2256,8730 2270,8801 2291,8892 C 2311,8984 2326,9057 2336,9111'
            + ' C 2346,9165 2350,9217 2350,9268'
            + ' C 2350,9347 2331,9417 2293,9479 C 2254,9541 2202,9589 2136,9623'
            + ' C 2071,9657 1999,9674 1921,9674'
            + ' C 1811,9674 1715,9643 1633,9582 C 1551,9520 1507,9437 1503,9331'
            + ' C 1506,9284 1517,9240 1537,9198 C 1557,9156 1584,9122 1619,9096'
            + ' C 1653,9069 1694,9055 1741,9052'
            + ' C 1780,9052 1817,9063 1852,9084 C 1886,9106 1914,9135 1935,9172'
            + ' C 1955,9209 1966,9250 1966,9294'
            + ' C 1966,9353 1946,9403 1906,9444 C 1866,9485 1815,9506 1754,9506'
            + ' L 1731,9506'
            + ' C 1770,9566 1834,9597 1923,9597'
            + ' C 1968,9597 2014,9587 2060,9569 C 2107,9550 2146,9525 2179,9493'
            + ' C 2212,9461 2234,9427 2243,9391 C 2260,9350 2268,9293 2268,9222'
            + ' C 2268,9174 2263,9126 2254,9078 C 2245,9031 2231,8968 2212,8890'
            + ' C 2193,8813 2179,8753 2171,8712'
            + ' C 2111,8727 2049,8735 1984,8735'
            + ' C 1875,8735 1772,8713 1675,8668 C 1578,8623 1493,8561 1419,8481'
            + ' C 1346,8401 1289,8311 1248,8209 C 1208,8108 1187,8002 1186,7892'
            + ' C 1190,7790 1209,7692 1245,7600 C 1281,7507 1327,7419 1384,7337'
            + ' C 1441,7255 1500,7180 1561,7113 C 1623,7047 1704,6962 1806,6859 z';
        const svg = `<g transform="translate(${tx},${ty}) scale(${k})">`
            + `<path d="${pathD}" fill="#000" fill-rule="evenodd"/>`
            + '</g>';
        const advance = (2621 - 1186) * k + ss(ctx, METRICS.clefPostGap);
        return { svg, advance };
    }
    if (letter === 'f') {
        // Bass clef path from https://commons.wikimedia.org/wiki/File:Bass_clef.svg
        // (staff lines removed). Wiki coordinate system: staff line spacing = 591 units,
        // F line (line 4) at y = 6968, clef left extent at x ≈ 1239, right at x ≈ 2889.
        const k = ctx.staffSpace / 591;
        const tx = x - 1239 * k;
        const ty = lineY - 6968 * k;
        const pathD = 'M 1239,8245 C 1397,8138 1515,8057 1591,8001 C 1667,7946 1747,7877 1829,7795'
            + ' C 1911,7713 1980,7620 2036,7517 C 2080,7441 2118,7353 2149,7253'
            + ' C 2180,7154 2196,7058 2199,6967 C 2199,6882 2188,6801 2165,6725'
            + ' C 2143,6648 2105,6585 2051,6534 C 1997,6484 1927,6459 1840,6459'
            + ' C 1756,6459 1677,6476 1603,6509 C 1530,6543 1478,6597 1449,6673'
            + ' C 1449,6680 1445,6689 1439,6702 C 1441,6718 1449,6730 1464,6739'
            + ' C 1479,6748 1492,6752 1504,6752 C 1510,6752 1527,6749 1553,6743'
            + ' C 1580,6737 1602,6733 1620,6733 C 1673,6733 1720,6752 1763,6789'
            + ' C 1805,6826 1826,6871 1826,6924 C 1826,6962 1815,6998 1794,7031'
            + ' C 1773,7064 1744,7091 1707,7110 C 1670,7130 1629,7139 1585,7139'
            + ' C 1505,7139 1437,7115 1381,7066 C 1326,7016 1298,6953 1298,6874'
            + ' C 1298,6773 1329,6686 1390,6612 C 1452,6538 1530,6483 1626,6446'
            + ' C 1721,6408 1817,6390 1915,6390 C 2022,6390 2124,6417 2219,6472'
            + ' C 2315,6526 2390,6601 2446,6694 C 2502,6788 2531,6888 2531,6996'
            + ' C 2531,7188 2467,7366 2339,7531 C 2211,7696 2053,7839 1864,7961'
            + ' C 1738,8044 1534,8156 1253,8297 L 1239,8245 z'
            + ' M 2628,6698 C 2628,6662 2641,6632 2667,6608 C 2692,6583 2723,6571 2760,6571'
            + ' C 2792,6571 2822,6585 2849,6612 C 2876,6638 2889,6669 2889,6703'
            + ' C 2889,6739 2875,6770 2849,6795 C 2821,6819 2790,6831 2755,6831'
            + ' C 2718,6831 2688,6819 2664,6792 C 2640,6766 2628,6735 2628,6698 z'
            + ' M 2628,7222 C 2628,7186 2641,7155 2665,7131 C 2690,7106 2721,7094 2760,7094'
            + ' C 2792,7094 2821,7107 2849,7134 C 2875,7161 2889,7190 2889,7222'
            + ' C 2889,7261 2876,7292 2851,7317 C 2825,7342 2795,7355 2760,7355'
            + ' C 2721,7355 2690,7342 2665,7318 C 2641,7294 2628,7262 2628,7222 z';
        const svg = `<g transform="translate(${tx},${ty}) scale(${k})">`
            + `<path d="${pathD}" fill="#000" fill-rule="evenodd"/>`
            + '</g>';
        const advance = (2889 - 1239) * k + ss(ctx, METRICS.clefPostGap);
        return { svg, advance };
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
        const y1 = top3 + ctx.staffSpace * 1.5;
        const y2 = top3 - ctx.staffSpace * 1.5;
        svg = `<line x1="${lineX}" y1="${y1}" x2="${lineX}" y2="${y2}" stroke="#000" stroke-width="${sw}"/>`;
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
