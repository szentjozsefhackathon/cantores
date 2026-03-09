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
     * Maps short abbreviations used in import data to canonical collection abbreviations.
     *
     * @var array<string, string>
     */
    private const ABBREVIATION_MAP = [
        'Ho' => 'SZVU',
        'E' => 'ÉE',
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cantores:musicplan-import {file : Path to the markdown file to import}';

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

            // Second pass: clean up unmatched items with complex prefixes / slot names
            $resolved = $this->runSecondPass($musicPlanImport);
            $this->info("Second pass resolved {$resolved} additional items");

            // Third pass: prefix-based matching for items that are still unmatched
            $prefixResolved = $this->runThirdPass($musicPlanImport);
            $this->info("Third pass resolved {$prefixResolved} additional items via prefix matching");

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

        // Step 1: Normalize bare numbers by prepending the leading abbreviation prefix.
        // E.g. "MG478, 485" → "MG478, MG485"
        $assignments = $this->normalizeLeadingAbbreviation($assignments);

        // Expand grouped parentheses containing semicolons/commas before splitting.
        // e.g. "(ÉE540; ÉE543)" → "(ÉE540); (ÉE543)" so each item gets low_priority individually.
        $assignments = preg_replace_callback('/\(([^)]+)\)/', function (array $m) {
            // Normalise " v. " alternatives and commas inside parentheses to semicolons so
            // each item gets the low_priority flag individually.
            $inner = str_replace([' v. ', ','], ['; ', ';'], $m[1]);

            if (str_contains($inner, ';')) {
                $items = array_map('trim', explode(';', $inner));

                return implode('; ', array_map(fn ($item) => "({$item})", $items));
            }

            return $m[0];
        }, $assignments);

        // Insert a semicolon between a bare abbreviation and a following parenthesised
        // group so "MG380 (ÉE591); (ÉE592)" becomes "MG380; (ÉE591); (ÉE592)".
        $assignments = preg_replace('/(\p{L}+\d+)\s+\(/u', '$1; (', $assignments);

        // Commas are also treated as separators (e.g. "MG473, MG387").
        $assignments = str_replace(',', ';', $assignments);

        // Split by semicolon for multiple assignments
        $parts = array_map('trim', explode(';', $assignments));

        foreach ($parts as $part) {
            if (empty($part)) {
                continue;
            }

            // Handle alternatives separated by " v. "
            $alternatives = array_map('trim', explode(' v. ', $part));

            // Extract letter prefix from the first alternative so numeric-only
            // alternatives can inherit it (e.g. "MG485 v. 248" → "MG485" and "MG248")
            $firstPrefix = null;
            if (preg_match('/^(\p{L}+)/u', $alternatives[0], $prefixMatches)) {
                $firstPrefix = $prefixMatches[1];
            }

            foreach ($alternatives as $alternative) {
                // If this alternative is purely numeric, prepend the first prefix
                if ($firstPrefix !== null && preg_match('/^\d+$/u', $alternative)) {
                    $alternative = $firstPrefix.$alternative;
                }

                // Expand ranges like "MG88-89" into ["MG88", "MG89"]
                foreach ($this->expandRange($alternative) as $expanded) {
                    $this->createMusicImport($importItem, $slotImport, $expanded);
                }
            }
        }
    }

    /**
     * Normalize an assignment string by prepending the abbreviation prefix from the first
     * token to all subsequent bare number tokens (digit sequences not directly preceded
     * by a letter or a dash).
     *
     * Examples:
     *   "MG478, 485"     → "MG478, MG485"
     *   "MG478-480, 485" → "MG478-480, MG485"
     */
    private function normalizeLeadingAbbreviation(string $assignments): string
    {
        // Extract the abbreviation prefix from the first token (letters before the first digit).
        if (! preg_match('/\p{L}+(?=\d)/u', $assignments, $matches)) {
            return $assignments;
        }

        $prefix = $matches[0];

        // Replace bare numbers — digit sequences (optionally with a dash-range suffix like 478-480)
        // that are NOT directly preceded by a letter, digit, or dash — with Prefix+number.
        return preg_replace_callback(
            '/(?<!\p{L})(?<!\d)(?<!-)(\d+(?:-\d+)?)/u',
            fn (array $m) => $prefix.$m[1],
            $assignments
        );
    }

    /**
     * Expand a range abbreviation like "MG88-89" into ["MG88", "MG89"].
     * Returns a single-element array with the original string if it is not a range.
     *
     * @return array<int, string>
     */
    private function expandRange(string $abbreviation): array
    {
        if (preg_match('/^(\p{L}+)(\d+)-(\d+)$/u', $abbreviation, $matches)) {
            $prefix = $matches[1];
            $start = (int) $matches[2];
            $end = (int) $matches[3];

            if ($end >= $start) {
                $result = [];
                for ($n = $start; $n <= $end; $n++) {
                    $result[] = $prefix.$n;
                }

                return $result;
            }
        }

        return [$abbreviation];
    }

    /**
     * Create a MusicImport record for a single music assignment.
     *
     * @param  array<int, string>  $flags
     */
    private function createMusicImport(MusicPlanImportItem $importItem, SlotImport $slotImport, string $abbreviation, array $flags = []): void
    {
        // Strip trailing dots (e.g. "Ho59/ÉE66." → "Ho59/ÉE66")
        $abbreviation = rtrim($abbreviation, '.');

        // Parentheses denote low priority (e.g. "(Ho227/ÉE143)" → "Ho227/ÉE143" + low_priority flag)
        if (str_starts_with($abbreviation, '(') && str_ends_with($abbreviation, ')')) {
            $abbreviation = substr($abbreviation, 1, -1);
            $flags = array_unique(array_merge($flags, ['low_priority']));
        }

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

            // After stripping a slot prefix the remaining abbreviation may itself be a
            // range (e.g. "Körmenet: ÉE801-805"). Expand it now and recurse so each
            // individual item is recorded under the correct target slot.
            $rangeItems = $this->expandRange($abbreviation);
            if (count($rangeItems) > 1) {
                foreach ($rangeItems as $rangeItem) {
                    $this->createMusicImport($importItem, $targetSlot, $rangeItem, $flags);
                }

                return;
            }
        }

        // Extract the abbreviation and any label
        $label = null;
        if (preg_match('/^([\p{L}]+\d+)\s+(.+)$/u', $abbreviation, $matches)) {
            $abbreviation = $matches[1];
            $label = $matches[2];
        }

        // Skip plain text that does not look like a music reference (no digits at all).
        // This ignores entries like "Gloria" or "Credo!".
        if (! preg_match('/\d/', str_replace('/', '', $abbreviation))) {
            return;
        }

        // Detect merge suggestion: slash-separated means two references for the same music
        $mergeSuggestion = str_contains($abbreviation, '/') ? $abbreviation : null;

        // Try to find matching Music records
        $musics = $this->findMusicsByAbbreviation($abbreviation);

        $flagsValue = empty($flags) ? null : array_values($flags);

        if (empty($musics)) {
            // No music found, create import record with just the abbreviation
            MusicImport::create([
                'music_plan_import_item_id' => $importItem->id,
                'slot_import_id' => $targetSlot->id,
                'music_id' => null,
                'abbreviation' => $abbreviation,
                'label' => $label,
                'merge_suggestion' => $mergeSuggestion,
                'flags' => $flagsValue,
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
                    'merge_suggestion' => $mergeSuggestion,
                    'flags' => $flagsValue,
                ]);
            }
        }
    }

    /**
     * Second pass: attempt to resolve MusicImport records that still have no music_id
     * by cleaning up complex raw abbreviation strings (e.g. prefixes, slot names).
     */
    private function runSecondPass(MusicPlanImport $musicPlanImport): int
    {
        $unmatched = MusicImport::whereNull('music_id')
            ->whereHas('musicPlanImportItem', fn ($q) => $q->where('music_plan_import_id', $musicPlanImport->id))
            ->with(['musicPlanImportItem', 'slotImport'])
            ->get();

        $resolved = 0;

        foreach ($unmatched as $import) {
            $cleaned = $this->cleanRawAbbreviation($import->abbreviation, $import->flags ?? []);

            if (empty($cleaned)) {
                continue;
            }

            // Expand any ranges returned by the cleaner so each abbreviation is individual.
            $allRecords = [];
            foreach ($cleaned as [$abbr, $slotName, $flags]) {
                foreach ($this->expandRange($abbr) as $expandedAbbr) {
                    $allRecords[] = [$expandedAbbr, $slotName, $flags];
                }
            }

            $isFirst = true;

            foreach ($allRecords as [$abbr, $slotName, $flags]) {
                // Skip when nothing would actually change
                if ($isFirst && $abbr === $import->abbreviation && $slotName === null && $flags === ($import->flags ?? [])) {
                    break;
                }

                // Resolve target slot (create named slot when required)
                $slotImport = $import->slotImport;

                if ($slotName !== null) {
                    $musicPlanImportId = $import->musicPlanImportItem->music_plan_import_id;
                    $slotImport = SlotImport::firstOrCreate(
                        ['music_plan_import_id' => $musicPlanImportId, 'name' => $slotName],
                        [
                            'column_number' => null,
                            'music_plan_slot_id' => MusicPlanSlot::where('name', $slotName)->first()?->id,
                        ]
                    );
                }

                $musics = $this->findMusicsByAbbreviation($abbr);
                $flagsValue = empty($flags) ? null : array_values($flags);
                $musicId = ! empty($musics) ? $musics[0]->id : null;

                if ($isFirst) {
                    $import->update([
                        'abbreviation' => $abbr,
                        'music_id' => $musicId,
                        'slot_import_id' => $slotImport->id,
                        'flags' => $flagsValue,
                        'merge_suggestion' => str_contains($abbr, '/') ? $abbr : null,
                    ]);
                    $isFirst = false;
                } else {
                    MusicImport::create([
                        'music_plan_import_item_id' => $import->music_plan_import_item_id,
                        'slot_import_id' => $slotImport->id,
                        'music_id' => $musicId,
                        'abbreviation' => $abbr,
                        'label' => null,
                        'merge_suggestion' => str_contains($abbr, '/') ? $abbr : null,
                        'flags' => $flagsValue,
                    ]);
                }

                if ($musicId !== null) {
                    $resolved++;
                }
            }
        }

        return $resolved;
    }

    /**
     * Third pass: attempt prefix-based matching for MusicImport records that still
     * have no music_id after passes 1 and 2.
     *
     * When the import contains a bare number (e.g. "ÉE569", "SZVU184") the database
     * may store variants with a suffix, such as "569 latin", "569 magyar", "184A",
     * "184B".  A LIKE '{number}%' query finds all such variants and creates one
     * MusicImport row per match (updating the existing row for the first match and
     * inserting new rows for the rest).
     */
    private function runThirdPass(MusicPlanImport $musicPlanImport): int
    {
        $unmatched = MusicImport::whereNull('music_id')
            ->whereHas('musicPlanImportItem', fn ($q) => $q->where('music_plan_import_id', $musicPlanImport->id))
            ->with(['musicPlanImportItem', 'slotImport'])
            ->get();

        $resolved = 0;

        foreach ($unmatched as $import) {
            $musics = $this->findMusicsByAbbreviationPrefix($import->abbreviation);

            if (empty($musics)) {
                continue;
            }

            $isFirst = true;

            foreach ($musics as $music) {
                if ($isFirst) {
                    $import->update(['music_id' => $music->id]);
                    $isFirst = false;
                } else {
                    MusicImport::create([
                        'music_plan_import_item_id' => $import->music_plan_import_item_id,
                        'slot_import_id' => $import->slot_import_id,
                        'music_id' => $music->id,
                        'abbreviation' => $import->abbreviation,
                        'label' => $import->label,
                        'merge_suggestion' => $import->merge_suggestion,
                        'flags' => $import->flags,
                    ]);
                }

                $resolved++;
            }
        }

        return $resolved;
    }

    /**
     * Attempt to extract one or more clean [abbreviation, slotName, flags] tuples
     * from a raw string that was stored unmatched during the first pass.
     *
     * Handles patterns such as:
     *   "v. ÉE533"                           → [("ÉE533", null, [])]
     *   "Ad lib. ÉE231 seq"                  → [("ÉE231", null, [])]
     *   "esetleg Ho79"                       → [("Ho79", null, ["low_priority"])]
     *   "MG155. Olajszentelésre: MG156"      → [("MG155", null, []), ("MG156", "Olajszentelésre", [])]
     *   "Búcsúbeszéd (ad lib.): ÉE812"       → [("ÉE812", "Búcsúbeszéd", [])]
     *   "(Ad lib.: ÉE540)"                   → [("ÉE540", null, ["low_priority"])]
     *   "Tűzszentelés előtt: ÉE826"          → [("ÉE826", "Tűzszentelés előtt", [])]
     *   "Ad. lib. Húsvéti misztériumjáték ÉE482" → [("ÉE482", "Húsvéti misztériumjáték", [])]
     *
     * @param  array<int, string>  $existingFlags
     * @return array<int, array{0: string, 1: string|null, 2: list<string>}>
     */
    private function cleanRawAbbreviation(string $raw, array $existingFlags = []): array
    {
        $raw = trim($raw);
        $flags = $existingFlags;
        $lowPriority = false;

        // If the string is already in canonical abbreviation form (e.g. "ÉE267", "Ho59/ÉE66"),
        // there is nothing to clean — leave it for manual review.
        if (preg_match('/^(\p{L}+\d+\p{L}*)(\/\p{L}+\d+\p{L}*)*$/u', $raw)) {
            return [];
        }

        // Strip leading "- " (isolated dash prefix, e.g. "- (Ad lib.: ÉE540)")
        $raw = preg_replace('/^-\s+/u', '', $raw);

        // Outer parentheses → low priority (e.g. "(Ad lib.: ÉE540)")
        if (str_starts_with($raw, '(') && str_ends_with($raw, ')')) {
            $raw = trim(substr($raw, 1, -1));
            $lowPriority = true;
        }

        // Strip leading "v. " (alternative marker)
        $raw = preg_replace('/^v\.\s+/u', '', $raw);

        // "esetleg …" → low priority (esetleg = "maybe" / optional in Hungarian)
        if (preg_match('/^esetleg\s+(.+)$/iu', $raw, $m)) {
            $raw = trim($m[1]);
            $lowPriority = true;
        }

        // Strip "Ad lib." / "Ad. lib." prefix, then any orphaned leading colon
        $raw = preg_replace('/^Ad\.?\s*lib\.?\s*/iu', '', $raw);
        $raw = trim(preg_replace('/^:\s*/u', '', $raw));

        // Strip leading "seq. " prefix and trailing " seq" / " seq." noise
        $raw = preg_replace('/^seq\.?\s+/iu', '', $raw);
        $raw = trim(preg_replace('/\s+seq\.?(\s+\S+)*$/iu', '', $raw));

        if ($lowPriority) {
            $flags = array_values(array_unique(array_merge($flags, ['low_priority'])));
        }

        // Normalise space between letter prefix and digits: "ÉE 413" → "ÉE413"
        $raw = preg_replace('/(\p{L}+)\s+(\d)/u', '$1$2', $raw);

        // Normalise spaces around range dash: "MG394- 395" → "MG394-395"
        $raw = preg_replace('/(\d)\s*-\s*(\d)/u', '$1-$2', $raw);

        // Collapse repeated prefix in range: "Ho433-Ho435" or "Ho433- Ho435" → "Ho433-435"
        $raw = preg_replace('/^(\p{L}+)(\d+)\s*-\s*\1(\d+)$/u', '$1$2-$3', $raw);

        // After normalisation, re-check canonical / range forms and short-circuit
        if (preg_match('/^(\p{L}+\d+\p{L}*)(\/\p{L}+\d+\p{L}*)*$/u', $raw)) {
            return [[$raw, null, $flags]];
        }
        if (preg_match('/^\p{L}+\d+-\d+$/u', $raw)) {
            return [[$raw, null, $flags]];
        }

        // "(ABBR) ABBR [label]" — parenthesised low-priority alternative followed by the main music
        // e.g. "(ÉE538) ÉE414 Kyrie"
        if (preg_match('/^\((\p{L}+\d+\p{L}*)\)\s+(\p{L}+\d+\p{L}*)\b/u', $raw, $m)) {
            $lowFlags = array_values(array_unique(array_merge($flags, ['low_priority'])));

            return [
                [$m[2], null, $flags],
                [$m[1], null, $lowFlags],
            ];
        }

        // "(ABBR) trailing-text" — parenthesised abbreviation with trailing non-abbreviation text
        // e.g. "(ÉE646) napján"
        if (preg_match('/^\((\p{L}+\d+\p{L}*)\)\s+[^(]/u', $raw, $m)) {
            $lowFlags = array_values(array_unique(array_merge($flags, ['low_priority'])));

            return [[$m[1], null, $lowFlags]];
        }

        // "RANGE (parens…)" — range followed by parenthesised annotations / alternatives
        // e.g. "MG220-221 (ÉE557)", "MG590-591 (ünn.) (ÉE680)", "ÉE576-579 (helyettesíthető: Ho107)"
        if (preg_match('/^(\p{L}+\d+-\d+)\s+(.+)$/u', $raw, $m)) {
            $results = [[$m[1], null, $flags]];

            preg_match_all('/\(([^)]+)\)/u', $m[2], $parenMatches);
            foreach ($parenMatches[1] as $inner) {
                $abbr = $this->extractFirstAbbreviation($inner);
                if ($abbr !== null) {
                    $lowFlags = array_values(array_unique(array_merge($flags, ['low_priority'])));
                    $results[] = [$abbr, null, $lowFlags];
                }
            }

            return $results;
        }

        // "ABBR. SLOTNAME: ABBR2" → two records (e.g. "MG155. Olajszentelésre: MG156")
        if (preg_match('/^(\p{L}+\d+\p{L}*)\.\s+([^:]+):\s+(\p{L}+\d+\p{L}*)$/u', $raw, $m)) {
            return [
                [$m[1], null, $flags],
                [$m[3], trim($m[2]), $flags],
            ];
        }

        // "SLOTNAME: ABBR" where slot may contain spaces or "(ad lib.)"
        // (e.g. "Búcsúbeszéd (ad lib.): ÉE812", "Tűzszentelés előtt: ÉE826")
        if (preg_match('/^(.+?)\s*:\s+(.+)$/u', $raw, $m)) {
            $potentialSlot = trim($m[1]);
            $rest = trim($m[2]);

            // Guard: if the part before the colon is itself a plain abbreviation, skip
            if (! preg_match('/^\p{L}+\d+\p{L}*$/u', $potentialSlot)) {
                // Strip presentational "(ad lib.)" noise from the slot label
                $slotName = trim(preg_replace('/\s*\(ad\.?\s*lib\.?\)\s*/iu', '', $potentialSlot));
                // Use the full rest when it is already a clean abbreviation or range;
                // otherwise fall back to extracting the first abbreviation-like token.
                if (preg_match('/^\p{L}+\d+\p{L}*(-\d+)?$/u', $rest)) {
                    $abbr = $rest;
                } else {
                    $abbr = $this->extractFirstAbbreviation($rest);
                }

                if ($abbr !== null) {
                    return [[$abbr, $slotName ?: null, $flags]];
                }
            }
        }

        // "SLOTNAME ABBR" — multi-word text followed by a trailing abbreviation
        // (e.g. "Húsvéti misztériumjáték ÉE482" after Ad lib. was stripped)
        if (preg_match('/^(.+)\s+(\p{L}+\d+\p{L}*)$/u', $raw, $m)) {
            $potentialSlot = trim($m[1]);
            $abbr = $m[2];

            if (str_contains($potentialSlot, ' ') || ! preg_match('/^\p{L}+\d+\p{L}*$/u', $potentialSlot)) {
                return [[$abbr, $potentialSlot, $flags]];
            }

            // Two adjacent abbreviation-like tokens without a slot — use only the first
            return [[$potentialSlot, null, $flags]];
        }

        // Fallback: extract the first recognisable abbreviation from whatever remains
        $abbr = $this->extractFirstAbbreviation($raw);

        if ($abbr === null) {
            // No useful extraction possible; worth updating only if flags changed
            if ($flags !== $existingFlags) {
                return [[$raw, null, $flags]];
            }

            return [];
        }

        return [[$abbr, null, $flags]];
    }

    /**
     * Extract the first occurrence of a music abbreviation (letters + digits) from text.
     */
    private function extractFirstAbbreviation(string $text): ?string
    {
        if (preg_match('/\p{L}+\d+\p{L}*/u', $text, $m)) {
            return $m[0];
        }

        return null;
    }

    /**
     * Expand a short abbreviation to its canonical form using ABBREVIATION_MAP.
     * E.g. "H" -> "SZVU", so "H23" becomes "SZVU23".
     */
    private function expandAbbreviation(string $abbr): string
    {
        // Extract letter prefix, digits, and optional trailing letters (e.g. "Ho233A")
        if (! preg_match('/^(\p{L}+)(\d+\p{L}*)$/u', $abbr, $matches)) {
            return $abbr;
        }

        $prefix = $matches[1];
        $number = $matches[2];

        return (self::ABBREVIATION_MAP[$prefix] ?? $prefix).$number;
    }

    /**
     * Find Music records by abbreviation prefix.
     *
     * Only applies when the parsed order number is purely numeric (no trailing
     * letters).  Finds all pivot rows whose order_number begins with that number
     * followed by at least one additional character, covering cases such as:
     *   "ÉE569"   → "569 latin", "569 magyar"
     *   "SZVU184" → "184A", "184B", "184C", "184D"
     *
     * @return array<int, Music>
     */
    private function findMusicsByAbbreviationPrefix(string $abbreviation): array
    {
        $musics = [];

        $parts = explode('/', $abbreviation);

        foreach ($parts as $part) {
            $part = trim($part);
            $part = $this->expandAbbreviation($part);

            // Only prefix-match when the order number consists solely of digits;
            // if it already has a trailing letter the exact-match pass covered it.
            if (preg_match('/^(\p{L}+)(\d+)$/u', $part, $matches)) {
                $collectionAbbr = $matches[1];
                $orderNumber = $matches[2];

                $collection = Collection::where('abbreviation', $collectionAbbr)->first();

                if ($collection) {
                    $foundMusics = $collection->music()
                        ->wherePivot('order_number', 'LIKE', $orderNumber.'%')
                        ->wherePivot('order_number', '!=', $orderNumber)
                        ->get();

                    $musics = array_merge($musics, $foundMusics->all());
                }
            }
        }

        return array_unique($musics, SORT_REGULAR);
    }

    /**
     * Find Music records by abbreviation.
     * Handles formats like "ÉE281", "Ho132/ÉE182", or "H23" (expanded to "SZVU23").
     * Supports Unicode collection abbreviations (e.g. "ÉE").
     *
     * @return array<int, Music>
     */
    private function findMusicsByAbbreviation(string $abbreviation): array
    {
        $musics = [];

        // Handle slash-separated abbreviations like "Ho132/ÉE182" or "ÉE267/H23"
        $parts = explode('/', $abbreviation);

        foreach ($parts as $part) {
            $part = trim($part);

            // Expand known short abbreviations (e.g. H -> SZVU)
            $part = $this->expandAbbreviation($part);

            // Parse collection abbreviation and order number (Unicode-aware, optional trailing letters)
            if (preg_match('/^(\p{L}+)(\d+\p{L}*)$/u', $part, $matches)) {
                $collectionAbbr = $matches[1];
                $orderNumber = $matches[2];

                // Find collection
                $collection = Collection::where('abbreviation', $collectionAbbr)->first();

                if ($collection) {
                    // Find music in this collection with the order number (case-insensitive)
                    $orderNumberVariants = array_unique([
                        $orderNumber,
                        mb_strtolower($orderNumber),
                        mb_strtoupper($orderNumber),
                    ]);
                    $foundMusics = $collection->music()
                        ->wherePivotIn('order_number', $orderNumberVariants)
                        ->get();

                    $musics = array_merge($musics, $foundMusics->all());
                }
            }
        }

        // Remove duplicates
        return array_unique($musics, SORT_REGULAR);
    }
}
