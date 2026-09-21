<?php

use App\Livewire\Pages\QrLogin;
use App\Livewire\Pages\QrLoginApproval;
use App\Models\DevicePairing;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

test('a plain get of the qr page mints no pairing', function () {
    $response = $this->get(route('qr-login'));

    $response->assertOk();

    // The crawler case: nothing is written until Livewire boots.
    expect(DevicePairing::query()->count())->toBe(0);
});

test('booting the component mints exactly one pending pairing', function () {
    Livewire::test(QrLogin::class)->call('startPairing');

    expect(DevicePairing::query()->count())->toBe(1);

    $pairing = DevicePairing::query()->sole();

    expect($pairing->isPending())->toBeTrue()
        ->and($pairing->hasExpired())->toBeFalse()
        ->and(strlen($pairing->token))->toBe(DevicePairing::TOKEN_LENGTH)
        ->and(strlen($pairing->confirmation_code))->toBe(4)
        ->and($pairing->user_id)->toBeNull();
});

test('booting twice in the same browser reuses the same row', function () {
    Livewire::test(QrLogin::class)->call('startPairing')->call('startPairing');

    expect(DevicePairing::query()->count())->toBe(1);
});

test('both qr pages send noindex', function () {
    $this->get(route('qr-login'))->assertSee('noindex, nofollow', false);

    $user = User::factory()->create();
    $pairing = DevicePairing::factory()->create();

    $this->actingAs($user)
        ->get(route('qr-login.approve', ['token' => $pairing->token]))
        ->assertSee('noindex, nofollow', false);
});

test('a signed-in visitor is sent to plan documents instead', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('qr-login'))
        ->assertRedirect(route('plan-documents'));
});

test('polling rotates the token on the same row once it has expired', function () {
    $component = Livewire::test(QrLogin::class)->call('startPairing');

    $pairing = DevicePairing::query()->sole();
    $firstToken = $pairing->token;

    $pairing->forceFill(['expires_at' => Carbon::now()->subMinute()])->save();

    $component->call('checkPairing');

    expect(DevicePairing::query()->count())->toBe(1);

    $rotated = DevicePairing::query()->sole();

    expect($rotated->id)->toBe($pairing->id)
        ->and($rotated->token)->not->toBe($firstToken)
        ->and($rotated->hasExpired())->toBeFalse();
});

test('a scanned pairing stops rotating and asks for confirmation', function () {
    $component = Livewire::test(QrLogin::class)->call('startPairing');

    $pairing = DevicePairing::query()->sole();
    $pairing->markScanned();

    $component->call('checkPairing')
        ->assertSet('scanned', true)
        ->assertSee(__('Waiting for confirmation on your phone…'));

    expect(DevicePairing::query()->sole()->token)->toBe($pairing->token);
});

test('the qr page stops polling once nobody has come for a quarter of an hour', function () {
    $component = Livewire::test(QrLogin::class)->call('startPairing');

    DevicePairing::query()->sole()->forceFill([
        'created_at' => Carbon::now()->subMinutes(QrLogin::ABANDON_MINUTES + 1),
    ])->save();

    $component->call('checkPairing')->assertSet('abandoned', true);
});

test('the approval page sends a signed-out phone to the login screen and back', function () {
    $pairing = DevicePairing::factory()->create();
    $url = route('qr-login.approve', ['token' => $pairing->token]);

    $this->get($url)
        ->assertRedirect(route('login'));

    expect(session('url.intended'))->toBe($url);
});

test('an unknown token is shown as no longer valid', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(QrLoginApproval::class, ['token' => str_repeat('a', 32)])
        ->assertSet('invalid', true)
        ->assertSee(__('This code is no longer valid.'));
});

test('an expired token is shown as no longer valid', function () {
    $user = User::factory()->create();
    $pairing = DevicePairing::factory()->expired()->create();

    Livewire::actingAs($user)
        ->test(QrLoginApproval::class, ['token' => $pairing->token])
        ->assertSet('invalid', true);
});

test('opening the approval page marks the pairing scanned', function () {
    $user = User::factory()->create();
    $pairing = DevicePairing::factory()->create();

    Livewire::actingAs($user)
        ->test(QrLoginApproval::class, ['token' => $pairing->token])
        ->assertSet('invalid', false)
        ->assertSee($pairing->confirmation_code);

    expect($pairing->fresh()->scanned_at)->not->toBeNull();
});

test('approving records the user and the moment', function () {
    $user = User::factory()->create();
    $pairing = DevicePairing::factory()->create();

    Livewire::actingAs($user)
        ->test(QrLoginApproval::class, ['token' => $pairing->token])
        ->call('approve')
        ->assertSet('approved', true)
        ->assertRedirect(route('projection-remote'));

    $pairing->refresh();

    expect($pairing->user_id)->toBe($user->id)
        ->and($pairing->approved_at)->not->toBeNull()
        ->and($pairing->isApproved())->toBeTrue();
});

test('cancelling revokes the pairing', function () {
    $user = User::factory()->create();
    $pairing = DevicePairing::factory()->create();

    Livewire::actingAs($user)
        ->test(QrLoginApproval::class, ['token' => $pairing->token])
        ->call('reject')
        ->assertSet('signedOut', true);

    expect($pairing->fresh()->revoked_at)->not->toBeNull();
});

test('claiming an approved pairing signs the laptop in and lands it on plan documents', function () {
    $user = User::factory()->create();

    $component = Livewire::test(QrLogin::class)->call('startPairing');
    $pairing = DevicePairing::query()->sole();
    $pairing->approveFor($user);

    $component->call('checkPairing')->assertRedirect(route('qr-login.claim'));

    $this->get(route('qr-login.claim'))->assertRedirect(route('plan-documents'));

    $this->assertAuthenticatedAs($user);

    $pairing->refresh();

    expect($pairing->claimed_at)->not->toBeNull()
        ->and($pairing->session_id)->toBe(session()->getId())
        ->and(session(DevicePairing::DEVICE_SESSION_KEY))->toBe($pairing->id);
});

test('a pairing cannot be claimed by a browser that did not request it', function () {
    $user = User::factory()->create();

    DevicePairing::factory()->approved($user)->create();

    $this->get(route('qr-login.claim'))->assertRedirect(route('qr-login'));

    $this->assertGuest();
});

test('a pairing cannot be claimed twice', function () {
    $user = User::factory()->create();

    Livewire::test(QrLogin::class)->call('startPairing');
    $pairing = DevicePairing::query()->sole();
    $pairing->approveFor($user);

    $this->get(route('qr-login.claim'))->assertRedirect(route('plan-documents'));

    $this->post(route('logout'));

    $this->get(route('qr-login.claim'))->assertRedirect(route('qr-login'));

    $this->assertGuest();
});

test('an expired approval cannot be claimed', function () {
    $user = User::factory()->create();

    Livewire::test(QrLogin::class)->call('startPairing');
    $pairing = DevicePairing::query()->sole();
    $pairing->approveFor($user);
    $pairing->forceFill(['expires_at' => Carbon::now()->subMinute()])->save();

    $this->get(route('qr-login.claim'))->assertRedirect(route('qr-login'));

    $this->assertGuest();
});

test('a blocked user cannot claim a pairing', function () {
    $user = User::factory()->create(['blocked' => true]);

    Livewire::test(QrLogin::class)->call('startPairing');
    DevicePairing::query()->sole()->approveFor($user);

    $this->get(route('qr-login.claim'))->assertRedirect(route('qr-login'));

    $this->assertGuest();

    expect(DevicePairing::query()->sole()->revoked_at)->not->toBeNull();
});

test('admin-only login blocks a non-admin claim', function () {
    config(['app.only_admin_login' => true]);

    $user = User::factory()->create();

    Livewire::test(QrLogin::class)->call('startPairing');
    DevicePairing::query()->sole()->approveFor($user);

    $this->get(route('qr-login.claim'))->assertRedirect(route('qr-login'));

    $this->assertGuest();
});

test('last_login_at is set by a qr claim', function () {
    $user = User::factory()->create();

    expect($user->fresh()->last_login_at)->toBeNull();

    Livewire::test(QrLogin::class)->call('startPairing');
    DevicePairing::query()->sole()->approveFor($user);

    $this->get(route('qr-login.claim'));

    expect($user->fresh()->last_login_at)->not->toBeNull();
});

test('a paired session cookie expires when the browser closes', function () {
    $user = User::factory()->create();

    Livewire::test(QrLogin::class)->call('startPairing');
    DevicePairing::query()->sole()->approveFor($user);

    $this->get(route('qr-login.claim'));

    $response = $this->get(route('plan-documents'));

    $cookie = collect($response->headers->getCookies())
        ->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));

    expect($cookie)->not->toBeNull()
        ->and($cookie->getExpiresTime())->toBe(0);
});

test('an ordinary session cookie keeps its real expiry', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    $response = $this->get(route('plan-documents'));

    $cookie = collect($response->headers->getCookies())
        ->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));

    expect($cookie)->not->toBeNull()
        ->and($cookie->getExpiresTime())->toBeGreaterThan(0);
});
