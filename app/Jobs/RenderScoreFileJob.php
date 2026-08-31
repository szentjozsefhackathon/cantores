<?php

namespace App\Jobs;

use App\Enums\ScoreFileRenderStatus;
use App\Models\ScoreFile;
use App\Services\MuseScoreRenderer;
use App\Services\PdfPageRasterizer;
use App\Services\ScoreFileIncipitCropper;
use App\Services\ScoreFileStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Engraves an uploaded score file and stores the artifacts a reader needs:
 * the PDF, one PNG per page, and the incipit crop.
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
    ): void {
        // Crypt is not streaming and base64 inflates by a third, so the whole
        // file plus its ciphertext are resident at once. The 25 MB upload cap
        // bounds that; the page images that follow are far smaller.
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
                $storage->put($this->scoreFile->pagePath($index + 1), $page);
            }

            $storage->put(
                $this->scoreFile->thumbPath(),
                $cropper->crop($rasterizer->rasterizePage($pdf, 1, self::INCIPIT_DPI)),
            );

            $this->scoreFile->update([
                'render_status' => ScoreFileRenderStatus::Ready,
                'render_error' => null,
                'has_thumbnail' => true,
                'page_count' => count($pages),
                'rendered_at' => now(),
            ]);

            Log::info('Score file rendered', [
                'score_file_id' => $this->scoreFile->id,
                'pages' => count($pages),
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
