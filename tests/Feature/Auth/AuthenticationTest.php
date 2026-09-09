<?php

use App\Models\User;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('login screen links to registration with a visible card above the login form', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
    $response->assertSee('data-test="register-link"', false);
    $response->assertSee(__('Create an account'));
    $response->assertSee(__('New here?'));

    $content = $response->getContent();

    expect(strpos($content, 'data-test="register-link"'))
        ->toBeLessThan(strpos($content, 'data-test="login-button"'));
});

test('login screen shows a compact header with a single logo and no description', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
    $response->assertSee(__('Log in to your account'));
    $response->assertDontSee(__('Enter your email and password below to log in'));

    expect(substr_count($response->getContent(), 'viewBox="0 0 90 74"'))->toBe(1);

    $response->assertSee('h-[1em] w-[1.22em]', false);
    $response->assertDontSee('w-5 h-4', false);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('last_login_at is updated on successful login', function () {
    $user = User::factory()->create();

    expect($user->fresh()->last_login_at)->toBeNull();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    expect($user->fresh()->last_login_at)->not->toBeNull();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrorsIn('email');

    $this->assertGuest();
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});

test('blocked users cannot authenticate', function () {
    $user = User::factory()->create(['blocked' => true]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrorsIn('email');
    $this->assertGuest();
});

test('admin-only login restricts non-admin users when enabled', function () {
    config(['app.only_admin_login' => true]);

    $user = User::factory()->create(); // non-admin

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrorsIn('email');
    $this->assertGuest();
});

test('admin-only login allows admin users when enabled', function () {
    config(['app.only_admin_login' => true]);

    $user = User::factory()->create();
    $user->assignRole('admin');

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticated();
});
