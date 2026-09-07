<?php

namespace App\Console\Commands;

use App\Jobs\RenderScoreFileJob;
use App\Models\ScoreFile;
use Illuminate\Console\Command;

/**
 * Puts the files that were rendered before banding existed back through the
 * renderer, so they can go into a booklet as systems rather than not at all.
 *
 * A re-render rather than a cut on its own, because the strips come off the
 * printing-resolution rasterisation of the PDF and nothing keeps that around —
 * only the reading-resolution pages are stored. The work goes onto the same
 * `musescore` queue as any other render and is therefore rate-limited by the
 * single worker that drains it.
 *
 * `--all` is also how a library rendered before ScoreImageCompressor existed
 * gets its pages, incipits and strips rewritten at a byte a pixel: every
 * artifact is stored again, so the saving lands on files that already have
 * their systems.
 */
class CutScoreFileSystems extends Command
{
    protected $signature = 'scores:cut-systems
                            {--limit=0 : Stop after this many files}
                            {--all : Re-render every ready file, not only the ones with no systems}';

    protected $description = 'Queue rendered score files to be cut into systems for booklets';

    public function handle(): int
    {
        $query = ScoreFile::query()
            ->where('render_status', \App\Enums\ScoreFileRenderStatus::Ready)
            ->whereNull('superseded_at')
            ->orderBy('id');

        if (! $this->option('all')) {
            $query->whereNull('strips');
        }

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $files = $query->get();

        if ($files->isEmpty()) {
            $this->info('Nothing to cut.');

            return self::SUCCESS;
        }

        $this->withProgressBar($files, function (ScoreFile $scoreFile): void {
            RenderScoreFileJob::dispatch($scoreFile);
        });

        $this->newLine(2);
        $this->info("Queued {$files->count()} file(s) on the musescore queue.");

        return self::SUCCESS;
    }
}
