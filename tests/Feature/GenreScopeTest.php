<?php

use App\Models\City;
use App\Models\Collection;
use App\Models\FirstName;
use App\Models\Genre;
use App\Models\Music;
use App\Models\MusicPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Clear any existing music plans to ensure clean test state
    MusicPlan::query()->delete();

    $this->organist = Genre::firstOrCreate(['name' => 'organist']);
    $this->guitarist = Genre::firstOrCreate(['name' => 'guitarist']);

    // Create unique city/first_name combos to avoid unique constraint violations
    $this->city1 = City::firstOrCreate(['name' => 'Test City A']);
    $this->city2 = City::firstOrCreate(['name' => 'Test City B']);
    $this->firstName1 = FirstName::firstOrCreate(['name' => 'Test First A'], ['gender' => 'male']);
    $this->firstName2 = FirstName::firstOrCreate(['name' => 'Test First B'], ['gender' => 'female']);
});

test('music plan scope for current genre filters correctly', function () {
    $user = User::factory()->create([
        'city_id' => $this->city1->id,
        'first_name_id' => $this->firstName1->id,
        'current_genre_id' => $this->organist->id,
    ]);
    $otherUser = User::factory()->create([
        'city_id' => $this->city2->id,
        'first_name_id' => $this->firstName2->id,
        'current_genre_id' => $this->guitarist->id,
    ]);

    // Create music plans for each genre, using the same users to avoid duplicate city/first_name combos
    $plan1 = MusicPlan::factory()->create([
        'genre_id' => $this->organist->id,
        'user_id' => $user->id,
    ]);
    $plan2 = MusicPlan::factory()->create([
        'genre_id' => $this->guitarist->id,
        'user_id' => $otherUser->id,
    ]);

    // Acting as user with organist genre
    $this->actingAs($user);
    $organistPlans = MusicPlan::forCurrentGenre()->get();
    expect($organistPlans)->toHaveCount(1);
    expect($organistPlans->first()->id)->toBe($plan1->id);

    // Acting as other user with guitarist genre
    $this->actingAs($otherUser);
    $guitaristPlans = MusicPlan::forCurrentGenre()->get();
    expect($guitaristPlans)->toHaveCount(1);
    expect($guitaristPlans->first()->id)->toBe($plan2->id);
});

test('music scope for current genre filters via many-to-many', function () {
    $user = User::factory()->create([
        'city_id' => $this->city1->id,
        'first_name_id' => $this->firstName1->id,
        'current_genre_id' => $this->organist->id,
    ]);
    $otherUser = User::factory()->create([
        'city_id' => $this->city2->id,
        'first_name_id' => $this->firstName2->id,
        'current_genre_id' => $this->guitarist->id,
    ]);

    $music1 = Music::factory()->create();
    $music2 = Music::factory()->create();
    $music1->genres()->attach($this->organist);
    $music2->genres()->attach($this->guitarist);

    $this->actingAs($user);
    $organistMusic = Music::forCurrentGenre()->get();
    // Includes music without genres (3 from seeder) + music1 with organist genre
    expect($organistMusic)->toHaveCount(4);
    expect($organistMusic->pluck('id'))->toContain($music1->id);

    $this->actingAs($otherUser);
    $guitaristMusic = Music::forCurrentGenre()->get();
    // Includes music without genres (3 from seeder) + music2 with guitarist genre
    expect($guitaristMusic)->toHaveCount(4);
    expect($guitaristMusic->pluck('id'))->toContain($music2->id);
});

test('collection scope for current genre filters via many-to-many', function () {
    $user = User::factory()->create([
        'city_id' => $this->city1->id,
        'first_name_id' => $this->firstName1->id,
        'current_genre_id' => $this->organist->id,
    ]);
    $otherUser = User::factory()->create([
        'city_id' => $this->city2->id,
        'first_name_id' => $this->firstName2->id,
        'current_genre_id' => $this->guitarist->id,
    ]);

    $collection1 = Collection::factory()->create();
    $collection2 = Collection::factory()->create();
    $collection1->genres()->attach($this->organist);
    $collection2->genres()->attach($this->guitarist);

    $this->actingAs($user);
    $organistCollections = Collection::forCurrentGenre()->get();
    // Includes collections without genres (from seeder) + collection1 with organist genre
    expect($organistCollections)->toHaveCount(3);
    expect($organistCollections->pluck('id'))->toContain($collection1->id);

    $this->actingAs($otherUser);
    $guitaristCollections = Collection::forCurrentGenre()->get();
    // Includes collections without genres (from seeder) + collection2 with guitarist genre
    expect($guitaristCollections)->toHaveCount(3);
    expect($guitaristCollections->pluck('id'))->toContain($collection2->id);
});
test('scope returns all when user has no current genre', function () {
    $user = User::factory()->create([
        'city_id' => $this->city1->id,
        'first_name_id' => $this->firstName1->id,
        'current_genre_id' => null,
    ]);

    // Create 4 music plans with various genre_ids (including null)
    MusicPlan::factory()->create(['genre_id' => $this->organist->id]);
    MusicPlan::factory()->create(['genre_id' => $this->guitarist->id]);
    MusicPlan::factory()->create(['genre_id' => null]);
    MusicPlan::factory()->create(['genre_id' => null]);

    $this->actingAs($user);
    $plans = MusicPlan::forCurrentGenre()->get();
    // When user has no current genre, scope returns all music plans
    expect($plans)->toHaveCount(4);
});

test('scope returns all when user is not authenticated and no session genre', function () {
    // Create 4 music plans with various genre_ids (including null)
    MusicPlan::factory()->create(['genre_id' => $this->organist->id]);
    MusicPlan::factory()->create(['genre_id' => $this->guitarist->id]);
    MusicPlan::factory()->create(['genre_id' => null]);
    MusicPlan::factory()->create(['genre_id' => null]);

    $plans = MusicPlan::forCurrentGenre()->get();
    // When no user and no session genre, scope returns all music plans
    expect($plans)->toHaveCount(4);
});

test('scope filters by session genre when user is not authenticated', function () {
    // Set session genre
    session(['current_genre_id' => $this->organist->id]);

    // Create plans for different genres
    $plan1 = MusicPlan::factory()->create(['genre_id' => $this->organist->id]);
    $plan2 = MusicPlan::factory()->create(['genre_id' => $this->guitarist->id]);
    $plan3 = MusicPlan::factory()->create(['genre_id' => null]);

    $plans = MusicPlan::forCurrentGenre()->get();
    expect($plans)->toHaveCount(2); // organist genre + null genre
    expect($plans->pluck('id')->toArray())->toContain($plan1->id);
    expect($plans->pluck('id')->toArray())->toContain($plan3->id);
    expect($plans->pluck('id')->toArray())->not->toContain($plan2->id);
});
