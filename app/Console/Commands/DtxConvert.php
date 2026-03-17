<?php

namespace App\Console\Commands;

use App\Models\BulkImport;
use App\Models\MusicTag;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use SplFileObject;

class DtxConvert extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cantores:dtx-convert {collection : The collection name (e.g., szvu)} {--title : Use ienek as title and leave reference empty} {--csv : Use CSV file instead of DTX} {--special : Special mode: only numbered songs, tag from header validated against DB, ÉE reference as subtitle, first lyrics line as title} {--skip-unknown-tags : In special mode, skip songs whose tag does not exist in the DB instead of aborting (unknown tags are listed as warnings)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download DTX file from GitHub and import into bulk_imports table, or import from CSV file';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $collection = $this->argument('collection');
        $useTitle = $this->option('title');
        $useCsv = $this->option('csv');
        $useSpecial = $this->option('special');
        $skipUnknownTags = $this->option('skip-unknown-tags');

        if ($useCsv) {
            // Import from CSV file
            $csvPath = storage_path("app/{$collection}.csv");
            if (! file_exists($csvPath)) {
                $this->error("CSV file not found: {$csvPath}");

                return self::FAILURE;
            }

            $this->info("Importing from CSV file: {$csvPath}");
            $songs = $this->parseCsv($csvPath);

            if (empty($songs)) {
                $this->warn('No songs found in CSV file.');

                return self::FAILURE;
            }

            // Store in database
            $this->storeInDatabase($collection, $songs);
            $this->info('Stored '.count($songs)." songs in bulk_imports table for collection '{$collection}'.");

            return self::SUCCESS;
        } else {
            // Original DTX import logic
            $url = "https://raw.githubusercontent.com/diatar/diatar-dtxs/refs/heads/main/{$collection}.dtx";

            $this->info("Downloading DTX file from: {$url}");

            try {
                $response = Http::timeout(30)->get($url);
            } catch (\Exception $e) {
                $this->error("Failed to download DTX file: {$e->getMessage()}");

                return self::FAILURE;
            }

            if (! $response->successful()) {
                $this->error("HTTP error: {$response->status()} - {$response->body()}");

                return self::FAILURE;
            }

            // Save to storage directory (private/dtximport)
            $dtxDir = storage_path('app/private/dtximport');
            if (! is_dir($dtxDir)) {
                mkdir($dtxDir, 0755, true);
            }
            $dtxPath = $dtxDir.'/'.$collection.'.dtx';
            file_put_contents($dtxPath, $response->body());

            $this->info("DTX file saved to: {$dtxPath}");

            if ($useSpecial) {
                $validTagNames = MusicTag::pluck('name')->all();
                try {
                    $songs = $this->parseSpecialDtx($dtxPath, $validTagNames, (bool) $skipUnknownTags, $collection);
                } catch (\RuntimeException $e) {
                    $this->error($e->getMessage());

                    return self::FAILURE;
                }
            } else {
                $songs = $this->parseDtx($dtxPath, $useTitle, $collection);
            }

            if (empty($songs)) {
                $this->warn('No songs found in DTX file.');

                return self::FAILURE;
            }

            // Store in database
            $this->storeInDatabase($collection, $songs);
            $this->info('Stored '.count($songs)." songs in bulk_imports table for collection '{$collection}'.");

            $csvPath = $this->generateCsv($dtxPath, $songs);

            $this->info("CSV file created: {$csvPath}");
            $this->info('Conversion completed.');

            // Optionally delete the temporary DTX file
            unlink($dtxPath);
            $this->info('Temporary DTX file removed.');

            return self::SUCCESS;
        }
    }

    /**
     * Parse CSV file with columns: title, reference, page number, tag, subtitle
     *
     * @return array<int, array{ienek: string, enek: string, page_number: ?int, tag: ?string, subtitle?: string}>
     */
    private function parseCsv(string $csvPath): array
    {
        $songs = [];

        if (! file_exists($csvPath)) {
            return $songs;
        }

        $file = new SplFileObject($csvPath);
        $file->setFlags(SplFileObject::READ_CSV);
        $file->setCsvControl(',', '"', '\\');

        $header = null;
        $rowCount = 0;

        foreach ($file as $row) {
            if ($row === null || $row === [null]) {
                continue; // Skip empty rows
            }

            if ($header === null) {
                $header = $row;
                // Normalize header names
                $header = array_map('strtolower', array_map('trim', $header));

                continue;
            }

            $rowCount++;

            // Map columns based on header
            $data = array_combine($header, array_pad($row, count($header), ''));

            // Determine title and reference based on available columns
            $title = '';
            $reference = '';
            $pageNumber = null;
            $tag = null;
            $subtitle = null;

            if (isset($data['title'])) {
                $title = trim($data['title']);
            } elseif (isset($data['enek'])) {
                $title = trim($data['enek']);
            }

            if (isset($data['reference'])) {
                $reference = trim($data['reference']);
            } elseif (isset($data['ienek'])) {
                $reference = trim($data['ienek']);
            }

            if (isset($data['page number']) && ! empty(trim($data['page number']))) {
                $pageNumber = (int) trim($data['page number']);
            } elseif (isset($data['page_number']) && ! empty(trim($data['page_number']))) {
                $pageNumber = (int) trim($data['page_number']);
            }

            if (isset($data['tag']) && ! empty(trim($data['tag']))) {
                $tag = trim($data['tag']);
            }

            if (isset($data['subtitle']) && ! empty(trim($data['subtitle']))) {
                $subtitle = trim($data['subtitle']);
            }

            if (empty($title) && empty($reference)) {
                continue; // Skip empty rows
            }

            $song = [
                'ienek' => $reference,
                'enek' => $title,
                'page_number' => $pageNumber,
                'tag' => $tag,
            ];
            if ($subtitle !== null) {
                $song['subtitle'] = $subtitle;
            }
            $songs[] = $song;
        }

        return $songs;
    }

    /**
     * Parse special DTX format: only numbered songs, tag from header (validated), ÉE reference as subtitle, first lyrics as title.
     *
     * @param  string[]  $validTagNames
     * @param  string  $collection  Collection name (e.g., 'taize')
     * @return array<int, array{ienek: string, enek: string, tag: string|null, related: string, subtitle?: string}>
     *
     * @throws \RuntimeException if a tag name from the file does not exist in the database and $skipUnknownTags is false.
     */
    private function parseSpecialDtx(string $dtxPath, array $validTagNames, bool $skipUnknownTags = false, string $collection = ''): array
    {
        $songs = [];
        $currentSong = null;
        $firstline = '';
        $captured = false;
        $ivers = 0;
        $unknownTags = [];

        $file = new SplFileObject($dtxPath);
        $file->setFlags(SplFileObject::DROP_NEW_LINE);

        while (! $file->eof()) {
            $line = $file->fgets();
            if ($line === false) {
                break;
            }
            $line = rtrim($line, "\r\n");
            if (empty($line)) {
                continue;
            }

            $firstChar = $line[0];

            switch ($firstChar) {
                case '>':
                    // Flush previous song if a lyrics line was captured
                    if ($currentSong !== null && ! empty($firstline)) {
                        $songs[] = array_merge($currentSong, ['enek' => $firstline]);
                    }

                    $content = trim(substr($line, 1));

                    // Skip entries without a leading numeric order number (e.g. >Introitus)
                    if (! preg_match('/^(\d+)\s+(.+)$/', $content, $matches)) {
                        $currentSong = null;
                        $firstline = '';
                        $captured = false;
                        $ivers = 0;
                        break;
                    }

                    $orderNumber = $matches[1];
                    $headerText = trim($matches[2]);

                    // Split optional [RELATED] from tag text
                    $tag = $headerText;
                    $related = '';
                    if (preg_match('/^(.+?)\s*\[([^\]]+)\]\s*$/', $headerText, $refMatches)) {
                        $tag = trim($refMatches[1]);
                        $related = trim($refMatches[2]);
                    }

                    // Try the full tag name first; fall back to the last word
                    // (handles "Évközi XII.Offertorium" or "IV.Offertorium" → "Offertorium")
                    $fullTag = $tag;
                    $tagParts = preg_split('/[\s.]+/', $tag, -1, PREG_SPLIT_NO_EMPTY);
                    $lastWord = mb_strtoupper(mb_substr(end($tagParts), 0, 1, 'UTF-8'), 'UTF-8').mb_substr(end($tagParts), 1, null, 'UTF-8');
                    $tag = in_array($fullTag, $validTagNames, true) ? $fullTag : $lastWord;

                    // Unknown tags: warn and leave tag null; abort if not in skip mode
                    if (! in_array($tag, $validTagNames, true)) {
                        $unknownTags[] = $tag;
                        if (! $skipUnknownTags) {
                            // Will throw after parsing; continue collecting unknowns
                        }
                        $tag = null;
                    }

                    $currentSong = [
                        'ienek' => $orderNumber,
                        'related' => $related,
                        'tag' => $tag,
                    ];
                    $firstline = '';
                    $captured = false;
                    $ivers = 0;
                    break;

                case '/':
                    $ivers++;
                    if (! $captured) {
                        $firstline = '';
                    }
                    break;

                case ' ':
                    if ($currentSong === null || $captured) {
                        break;
                    }
                    if ($ivers <= 1) {
                        $text = $this->unescape($line);
                        $text = $this->cleanTxt($text);
                        if (! empty($text)) {
                            $firstline = $text;
                            $captured = true;
                        }
                    }
                    break;

                default:
                    break;
            }
        }

        // Flush the last song
        if ($currentSong !== null && ! empty($firstline)) {
            $songs[] = array_merge($currentSong, ['enek' => $firstline]);
        }

        if (! empty($unknownTags)) {
            if ($skipUnknownTags) {
                foreach (array_unique($unknownTags) as $unknownTag) {
                    $this->warn("Unknown tag (set to empty): {$unknownTag}");
                }
            } else {
                throw new \RuntimeException(
                    'The following tags do not exist in the music_tags table: '.implode(', ', array_unique($unknownTags)).
                    "\nAdd them first before importing, or use --skip-unknown-tags to import without a tag."
                );
            }
        }

        return $songs;
    }

    /**
     * Parse DTX file and extract songs.
     *
     * @param  bool  $useTitle  If true, use ienek as title and leave reference empty.
     * @param  string  $collection  Collection name (e.g., 'taize')
     * @return array<int, array{ienek: string, enek: string, subtitle?: string}>
     */
    private function parseDtx(string $dtxPath, bool $useTitle = false, string $collection = ''): array
    {
        $songs = [];
        $enekszam = '';
        $diaszam = '';
        $firstline = '';
        $ivers = 0;
        $captured = false;
        $isTaize = strtolower($collection) === 'taize';
        $firstLineVerse1 = '';
        $firstLineVerse2 = '';
        $capturedVerse1 = false;
        $capturedVerse2 = false;
        $pendingSong = null;

        $file = new SplFileObject($dtxPath);
        $file->setFlags(SplFileObject::DROP_NEW_LINE);

        // Helper to finalize pending song
        $finalizePendingSong = function () use (&$songs, &$pendingSong, $useTitle, $isTaize) {
            if ($pendingSong === null) {
                return;
            }
            $title = '';
            $subtitle = null;
            if ($isTaize && $pendingSong['firstLineVerse2'] !== '') {
                // Use second verse as title, first verse as subtitle
                $title = $pendingSong['firstLineVerse2'];
                $subtitle = $pendingSong['firstLineVerse1'];
            } elseif ($pendingSong['firstLineVerse1'] !== '') {
                // Use first verse as title
                $title = $pendingSong['firstLineVerse1'];
            } elseif ($pendingSong['titleOnSameLine'] !== '') {
                // Title already captured on same line as song number
                $title = $pendingSong['titleOnSameLine'];
            }
            // If $useTitle is true, swap title and reference
            $ienek = $pendingSong['ienek'];
            if ($useTitle) {
                $title = $ienek;
                $ienek = '';
            }
            if ($title !== '' || $useTitle) {
                $song = [
                    'ienek' => $ienek,
                    'enek' => $title,
                ];
                if ($subtitle !== null) {
                    $song['subtitle'] = $subtitle;
                }
                $songs[] = $song;
            }
            $pendingSong = null;
        };

        while (! $file->eof()) {
            $line = $file->fgets();
            if ($line === false) {
                break;
            }
            $line = rtrim($line, "\r\n");
            if (empty($line)) {
                continue;
            }

            $firstChar = $line[0];

            switch ($firstChar) {
                case 'R':
                    // short name, ignore
                    break;
                case '>':
                    // New song - finalize previous pending song
                    $finalizePendingSong();

                    $content = trim(substr($line, 1));
                    $parts = preg_split('/\s{2,}|\t/', $content, 2);
                    $enekszam = $parts[0];

                    // Reset state for new song
                    $firstline = '';
                    $captured = false;
                    $ivers = 0;
                    $diaszam = '';
                    $firstLineVerse1 = '';
                    $firstLineVerse2 = '';
                    $capturedVerse1 = false;
                    $capturedVerse2 = false;

                    // If there's a title on the same line, store it
                    $titleOnSameLine = '';
                    if (isset($parts[1]) && ! empty($parts[1])) {
                        $titleOnSameLine = $this->unescape($parts[1]);
                        $titleOnSameLine = $this->cleanTxt($titleOnSameLine);
                    }

                    $pendingSong = [
                        'ienek' => $enekszam,
                        'firstLineVerse1' => '',
                        'firstLineVerse2' => '',
                        'titleOnSameLine' => $titleOnSameLine,
                    ];
                    break;
                case '/':
                    // New verse
                    $ivers++;
                    $diaszam = trim($line);
                    $firstline = '';
                    break;
                case ' ':
                    // Text line
                    if ($pendingSong === null) {
                        break;
                    }
                    // Capture first line of each verse (only first line per verse)
                    if ($ivers === 0 && ! $capturedVerse1) {
                        // No verse prefix - treat as first verse
                        $text = $this->unescape($line);
                        $text = $this->cleanTxt($text);
                        if (! empty($text)) {
                            $pendingSong['firstLineVerse1'] = $text;
                            $capturedVerse1 = true;
                        }
                    } elseif ($ivers === 1 && ! $capturedVerse1) {
                        $text = $this->unescape($line);
                        $text = $this->cleanTxt($text);
                        if (! empty($text)) {
                            $pendingSong['firstLineVerse1'] = $text;
                            $capturedVerse1 = true;
                        }
                    } elseif ($ivers === 2 && ! $capturedVerse2) {
                        $text = $this->unescape($line);
                        $text = $this->cleanTxt($text);
                        if (! empty($text)) {
                            $pendingSong['firstLineVerse2'] = $text;
                            $capturedVerse2 = true;
                        }
                    }
                    break;
                default:
                    // Other lines ignored
                    break;
            }
        }

        // Finalize last pending song
        $finalizePendingSong();

        return $songs;
    }

    /**
     * Generate CSV file with same name as DTX but .csv extension.
     *
     * @return string Path to created CSV file
     */
    private function generateCsv(string $dtxPath, array $songs): string
    {
        $csvPath = preg_replace('/\.dtx$/i', '.csv', $dtxPath);
        if ($csvPath === $dtxPath) {
            $csvPath .= '.csv';
        }

        $csvFile = new SplFileObject($csvPath, 'w');
        // Generate CSV with new format columns
        $csvFile->fputcsv(['title', 'reference', 'related', 'page number', 'tag', 'subtitle']);

        foreach ($songs as $song) {
            $csvFile->fputcsv([
                $song['enek'], // title
                $song['ienek'], // reference (order number)
                $song['related'] ?? '', // related
                '', // page number (empty for DTX imports)
                $song['tag'] ?? '', // tag
                $song['subtitle'] ?? '', // subtitle
            ]);
        }

        return $csvPath;
    }

    /**
     * Store songs in bulk_imports table.
     */
    private function storeInDatabase(string $collection, array $songs): void
    {
        // Determine next batch number
        $latestBatchNumber = BulkImport::max('batch_number');
        $nextBatchNumber = $latestBatchNumber ? $latestBatchNumber + 1 : 1;

        // Delete existing records for this collection
        BulkImport::where('collection', $collection)->delete();

        $records = [];
        foreach ($songs as $song) {
            $record = [
                'collection' => $collection,
                'piece' => $song['enek'],
                'reference' => (string) $song['ienek'],
                'related' => $song['related'] ?? null,
                'page_number' => $song['page_number'] ?? null,
                'tag' => $song['tag'] ?? null,
                'subtitle' => $song['subtitle'] ?? null,
                'batch_number' => $nextBatchNumber,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $records[] = $record;
        }

        // Use chunk insert for performance
        foreach (array_chunk($records, 100) as $chunk) {
            BulkImport::insert($chunk);
        }
    }

    /**
     * Remove escape sequences from DTX text line.
     */
    private function unescape(string $txt): string
    {
        $res = '';
        $escmode = 0;
        $len = strlen($txt);
        for ($i = 0; $i < $len; $i++) {
            $c = $txt[$i];
            if ($escmode == 2) {
                if ($c === ';') {
                    $escmode = 0;
                }

                continue;
            }
            if ($escmode == 1) {
                if ($c === '?' || $c === 'K' || $c === 'G') {
                    $escmode = 2;

                    continue;
                }
                if ($c === '\\' || $c === ' ') {
                    $res .= $c;
                } elseif ($c === '.') {
                    $res .= ' ';
                }
                $escmode = 0;

                continue;
            }
            if ($c === '\\') {
                $escmode = 1;

                continue;
            }
            $res .= $c;
        }

        return $res;
    }

    /**
     * Trim punctuation and numbers from start/end of text.
     */
    private function cleanTxt(string $txt): string
    {
        $p1 = 0;
        $p2 = mb_strlen($txt, 'UTF-8');
        while ($p1 < $p2 && mb_strpos(" \t\n\r\v\x00-*):.+/…–|’(\"„”“,;!?0123456789'", mb_substr($txt, $p1, 1, 'UTF-8'), 0, 'UTF-8') !== false) {
            $p1++;
        }
        while ($p1 < $p2 && mb_strpos(" \t\n\r\v\x00-*):.+/…–|’(\"„”“,;!?0123456789'", mb_substr($txt, $p2 - 1, 1, 'UTF-8'), 0, 'UTF-8') !== false) {
            $p2--;
        }

        return mb_substr($txt, $p1, $p2 - $p1, 'UTF-8');
    }
}
