<?php

namespace App\Jobs;

use App\Enums\ScoreFileRenderStatus;
use App\Models\ScoreFile;
use App\Services\MuseScoreRenderer;
use App\Services\PdfPageRasterizer;
use App\Services\ScoreFileIncipitCropper;
use App\Services\ScoreFileStorage;
use App\Services\ScoreImageCompressor;
use App\Services\ScorePageBander;
use App\Services\ScoreStripCutter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Engraves an uploaded score file and stores the artifacts a reader needs:
 * the PDF, one PNG per page, the incipit crop, and the system strips a booklet
 * flows.
 *
 * Runs on its own `musescore` queue so a slow or hostile file cannot starve
 * the default queue, and so the worker can live in the renderer image — the
 * app image has neither MuseScore nor poppler.
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
    ): void {
        // Nothing here streams: the whole file and its ciphertext are resident
        // at once, and the rasterised pages after them. The 25 MB upload cap
        // bounds the first two; the pages are far smaller.
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

            $pages = $rasterizer->rasterize($pdf);
            foreach ($pages as $index => $page) {
                $storage->put($this->scoreFile->pagePath($index + 1), $compressor->compress($page));
            }

            $storage->put(
                $this->scoreFile->thumbPath(),
                $compressor->compress($cropper->crop($rasterizer->rasterizePage($pdf, 1, self::INCIPIT_DPI))),
            );

            $strips = $this->cutStrips($pdf, $pages, $storage, $rasterizer, $bander, $cutter, $compressor);

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
     * Cut every page into its systems, and store them.
     *
     * Two passes over the document, because the two halves want different
     * resolutions. The bands are found on the reading-resolution pages that were
     * rasterised anyway — a system gap is a system gap at 150 dpi — and the
     * cutting happens at printing resolution, one page in memory at a time. What
     * travels between the passes is fractions of a page, which is why that works.
     *
     * The horizontal window is unioned across the whole document before anything
     * is cut, so every strip of a file comes out the same width and the systems
     * still line up once the booklet has scaled them to its own page.
     *
     * A failure here is logged and swallowed. Strips are what a booklet wants;
     * the reading view needs only the pages and the thumbnail, and a file that
     * cannot be banded should still be readable.
     *
     * @param  list<string>  $pages  the reading-resolution renders, in order
     * @return list<array{page: int, index: int, width: int, height: int}>
     */
    private function cutStrips(
        string $pdf,
        array $pages,
        ScoreFileStorage $storage,
        PdfPageRasterizer $rasterizer,
        ScorePageBander $bander,
        ScoreStripCutter $cutter,
        ScoreImageCompressor $compressor,
    ): array {
        try {
            $analyses = array_map(fn (string $page): array => $bander->analyse($page), $pages);

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
