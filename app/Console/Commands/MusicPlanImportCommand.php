<?php

namespace App\Console\Commands;

use App\Models\Collection;
use App\Models\Music;
use App\Models\MusicImport;
use App\Models\MusicPlanImport;
use App\Models\MusicPlanImportItem;
use App\Models\MusicPlanSlot;
use App\Models\SlotImport;
use Carbon\Carbon;
use Illuminate\Console\Command;

class MusicPlanImportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'musicplan:import {file : Path to the markdown file to import}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import music plan assignments from a markdown table file';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $filePath = $this->argument('file');

        if (! file_exists($filePath)) {
            $this->error("File not found: {$filePath}");

            return self::FAILURE;
        }

        try {
            $this->info("Importing from: {$filePath}");

            $content = file_get_contents($filePath);
            $lines = explode("\n", $content);

            // Parse markdown table
            $table = $this->parseMarkdownTable($lines);

            if (empty($table)) {
                $this->error('No valid table found in the markdown file.');

                return self::FAILURE;
            }

            // Create the import batch
            $musicPlanImport = MusicPlanImport::create([
                'source_file' => basename($filePath),
            ]);

            $this->info("Created MusicPlanImport batch #{$musicPlanImport->id}");

            // Process column headers (slots)
            $headers = $table[0];
            $slotImports = $this->processHeaders($musicPlanImport, $headers);

            $this->info('Created '.count($slotImports).' slot imports');

            // Process data rows
            $rowCount = 0;
            for ($i = 1; $i < count($table); $i++) {
                $row = $table[$i];
                if ($this->processRow($musicPlanImport, $slotImports, $row)) {
                    $rowCount++;
                }
            }

            $this->info("Imported {$rowCount} celebration items");
            $this->info('Import completed successfully!');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Import failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Parse markdown table from lines.
     *
     * @param  array<int, string>  $lines
     * @return array<int, array<int, string>>
     */
    private function parseMarkdownTable(array $lines): array
    {
        $table = [];
        $inTable = false;

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines
            if (empty($line)) {
                continue;
            }

            // Check if this is a table row (starts and ends with |)
            if (! str_starts_with($line, '|') || ! str_ends_with($line, '|')) {
                continue;
            }

            // Skip separator rows (contain only dashes and pipes)
            if (preg_match('/^\|[\s\-:]+\|$/', $line)) {
                continue;
            }

            // Parse the row
            $cells = explode('|', $line);
            // Remove first and last empty elements from split
            array_shift($cells);
            array_pop($cells);

            // Clean up cells
            $cells = array_map(function ($cell) {
                return trim($cell);
            }, $cells);

            if (! empty($cells)) {
                $table[] = $cells;
            }
        }

        return $table;
    }

    /**
     * Process table headers and create SlotImport records.
     *
     * @param  array<int, string>  $headers
     * @return array<int, SlotImport>
     */
    private function processHeaders(MusicPlanImport $musicPlanImport, array $headers): array
    {
        $slotImports = [];

        // Skip first column (date/info column)
        for ($i = 1; $i < count($headers); $i++) {
            $slotName = $headers[$i];

            // Try to find existing MusicPlanSlot
            $musicPlanSlot = MusicPlanSlot::where('name', $slotName)->first();

            $slotImport = SlotImport::create([
                'music_plan_import_id' => $musicPlanImport->id,
                'name' => $slotName,
                'column_number' => $i - 1,
                'music_plan_slot_id' => $musicPlanSlot?->id,
            ]);

            $slotImports[$i] = $slotImport;
        }

        return $slotImports;
    }

    /**
     * Process a data row from the table.
     *
     * @param  array<int, SlotImport>  $slotImports
     * @param  array<int, string>  $row
     */
    private function processRow(MusicPlanImport $musicPlanImport, array $slotImports, array $row): bool
    {
        // First column contains date and celebration info
        $dateInfo = $row[0];

        // Parse the date from the first column
        $celebrationDate = $this->parseHungarianDate($dateInfo);

        if (! $celebrationDate) {
            $this->warn("Could not parse date from: {$dateInfo}");

            return false;
        }

        // Create the import item
        $importItem = MusicPlanImportItem::create([
            'music_plan_import_id' => $musicPlanImport->id,
            'celebration_date' => $celebrationDate,
            'celebration_info' => $dateInfo,
        ]);

        // Process music assignments for each slot
        for ($i = 1; $i < count($row); $i++) {
            if (! isset($slotImports[$i])) {
                continue;
            }

            $assignments = $row[$i];
            $slotImport = $slotImports[$i];

            $this->processMusicAssignments($importItem, $slotImport, $assignments);
        }

        return true;
    }

    /**
     * Parse Hungarian date from text like "Márc. 1. NAGYBÖJT II. VASÁRNAPJA" or "Ápr. 2. NAGYCSÜTÖRTÖK de Krizmaszentelési mise".
     */
    private function parseHungarianDate(string $text): ?Carbon
    {
        // Hungarian month abbreviations
        $months = [
            'jan' => 1, 'január' => 1,
            'feb' => 2, 'február' => 2,
            'márc' => 3, 'március' => 3,
            'ápr' => 4, 'április' => 4,
            'máj' => 5, 'május' => 5,
            'jún' => 6, 'június' => 6,
            'júl' => 7, 'július' => 7,
            'aug' => 8, 'augusztus' => 8,
            'szept' => 9, 'szeptember' => 9,
            'okt' => 10, 'október' => 10,
            'nov' => 11, 'november' => 11,
            'dec' => 12, 'december' => 12,
        ];

        // Try to extract month and day
        // Pattern: "Márc. 1." or "március 1." or "Márc. 1 " or "Ápr. 2. NAGYCSÜTÖRTÖK de" or "Ápr. 4 szombat"
        // Matches: month abbreviation, period, space, day number, then optional period/dash/space followed by anything
        if (preg_match('/([a-záéíóöőúüűA-ZÁÉÍÓÖŐÚÜŰ]+)\.\s+(\d+)(?:\s|\.|-|$)/', $text, $matches)) {
            $monthStr = mb_strtolower($matches[1]);
            $day = (int) $matches[2];

            // Find matching month
            foreach ($months as $monthKey => $monthNum) {
                if (str_starts_with($monthStr, $monthKey)) {
                    // Assume current year, adjust if needed
                    $year = now()->year;

                    try {
                        return Carbon::createFromDate($year, $monthNum, $day);
                    } catch (\Exception) {
                        return null;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Process music assignments for a slot.
     */
    private function processMusicAssignments(MusicPlanImportItem $importItem, SlotImport $slotImport, string $assignments): void
    {
        if (empty(trim($assignments))) {
            return;
        }

        // Split by semicolon for multiple assignments
        $parts = array_map('trim', explode(';', $assignments));

        foreach ($parts as $part) {
            if (empty($part)) {
                continue;
            }

            // Handle alternatives separated by " v. "
            $alternatives = array_map('trim', explode(' v. ', $part));

            foreach ($alternatives as $alternative) {
                $this->createMusicImport($importItem, $slotImport, $alternative);
            }
        }
    }

    /**
     * Create a MusicImport record for a single music assignment.
     */
    private function createMusicImport(MusicPlanImportItem $importItem, SlotImport $slotImport, string $abbreviation): void
    {
        // Check if the abbreviation has a slot name prefix like "végén: Ho123"
        $targetSlot = $slotImport;
        if (preg_match('/^(\w+):\s+(.+)$/u', $abbreviation, $matches)) {
            $slotNamePrefix = $matches[1];
            $abbreviation = $matches[2];

            // Find or create the slot import with this name
            $targetSlot = SlotImport::firstOrCreate(
                [
                    'music_plan_import_id' => $slotImport->music_plan_import_id,
                    'name' => $slotNamePrefix,
                ],
                [
                    'column_number' => null,
                    'music_plan_slot_id' => MusicPlanSlot::where('name', $slotNamePrefix)->first()?->id,
                ]
            );
        }

        // Extract the abbreviation and any label
        $label = null;
        if (preg_match('/^([A-Z]+\d+)\s+(.+)$/u', $abbreviation, $matches)) {
            $abbreviation = $matches[1];
            $label = $matches[2];
        }

        // Try to find matching Music records
        $musics = $this->findMusicsByAbbreviation($abbreviation);

        if (empty($musics)) {
            // No music found, create import record with just the abbreviation
            MusicImport::create([
                'music_plan_import_item_id' => $importItem->id,
                'slot_import_id' => $targetSlot->id,
                'music_id' => null,
                'abbreviation' => $abbreviation,
                'label' => $label,
            ]);
        } else {
            // Create import record for each found music
            foreach ($musics as $music) {
                MusicImport::create([
                    'music_plan_import_item_id' => $importItem->id,
                    'slot_import_id' => $targetSlot->id,
                    'music_id' => $music->id,
                    'abbreviation' => $abbreviation,
                    'label' => $label,
                ]);
            }
        }
    }

    /**
     * Find Music records by abbreviation.
     * Handles formats like "ÉE281" or "Ho132/ÉE182".
     *
     * @return array<int, Music>
     */
    private function findMusicsByAbbreviation(string $abbreviation): array
    {
        $musics = [];

        // Handle slash-separated abbreviations like "Ho132/ÉE182"
        $parts = explode('/', $abbreviation);

        foreach ($parts as $part) {
            $part = trim($part);

            // Parse collection abbreviation and order number
            if (preg_match('/^([A-Z]+)(\d+)$/', $part, $matches)) {
                $collectionAbbr = $matches[1];
                $orderNumber = $matches[2];

                // Find collection
                $collection = Collection::where('abbreviation', $collectionAbbr)->first();

                if ($collection) {
                    // Find music in this collection with the order number
                    $foundMusics = $collection->music()
                        ->wherePivot('order_number', (string) $orderNumber)
                        ->get();

                    $musics = array_merge($musics, $foundMusics->all());
                }
            }
        }

        // Remove duplicates
        return array_unique($musics, SORT_REGULAR);
    }
}
