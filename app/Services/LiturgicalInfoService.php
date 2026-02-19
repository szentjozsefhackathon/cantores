<?php

namespace App\Services;

use App\Support\CacheKey;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LiturgicalInfoService
{
    /**
     * Base URL for the liturgical information API.
     */
    protected const BASE_URL = 'https://szentjozsefhackathon.github.io/napi-lelki-batyu';

    /**
     * Cache TTL in seconds (1 day).
     */
    protected const CACHE_TTL = 86400;

    /**
     * Fetch liturgical information for a specific date.
     *
     * @param  string  $date  Date in Y-m-d format
     * @return array<string, mixed>|null
     */
    public function getForDate(string $date): ?array
    {
        $cacheKey = CacheKey::forModel('liturgical_info', 'date', ['date' => $date]);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($date) {
            return $this->fetchFromApi($date);
        });
    }

    /**
     * Fetch celebration details for a specific date.
     * Returns only the celebrations array from the API response.
     *
     * @param  string  $date  Date in Y-m-d format
     * @return array<int, array<string, mixed>>
     */
    public function getCelebrations(string $date): array
    {
        $data = $this->getForDate($date);

        return $data['celebration'] ?? [];
    }

    /**
     * Find a specific celebration in the API response by name and date.
     *
     * @param  string  $date  Date in Y-m-d format
     * @param  string  $name  Celebration name
     * @param  string  $dateISO  Date in ISO format (Y-m-d)
     * @return array<string, mixed>|null
     */
    public function findCelebration(string $date, string $name, string $dateISO): ?array
    {
        $celebrations = $this->getCelebrations($date);

        foreach ($celebrations as $celebration) {
            $nameMatches = ($celebration['name'] ?? $celebration['title'] ?? null) === $name;
            $dateMatches = ($celebration['dateISO'] ?? null) === $dateISO;

            if ($nameMatches && $dateMatches) {
                return $celebration;
            }
        }

        return null;
    }

    /**
     * Clear the cache for a specific date.
     *
     * @param  string  $date  Date in Y-m-d format
     */
    public function clearCache(string $date): void
    {
        $cacheKey = CacheKey::forModel('liturgical_info', 'date', ['date' => $date]);
        Cache::forget($cacheKey);
    }

    /**
     * Fetch data directly from the API without caching.
     *
     * @param  string  $date  Date in Y-m-d format
     * @return array<string, mixed>|null
     */
    protected function fetchFromApi(string $date): ?array
    {
        try {
            $response = Http::timeout(10)->get(self::BASE_URL."/{$date}.json");

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('Failed to fetch liturgical information', [
                'date' => $date,
                'status' => $response->status(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching liturgical information', [
                'date' => $date,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }
}
