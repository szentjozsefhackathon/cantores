<?php

namespace App\Console\Commands;

use App\Concerns\ReadsEnekszamokInput;
use App\Models\User;
use App\Services\EnekszamokImporter;
use App\Support\EnekszamokDecisions;
use Illuminate\Console\Command;

class ImportEnekszamokCommand extends Command
{
    use ReadsEnekszamokInput;

    protected $signature = 'cantores:import-enekszamok
                            {--user= : User ID or email to own imported records (required)}
                            {--path= : Path to songs.csv (defaults to import/enekszamok/songs.csv)}
                            {--decisions= : Path to decisions.csv (defaults to import/enekszamok/decisions.csv)}
                            {--threshold=0.55 : Title similarity below which a match needs a manual decision}';

    protected $description = 'Import songs, author links and songbook numbers from the énekszámok songs.csv.';

    public function handle(): int
    {
        $user = $this->resolveUser();
        if (! $user) {
            return self::FAILURE;
        }

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

        $preflight = $importer->audit($rows, $collections, $decisions);
        $undecided = collect($preflight['conflicts'])->whereNull('decision')->count();
        if ($undecided > 0) {
            $this->error("{$undecided} title conflict(s) are undecided — nothing was imported.");
            $this->line("Run 'cantores:audit-enekszamok' and set the 'decision' column (m = merge, d = divide) in:");
            $this->line('  '.$decisionsPath);

            return self::FAILURE;
        }

        $result = $importer->import($rows, $collections, $decisions, $user);

        $this->info("Done. Created: {$result['created']}, Updated: {$result['updated']}, Duplicates: {$result['duplicates']}.");

        return self::SUCCESS;
    }

    private function resolveUser(): ?User
    {
        $userOption = $this->option('user');
        if ($userOption === null) {
            $this->error('The --user option is required. Pass a user ID or email.');

            return null;
        }

        $user = is_numeric($userOption)
            ? User::find((int) $userOption)
            : User::where('email', $userOption)->first();

        if (! $user) {
            $this->error("User not found: {$userOption}");

            return null;
        }

        return $user;
    }
}
