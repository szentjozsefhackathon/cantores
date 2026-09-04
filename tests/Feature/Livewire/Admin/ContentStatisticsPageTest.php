<?php

use App\Models\Author;
use App\Models\Collection;
use App\Models\Music;
use App\Models\MusicPlan;
use App\Models\Score;
use App\Models\ScorePublication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create();
    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $this->admin->assignRole($adminRole);
});

test('admin can access the content statistics page', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.content-statistics')
        ->assertStatus(200)
        ->assertSee(__('Content Statistics'));
});

test('non-admin cannot access the content statistics page', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::admin.content-statistics')
        ->assertForbidden();
});

test('guest cannot access the content statistics page', function () {
    Livewire::test('pages::admin.content-statistics')
        ->assertForbidden();
});

test('it counts a user\'s public and private content separately without leaking the private content itself', function () {
    $user = User::factory()->create();

    Music::factory()->create(['user_id' => $user->id, 'is_private' => false]);
    Music::factory()->count(2)->create(['user_id' => $user->id, 'is_private' => true]);

    Author::factory()->create(['user_id' => $user->id, 'is_private' => false]);
    Collection::factory()->create(['user_id' => $user->id, 'is_private' => false]);

    MusicPlan::factory()->create(['user_id' => $user->id, 'genre_id' => null, 'is_private' => false]);
    MusicPlan::factory()->create(['user_id' => $user->id, 'genre_id' => null, 'is_private' => true, 'private_notes' => 'top secret plan notes']);

    $publishedScore = Score::factory()->create(['user_id' => $user->id, 'title' => 'A published score']);
    ScorePublication::factory()->of($publishedScore)->approved()->create();

    Score::factory()->create(['user_id' => $user->id, 'title' => 'A private score, never shown']);

    $component = Livewire::actingAs($this->admin)
        ->test('pages::admin.content-statistics');

    $component->assertSee($user->display_name)
        ->assertSee('1') // public music
        ->assertSee('2') // private music
        ->assertDontSee('top secret plan notes')
        ->assertDontSee('A private score, never shown');

    $rows = $component->instance()->rows;
    $row = $rows->firstWhere('id', $user->id);

    expect($row['public_musics_count'])->toBe(1)
        ->and($row['private_musics_count'])->toBe(2)
        ->and($row['public_authors_count'])->toBe(1)
        ->and($row['public_collections_count'])->toBe(1)
        ->and($row['public_music_plans_count'])->toBe(1)
        ->and($row['private_music_plans_count'])->toBe(1)
        ->and($row['published_scores_count'])->toBe(1)
        ->and($row['private_scores_count'])->toBe(1)
        ->and($row['total'])->toBe(9);
});
