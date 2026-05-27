const SCORE_PREVIEW_PAGE_CLASSES = [
    'score-preview-page',
    'overflow-auto',
    'rounded-lg',
    'border',
    'border-zinc-200',
    'bg-white',
    'dark:border-zinc-700',
];

export function scorePreviewPageClassForRatio(ratio) {
    return [
        ...SCORE_PREVIEW_PAGE_CLASSES,
        ratio === 'paper' || ratio === 'auto' ? 'score-preview-paper' : null,
    ].filter(Boolean).join(' ');
}