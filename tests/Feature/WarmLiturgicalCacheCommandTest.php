<?php

use App\Support\CacheKey;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\artisan;

beforeEach(function () {
    Cache::flush();
});

test('it warms the cache for today and the configured number of days', function () {
    $this->travelTo('2026-06-01');

    Http::fake([
        'https://szentjozsefhackathon.github.io/napi-lelki-batyu/*' => Http::response(['celebration' => []]),
    ]);

    artisan('liturgical:warm-cache', ['--days' => 2])->assertSuccessful();

    expect(Cache::get(CacheKey::forModel('liturgical_info', 'date', ['date' => '2026-06-01'])))->not->toBeNull();
    expect(Cache::get(CacheKey::forModel('liturgical_info', 'date', ['date' => '2026-06-02'])))->not->toBeNull();
});

test('it fails when the upstream API is unavailable and nothing could be warmed', function () {
    $this->travelTo('2026-06-01');

    Http::fake([
        'https://szentjozsefhackathon.github.io/napi-lelki-batyu/*' => Http::response(null, 500),
    ]);

    artisan('liturgical:warm-cache', ['--days' => 1])->assertFailed();
});
