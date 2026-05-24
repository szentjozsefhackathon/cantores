function browserViewport() {
    return typeof window === 'undefined' ? null : window;
}

export async function renderCurrentPreview(component, viewport = browserViewport()) {
    const scrollY = viewport?.scrollY ?? 0;

    try {
        if (component.$wire.format === 'abc') {
            await component.renderAbcPreview();
        } else if (component.$wire.format === 'chordpro') {
            await component.renderChordproPreview();
        } else if (component.$wire.format === 'aretino') {
            await component.renderAretinoPreview();
        } else {
            await component.renderGabcPreview();
        }
    } finally {
        viewport?.scrollTo?.(0, scrollY);
    }
}
