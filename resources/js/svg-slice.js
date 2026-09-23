/**
 * Lifting the lines of a chant out of the one document exsurge draws.
 *
 * exsurge engraves a whole chant as a single <svg>, but marks each staff line
 * with class="chantLine", so the lines can be measured and cut out one at a
 * time. The booklet flows them across pages that way, and a slide that runs
 * over is cut between them the same way (see slide-systems.js).
 */

const SVG_NS = 'http://www.w3.org/2000/svg';

/** Declared on every lifted fragment: exsurge draws its glyphs with xlink:href. */
const XLINK_NS = 'http://www.w3.org/1999/xlink';

/** Numbers the documents lifted here, so no two of them are scoped alike. */
let sliceSerial = 0;

/**
 * Cut a rendered document into one standalone SVG per matching line.
 *
 * Measured through getBoundingClientRect rather than getBBox, so nested
 * transforms need no unpicking: the ratio between the root's box on screen and
 * its viewBox converts a line's screen position straight back into user units.
 * The host must therefore be laid out — off-screen is fine, `display: none` is
 * not.
 */
export function sliceRenderedSvg(svgMarkup, selector, host) {
    host.innerHTML = svgMarkup;
    const root = host.querySelector('svg');

    if (!root) {
        return [];
    }

    const viewBox = (root.getAttribute('viewBox') ?? '').trim().split(/[\s,]+/).map(Number);
    const lines = Array.from(root.querySelectorAll(selector));
    const rootRect = root.getBoundingClientRect();
    const scope = `exs-${++sliceSerial}`;
    const ids = Array.from(root.querySelectorAll('defs > [id]')).map((node) => node.getAttribute('id'));
    const scopedClass = [root.getAttribute('class'), scope].filter(Boolean).join(' ');

    if (lines.length === 0 || viewBox.length !== 4 || rootRect.height === 0) {
        root.setAttribute('class', scopedClass);
        const whole = scopeLiftedMarkup(root.outerHTML, scope, ids);
        host.innerHTML = '';

        return [{ height: svgHeight(whole), svg: whole }];
    }

    const [, viewTop, viewWidth, viewHeight] = viewBox;
    const unitsPerPixel = viewHeight / rootRect.height;
    const preamble = definitionsOf(root);

    const blocks = lines.map((line) => {
        const rect = line.getBoundingClientRect();
        const top = viewTop + (rect.top - rootRect.top) * unitsPerPixel;
        const height = Math.max(rect.height * unitsPerPixel, 1);
        const markup = `<svg xmlns="${SVG_NS}" xmlns:xlink="${XLINK_NS}" class="${scopedClass}" `
            + `viewBox="0 ${top} ${viewWidth} ${height}" `
            + `width="${viewWidth}" height="${height}">${preamble}${line.outerHTML}</svg>`;

        return { height, svg: scopeLiftedMarkup(markup, scope, ids) };
    });

    host.innerHTML = '';

    return blocks;
}

/**
 * Make a lifted fragment's stylesheet and definitions its own.
 *
 * Both of exsurge's ways of naming things hold for the one document it drew and
 * for nothing else. Every rule it writes is scoped to `svg.Exsurge`, which the
 * root of a cut-out line is not — and stops being altogether once stackSvgs
 * nests it in a <g> under the page's own <svg> — so unscoped the lyrics come out
 * in the browser's default face at its default size rather than in the booklet's.
 * And each glyph is defined under its own name, so two chants on one page both
 * define PunctumQuadratum; stackSvgs keeps the first, and since the definition
 * carries the staff scaling it was drawn at, the second chant's notes would come
 * out at the first one's size.
 *
 * Both are answered by a name only this score's fragments carry: the rules are
 * re-pointed at it, and the glyphs are renamed under it.
 *
 * @param {string} markup
 * @param {string} scope a class the fragment root carries
 * @param {string[]} ids what the document it came from defines
 */
export function scopeLiftedMarkup(markup, scope, ids) {
    let out = markup.replace(/svg\.Exsurge\b/g, `.${scope}`);

    for (const id of ids) {
        // Split rather than a regex: a glyph name is not escaped for one.
        out = out.split(`id="${id}"`).join(`id="${scope}-${id}"`);
        // Catches xlink:href as well, which is what exsurge actually writes.
        out = out.split(`href="#${id}"`).join(`href="#${scope}-${id}"`);
    }

    return out;
}

/**
 * Everything a lifted line still needs from the document it was cut out of: the
 * glyph symbols its <use> elements point at, and the stylesheet that faces them.
 *
 * Searched through the whole tree rather than among the root's own children,
 * because exsurge buries its <defs> in the same <g> as the music — cut a line out
 * without them and it draws nothing at all. The stylesheet comes back out to the
 * top, where rsvg-convert will read it and where stackSvgs looks for it.
 */
function definitionsOf(root) {
    const styles = Array.from(root.querySelectorAll('style'))
        .map((node) => node.outerHTML)
        .join('');

    const defs = Array.from(root.querySelectorAll('defs'))
        .map((node) => {
            const clone = node.cloneNode(true);
            clone.querySelectorAll('style').forEach((style) => style.remove());

            return clone.outerHTML;
        })
        .join('');

    return styles + defs;
}

/** The height an SVG fragment declares, in its own user units. */
export function svgHeight(markup) {
    const viewBox = markup.match(/viewBox="\s*(-?[\d.]+)[\s,]+(-?[\d.]+)[\s,]+([\d.]+)[\s,]+([\d.]+)/);
    if (viewBox) {
        return parseFloat(viewBox[4]);
    }

    const height = markup.match(/\bheight="([\d.]+)"/);

    return height ? parseFloat(height[1]) : 0;
}
