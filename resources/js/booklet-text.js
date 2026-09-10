/**
 * Plain text as a flowable block.
 *
 * A booklet adds words of its own to the music: the heading above each score, the
 * page number at the foot. Both are the same shape as everything else on the
 * page — a standalone SVG with a height — so the packer treats them no
 * differently from a staff.
 */

/** Line height as a multiple of the font size. */
const LINE = 1.5;

export function escapeXml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&apos;');
}

export function round(value) {
    return Math.round(value * 1000) / 1000;
}

/**
 * @param {object} options
 * @param {string} options.content
 * @param {number} options.fontSize in px
 * @param {string} options.fontFamily
 * @param {number} options.width the box the text sits in
 * @param {boolean} [options.bold]
 * @param {boolean} [options.italic]
 * @param {string} [options.fill]
 * @param {number} [options.lineHeight] multiple of the font size
 * @param {number} [options.leadingScale] the font size restated as the size the
 *   leading is measured in; see leadingScale() in booklet-geometry.js
 * @param {string} [options.suffix] set smaller on the same line, after a space
 * @param {number} [options.suffixSize] in px; defaults to three quarters of the text's
 * @returns {{height: number, svg: string}}
 */
export function textRowSvg({
    content,
    fontSize,
    fontFamily,
    width,
    bold = false,
    italic = false,
    fill = '#000000',
    lineHeight = LINE,
    leadingScale = 1,
    suffix = null,
    suffixSize = null,
}) {
    const height = fontSize * lineHeight * leadingScale;
    const weight = bold ? ' font-weight="bold"' : '';
    const style = italic ? ' font-style="italic"' : '';

    // Said on the line it belongs to and in the same breath, so it is a span of
    // the same text rather than a row of its own: a reference set beside a title
    // must not be able to break away from it, and the line it shares keeps its
    // own height whatever size the span is set in.
    const tail = suffix
        ? `<tspan font-size="${round(suffixSize ?? fontSize * 0.75)}" font-weight="normal"> ${escapeXml(suffix)}</tspan>`
        : '';

    const body = `<text x="0" y="${round(height * 0.75)}" font-family="${escapeXml(fontFamily)}" `
        + `font-size="${round(fontSize)}" fill="${fill}"${weight}${style} `
        + `xml:space="preserve">${escapeXml(content)}${tail}</text>`;

    return {
        height,
        svg: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${round(Math.max(width, 1))} ${round(height)}" `
            + `width="${round(Math.max(width, 1))}" height="${round(height)}">${body}</svg>`,
    };
}
