<?php

use App\Models\Genre;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    // Create genres
    Genre::factory()->organist()->create();
    Genre::factory()->guitarist()->create();
    Genre::factory()->other()->create();
});

test('component renders with current genre', function () {
    $user = User::factory()->create([
        'current_genre_id' => Genre::where('name', 'organist')->first()->id,
    ]);

    Livewire::actingAs($user)
        ->test('genre-selector')
        ->assertSet('selectedGenreId', $user->current_genre_id)
        ->assertSee('Mind');
});

test('component shows all genre options', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('genre-selector')
        ->assertSee('Mind');
});

test('selecting a genre updates user', function () {
    $user = User::factory()->create(['current_genre_id' => null]);
    $guitarist = Genre::where('name', 'guitarist')->first();

    Livewire::actingAs($user)
        ->test('genre-selector')
        ->set('selectedGenreId', $guitarist->id)
        ->assertSet('selectedGenreId', $guitarist->id);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'current_genre_id' => $guitarist->id,
    ]);
});

test('selecting a genre dispatches event', function () {
    $user = User::factory()->create(['current_genre_id' => null]);
    $organist = Genre::where('name', 'organist')->first();

    Livewire::actingAs($user)
        ->test('genre-selector')
        ->set('selectedGenreId', $organist->id)
        ->assertDispatched('genre-changed', genreId: $organist->id);
});

test('component works when no genre selected', function () {
    $user = User::factory()->create(['current_genre_id' => null]);

    Livewire::actingAs($user)
        ->test('genre-selector')
        ->assertSet('selectedGenreId', null)
        ->assertSee('Mind');
});
