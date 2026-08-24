export function removeEditorOnlySvgMarkup(svgEl) {
    if (!svgEl) {
        return svgEl;
    }

    [
        'aretino-cursor-rect',
        'aretino-cursor-bg',
        'aretino-cursor-line',
        'aretino-cursor-modbox',
    ].forEach(className => {
        svgEl.querySelectorAll?.(`.${className}`).forEach(el => el.remove());
    });

    const activeElements = [];
    if (svgEl.classList?.contains('aretino-active')) {
        activeElements.push(svgEl);
    }
    svgEl.querySelectorAll?.('.aretino-active').forEach(el => activeElements.push(el));

    activeElements.forEach(el => {
        el.classList.remove('aretino-active');

        if (el.getAttribute?.('class') === '') {
            el.removeAttribute?.('class');
        }
    });

    return svgEl;
}

// Millimetres per CSS pixel: scores are laid out in user units where
// 1 unit = 1 px at 96 dpi.
const MM_PER_PX = 25.4 / 96;

/**
 * Restate an exported SVG's intrinsic size as the physical size of its viewBox.
 *
 * The renderer emits width/height as the layout size multiplied by the preview
 * zoom, which is a screen magnification: the score itself is engraved at a
 * physical size (a 170 mm staff width becomes a 643 unit viewBox). Left as is,
 * a downloaded score opens and prints at zoom times its intended size. Writing
 * the viewBox size back in millimetres drops the zoom and states the size in a
 * unit every consumer reads the same way; the viewBox is untouched, so nothing
 * about the drawing changes. Documents without a usable viewBox are left alone.
 */
export function applyPhysicalSvgSize(svgEl) {
    const viewBox = svgEl?.getAttribute?.('viewBox');
    if (!viewBox) {
        return svgEl;
    }

    const [, , width, height] = viewBox.trim().split(/[\s,]+/).map(Number);
    if (!(width > 0) || !(height > 0)) {
        return svgEl;
    }

    svgEl.setAttribute('width', millimetres(width));
    svgEl.setAttribute('height', millimetres(height));
    // An inline width/height (set on screen to fit the preview frame) would
    // override the attributes wherever CSS is applied.
    svgEl.style?.removeProperty('width');
    svgEl.style?.removeProperty('height');

    return svgEl;
}

function millimetres(pixels) {
    return `${Number((pixels * MM_PER_PX).toFixed(4))}mm`;
}
