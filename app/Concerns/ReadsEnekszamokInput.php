<?php

namespace App\Concerns;

use App\Models\Collection;
use App\Services\EnekszamokImporter;

/**
 * Shared input handling for the énekszámok audit and import commands.
 */
trait ReadsEnekszamokInput
{
    /**
     * Read a CSV file into an array of associative rows keyed by header.
     *
     * @return array<int, array<string, string>>
     */
    protected function readCsv(string $path): array
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

    /**
     * Ensure every required songbook collection exists.
     *
     * @return array<string, Collection>|null
     */
    protected function resolveCollections(): ?array
    {
        $required = EnekszamokImporter::requiredCollections();

        $found = Collection::whereIn('abbreviation', $required)->get()->keyBy('abbreviation');
        $missing = array_diff($required, $found->keys()->all());

        if (! empty($missing)) {
            $this->error('The following songbook collections are missing (create them first):');
            foreach ($missing as $abbr) {
                $this->line("  - {$abbr}");
            }

            return null;
        }

        return $found->all();
    }

    protected function defaultSongsPath(): string
    {
        return base_path('import/enekszamok/songs.csv');
    }

    protected function defaultDecisionsPath(): string
    {
        return base_path('import/enekszamok/decisions.csv');
    }
}
