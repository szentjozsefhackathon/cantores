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
