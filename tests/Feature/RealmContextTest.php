<?php

use App\Facades\RealmContext;
use App\Models\Realm;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

test('realm context returns null when no user and no session', function () {
    // Ensure no authenticated user
    Auth::logout();
    Session::forget('current_realm_id');

    expect(RealmContext::getId())->toBeNull();
    expect(RealmContext::get())->toBeNull();
    expect(RealmContext::hasRealm())->toBeFalse();
});

test('realm context returns user realm when authenticated', function () {
    $realm = Realm::factory()->organist()->create();
    $user = User::factory()->create(['current_realm_id' => $realm->id]);

    $this->actingAs($user);

    expect(RealmContext::getId())->toBe($realm->id);
    expect(RealmContext::get()->id)->toBe($realm->id);
    expect(RealmContext::hasRealm())->toBeTrue();
    expect(RealmContext::label())->toBe($realm->label());
    expect(RealmContext::icon())->toBe($realm->icon());
    expect(RealmContext::color())->toBe($realm->color());
});

test('realm context returns session realm when guest', function () {
    $realm = Realm::factory()->guitarist()->create();
    Session::put('current_realm_id', $realm->id);

    expect(RealmContext::getId())->toBe($realm->id);
    expect(RealmContext::get()->id)->toBe($realm->id);
    expect(RealmContext::hasRealm())->toBeTrue();
});

test('realm context prefers user realm over session', function () {
    $userRealm = Realm::factory()->organist()->create();
    $sessionRealm = Realm::factory()->guitarist()->create();
    $user = User::factory()->create(['current_realm_id' => $userRealm->id]);

    Session::put('current_realm_id', $sessionRealm->id);
    $this->actingAs($user);

    expect(RealmContext::getId())->toBe($userRealm->id);
    expect(RealmContext::get()->id)->toBe($userRealm->id);
});

test('realm context set updates user realm', function () {
    $realm = Realm::factory()->organist()->create();
    $user = User::factory()->create(['current_realm_id' => null]);

    $this->actingAs($user);

    RealmContext::set($realm->id);

    expect($user->fresh()->current_realm_id)->toBe($realm->id);
    expect(Session::has('current_realm_id'))->toBeFalse();
});

test('realm context set updates session when guest', function () {
    $realm = Realm::factory()->guitarist()->create();

    RealmContext::set($realm->id);

    expect(Session::get('current_realm_id'))->toBe($realm->id);
});

test('realm context clear removes realm', function () {
    $realm = Realm::factory()->organist()->create();
    $user = User::factory()->create(['current_realm_id' => $realm->id]);

    $this->actingAs($user);

    RealmContext::clear();

    expect($user->fresh()->current_realm_id)->toBeNull();
    expect(RealmContext::getId())->toBeNull();
});

test('realm context clear removes session realm', function () {
    Session::put('current_realm_id', 5);

    RealmContext::clear();

    expect(Session::has('current_realm_id'))->toBeFalse();
});

test('realm context label returns default when no realm', function () {
    expect(RealmContext::label())->toBe(__('No realm selected'));
});

test('realm context icon and color return null when no realm', function () {
    expect(RealmContext::icon())->toBeNull();
    expect(RealmContext::color())->toBeNull();
});
