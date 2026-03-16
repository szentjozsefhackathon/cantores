<?php

namespace App\Jobs;

use App\Enums\DirektoriumProcessingStatus;
use App\Models\DirektoriumEdition;
use App\Models\DirektoriumEntry;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessDirektoriumJob implements ShouldQueue
{
    use Queueable;

    /**
     * Number of pages to send to Claude in one batch.
     * Smaller batches = more accurate, larger = faster and cheaper.
     */
    private const PAGES_PER_BATCH = 4;

    public int $timeout = 3600; // 1 hour – PDF processing can be slow

    public int $tries = 1;

    public function __construct(
        public readonly DirektoriumEdition $edition,
        public readonly int $startPage = 28,
        public readonly ?int $endPage = 171,
    ) {}

    public function handle(): void
    {
        ini_set('memory_limit', '512M');

        Log::info('Direktorium: job started', ['edition_id' => $this->edition->id, 'year' => $this->edition->year]);

        $this->edition->update([
            'processing_status' => DirektoriumProcessingStatus::Processing,
            'processing_started_at' => now(),
            'processing_error' => null,
        ]);

        try {
            $this->processEdition();

            $this->edition->update([
                'processing_status' => DirektoriumProcessingStatus::Completed,
                'processing_completed_at' => now(),
            ]);

            Log::info('Direktorium: processing completed', [
                'edition_id' => $this->edition->id,
                'entries' => $this->edition->entries()->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Direktorium processing failed', [
                'edition_id' => $this->edition->id,
                'error' => $e->getMessage(),
            ]);

            $this->edition->update([
                'processing_status' => DirektoriumProcessingStatus::Failed,
                'processing_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Split a marker-pdf paginated markdown into a page-number-indexed array.
     * marker-pdf --paginate_output separates pages with: {PAGE_NUMBER}---…--- (48 dashes)
     *
     * @return array<int, string>
     */
    private function parseMarkdownPages(string $markdown): array
    {
        // marker-pdf --paginate_output separates pages with: {PAGE_NUMBER}---…--- (48 dashes)
        // Example: {3}------------------------------------------------
        $parts = preg_split('/\n*\{(\d+)\}-+\n*/u', $markdown, -1, PREG_SPLIT_DELIM_CAPTURE);

        $pages = [];

        // preg_split with DELIM_CAPTURE gives: [before, num, content, num, content, …]
        for ($i = 1; $i < count($parts); $i += 2) {
            $pageNumber = (int) $parts[$i];
            $content = trim($parts[$i + 1] ?? '');
            $pages[$pageNumber] = $content;

            Log::info('Direktorium: page parsed', [
                'page' => $pageNumber,
                'chars' => strlen($content),
                'preview' => mb_substr($content, 0, 80),
            ]);
        }

        return $pages;
    }

    private function processEdition(): void
    {
        $markdown = Storage::disk('private')->get($this->edition->file_path);

        Log::info('Direktorium: parsing markdown', ['file_path' => $this->edition->file_path]);

        $pages = $this->parseMarkdownPages($markdown);
        $totalPages = $pages ? max(array_keys($pages)) : 0;

        Log::info('Direktorium: markdown parsed', ['total_pages' => $totalPages]);

        $rangeStart = max(1, $this->startPage);
        $rangeEnd = min($totalPages, $this->endPage ?? $totalPages);

        $this->edition->update(['total_pages' => $totalPages, 'processed_pages' => 0]);

        $client = new \Anthropic\Client(apiKey: config('services.anthropic.key'));

        // Overlapping sliding window: each batch shares its last page with the next batch's first page,
        // so day entries that span a page boundary are always fully visible in at least one batch.
        // Later batches win via upsert — they have more context and produce more complete entries.
        $processedPages = 0;

        for ($first = $rangeStart; $first <= $rangeEnd; ) {
            $last = min($first + self::PAGES_PER_BATCH, $rangeEnd);

            // Build clean text without page markers — page markers confuse the AI into
            // thinking page breaks are day boundaries. We resolve page numbers in PHP.
            $batchPages = [];
            $batchText = '';
            for ($p = $first; $p <= $last; $p++) {
                if (isset($pages[$p])) {
                    $batchPages[$p] = $pages[$p];
                    $batchText .= "\n\n".$pages[$p];
                }
            }

            Log::info('Direktorium: sending batch to AI', [
                'pages' => "$first-$last",
                'text_length' => strlen($batchText),
            ]);

            $this->processBatch($client, trim($batchText), $first, $last, $batchPages);

            $processedPages = $last;
            $this->edition->update(['processed_pages' => $processedPages]);

            Log::info('Direktorium: batch done', ['pages' => "$first-$last", 'progress' => "$processedPages/$rangeEnd"]);

            if ($last >= $rangeEnd) {
                break;
            }

            $first = $last; // overlap: next batch starts at this batch's last page
        }

        // Only delete old entries after all batches succeed – preserves data if job fails mid-way.
        // When reprocessing a partial range, only delete old entries within that page range
        // so entries from other pages are left untouched.
        $isFullReprocess = $rangeStart === 1 && $rangeEnd === $totalPages;

        $this->edition->entries()
            ->where(function ($q) {
                $q->whereNull('created_at')
                    ->orWhere('created_at', '<', $this->edition->processing_started_at);
            })
            ->when(! $isFullReprocess, function ($q) use ($rangeStart, $rangeEnd) {
                $q->whereBetween('pdf_page_start', [$rangeStart, $rangeEnd]);
            })
            ->delete();
    }

    /**
     * @param  array<int, string>  $batchPages  Page-number-indexed array of page content for this batch
     */
    private function processBatch(\Anthropic\Client $client, string $batchText, int $firstPage, int $lastPage, array $batchPages): void
    {
        $prompt = $this->buildPrompt($batchText, $firstPage, $lastPage);
        $promptPath = $this->savePromptDebug($prompt, $firstPage, $lastPage);

        Log::info('Direktorium: Anthropic prompt saved', [
            'pages' => "$firstPage-$lastPage",
            'path' => $promptPath,
        ]);

        $response = $client->messages->create(
            maxTokens: 20000,
            messages: [['role' => 'user', 'content' => $prompt]],
            model: 'claude-haiku-4-5-20251001',
            temperature: 0.5,
        );

        $rawResponse = $response->content[0]->text ?? '';

        Storage::disk('local')->put(
            "direktorium/debug/edition-{$this->edition->id}/batch-{$firstPage}-{$lastPage}.txt",
            implode("\n\n".str_repeat('=', 80)."\n\n", [
                "EXTRACTED TEXT:\n\n{$batchText}",
                "PROMPT:\n\n{$prompt}",
                "RESPONSE (stop_reason={$response->stopReason}, tokens={$response->usage->outputTokens}):\n\n{$rawResponse}",
            ])
        );

        Log::info('Direktorium: AI response received', [
            'pages' => "$firstPage-$lastPage",
            'stop_reason' => $response->stopReason,
            'output_tokens' => $response->usage->outputTokens,
            'preview' => substr($rawResponse, -200),
        ]);

        $json = $this->extractJson($rawResponse);

        if ($json === null) {
            Log::warning('Direktorium: no JSON found in AI response', [
                'edition_id' => $this->edition->id,
                'pages' => "$firstPage-$lastPage",
                'raw' => substr($rawResponse, 0, 500),
            ]);

            return;
        }

        $entries = json_decode($json, true);

        if (! is_array($entries)) {
            return;
        }

        $rows = [];
        $now = now()->toDateTimeString();

        foreach ($entries as $entryData) {
            $row = $this->buildEntryRow($entryData, $rawResponse, $now);
            if ($row === null) {
                continue;
            }

            // Resolve pdf_page_start/end by matching entry text against original pages
            [$pageStart, $pageEnd] = $this->resolvePageNumbers($row['markdown_text'], $batchPages);
            $row['pdf_page_start'] = $pageStart;
            $row['pdf_page_end'] = $pageEnd;

            $rows[$row['entry_date']] = $row; // keyed by date: last entry in batch wins
        }

        $rows = array_values($rows);

        Log::info('Direktorium: batch upserted', [
            'pages' => "$firstPage-$lastPage",
            'entries' => count($rows),
        ]);

        if (! empty($rows)) {
            DirektoriumEntry::upsert(
                $rows,
                uniqueBy: ['direktorium_edition_id', 'entry_date'],
                update: ['markdown_text', 'pdf_page_start', 'pdf_page_end', 'raw_ai_response', 'updated_at'],
            );
        }
    }

    private function savePromptDebug(string $prompt, int $firstPage, int $lastPage): string
    {
        $path = $this->debugDirectory()."/batch-{$firstPage}-{$lastPage}-prompt.txt";

        Storage::disk('local')->put($path, $prompt);

        return $path;
    }

    private function debugDirectory(): string
    {
        return "direktorium/debug/edition-{$this->edition->id}";
    }

    /**
     * Build a raw array row for bulk insert. Returns null if the entry is invalid.
     * pdf_page_start/end are resolved later via resolvePageNumbers().
     *
     * @return array<string, mixed>|null
     */
    private function buildEntryRow(array $data, string $rawResponse, string $now): ?array
    {
        if (empty($data['entry_date']) || empty($data['markdown_text'])) {
            return null;
        }

        try {
            $date = Carbon::parse($data['entry_date'])->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }

        return [
            'direktorium_edition_id' => $this->edition->id,
            'entry_date' => $date,
            'markdown_text' => $data['markdown_text'],
            'pdf_page_start' => null,
            'pdf_page_end' => null,
            'raw_ai_response' => substr($rawResponse, 0, 65535),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * Find which PDF pages an entry's text spans by matching a snippet against the original page contents.
     *
     * @param  array<int, string>  $batchPages
     * @return array{int|null, int|null} [pdf_page_start, pdf_page_end]
     */
    private function resolvePageNumbers(string $markdownText, array $batchPages): array
    {
        // Take the first 60 chars as a search snippet (enough to be unique, short enough to be resilient)
        $snippet = mb_substr(trim($markdownText), 0, 60);

        if ($snippet === '') {
            return [null, null];
        }

        $startPage = null;
        $endPage = null;

        foreach ($batchPages as $pageNum => $pageContent) {
            if (mb_stripos($pageContent, $snippet) !== false) {
                $startPage = $pageNum;
                break;
            }
        }

        // Check if the entry's text also appears on later pages (for multi-page days)
        if ($startPage !== null) {
            $endSnippet = mb_substr(trim($markdownText), -60);
            foreach (array_reverse($batchPages, true) as $pageNum => $pageContent) {
                if ($pageNum > $startPage && mb_stripos($pageContent, $endSnippet) !== false) {
                    $endPage = $pageNum;
                    break;
                }
            }
        }

        return [$startPage, $endPage];
    }

    private function extractJson(string $text): ?string
    {
        if (preg_match('/```json\s*([\s\S]*?)\s*```/i', $text, $matches)) {
            return $matches[1];
        }

        if (preg_match('/(\[[\s\S]*\])/s', $text, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function buildPrompt(string $batchText, int $firstPage, int $lastPage): string
    {
        $year = $this->edition->year;
        $prevYear = $year - 1;

        if ($firstPage >= 50) {
            $yearHint = "All dates in this text are in {$year}.";
        } else {
            $yearHint = "November and December dates = {$prevYear}. All other months = {$year}.";
        }

        return <<<PROMPT
        You are parsing a Hungarian Catholic Direktorium ({$year} edition).
        {$yearHint}

        TASK: Split the text into one entry per calendar day. Return each day's FULL ORIGINAL TEXT unchanged.

        A new day starts ONLY with a day header: a number followed by a day-of-week abbreviation on the next line
        (e.g. "30.\nVAS", "1.\nHÉ", "8.\nHÉ"). The month name appears at the top or inline between days.
        Every line belongs to the most recent day header above it. Nothing else starts a new day.

        Only include days that are COMPLETE in this text:
        - If the text begins with content that belongs to a day whose header is NOT visible in this text,
          skip that leading fragment entirely — it was fully captured in the previous batch.
        - If the text ends mid-day (the last day has no following day header), skip that trailing fragment
          too — it will be fully captured in the next batch.
        Only return days whose day header AND full content are both present in this text.

        Return a JSON array:
        [{"entry_date": "YYYY-MM-DD", "markdown_text": "full original text of this day"}]

        Return ONLY the JSON array. No calendar days found → return [].

        TEXT:
        {$batchText}
        PROMPT;
    }
}
