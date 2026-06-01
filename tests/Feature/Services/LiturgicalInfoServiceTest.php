<?php

use App\Services\LiturgicalInfoService;
use App\Support\CacheKey;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
});

function liturgicalUrl(string $date): string
{
    return "https://szentjozsefhackathon.github.io/napi-lelki-batyu/{$date}.json";
}

test('a successful fetch primes both the fresh and stale caches', function () {
    $date = '2026-06-01';
    Http::fake([
        liturgicalUrl($date) => Http::response(['celebration' => [['name' => 'Pünkösd']]]),
    ]);

    $result = app(LiturgicalInfoService::class)->getForDate($date);

    expect($result)->toBe(['celebration' => [['name' => 'Pünkösd']]]);
    expect(Cache::get(CacheKey::forModel('liturgical_info', 'date', ['date' => $date])))->not->toBeNull();
    expect(Cache::get(CacheKey::forModel('liturgical_info', 'stale', ['date' => $date])))->not->toBeNull();
});

test('a failed fetch falls back to the stale cache', function () {
    $date = '2026-06-01';

    // Prime the stale cache with a previously successful response.
    Cache::put(
        CacheKey::forModel('liturgical_info', 'stale', ['date' => $date]),
        ['celebration' => [['name' => 'Régi adat']]],
        3600,
    );

    Http::fake([
        liturgicalUrl($date) => Http::response(null, 500),
    ]);

    $result = app(LiturgicalInfoService::class)->getForDate($date);

    expect($result)->toBe(['celebration' => [['name' => 'Régi adat']]]);
});

test('a failed fetch without any stale cache returns null and does not poison the fresh cache', function () {
    $date = '2026-06-01';
    Http::fake([
        liturgicalUrl($date) => Http::response(null, 500),
    ]);

    $service = app(LiturgicalInfoService::class);

    expect($service->getForDate($date))->toBeNull();
    expect(Cache::get(CacheKey::forModel('liturgical_info', 'date', ['date' => $date])))->toBeNull();
});

test('the fresh cache is served without hitting the API', function () {
    $date = '2026-06-01';
    Cache::put(
        CacheKey::forModel('liturgical_info', 'date', ['date' => $date]),
        ['celebration' => [['name' => 'Gyors']]],
        3600,
    );
    Http::fake();

    $result = app(LiturgicalInfoService::class)->getForDate($date);

    expect($result)->toBe(['celebration' => [['name' => 'Gyors']]]);
    Http::assertNothingSent();
});

test('force refresh bypasses the fresh cache and refetches', function () {
    $date = '2026-06-01';
    Cache::put(
        CacheKey::forModel('liturgical_info', 'date', ['date' => $date]),
        ['celebration' => [['name' => 'Régi']]],
        3600,
    );
    Http::fake([
        liturgicalUrl($date) => Http::response(['celebration' => [['name' => 'Friss']]]),
    ]);

    $result = app(LiturgicalInfoService::class)->getForDate($date, forceRefresh: true);

    expect($result)->toBe(['celebration' => [['name' => 'Friss']]]);
});
