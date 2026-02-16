<?php

use App\Models\City;
use App\Models\Collection;
use App\Models\FirstName;
use App\Models\Genre;
use App\Models\Music;
use App\Models\MusicPlan;
use App\Models\User;

beforeEach(function () {
    $this->organist = Genre::factory()->organist()->create();
    $this->guitarist = Genre::factory()->guitarist()->create();

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
    expect($organistMusic)->toHaveCount(1);
    expect($organistMusic->first()->id)->toBe($music1->id);

    $this->actingAs($otherUser);
    $guitaristMusic = Music::forCurrentGenre()->get();
    expect($guitaristMusic)->toHaveCount(1);
    expect($guitaristMusic->first()->id)->toBe($music2->id);
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
    expect($organistCollections)->toHaveCount(1);
    expect($organistCollections->first()->id)->toBe($collection1->id);

    $this->actingAs($otherUser);
    $guitaristCollections = Collection::forCurrentGenre()->get();
    expect($guitaristCollections)->toHaveCount(1);
    expect($guitaristCollections->first()->id)->toBe($collection2->id);
});

test('scope returns all when user has no current genre', function () {
    $user = User::factory()->create([
        'city_id' => $this->city1->id,
        'first_name_id' => $this->firstName1->id,
        'current_genre_id' => null,
    ]);
    MusicPlan::factory()->create(['genre_id' => $this->organist->id]);

    $this->actingAs($user);
    $plans = MusicPlan::forCurrentGenre()->get();
    expect($plans)->toHaveCount(1);
});

test('scope returns all when user is not authenticated and no session genre', function () {
    MusicPlan::factory()->create(['genre_id' => $this->organist->id]);

    $plans = MusicPlan::forCurrentGenre()->get();
    expect($plans)->toHaveCount(1);
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
