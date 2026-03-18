<?php

namespace App\Console\Commands;

use App\Models\Music;
use Illuminate\Console\Command;

class MusicInferGenreCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cantores:music-infer-genre
                            {--dry-run : Show what would be updated without making changes}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign genres to music records that have none, inferring them from associated collections';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $query = Music::query()
            ->whereDoesntHave('genres')
            ->whereHas('collections.genres')
            ->with(['collections.genres']);

        $total = $query->count();

        if ($total === 0) {
            $this->info('No music records found that need genre inference.');

            return self::SUCCESS;
        }

        $this->info("Found {$total} music record(s) without genres but with genre-bearing collections.");

        if (! $dryRun && ! $force) {
            if (! $this->confirm('Assign inferred genres to these records?', true)) {
                $this->info('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $updated = 0;

        $ids = $query->pluck('id');

        foreach ($ids->chunk(100) as $chunkIds) {
            $musicItems = Music::query()
                ->whereIn('id', $chunkIds)
                ->with(['collections.genres'])
                ->get();

            foreach ($musicItems as $music) {
                $genreIds = $this->collectGenreIds($music);

                if ($genreIds !== []) {
                    if (! $dryRun) {
                        $music->genres()->sync($genreIds);
                    }
                    $updated++;
                }

                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine();

        $prefix = $dryRun ? '[DRY RUN] Would update' : 'Updated';
        $this->info("{$prefix} {$updated} music record(s) with inferred genres.");

        return self::SUCCESS;
    }

    /**
     * Collect unique genre IDs from a music record's associated collections.
     *
     * @return array<int>
     */
    private function collectGenreIds(Music $music): array
    {
        $genreIds = [];

        foreach ($music->collections as $collection) {
            foreach ($collection->genres as $genre) {
                $genreIds[$genre->id] = $genre->id;
            }
        }

        return array_values($genreIds);
    }
}
