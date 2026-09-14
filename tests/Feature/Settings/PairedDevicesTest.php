<?php

use App\Models\DevicePairing;
use App\Models\User;
use App\Services\SessionStore;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('the devices page lists only this user\'s live paired devices', function () {
    $mine = DevicePairing::factory()->claimed($this->user)->create([
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36',
    ]);

    $revoked = DevicePairing::factory()->claimed($this->user)->revoked()->create([
        'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Firefox/121.0',
    ]);

    $someoneElses = DevicePairing::factory()->claimed()->create();

    Livewire::actingAs($this->user)
        ->test('pages::settings.devices')
        ->assertSee($mine->ip_address)
        ->assertDontSee($revoked->ip_address)
        ->assertDontSee($someoneElses->ip_address);
});

test('the devices page says so when nothing is paired', function () {
    Livewire::actingAs($this->user)
        ->test('pages::settings.devices')
        ->assertSee(__('No devices are signed in with a QR code.'));
});

test('logging a device out revokes the pairing', function () {
    $pairing = DevicePairing::factory()->claimed($this->user)->create();

    Livewire::actingAs($this->user)
        ->test('pages::settings.devices')
        ->call('signOut', $pairing->id);

    expect($pairing->fresh()->revoked_at)->not->toBeNull()
        ->and($pairing->fresh()->isLiveDevice())->toBeFalse();
});

test('logging a device out removes its row from the sessions table', function () {
    // The suite runs on the array session driver, so the deletion path is
    // exercised directly rather than through a request.
    config(['session.driver' => 'database']);

    $sessionId = 'a-borrowed-laptop-session';

    DB::table('sessions')->insert([
        'id' => $sessionId,
        'user_id' => $this->user->id,
        'ip_address' => '203.0.113.9',
        'user_agent' => 'Chrome',
        'payload' => base64_encode(serialize([])),
        'last_activity' => now()->getTimestamp(),
    ]);

    $pairing = DevicePairing::factory()->claimed($this->user)->create(['session_id' => $sessionId]);

    Livewire::actingAs($this->user)
        ->test('pages::settings.devices')
        ->call('signOut', $pairing->id);

    expect(DB::table('sessions')->where('id', $sessionId)->exists())->toBeFalse();
});

test('a user cannot log out someone else\'s device', function () {
    $someoneElses = DevicePairing::factory()->claimed()->create();

    Livewire::actingAs($this->user)
        ->test('pages::settings.devices')
        ->call('signOut', $someoneElses->id);

    expect($someoneElses->fresh()->revoked_at)->toBeNull();
});

test('a revoked pairing signs its browser out on the next request', function () {
    $pairing = DevicePairing::factory()->claimed($this->user)->create();

    $this->actingAs($this->user)
        ->withSession([DevicePairing::DEVICE_SESSION_KEY => $pairing->id])
        ->get(route('plan-documents'))
        ->assertOk();

    $pairing->revoke();

    $this->actingAs($this->user)
        ->withSession([DevicePairing::DEVICE_SESSION_KEY => $pairing->id])
        ->get(route('plan-documents'))
        ->assertRedirect(route('home'));

    $this->assertGuest();
});

test('a paired session that browses refreshes last_seen_at', function () {
    $pairing = DevicePairing::factory()->claimed($this->user)->create([
        'last_seen_at' => now()->subHour(),
    ]);

    $this->actingAs($this->user)
        ->withSession([DevicePairing::DEVICE_SESSION_KEY => $pairing->id])
        ->get(route('plan-documents'));

    expect($pairing->fresh()->last_seen_at->diffInSeconds(now()))->toBeLessThan(5);
});

test('the session store leaves other drivers alone', function () {
    config(['session.driver' => 'array']);

    expect(app(SessionStore::class)->isQueryable())->toBeFalse()
        ->and(app(SessionStore::class)->forget('anything'))->toBeFalse();
});
