export function scoreFileBasename(title, fallback = 'score') {
    const basename = String(title ?? '')
        .trim()
        .replace(/[^\p{L}\p{N}\s._-]/gu, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^[._-]+|[._-]+$/g, '');

    return basename || fallback;
}

export function scoreSourceExtension(format) {
    const extensions = {
        abc: 'abc',
        aretino: 'aretino',
        gabc: 'gabc',
        chordpro: 'cho',
    };

    return extensions[format] ?? 'txt';
}

export function scoreSourceFilename(title, extension = 'aretino') {
    const cleanExtension = String(extension ?? '').replace(/^\.+/, '') || 'txt';
    const basename = scoreFileBasename(title);
    const suffix = `.${cleanExtension}`;

    return basename.toLowerCase().endsWith(suffix.toLowerCase())
        ? basename
        : `${basename}${suffix}`;
}

export function openTextFile(accept, options = {}) {
    const documentRef = options.documentRef ?? document;

    return new Promise((resolve) => {
        const input = documentRef.createElement('input');
        let settled = false;

        const settle = (result) => {
            if (settled) { return; }
            settled = true;
            input.remove?.();
            resolve(result);
        };

        input.type = 'file';
        input.accept = accept;
        input.style.display = 'none';
        documentRef.body?.appendChild(input);

        input.addEventListener('change', () => {
            const file = input.files?.[0];
            if (!file) {
                settle(null);

                return;
            }
            file.text()
                .then((content) => settle({ name: file.name, content }))
                .catch(() => settle(null));
        });
        input.addEventListener('cancel', () => settle(null));

        input.click();
    });
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
