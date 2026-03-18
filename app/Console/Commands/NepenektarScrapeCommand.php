<?php

namespace App\Console\Commands;

use App\Models\Collection;
use App\Models\Music;
use App\Models\MusicCollection;
use App\Models\MusicUrl;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class NepenektarScrapeCommand extends Command
{
    protected $signature = 'cantores:nepenektar-scrape
                            {action : "scrape" to generate CSV, "import" to create MusicUrl records}
                            {--user= : User ID or email (required for import)}
                            {--csv= : Path to CSV file (default: import/nepenektar_links.csv)}';

    protected $description = 'Scrape nepenektar HTML files to map music IDs to URLs, then import as MusicUrl records.';

    private const BASE_URL = 'https://nepenektar.hu/idoszak/';

    private const LABEL = 'sheet_music';

    public function handle(): int
    {
        $action = $this->argument('action');
        $csvPath = $this->option('csv') ?? base_path('import/nepenektar_links.csv');

        return match ($action) {
            'scrape' => $this->runScrape($csvPath),
            'import' => $this->runImport($csvPath),
            default => $this->abort("Unknown action '{$action}'. Use 'scrape' or 'import'."),
        };
    }

    private function runScrape(string $csvPath): int
    {
        $htmlDir = base_path('import/nepenektar');
        $files = glob("{$htmlDir}/*.html");

        if (empty($files)) {
            $this->error("No HTML files found in {$htmlDir}");

            return self::FAILURE;
        }

        [$collections, $abbrevPattern] = $this->loadCollections();

        if ($collections->isEmpty()) {
            $this->error('No collections with abbreviations found in the database.');

            return self::FAILURE;
        }

        $rows = [];
        $itemId = 0;

        foreach ($files as $file) {
            $this->line('Parsing: '.basename($file));
            $html = file_get_contents($file);
            $items = $this->extractItems($html);

            foreach ($items as $item) {
                $itemId++;
                $link = $item['link'];
                $title = $item['title'];
                $subtitle = $item['subtitle'];
                $sources = $this->parseSources($item['forras'], $abbrevPattern);

                $matched = false;

                foreach ($sources as $source) {
                    $collection = $collections->get($source['abbreviation']);
                    if (! $collection) {
                        continue;
                    }

                    if ($source['order_number'] !== null) {
                        $musicId = MusicCollection::where('collection_id', $collection->id)
                            ->where('order_number', $source['order_number'])
                            ->value('music_id');
                    } else {
                        // Page-only reference: look up by page_number
                        $musicId = MusicCollection::where('collection_id', $collection->id)
                            ->where('page_number', $source['page_number'])
                            ->value('music_id');
                    }

                    if (! $musicId) {
                        continue;
                    }

                    $rows[] = [
                        'item_id' => $itemId,
                        'title' => $title,
                        'subtitle' => $subtitle,
                        'link' => $link,
                        'sources' => $item['forras'],
                        'music_id' => $musicId,
                    ];

                    $matched = true;
                }

                if (! $matched) {
                    // Fallback: check if a MusicUrl already exists for this link (e.g. from a previous import)
                    $existingMusicId = MusicUrl::where('url', self::BASE_URL.$link)
                        ->where('label', self::LABEL)
                        ->value('music_id');

                    $rows[] = [
                        'item_id' => $itemId,
                        'title' => $title,
                        'subtitle' => $subtitle,
                        'link' => $link,
                        'sources' => $item['forras'],
                        'music_id' => $existingMusicId ?? '',
                    ];
                }
            }
        }

        if (empty($rows)) {
            $this->warn('No items found. Check HTML file structure.');

            return self::FAILURE;
        }

        // Write CSV
        $fp = fopen($csvPath, 'w');
        fputcsv($fp, ['item_id', 'title', 'subtitle', 'link', 'sources', 'music_id']);
        foreach ($rows as $row) {
            fputcsv($fp, [
                $row['item_id'],
                $row['title'],
                $row['subtitle'],
                $row['link'],
                $row['sources'],
                $row['music_id'],
            ]);
        }
        fclose($fp);

        $matched = count(array_filter($rows, fn ($r) => $r['music_id'] !== ''));
        $unmatched = count(array_filter($rows, fn ($r) => $r['music_id'] === ''));

        $this->info('CSV written to: '.$csvPath);
        $this->info("Total TÉTEL items: {$itemId}");
        $this->info("Rows with music_id match: {$matched}");
        $this->warn("Rows without match (new Music needed): {$unmatched}");

        return self::SUCCESS;
    }

    private function runImport(string $csvPath): int
    {
        $userOption = $this->option('user');
        if ($userOption === null) {
            $this->error('The --user option is required for import.');

            return self::FAILURE;
        }

        $user = is_numeric($userOption)
            ? User::find((int) $userOption)
            : User::where('email', $userOption)->first();

        if (! $user) {
            $this->error("User not found: {$userOption}");

            return self::FAILURE;
        }

        if (! file_exists($csvPath)) {
            $this->error("CSV file not found: {$csvPath}. Run 'scrape' first.");

            return self::FAILURE;
        }

        [$collections, $abbrevPattern] = $this->loadCollections();

        $fp = fopen($csvPath, 'r');
        $header = fgetcsv($fp);
        $columnIndex = array_flip($header);

        $urlsCreated = 0;
        $urlsSkipped = 0;
        $musicsCreated = 0;
        $musicsUpdated = 0;

        // Track Music records created in this run keyed by link (to avoid duplicates within the import)
        $createdMusicsByLink = [];

        while (($row = fgetcsv($fp)) !== false) {
            $link = $row[$columnIndex['link']];
            $title = $row[$columnIndex['title']];
            $subtitle = $row[$columnIndex['subtitle']];
            $sourcesRaw = $row[$columnIndex['sources']];
            $musicId = $row[$columnIndex['music_id']] !== '' ? (int) $row[$columnIndex['music_id']] : null;
            $url = self::BASE_URL.$link;

            if ($musicId === null) {
                // Create Music record if not already created for this link
                if (isset($createdMusicsByLink[$link])) {
                    $musicId = $createdMusicsByLink[$link];
                } else {
                    $music = Music::create([
                        'title' => $title,
                        'subtitle' => $subtitle !== '' ? $subtitle : null,
                        'user_id' => $user->id,
                    ]);
                    $musicId = $music->id;
                    $createdMusicsByLink[$link] = $musicId;
                    $musicsCreated++;

                    // Create MusicCollection records for each parsed source reference
                    foreach ($this->parseSources($sourcesRaw, $abbrevPattern) as $source) {
                        $collection = $collections->get($source['abbreviation']);
                        if (! $collection) {
                            continue;
                        }

                        MusicCollection::firstOrCreate(
                            [
                                'music_id' => $musicId,
                                'collection_id' => $collection->id,
                                'order_number' => $source['order_number'],
                            ],
                            [
                                'page_number' => $source['page_number'],
                                'user_id' => $user->id,
                            ],
                        );
                    }
                }
            } else {
                // Update title and subtitle for existing Music records
                Music::where('id', $musicId)->update([
                    'title' => $title,
                    'subtitle' => $subtitle !== '' ? $subtitle : null,
                ]);
                $musicsUpdated++;
            }

            $exists = MusicUrl::where('music_id', $musicId)
                ->where('label', self::LABEL)
                ->where('url', $url)
                ->exists();

            if ($exists) {
                $urlsSkipped++;

                continue;
            }

            MusicUrl::create([
                'music_id' => $musicId,
                'url' => $url,
                'label' => self::LABEL,
                'user_id' => $user->id,
            ]);
            $urlsCreated++;
        }

        fclose($fp);

        $this->info("Done. Music records created: {$musicsCreated}, updated: {$musicsUpdated}, URLs created: {$urlsCreated}, URLs skipped (already exist): {$urlsSkipped}.");

        return self::SUCCESS;
    }

    /**
     * Load collections keyed by abbreviation and build the abbreviation regex pattern.
     *
     * @return array{0: EloquentCollection<string, Collection>, 1: string}
     */
    private function loadCollections(): array
    {
        $collections = Collection::whereNotNull('abbreviation')->get()->keyBy('abbreviation');

        $abbreviations = $collections->keys()->sortByDesc(fn ($a) => mb_strlen($a))->values()->toArray();
        $abbrevPattern = implode('|', array_map('preg_quote', $abbreviations));

        return [$collections, $abbrevPattern];
    }

    /**
     * Extract all TÉTEL items from an HTML file.
     *
     * @return array<int, array{link: string, title: string, subtitle: string, forras: string}>
     */
    private function extractItems(string $html): array
    {
        $items = [];

        // Split on TÉTEL comment markers
        $blocks = preg_split('/<!--\s*TÉTEL\s*-->/', $html);
        array_shift($blocks); // Remove content before first marker

        foreach ($blocks as $block) {
            // Extract href from first <a href="..."> with an .html path
            if (! preg_match('/<a\s+href="([^"]+\.html)"/', $block, $hrefMatch)) {
                continue;
            }
            $link = preg_replace('/\.html$/', '', $hrefMatch[1]);

            // Extract title from <span class="title">...</span>
            $title = '';
            if (preg_match('/<span\s+class="title">(.*?)<\/span>/s', $block, $titleMatch)) {
                $title = trim(strip_tags($titleMatch[1]));
            }

            // Extract subtitle from <span class="subtitle">...</span>
            $subtitle = '';
            if (preg_match('/<span\s+class="subtitle">(.*?)<\/span>/s', $block, $subtitleMatch)) {
                $subtitle = trim(strip_tags($subtitleMatch[1]));
            }

            // Extract Forrás content: after info-title span containing "Forrás:" find next <span>...</span>
            $forras = '';
            if (preg_match('/<span[^>]*class="info-title"[^>]*>\s*Forrás:\s*<\/span>\s*<span[^>]*>(.*?)<\/span>/s', $block, $forrasMatch)) {
                $forras = trim(strip_tags($forrasMatch[1]));
            }

            $items[] = [
                'link' => $link,
                'title' => $title,
                'subtitle' => $subtitle,
                'forras' => $forras,
            ];
        }

        return $items;
    }

    /**
     * Parse a Forrás string into structured source references.
     *
     * Handles three formats:
     *   - "ÉE523 (615. old.)" → order_number=523, page_number=615
     *   - "ÉE1112. old."       → order_number=null, page_number=1112
     *   - "K. old."            → normalised to PK, order_number=null, page_number=null
     *
     * @return array<int, array{abbreviation: string, order_number: string|null, page_number: int|null}>
     */
    private function parseSources(string $forras, string $abbrevPattern): array
    {
        $results = [];

        // Normalise bare "K. old." → "PK. old." (data error in source)
        $forras = preg_replace('/\bK\.(\s*old\.)/', 'PK.$1', $forras);

        // Three alternatives per match:
        //   Alt A (page-only):  ABBREV + digits + ". old."  → group 2 = page number
        //   Alt B (normal):     ABBREV + digits[/letter]    → group 3 = order number, group 4 = optional page number
        //   Alt C (no-number):  ABBREV + ". old."           → both order_number and page_number null
        preg_match_all(
            '/('.$abbrevPattern.')(?:(\d+)\.\s*old\.|(\d+(?:\/[A-Za-z])?)(?:\s*\((\d+)\.\s*old\.\))?|\.\s*old\.)/u',
            $forras,
            $matches,
            PREG_SET_ORDER,
        );

        foreach ($matches as $match) {
            if (($match[2] ?? '') !== '') {
                // Alt A — page-only: e.g. ÉE1112. old.
                $results[] = [
                    'abbreviation' => $match[1],
                    'order_number' => null,
                    'page_number' => (int) $match[2],
                ];
            } elseif (($match[3] ?? '') !== '') {
                // Alt B — normal: e.g. ÉE523 or ÉE523 (615. old.)
                $results[] = [
                    'abbreviation' => $match[1],
                    'order_number' => $match[3],
                    'page_number' => ($match[4] ?? '') !== '' ? (int) $match[4] : null,
                ];
            } else {
                // Alt C — no number: e.g. PK. old.
                $results[] = [
                    'abbreviation' => $match[1],
                    'order_number' => null,
                    'page_number' => null,
                ];
            }
        }

        return $results;
    }

    private function abort(string $message): int
    {
        $this->error($message);

        return self::FAILURE;
    }
}
