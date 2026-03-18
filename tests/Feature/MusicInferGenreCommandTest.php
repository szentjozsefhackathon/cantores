<?php

use App\Models\Collection;
use App\Models\Genre;
use App\Models\Music;

use function Pest\Laravel\artisan;

it('reports no records when all music already have genres', function () {
    $genre = Genre::factory()->create();
    $music = Music::factory()->create();
    $music->genres()->attach($genre);

    artisan('cantores:music-infer-genre', ['--force' => true])
        ->expectsOutput('No music records found that need genre inference.')
        ->assertSuccessful();
});

it('reports no records when music without genres has no genre-bearing collections', function () {
    $collection = Collection::factory()->create();
    $music = Music::factory()->create();
    $music->collections()->attach($collection);

    artisan('cantores:music-infer-genre', ['--force' => true])
        ->expectsOutput('No music records found that need genre inference.')
        ->assertSuccessful();
});

it('assigns genres from collections to music with no genres', function () {
    $genre = Genre::factory()->create();
    $collection = Collection::factory()->create();
    $collection->genres()->attach($genre);

    $music = Music::factory()->create();
    $music->collections()->attach($collection);

    artisan('cantores:music-infer-genre', ['--force' => true])
        ->assertSuccessful();

    expect($music->genres()->pluck('genres.id'))->toContain($genre->id);
});

it('does not assign genres to music with no collection associations', function () {
    Music::factory()->create();

    artisan('cantores:music-infer-genre', ['--force' => true])
        ->expectsOutput('No music records found that need genre inference.')
        ->assertSuccessful();
});

it('collects genres from multiple collections', function () {
    $genreA = Genre::factory()->create();
    $genreB = Genre::factory()->create();

    $collectionA = Collection::factory()->create();
    $collectionA->genres()->attach($genreA);

    $collectionB = Collection::factory()->create();
    $collectionB->genres()->attach($genreB);

    $music = Music::factory()->create();
    $music->collections()->attach([$collectionA->id, $collectionB->id]);

    artisan('cantores:music-infer-genre', ['--force' => true])
        ->assertSuccessful();

    $assignedIds = $music->genres()->pluck('genres.id');
    expect($assignedIds)->toContain($genreA->id)
        ->and($assignedIds)->toContain($genreB->id);
});

it('dry run does not assign genres', function () {
    $genre = Genre::factory()->create();
    $collection = Collection::factory()->create();
    $collection->genres()->attach($genre);

    $music = Music::factory()->create();
    $music->collections()->attach($collection);

    artisan('cantores:music-infer-genre', ['--dry-run' => true])
        ->expectsOutputToContain('[DRY RUN]')
        ->assertSuccessful();

    expect($music->genres()->count())->toBe(0);
});

it('skips music that already has genres even if its collections have genres', function () {
    $existingGenre = Genre::factory()->create();
    $collectionGenre = Genre::factory()->create();

    $collection = Collection::factory()->create();
    $collection->genres()->attach($collectionGenre);

    $music = Music::factory()->create();
    $music->genres()->attach($existingGenre);
    $music->collections()->attach($collection);

    artisan('cantores:music-infer-genre', ['--force' => true])
        ->expectsOutput('No music records found that need genre inference.')
        ->assertSuccessful();

    expect($music->genres()->pluck('genres.id'))->toContain($existingGenre->id)
        ->and($music->genres()->count())->toBe(1);
});
