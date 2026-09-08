<?php

namespace App\Jobs;

use App\Enums\ScoreFileRenderStatus;
use App\Models\ScoreFile;
use App\Services\MuseScoreRenderer;
use App\Services\PdfPageRasterizer;
use App\Services\PdfPageVectorizer;
use App\Services\ScoreFileIncipitCropper;
use App\Services\ScoreFileStorage;
use App\Services\ScoreImageCompressor;
use App\Services\ScorePageBander;
use App\Services\ScoreStripCutter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Engraves an uploaded score file and stores the artifacts a reader needs.
 *
 * Every file gets the engraved PDF and the incipit crop. Its pages are then
 * kept one of two ways, chosen per file by measurement:
 *
 * - **vector** — one gzipped `pdftocairo` SVG per page, and a booklet strip is a
 *   `viewBox` window onto it. Resolution-independent, and its size scales with
 *   engraved content rather than with dpi times page area. This is what an
 *   ordinary MuseScore export gets.
 * - **raster** — one page PNG per page plus one 300 dpi PNG per system, exactly
 *   as before. The fallback for scans, whose image-only pages have nothing to
 *   vectorise, and for anything `pdftocairo` cannot handle.
 *
 * Runs on its own `musescore` queue so a slow or hostile file cannot starve the
 * default queue, and so the worker can live in the renderer image — the app
 * image has neither MuseScore nor poppler.
 */
class RenderScoreFileJob implements ShouldQueue
{
    use Queueable;

    /** The dpi page 1 is rasterised at for the incipit, so the crop downscales. */
    private const INCIPIT_DPI = 200;

    public int $timeout = 600;

    public int $tries = 2;

    public function __construct(
        public readonly ScoreFile $scoreFile,
    ) {
        $this->onQueue('musescore');
    }

    public function handle(
        ScoreFileStorage $storage,
        MuseScoreRenderer $renderer,
        PdfPageRasterizer $rasterizer,
        ScoreFileIncipitCropper $cropper,
        ScorePageBander $bander,
        ScoreStripCutter $cutter,
        ScoreImageCompressor $compressor,
        PdfPageVectorizer $vectorizer,
    ): void {
        // Nothing here streams. On the raster path the whole file, its
        // ciphertext and the rasterised pages are resident at once; on the
        // vector path the pages are discarded after banding, but one page SVG
        // and the PDF sit beside them. The 25 MB upload cap bounds the file.
        ini_set('memory_limit', '512M');

        if (! $this->scoreFile->isRenderable()) {
            $this->scoreFile->update([
                'render_status' => ScoreFileRenderStatus::Unsupported,
                'render_error' => null,
            ]);

            return;
        }

        $this->scoreFile->update([
            'render_status' => ScoreFileRenderStatus::Processing,
            'render_error' => null,
        ]);

        try {
            $source = $storage->get($this->scoreFile->path);

            // An uploaded PDF is already engraved: it only needs cutting into
            // page images, and it is its own render, so it is not stored twice.
            if ($this->scoreFile->isPrerendered()) {
                $pdf = $source;
            } else {
                $pdf = $renderer->render($source, $this->scoreFile->extension());

                $storage->put($this->scoreFile->renderPath(), $pdf);
            }

            // Reading-resolution pages. Always produced — they are what the
            // bander analyses and what the vector form is measured against — but
            // only stored on the raster path.
            $pages = $rasterizer->rasterize($pdf);

            $storage->put(
                $this->scoreFile->thumbPath(),
                $compressor->compress($cropper->crop($rasterizer->rasterizePage($pdf, 1, self::INCIPIT_DPI))),
            );

            $strips = $this->renderPages(
                $pdf, $pages, $storage, $rasterizer, $bander, $cutter, $compressor, $vectorizer,
            );

            $isVector = array_filter($strips, fn (array $strip): bool => isset($strip['rect'])) !== [];

            // Drop whichever representation this render did not write, so a
            // re-render onto the other one does not leave the first behind.
            $storage->deleteMatching(
                $this->scoreFile,
                $isVector ? ['page-*.png', 'strip-*.png'] : ['page-*.svgz'],
            );

            $this->scoreFile->update([
                'render_status' => ScoreFileRenderStatus::Ready,
                'render_error' => null,
                'has_thumbnail' => true,
                'page_count' => count($pages),
                'strips' => $strips,
                'rendered_at' => now(),
            ]);

            Log::info('Score file rendered', [
                'score_file_id' => $this->scoreFile->id,
                'pages' => count($pages),
                'strips' => count($strips),
                'vector' => $isVector,
            ]);
        } catch (\Throwable $e) {
            Log::error('Score file rendering failed', [
                'score_file_id' => $this->scoreFile->id,
                'error' => $e->getMessage(),
            ]);

            $this->markAsFailed($e->getMessage());

            throw $e;
        }
    }

    /**
     * Store this file's pages, and return the systems index for a booklet.
     *
     * The pages are banded once, here. Where they carry systems and the vector
     * form of the whole file comes out smaller than its page PNGs, the pages are
     * stored as gzipped SVGs and each system is recorded as a rectangle onto its
     * page. Otherwise the page PNGs are stored and the systems are cut at
     * printing resolution, exactly as before.
     *
     * A banding failure is logged and swallowed: strips are what a booklet
     * wants, and a file that cannot be banded should still be readable. A
     * vectorisation failure falls back to the raster path rather than failing
     * the render.
     *
     * @param  list<string>  $pages  the reading-resolution renders, in order
     * @return list<array{page: int, index: int, width: int|float, height: int|float, rect?: array{float, float, float, float}}>
     */
    private function renderPages(
        string $pdf,
        array $pages,
        ScoreFileStorage $storage,
        PdfPageRasterizer $rasterizer,
        ScorePageBander $bander,
        ScoreStripCutter $cutter,
        ScoreImageCompressor $compressor,
        PdfPageVectorizer $vectorizer,
    ): array {
        $analyses = null;

        try {
            $analyses = array_map(fn (string $page): array => $bander->analyse($page), $pages);
        } catch (\Throwable $e) {
            Log::warning('Score file could not be banded', [
                'score_file_id' => $this->scoreFile->id,
                'error' => $e->getMessage(),
            ]);
        }

        $hasBands = $analyses !== null && array_filter(
            $analyses,
            fn (array $analysis): bool => $analysis['bands'] !== []
        ) !== [];

        if ($hasBands) {
            $vectorStrips = $this->vectorPages($pdf, $pages, $analyses, $storage, $compressor, $vectorizer);

            if ($vectorStrips !== null) {
                return $vectorStrips;
            }
        }

        // Raster path: store every page image.
        foreach ($pages as $index => $page) {
            $storage->put($this->scoreFile->pagePath($index + 1), $compressor->compress($page));
        }

        if (! $hasBands) {
            return [];
        }

        return $this->cutRasterStrips($pdf, $analyses, $storage, $rasterizer, $cutter, $compressor);
    }

    /**
     * Keep the file's pages as vector SVGs, if that is worth doing.
     *
     * Returns the systems index once every page is stored as `page-{n}.svgz`, or
     * null to say the raster path should be taken — because `pdftocairo` failed,
     * or because the gzipped SVGs come out no smaller than the page PNGs, which
     * is what an image-only scan does.
     *
     * @param  list<string>  $pages
     * @param  list<array{width: int, height: int, left: float, right: float, bands: list<array{top: float, bottom: float}>}>  $analyses
     * @return list<array{page: int, index: int, width: float, height: float, rect: array{float, float, float, float}}>|null
     */
    private function vectorPages(
        string $pdf,
        array $pages,
        array $analyses,
        ScoreFileStorage $storage,
        ScoreImageCompressor $compressor,
        PdfPageVectorizer $vectorizer,
    ): ?array {
        try {
            $svgz = [];
            foreach (array_keys($pages) as $index) {
                $svgz[$index] = gzencode($vectorizer->vectorizePage($pdf, $index + 1), 9);
            }
        } catch (\Throwable $e) {
            Log::warning('Score file could not be vectorised, using the raster path', [
                'score_file_id' => $this->scoreFile->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $vectorBytes = array_sum(array_map('strlen', $svgz));
        $rasterBytes = array_sum(array_map(
            fn (string $page): int => strlen($compressor->compress($page)),
            $pages,
        ));

        if ($vectorBytes >= $rasterBytes) {
            return null;
        }

        $wanted = array_values(array_filter(
            $analyses,
            fn (array $analysis): bool => $analysis['bands'] !== []
        ));

        $window = [
            'left' => min(array_column($wanted, 'left')),
            'right' => max(array_column($wanted, 'right')),
        ];

        foreach (array_keys($pages) as $index) {
            $storage->put($this->scoreFile->pageVectorPath($index + 1), $svgz[$index]);
        }

        $strips = [];

        foreach ($analyses as $index => $analysis) {
            if ($analysis['bands'] === []) {
                continue;
            }

            $page = $index + 1;

            // The bander measured fractions on the raster; poppler applies
            // /CropBox and /Rotate identically in pdftoppm and pdftocairo, so
            // they land on the same place in the SVG. Expressed in the page's
            // own units (points) so a strip is a plain viewBox window.
            $pageWidthPt = $analysis['width'] / PdfPageRasterizer::VIEW_DPI * 72;
            $pageHeightPt = $analysis['height'] / PdfPageRasterizer::VIEW_DPI * 72;

            $left = $window['left'] * $pageWidthPt;
            $width = ($window['right'] - $window['left']) * $pageWidthPt;

            foreach ($analysis['bands'] as $offset => $band) {
                $top = $band['top'] * $pageHeightPt;
                $height = ($band['bottom'] - $band['top']) * $pageHeightPt;

                $strips[] = [
                    'page' => $page,
                    'index' => $offset + 1,
                    'width' => round($width, 2),
                    'height' => round($height, 2),
                    'rect' => [
                        round($left, 2),
                        round($top, 2),
                        round($width, 2),
                        round($height, 2),
                    ],
                ];
            }
        }

        return $strips;
    }

    /**
     * Cut every banded page into its systems at printing resolution, and store
     * them. The raster fallback, unchanged in what it produces.
     *
     * The horizontal window is unioned across the whole document before anything
     * is cut, so every strip of a file comes out the same width and the systems
     * still line up once the booklet has scaled them to its own page.
     *
     * @param  list<array{width: int, height: int, left: float, right: float, bands: list<array{top: float, bottom: float}>}>  $analyses
     * @return list<array{page: int, index: int, width: int, height: int}>
     */
    private function cutRasterStrips(
        string $pdf,
        array $analyses,
        ScoreFileStorage $storage,
        PdfPageRasterizer $rasterizer,
        ScoreStripCutter $cutter,
        ScoreImageCompressor $compressor,
    ): array {
        try {
            $wanted = array_values(array_filter(
                $analyses,
                fn (array $analysis): bool => $analysis['bands'] !== []
            ));

            if ($wanted === []) {
                return [];
            }

            $window = [
                'left' => min(array_column($wanted, 'left')),
                'right' => max(array_column($wanted, 'right')),
            ];

            $strips = [];

            foreach ($analyses as $index => $analysis) {
                if ($analysis['bands'] === []) {
                    continue;
                }

                $page = $index + 1;
                $dense = $rasterizer->rasterizePage($pdf, $page, ScoreFile::STRIP_DPI);

                foreach ($cutter->cut($dense, $window, $analysis['bands']) as $offset => $strip) {
                    $number = $offset + 1;
                    $storage->put($this->scoreFile->stripPath($page, $number), $compressor->compress($strip['png']));

                    $strips[] = [
                        'page' => $page,
                        'index' => $number,
                        'width' => $strip['width'],
                        'height' => $strip['height'],
                    ];
                }
            }

            return $strips;
        } catch (\Throwable $e) {
            Log::warning('Score file could not be cut into systems', [
                'score_file_id' => $this->scoreFile->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $this->markAsFailed(
            $exception?->getMessage() ?? 'A queue worker váratlanul leállt a kotta feldolgozása közben.'
        );
    }

    /**
     * Record the failure without overwriting a status the job already reached —
     * a retry that succeeded must not be undone by the failed() hook firing for
     * the attempt before it.
     */
    private function markAsFailed(string $message): void
    {
        $scoreFile = $this->scoreFile->fresh();

        if (! $scoreFile instanceof ScoreFile || $scoreFile->render_status === ScoreFileRenderStatus::Ready) {
            return;
        }

        $scoreFile->update([
            'render_status' => ScoreFileRenderStatus::Failed,
            'render_error' => mb_substr($message, 0, 2000),
        ]);
    }
}
