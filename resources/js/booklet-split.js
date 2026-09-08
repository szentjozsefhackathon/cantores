/**
 * The movable boundary between the booklet's plan and its preview.
 *
 * The split is kept as the share of the row the plan column takes, because that
 * is what survives a window resize: a pixel width chosen on a wide screen leaves
 * the preview a sliver on a narrow one.
 */

export const SPLIT_MIN = 20;
export const SPLIT_MAX = 80;
export const SPLIT_DEFAULT = 60;

export function clampSplitPercent(value) {
    if (!Number.isFinite(value)) { return SPLIT_DEFAULT; }

    return Math.min(SPLIT_MAX, Math.max(SPLIT_MIN, value));
}

/**
 * Where the pointer is, as a share of the row the handle divides. Null when the
 * row has no width yet — a division nobody can answer.
 */
export function splitPercentAt(clientX, box) {
    if (!box || !box.width) { return null; }

    return clampSplitPercent(((clientX - box.left) / box.width) * 100);
}

/**
 * Follows one drag of the handle, calling back with each new share and once more
 * when the drag ends.
 *
 * The pointer is captured for the length of the drag: without it the moves land
 * on whatever the pointer happens to be over — an SVG sheet, a text field — and
 * the handle stops hearing about them halfway across the row.
 *
 * @param {{onMove: (percent: number) => void, onEnd?: () => void}} handlers
 * @return {() => void} ends the drag early, listeners and all
 */
export function beginSplitDrag(handle, row, event, handlers) {
    handle.setPointerCapture?.(event.pointerId);

    const move = (moveEvent) => {
        const percent = splitPercentAt(moveEvent.clientX, row.getBoundingClientRect());

        if (percent !== null) { handlers.onMove(percent); }
    };

    const stop = () => {
        handle.removeEventListener('pointermove', move);
        handle.removeEventListener('pointerup', stop);
        handle.removeEventListener('pointercancel', stop);
        handlers.onEnd?.();
    };

    handle.addEventListener('pointermove', move);
    handle.addEventListener('pointerup', stop);
    handle.addEventListener('pointercancel', stop);

    return stop;
}
