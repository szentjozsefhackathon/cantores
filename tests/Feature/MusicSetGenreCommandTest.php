<?php

use App\Models\Genre;
use App\Models\Music;

use function Pest\Laravel\artisan;

it('leaves music that already has a genre untouched', function () {
    $genre = Genre::factory()->create();
    $other = Genre::factory()->create();
    $music = Music::factory()->create();
    $music->genres()->attach($other);

    artisan('cantores:music-set-genre', ['genre' => $genre->id, '--force' => true])
        ->assertSuccessful();

    expect($music->genres()->pluck('genres.id'))->toEqual(collect([$other->id]));
});

it('fails when the genre cannot be resolved', function () {
    Music::factory()->create();

    artisan('cantores:music-set-genre', ['genre' => 'does-not-exist'])
        ->expectsOutputToContain('Genre not found')
        ->assertFailed();
});

it('assigns the genre to music without any genre', function () {
    $genre = Genre::factory()->create();
    $withGenre = Music::factory()->create();
    $withGenre->genres()->attach($genre);
    $withoutGenre = Music::factory()->create();

    artisan('cantores:music-set-genre', ['genre' => $genre->name, '--force' => true])
        ->assertSuccessful();

    expect($withoutGenre->genres()->pluck('genres.id'))->toContain($genre->id)
        ->and($withGenre->genres()->count())->toBe(1);
});

it('resolves the genre by id', function () {
    $genre = Genre::factory()->create();
    $music = Music::factory()->create();

    artisan('cantores:music-set-genre', ['genre' => (string) $genre->id, '--force' => true])
        ->assertSuccessful();

    expect($music->genres()->pluck('genres.id'))->toContain($genre->id);
});

it('dry run lists the music but assigns nothing', function () {
    $genre = Genre::factory()->create();
    $music = Music::factory()->create();

    artisan('cantores:music-set-genre', ['genre' => $genre->id, '--dry-run' => true])
        ->expectsOutputToContain('[DRY RUN]')
        ->assertSuccessful();

    expect($music->genres()->count())->toBe(0);
});
