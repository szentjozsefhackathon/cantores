<?php

namespace App\Console\Commands;

use App\Models\BulkImport;
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
    protected $signature = 'dtx:convert {collection : The collection name (e.g., szvu)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download DTX file from GitHub and import into bulk_imports table';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $collection = $this->argument('collection');
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

        $songs = $this->parseDtx($dtxPath);

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

    /**
     * Parse DTX file and extract songs.
     *
     * @return array<int, array{ienek: string, enek: string}>
     */
    private function parseDtx(string $dtxPath): array
    {
        $songs = [];
        $enekszam = '';
        $diaszam = '';
        $firstline = '';
        $ivers = 0;
        $captured = false;

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
                case 'R':
                    // short name, ignore
                    break;
                case '>':
                    // New song
                    $enekszam = trim(substr($line, 1));
                    $ivers = 0;
                    $firstline = '';
                    $diaszam = '';
                    $captured = false;
                    break;
                case '/':
                    // New verse
                    $ivers++;
                    $diaszam = trim($line);
                    $firstline = '';
                    break;
                case ' ':
                    // Text line
                    if (empty($enekszam) || ! empty($firstline) || $captured) {
                        break;
                    }
                    // Only capture first line of first verse
                    if ($ivers === 1) {
                        $firstline = $this->unescape($line);
                        $firstline = $this->cleanTxt($firstline);
                        if (! empty($firstline)) {
                            $songs[] = [
                                'ienek' => $enekszam,
                                'enek' => $firstline,
                            ];
                            $captured = true;
                        }
                    }
                    break;
                default:
                    // Other lines ignored
                    break;
            }
        }

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
        $csvFile->fputcsv(['enek', 'ienek']);

        foreach ($songs as $song) {
            $csvFile->fputcsv([$song['enek'], $song['ienek']]);
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
            $records[] = [
                'collection' => $collection,
                'piece' => $song['enek'],
                'reference' => (string) $song['ienek'],
                'batch_number' => $nextBatchNumber,
                'created_at' => now(),
                'updated_at' => now(),
            ];
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
