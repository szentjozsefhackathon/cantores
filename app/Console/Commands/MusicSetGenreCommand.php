<?php

namespace App\Console\Commands;

use App\Models\Genre;
use App\Models\Music;
use Illuminate\Console\Command;

class MusicSetGenreCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cantores:music-set-genre
                            {genre : Genre name or ID to assign to music without any genre}
                            {--dry-run : List the music records that would be updated without making changes}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign a genre to every music record that currently has no genre';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $genre = $this->resolveGenre($this->argument('genre'));
        if ($genre === null) {
            $this->error("Genre not found: {$this->argument('genre')}");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $query = Music::query()
            ->whereDoesntHave('genres')
            ->orderBy('id');

        $total = $query->count();

        if ($total === 0) {
            $this->info('No music records without a genre were found.');

            return self::SUCCESS;
        }

        $this->info("Found {$total} music record(s) without a genre.");

        if ($dryRun) {
            $this->table(
                ['ID', 'Title'],
                $query->get(['id', 'title'])->map(fn (Music $music): array => [$music->id, $music->title])->all(),
            );
            $this->info("[DRY RUN] Would assign genre '{$genre->name}' to {$total} music record(s).");

            return self::SUCCESS;
        }

        if (! $force && ! $this->confirm("Assign genre '{$genre->name}' to these {$total} record(s)?", true)) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $updated = 0;

        foreach ($query->lazyById(100) as $music) {
            $music->genres()->syncWithoutDetaching([$genre->id]);
            $updated++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("Assigned genre '{$genre->name}' to {$updated} music record(s).");

        return self::SUCCESS;
    }

    /**
     * Resolve a genre from a numeric ID or a name.
     */
    private function resolveGenre(string $identifier): ?Genre
    {
        if (ctype_digit($identifier)) {
            return Genre::find((int) $identifier);
        }

        return Genre::whereName($identifier)->first();
    }
}
