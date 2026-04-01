<?php

use App\Models\Genre;
use App\Models\MusicPlan;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    Genre::firstOrCreate(['name' => 'organist']);
    Genre::firstOrCreate(['name' => 'guitarist']);
    Genre::firstOrCreate(['name' => 'other']);
});

// --- Policy tests ---

test('owner can update genre of their own music plan', function () {
    $owner = User::factory()->create();
    $musicPlan = MusicPlan::factory()->create(['user_id' => $owner->id]);

    expect($owner->can('updateGenre', $musicPlan))->toBeTrue();
});

test('editor can update genre of a published music plan', function () {
    $editor = User::factory()->create();
    $editor->assignRole('editor');

    $musicPlan = MusicPlan::factory()->create(['is_private' => false]);

    expect($editor->can('updateGenre', $musicPlan))->toBeTrue();
});

test('admin can update genre of a published music plan', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $musicPlan = MusicPlan::factory()->create(['is_private' => false]);

    expect($admin->can('updateGenre', $musicPlan))->toBeTrue();
});

test('editor cannot update genre of a private music plan they do not own', function () {
    $editor = User::factory()->create();
    $editor->assignRole('editor');

    $musicPlan = MusicPlan::factory()->create(['is_private' => true]);

    expect($editor->can('updateGenre', $musicPlan))->toBeFalse();
});

test('regular user cannot update genre of another user\'s music plan', function () {
    $user = User::factory()->create();
    $musicPlan = MusicPlan::factory()->create(['is_private' => false]);

    expect($user->can('updateGenre', $musicPlan))->toBeFalse();
});

// --- Livewire genre-select component tests ---

test('owner can save genre via genre-select component', function () {
    $owner = User::factory()->create();
    $musicPlan = MusicPlan::factory()->create(['user_id' => $owner->id, 'genre_id' => null]);
    $organist = Genre::where('name', 'organist')->first();

    Livewire::actingAs($owner)
        ->test('music-plan-editor.genre-select', ['musicPlan' => $musicPlan])
        ->set('genreId', $organist->id)
        ->call('saveGenre')
        ->assertSet('isEditingGenre', false);

    expect($musicPlan->fresh()->genre_id)->toBe($organist->id);
});

test('editor can save genre of a published music plan via genre-select component', function () {
    $editor = User::factory()->create();
    $editor->assignRole('editor');

    $musicPlan = MusicPlan::factory()->create(['is_private' => false, 'genre_id' => null]);
    $guitarist = Genre::where('name', 'guitarist')->first();

    Livewire::actingAs($editor)
        ->test('music-plan-editor.genre-select', ['musicPlan' => $musicPlan])
        ->set('genreId', $guitarist->id)
        ->call('saveGenre')
        ->assertSet('isEditingGenre', false);

    expect($musicPlan->fresh()->genre_id)->toBe($guitarist->id);
});

test('editor cannot save genre of a private music plan via genre-select component', function () {
    $editor = User::factory()->create();
    $editor->assignRole('editor');

    $musicPlan = MusicPlan::factory()->create(['is_private' => true, 'genre_id' => null]);
    $guitarist = Genre::where('name', 'guitarist')->first();

    Livewire::actingAs($editor)
        ->test('music-plan-editor.genre-select', ['musicPlan' => $musicPlan])
        ->set('genreId', $guitarist->id)
        ->call('saveGenre')
        ->assertForbidden();
});

test('unauthorized user cannot save genre via genre-select component', function () {
    $user = User::factory()->create();
    $musicPlan = MusicPlan::factory()->create(['is_private' => false, 'genre_id' => null]);
    $organist = Genre::where('name', 'organist')->first();

    Livewire::actingAs($user)
        ->test('music-plan-editor.genre-select', ['musicPlan' => $musicPlan])
        ->set('genreId', $organist->id)
        ->call('saveGenre')
        ->assertForbidden();
});

// --- Music plan view page tests ---

test('genre-select is shown on view page for editors with a published plan', function () {
    $editor = User::factory()->create();
    $editor->assignRole('editor');

    $musicPlan = MusicPlan::factory()->create(['is_private' => false]);

    $this->actingAs($editor)
        ->get(route('music-plan-view', $musicPlan))
        ->assertOk()
        ->assertSee('Műfaj');
});

test('genre-select is not shown on view page for regular users', function () {
    $user = User::factory()->create();
    $musicPlan = MusicPlan::factory()->create(['is_private' => false]);

    $this->actingAs($user)
        ->get(route('music-plan-view', $musicPlan))
        ->assertOk()
        ->assertDontSee('Műfaj');
});
