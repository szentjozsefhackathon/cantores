<?php

namespace App\Console\Commands;

use App\Models\Author;
use App\Models\User;
use Illuminate\Console\Command;

class ImportEnekszamokAuthorsCommand extends Command
{
    protected $signature = 'cantores:import-enekszamok-authors
                            {--user= : User ID or email to own imported records (required)}
                            {--path= : Path to authors.csv (defaults to import/enekszamok/authors.csv)}';

    protected $description = 'Import authors from the énekszámok authors.csv. Skips authors that already exist.';

    public function handle(): int
    {
        $user = $this->resolveUser();
        if (! $user) {
            return self::FAILURE;
        }

        $path = $this->option('path') ?: base_path('import/enekszamok/authors.csv');
        if (! file_exists($path)) {
            $this->error("authors.csv not found at: {$path}");

            return self::FAILURE;
        }

        $rows = $this->readCsv($path);
        $this->info('Loaded '.count($rows).' authors from '.basename($path).'.');

        $created = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $name = trim($row['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $exists = Author::query()->where('name', $name)->where('is_private', false)->exists();
            if ($exists) {
                $skipped++;

                continue;
            }

            Author::create([
                'name' => $name,
                'user_id' => $user->id,
                'is_private' => false,
            ]);
            $created++;
        }

        $this->info("Done. Created: {$created}, Skipped (already existed): {$skipped}.");

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

    /**
     * Read a CSV file into an array of associative rows keyed by header.
     *
     * @return array<int, array<string, string>>
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);
        $rows = [];
        while (($data = fgetcsv($handle)) !== false) {
            $rows[] = array_combine($header, $data);
        }
        fclose($handle);

        return $rows;
    }
}
