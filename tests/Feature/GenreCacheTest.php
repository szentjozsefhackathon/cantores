<?php

use App\Models\Genre;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

test('allCached returns same data as all', function () {
    $genres = Genre::all();
    $cached = Genre::allCached();

    expect($cached->count())->toBe($genres->count());
    expect($cached->pluck('id')->toArray())->toBe($genres->pluck('id')->toArray());
});

test('allCached is cached', function () {
    $firstCall = Genre::allCached();
    $secondCall = Genre::allCached();

    expect($firstCall)->toBe($secondCall);
});

test('findCached returns same as find', function () {
    $genre = Genre::first();
    $cached = Genre::findCached($genre->id);

    expect($cached->id)->toBe($genre->id);
    expect($cached->name)->toBe($genre->name);
});

test('findCached is cached', function () {
    $genre = Genre::first();
    $firstCall = Genre::findCached($genre->id);
    $secondCall = Genre::findCached($genre->id);

    expect($firstCall)->toBe($secondCall);
});

test('optionsCached returns array of id => label', function () {
    $options = Genre::optionsCached();

    expect($options)->toBeArray();
    foreach ($options as $id => $label) {
        expect($id)->toBeInt();
        expect($label)->toBeString();
    }
});

test('optionsCached is cached', function () {
    $firstCall = Genre::optionsCached();
    $secondCall = Genre::optionsCached();

    expect($firstCall)->toBe($secondCall);
});

test('cache is invalidated on genre creation', function () {
    $initialAll = Genre::allCached();
    $initialOptions = Genre::optionsCached();

    $genre = Genre::create(['name' => 'test-cache-invalidation']);

    // After creation, cache should be cleared by observer
    $newAll = Genre::allCached();
    $newOptions = Genre::optionsCached();

    expect($newAll->count())->toBe($initialAll->count() + 1);
    expect($newOptions)->toHaveCount(count($initialOptions) + 1);
});

test('cache is invalidated on genre update', function () {
    $genre = Genre::create(['name' => 'original']);
    $initialOptions = Genre::optionsCached();

    $genre->update(['name' => 'updated']);

    $newOptions = Genre::optionsCached();
    expect($newOptions[$genre->id])->toBe('updated');
});

test('cache is invalidated on genre deletion', function () {
    $genre = Genre::create(['name' => 'to-delete']);
    $initialAll = Genre::allCached();

    $genre->delete();

    $newAll = Genre::allCached();
    expect($newAll->count())->toBe($initialAll->count() - 1);
});
