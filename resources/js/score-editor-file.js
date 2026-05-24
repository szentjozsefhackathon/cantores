export function scoreFileBasename(title, fallback = 'score') {
    const basename = String(title ?? '')
        .trim()
        .replace(/[^\p{L}\p{N}\s._-]/gu, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^[._-]+|[._-]+$/g, '');

    return basename || fallback;
}

export function scoreSourceFilename(title, extension = 'aretino') {
    const cleanExtension = String(extension ?? '').replace(/^\.+/, '') || 'txt';
    const basename = scoreFileBasename(title);
    const suffix = `.${cleanExtension}`;

    return basename.toLowerCase().endsWith(suffix.toLowerCase())
        ? basename
        : `${basename}${suffix}`;
}

export function downloadTextFile(content, filename, options = {}) {
    const documentRef = options.documentRef ?? document;
    const urlRef = options.urlRef ?? URL;
    const mimeType = options.mimeType ?? 'text/plain;charset=utf-8';
    const blob = new Blob([String(content ?? '')], { type: mimeType });
    const url = urlRef.createObjectURL(blob);
    const anchor = documentRef.createElement('a');

    anchor.download = filename;
    anchor.href = url;
    anchor.style.display = 'none';
    documentRef.body?.appendChild(anchor);

    try {
        anchor.click();
    } finally {
        anchor.remove?.();
        urlRef.revokeObjectURL(url);
    }
}
