const SVG_NS = 'http://www.w3.org/2000/svg';

/** One highlight region per entry on each page, including all its staff rows. */
export function entryRegions(page) {
    const regions = new Map();

    for (const { block, y } of page.items) {
        if (block.entryId == null) { continue; }

        const region = regions.get(block.entryId) ?? { entryId: block.entryId, top: y, bottom: y };
        region.bottom = Math.max(region.bottom, y + block.height);
        regions.set(block.entryId, region);
    }

    return [...regions.values()];
}

export function appendEntryRegions(svg, page, geometry) {
    for (const region of entryRegions(page)) {
        const rect = svg.ownerDocument.createElementNS(SVG_NS, 'rect');
        const attributes = {
            'data-booklet-entry': region.entryId,
            x: geometry.marginPx,
            y: geometry.marginPx + region.top,
            width: geometry.contentWidthPx,
            height: region.bottom - region.top,
            fill: 'none',
            'pointer-events': 'none',
        };

        for (const [name, value] of Object.entries(attributes)) {
            rect.setAttribute(name, String(value));
        }

        svg.appendChild(rect);
    }
}

/** Only preview clones receive color; the original pages remain ready for export. */
export function highlightEntry(container, entryId, scroll = false) {
    if (!container) { return; }

    let first = null;

    container.querySelectorAll('[data-booklet-entry]').forEach((region) => {
        const active = entryId != null && region.getAttribute('data-booklet-entry') === String(entryId);
        region.setAttribute('fill', 'none');
        region.setAttribute('stroke', active ? '#3b82f6' : 'none');
        region.setAttribute('stroke-width', '2');
        region.setAttribute('vector-effect', 'non-scaling-stroke');
        if (active && !first) { first = region; }
    });

    const pane = container.closest('[data-booklet-pane="pages"]');
    if (!scroll || !first || !pane || pane.scrollHeight <= pane.clientHeight) { return; }

    const bounds = first.getBoundingClientRect();
    const viewport = pane.getBoundingClientRect();
    if (bounds.top >= viewport.top && bounds.bottom <= viewport.bottom) { return; }

    pane.scrollTo({ top: pane.scrollTop + bounds.top - viewport.top - 16, behavior: 'instant' });
}
