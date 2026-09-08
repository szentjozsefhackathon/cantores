<?php

namespace App\Services;

use App\Models\Booklet;
use App\Models\ScoreFile;
use App\Models\User;

/**
 * Puts the engraving back into a booklet's pages before they are turned into a
 * PDF.
 *
 * The browser sends a vector file's system as a placeholder —
 * `<g data-score-page data-page data-rect/>` — rather than the page SVG itself,
 * so one page's glyph table is not repeated once per system standing on it.
 * This swaps each placeholder for the stored page, clipped to that rectangle.
 *
 * The placeholder is client-supplied, so it is re-authorised exactly as
 * BookletScorePageController does: the file is not superseded, it keeps that
 * page as a vector, and MusicPlanScoreListService still places the score in
 * front of this viewer. An unresolvable placeholder is dropped, not fatal — the
 * rest of the booklet still exports.
 */
class BookletScorePageInliner
{
    public function __construct(
        private readonly MusicPlanScoreListService $scores,
        private readonly ScoreFileStorage $storage,
    ) {}

    /**
     * @param  list<string>  $pages
     * @return list<string>
     */
    public function inline(array $pages, Booklet $booklet, ?User $viewer): array
    {
        $windowed = [];

        return array_map(
            fn (string $page): string => $this->inlinePage($page, $viewer, $windowed),
            array_values($pages),
        );
    }

    /**
     * @param  array<string, string|null>  $windowed  page SVGs already resolved this export, keyed by "fileId:page"
     */
    private function inlinePage(string $page, ?User $viewer, array &$windowed): string
    {
        // The browser's XMLSerializer writes the empty placeholder self-closed
        // (`<g …/>`); a hand-written one may spell out `<g …></g>`. Accept both.
        $result = preg_replace_callback(
            '/<g\b[^>]*\bdata-score-page="[^"]*"[^>]*?(?:\/>|>\s*<\/g>)/i',
            function (array $match) use ($viewer, &$windowed): string {
                $tag = $match[0];

                $fileId = (int) ($this->attribute($tag, 'data-score-page') ?? 0);
                $pageNumber = (int) ($this->attribute($tag, 'data-page') ?? 0);
                $rect = trim((string) ($this->attribute($tag, 'data-rect') ?? ''));

                return $this->window($fileId, $pageNumber, $rect, $viewer, $windowed) ?? '';
            },
            $page,
        );

        return $result ?? $page;
    }

    /**
     * The stored page, trimmed to its children and wrapped in an <svg> that
     * clips to the rectangle. Null when the placeholder cannot be honoured.
     *
     * @param  array<string, string|null>  $windowed
     */
    private function window(int $fileId, int $page, string $rect, ?User $viewer, array &$windowed): ?string
    {
        if ($fileId < 1 || $page < 1) {
            return null;
        }

        if (! preg_match('/^-?[\d.]+ -?[\d.]+ [\d.]+ [\d.]+$/', $rect)) {
            return null;
        }

        $key = $fileId.':'.$page;

        if (! array_key_exists($key, $windowed)) {
            $windowed[$key] = $this->pageSvg($fileId, $page, $viewer);
        }

        $inner = $windowed[$key];

        if ($inner === null) {
            return null;
        }

        [, , $width, $height] = array_map('floatval', explode(' ', $rect));

        return '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" '
            .'viewBox="'.$this->escape($rect).'" width="'.$width.'" height="'.$height.'" '
            .'overflow="hidden" preserveAspectRatio="none">'.$inner.'</svg>';
    }

    /**
     * The children of the stored page SVG, or null when this viewer may not have
     * it or it is not there.
     */
    private function pageSvg(int $fileId, int $page, ?User $viewer): ?string
    {
        $scoreFile = ScoreFile::find($fileId);

        if (! $scoreFile instanceof ScoreFile
            || $scoreFile->isSuperseded()
            || ! $scoreFile->hasVectorPage($page)) {
            return null;
        }

        if ($this->scores->sourcesFor([$scoreFile->score_id], $viewer)->isEmpty()) {
            return null;
        }

        $path = $scoreFile->pageVectorPath($page);

        if (! $this->storage->exists($path)) {
            return null;
        }

        $svg = @gzdecode($this->storage->get($path));

        if ($svg === false || ! str_contains($svg, '<svg')) {
            return null;
        }

        $inner = preg_replace(
            ['/^.*?<svg\b[^>]*>/s', '/<\/svg>\s*$/'],
            '',
            $svg,
        );

        // Cairo names glyph symbols per document, so two files on one exported
        // page would collide. Prefix every id and reference to one.
        $prefix = 'sp'.$fileId.'_'.$page;

        return preg_replace(
            ['/\bid="([^"]+)"/', '/href="#([^"]+)"/', '/url\(#([^)]+)\)/'],
            ['id="'.$prefix.'-$1"', 'href="#'.$prefix.'-$1"', 'url(#'.$prefix.'-$1)'],
            (string) $inner,
        );
    }

    private function attribute(string $tag, string $name): ?string
    {
        return preg_match('/\b'.preg_quote($name, '/').'="([^"]*)"/i', $tag, $match) === 1
            ? $match[1]
            : null;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
