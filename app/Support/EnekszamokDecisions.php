<?php

namespace App\Support;

use App\Services\EnekszamokImporter;

/**
 * Reads and writes the énekszámok conflict decisions file, where the user records
 * whether each too-different title match should be merged or kept separate.
 */
class EnekszamokDecisions
{
    /**
     * @var list<string>
     */
    private const HEADER = ['row_title', 'existing_id', 'existing_title', 'matched_via', 'similarity', 'decision'];

    /**
     * Single-character codes used in the file's decision column.
     *
     * @var array<string, string>
     */
    private const CODES = ['m' => 'merge', 'd' => 'separate'];

    /**
     * Load the file into a map of decision key => 'merge'|'separate'. Blank or
     * unknown decisions are omitted so they read as undecided.
     *
     * @return array<string, string>
     */
    public static function read(string $path): array
    {
        if (! file_exists($path)) {
            return [];
        }

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);
        $decisions = [];

        while (($data = fgetcsv($handle)) !== false) {
            $row = array_combine($header, $data);
            $code = strtolower(trim($row['decision'] ?? ''));
            $decision = self::CODES[$code] ?? null;
            if ($decision === null) {
                continue;
            }
            $key = EnekszamokImporter::decisionKeyFor(
                $row['row_title'] ?? '',
                $row['existing_id'] ?? '',
                $row['existing_title'] ?? '',
            );
            $decisions[$key] = $decision;
        }
        fclose($handle);

        return $decisions;
    }

    /**
     * Write all current conflicts to the file, carrying over any decision already
     * recorded against them.
     *
     * @param  array<string, array{row_title: string, existing_id: ?int, existing_title: string, matched_via: list<string>, similarity: float, decision: ?string}>  $conflicts
     */
    public static function write(string $path, array $conflicts): void
    {
        $handle = fopen($path, 'w');
        fputcsv($handle, self::HEADER);

        $codes = array_flip(self::CODES);

        foreach ($conflicts as $conflict) {
            fputcsv($handle, [
                $conflict['row_title'],
                $conflict['existing_id'],
                $conflict['existing_title'],
                implode(', ', $conflict['matched_via']),
                $conflict['similarity'],
                $codes[$conflict['decision']] ?? '',
            ]);
        }
        fclose($handle);
    }
}
