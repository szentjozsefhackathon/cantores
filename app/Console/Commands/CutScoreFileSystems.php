<?php

namespace App\Console\Commands;

use App\Jobs\RenderScoreFileJob;
use App\Models\ScoreFile;
use Illuminate\Console\Command;

/**
 * Puts the files that were rendered before banding existed back through the
 * renderer, so they can go into a booklet as systems rather than not at all.
 *
 * A re-render rather than a cut on its own, because a system is a window onto
 * the page and the page has to be re-engraved to vector form (or, for a scan,
 * re-rasterised at printing resolution) — nothing keeps either around. The work
 * goes onto the same `musescore` queue as any other render and is therefore
 * rate-limited by the single worker that drains it.
 *
 * `--all` is also how the library is converted from stored strip PNGs to vector
 * pages: every artifact is stored again, and an engraving's page PNGs and 300
 * dpi strips are replaced by one gzipped SVG per page.
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
