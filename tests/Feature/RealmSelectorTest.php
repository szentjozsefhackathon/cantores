<?php

use App\Models\Realm;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    // Create realms
    Realm::factory()->organist()->create();
    Realm::factory()->guitarist()->create();
    Realm::factory()->other()->create();
});

test('component renders with current realm', function () {
    $user = User::factory()->create([
        'current_realm_id' => Realm::where('name', 'organist')->first()->id,
    ]);

    Livewire::actingAs($user)
        ->test('realm-selector')
        ->assertSet('selectedRealmId', $user->current_realm_id)
        ->assertSee('Organist');
});

test('component shows all realm options', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('realm-selector')
        ->assertSee('Organist')
        ->assertSee('Guitarist')
        ->assertSee('Other');
});

test('selecting a realm updates user', function () {
    $user = User::factory()->create(['current_realm_id' => null]);
    $guitarist = Realm::where('name', 'guitarist')->first();

    Livewire::actingAs($user)
        ->test('realm-selector')
        ->set('selectedRealmId', $guitarist->id)
        ->assertSet('selectedRealmId', $guitarist->id);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'current_realm_id' => $guitarist->id,
    ]);
});

test('selecting a realm dispatches event', function () {
    $user = User::factory()->create(['current_realm_id' => null]);
    $organist = Realm::where('name', 'organist')->first();

    Livewire::actingAs($user)
        ->test('realm-selector')
        ->set('selectedRealmId', $organist->id)
        ->assertDispatched('realm-changed', realmId: $organist->id);
});

test('component works when no realm selected', function () {
    $user = User::factory()->create(['current_realm_id' => null]);

    Livewire::actingAs($user)
        ->test('realm-selector')
        ->assertSet('selectedRealmId', null)
        ->assertSee('Select a realm');
});
