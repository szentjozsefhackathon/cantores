<?php

namespace App\Services;

use App\Support\CacheKey;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ScriptureReferenceService
{
    /**
     * Cache TTL in seconds (30 days). A reference's text is stable, so we cache
     * lookups to avoid repeatedly hitting the upstream API.
     */
    protected const CACHE_TTL = 2592000;

    /**
     * Maximum number of characters of scripture text to store.
     */
    protected const MAX_TEXT_LENGTH = 1000;

    /**
     * Look up a scripture reference on the szentiras.eu API.
     *
     * Returns the canonical reference (as normalized by the API) together with
     * the joined verse text. Returns null when the reference is invalid (the API
     * responds with an empty verse list) or when the request fails.
     *
     * @return array{reference: string, text: string}|null
     */
    public function lookup(string $reference): ?array
    {
        $reference = trim($reference);

        if ($reference === '') {
            return null;
        }

        $cacheKey = CacheKey::forModel('scripture_reference', 'lookup', ['ref' => $reference]);

        return Cache::remember($cacheKey, self::CACHE_TTL, fn (): ?array => $this->fetchFromApi($reference));
    }

    /**
     * Fetch a reference directly from the API without caching.
     *
     * @return array{reference: string, text: string}|null
     */
    protected function fetchFromApi(string $reference): ?array
    {
        $baseUrl = rtrim((string) config('services.szentiras.base_url'), '/');

        try {
            $response = Http::timeout(10)
                ->withHeaders(['X-API-Key' => config('services.szentiras.key')])
                ->get($baseUrl.'/idezet/'.rawurlencode($reference));

            if (! $response->successful()) {
                Log::warning('Failed to fetch scripture reference', [
                    'reference' => $reference,
                    'status' => $response->status(),
                ]);

                return null;
            }

            return $this->parseResponse($response->json());
        } catch (\Exception $e) {
            Log::error('Error fetching scripture reference', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Extract the canonical reference and verse text from the API payload.
     *
     * @param  array<string, mixed>|null  $data
     * @return array{reference: string, text: string}|null
     */
    protected function parseResponse(?array $data): ?array
    {
        $verses = $data['valasz']['versek'] ?? [];

        if (empty($verses)) {
            return null;
        }

        $text = trim(implode("\n", array_map(
            static fn (array $verse): string => trim((string) ($verse['szoveg'] ?? '')),
            $verses,
        )));

        if ($text === '') {
            return null;
        }

        if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
            $text = Str::limit($text, self::MAX_TEXT_LENGTH - 1, '…');
        }

        $reference = trim((string) ($data['keres']['hivatkozas'] ?? ''));

        return [
            'reference' => $reference,
            'text' => $text,
        ];
    }
}
