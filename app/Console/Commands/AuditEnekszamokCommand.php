<?php

namespace App\Console\Commands;

use App\Concerns\ReadsEnekszamokInput;
use App\Services\EnekszamokImporter;
use App\Support\EnekszamokDecisions;
use Illuminate\Console\Command;

class AuditEnekszamokCommand extends Command
{
    use ReadsEnekszamokInput;

    protected $signature = 'cantores:audit-enekszamok
                            {--path= : Path to songs.csv (defaults to import/enekszamok/songs.csv)}
                            {--decisions= : Path to decisions.csv (defaults to import/enekszamok/decisions.csv)}
                            {--threshold=0.55 : Title similarity below which a match needs a manual decision}';

    protected $description = 'Detect énekszámok rows that would merge into a too-different song and write them to decisions.csv for review.';

    public function handle(): int
    {
        $path = $this->option('path') ?: $this->defaultSongsPath();
        if (! file_exists($path)) {
            $this->error("songs.csv not found at: {$path}");

            return self::FAILURE;
        }

        $collections = $this->resolveCollections();
        if ($collections === null) {
            return self::FAILURE;
        }

        $decisionsPath = $this->option('decisions') ?: $this->defaultDecisionsPath();
        $decisions = EnekszamokDecisions::read($decisionsPath);

        $rows = $this->readCsv($path);
        $this->info('Loaded '.count($rows).' songs from '.basename($path).'.');

        $importer = new EnekszamokImporter((float) $this->option('threshold'));
        $result = $importer->audit($rows, $collections, $decisions);

        $conflicts = $result['conflicts'];
        EnekszamokDecisions::write($decisionsPath, $conflicts);

        $total = count($conflicts);
        $undecided = collect($conflicts)->whereNull('decision')->count();
        $decided = $total - $undecided;

        $this->newLine();
        $this->info("Conflicts: {$total} ({$decided} decided, {$undecided} undecided).");
        $this->line('Decisions written to: '.$decisionsPath);

        if ($undecided > 0) {
            $this->warn("Edit the 'decision' column (m = merge, d = divide) for the undecided rows, then run the import.");
        } else {
            $this->info('All conflicts decided — the import can run.');
        }

        return self::SUCCESS;
    }
}
