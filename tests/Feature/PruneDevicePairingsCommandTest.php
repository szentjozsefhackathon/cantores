<?php

use App\Models\DevicePairing;
use Illuminate\Support\Carbon;

test('expired unclaimed pairings are deleted once the grace hour is up', function () {
    $stale = DevicePairing::factory()->create(['expires_at' => Carbon::now()->subHours(2)]);
    $justExpired = DevicePairing::factory()->create(['expires_at' => Carbon::now()->subMinute()]);
    $current = DevicePairing::factory()->create();

    $this->artisan('cantores:prune-device-pairings')->assertSuccessful();

    expect(DevicePairing::query()->find($stale->id))->toBeNull()
        ->and(DevicePairing::query()->find($justExpired->id))->not->toBeNull()
        ->and(DevicePairing::query()->find($current->id))->not->toBeNull();
});

test('long-revoked pairings are deleted', function () {
    $old = DevicePairing::factory()->claimed()->create(['revoked_at' => Carbon::now()->subDays(8)]);
    $recent = DevicePairing::factory()->claimed()->create(['revoked_at' => Carbon::now()->subDay()]);

    $this->artisan('cantores:prune-device-pairings')->assertSuccessful();

    expect(DevicePairing::query()->find($old->id))->toBeNull()
        ->and(DevicePairing::query()->find($recent->id))->not->toBeNull();
});

test('a claimed pairing whose session went idle is retired', function () {
    $lifetime = (int) config('session.lifetime');

    $idle = DevicePairing::factory()->claimed()->create([
        'last_seen_at' => Carbon::now()->subMinutes($lifetime + 10),
    ]);

    $active = DevicePairing::factory()->claimed()->create([
        'last_seen_at' => Carbon::now()->subMinute(),
    ]);

    $this->artisan('cantores:prune-device-pairings')->assertSuccessful();

    expect($idle->fresh()->revoked_at)->not->toBeNull()
        ->and($active->fresh()->revoked_at)->toBeNull();
});

test('a dry run changes nothing', function () {
    $stale = DevicePairing::factory()->create(['expires_at' => Carbon::now()->subHours(2)]);
    $idle = DevicePairing::factory()->claimed()->create([
        'last_seen_at' => Carbon::now()->subMinutes((int) config('session.lifetime') + 10),
    ]);

    $this->artisan('cantores:prune-device-pairings', ['--dry-run' => true])->assertSuccessful();

    expect(DevicePairing::query()->find($stale->id))->not->toBeNull()
        ->and($idle->fresh()->revoked_at)->toBeNull();
});
