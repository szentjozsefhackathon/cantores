/**
 * Stacking several SVG documents into one.
 *
 * abc2svg emits a separate <svg> per music line, Aretino's splitRowSVGs() one
 * per staff row, and a booklet page is a handful of such fragments from several
 * scores placed down a sheet — so "combine these documents into one, keeping
 * each fragment's own coordinate system" is the same operation in three places.
 * It was written twice already (score-editor.js buildMergedSvg and
 * mergeAbcSvgsToElement, near-identical); this is that code, once.
 *
 * The wrinkle it exists to handle is that a fragment carries its drawing in
 * <defs> and <style> elements which mean nothing once nested inside a <g>: ids
 * collide between fragments and some renderers — rsvg-convert among them — will
 * not process a @font-face buried below the root. Both are therefore hoisted to
 * the wrapper, with ids de-duplicated on a first-one-wins basis.
 *
 * The geometry half is kept free of the DOM so it can be tested directly.
 */

const SVG_NS = 'http://www.w3.org/2000/svg';
const XMLNS_NS = 'http://www.w3.org/2000/xmlns/';

/** What a fragment without a usable viewBox is assumed to be. */
const FALLBACK_BOX = { x: 0, y: 0, w: 1920, h: 200 };

/**
 * The viewBox of one fragment, as a box. Fragments that declare none are
 * assumed to be a full-width line, which is what abc2svg produces.
 */
export function viewBoxOf(svg, fallback = FALLBACK_BOX) {
    const attr = svg?.getAttribute?.('viewBox');
    if (!attr) { return { ...fallback }; }

    const [x, y, w, h] = attr.trim().split(/[\s,]+/).map(Number);
    if (![x, y, w, h].every(Number.isFinite)) { return { ...fallback }; }

    return { x, y, w, h };
}

/**
 * Lay boxes out one under the next, each at its natural size. The default when
 * no explicit placement is given, and what both editor call sites want.
 *
 * @param {Array<{w: number, h: number}>} boxes
 * @returns {Array<{x: number, y: number, scale: number}>}
 */
export function stackPlacements(boxes) {
    let y = 0;

    return boxes.map((box) => {
        const placement = { x: 0, y, scale: 1 };
        y += box.h;

        return placement;
    });
}

/**
 * The size of the region the placed boxes occupy.
 */
export function stackedExtent(boxes, placements) {
    let width = 0;
    let height = 0;

    boxes.forEach((box, i) => {
        const { x, y, scale } = placements[i];
        width = Math.max(width, x + box.w * scale);
        height = Math.max(height, y + box.h * scale);
    });

    return { width, height };
}

/**
 * The transform putting a fragment's own top-left corner at its placement.
 *
 * Read right to left: shift the fragment's viewBox origin to zero, scale, then
 * move to where it belongs. The scale factor is omitted when it is 1, which is
 * every case outside a booklet's scale-to-fit.
 */
export function placementTransform(box, placement) {
    const { x, y, scale } = placement;
    const dx = x - box.x * scale;
    const dy = y - box.y * scale;
    const move = `translate(${dx} ${dy})`;

    return scale === 1 ? move : `${move} scale(${scale})`;
}

/**
 * Combine SVG elements into a single wrapper <svg>.
 *
 * @param {Array<SVGElement>} svgs
 * @param {object} [options]
 * @param {Array<{x: number, y: number, scale: number}>} [options.placements]
 *        Where each fragment goes. Defaults to stacking them in order.
 * @param {string} [options.extraStyle] CSS prepended to the hoisted styles.
 * @param {boolean} [options.intrinsicSize] Also write width/height attributes.
 * @param {{x: number, y: number, w: number, h: number}} [options.viewBox]
 *        An explicit wrapper viewBox — a booklet page states its paper size
 *        rather than shrink-wrapping its contents.
 * @returns {{svg: SVGElement, width: number, height: number}}
 */
export function stackSvgs(svgs, options = {}) {
    const { extraStyle = '', intrinsicSize = false, viewBox = null } = options;

    const boxes = svgs.map((svg) => viewBoxOf(svg));
    const placements = options.placements ?? stackPlacements(boxes);
    const extent = stackedExtent(boxes, placements);

    const width = viewBox ? viewBox.w : extent.width;
    const height = viewBox ? viewBox.h : extent.height;
    const originX = viewBox ? viewBox.x : 0;
    const originY = viewBox ? viewBox.y : 0;

    const wrapper = document.createElementNS(SVG_NS, 'svg');
    wrapper.setAttribute('xmlns', SVG_NS);
    wrapper.setAttributeNS(XMLNS_NS, 'xmlns:xlink', 'http://www.w3.org/1999/xlink');
    wrapper.setAttribute('viewBox', `${originX} ${originY} ${width} ${height}`);
    if (intrinsicSize) {
        wrapper.setAttribute('width', String(width));
        wrapper.setAttribute('height', String(height));
    }
    wrapper.setAttribute('color', '#000');
    wrapper.setAttribute('fill', 'currentColor');

    let combinedStyle = extraStyle;
    const mergedDefs = document.createElementNS(SVG_NS, 'defs');
    const seenIds = new Set();

    svgs.forEach((svg, i) => {
        const clone = svg.cloneNode(true);
        const g = document.createElementNS(SVG_NS, 'g');
        g.setAttribute('transform', placementTransform(boxes[i], placements[i]));
        ['class', 'fill', 'stroke-width', 'color'].forEach((attr) => {
            const value = clone.getAttribute(attr);
            if (value) { g.setAttribute(attr, value); }
        });

        Array.from(clone.childNodes).forEach((child) => {
            const tag = child.nodeName.toLowerCase();
            if (tag === 'style') {
                combinedStyle += child.textContent + '\n';
            } else if (tag === 'defs') {
                Array.from(child.childNodes).forEach((def) => {
                    if (def.nodeType !== 1) { return; }
                    const id = def.getAttribute && def.getAttribute('id');
                    if (id) {
                        if (seenIds.has(id)) { return; }
                        seenIds.add(id);
                    }
                    mergedDefs.appendChild(def.cloneNode(true));
                });
            } else {
                g.appendChild(child);
            }
        });

        wrapper.appendChild(g);
    });

    const styleEl = document.createElementNS(SVG_NS, 'style');
    styleEl.textContent = combinedStyle;
    wrapper.insertBefore(styleEl, wrapper.firstChild);
    if (mergedDefs.childNodes.length) {
        wrapper.insertBefore(mergedDefs, wrapper.firstChild);
    }

    return { svg: wrapper, width, height };
}
