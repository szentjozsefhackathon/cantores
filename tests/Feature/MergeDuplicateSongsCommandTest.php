<?php

use App\AuthorType;
use App\Models\Author;
use App\Models\Collection;
use App\Models\Genre;
use App\Models\Music;
use App\Models\MusicScriptureReference;

use function Pest\Laravel\artisan;

/**
 * Create a song attached to the given collections, each with an optional order number.
 *
 * @param  array<int|string, string|null>  $collections  collectionId => orderNumber
 * @param  array<string, mixed>  $attributes
 */
function songInCollections(array $collections, array $attributes = []): Music
{
    $music = Music::factory()->create($attributes + ['custom_id' => null]);

    foreach ($collections as $collectionId => $orderNumber) {
        $music->collections()->attach($collectionId, [
            'order_number' => $orderNumber,
            'user_id' => $music->user_id,
        ]);
    }

    return $music;
}

it('merges songs that share the same set of collections into the oldest', function () {
    $a = Collection::factory()->create();
    $b = Collection::factory()->create();

    $survivor = songInCollections([$a->id => '10', $b->id => '20'], ['title' => 'Keep Me']);
    $duplicate = songInCollections([$a->id => '10', $b->id => '20'], ['title' => 'Drop Me']);

    artisan('cantores:merge-duplicate-songs')->assertSuccessful();

    expect(Music::find($duplicate->id))->toBeNull()
        ->and(Music::find($survivor->id)->title)->toBe('Keep Me');
});

it('does not merge songs with different collection sets', function () {
    $a = Collection::factory()->create();
    $b = Collection::factory()->create();

    $first = songInCollections([$a->id => '1']);
    $second = songInCollections([$a->id => '1', $b->id => '2']);

    artisan('cantores:merge-duplicate-songs')->assertSuccessful();

    expect(Music::find($first->id))->not->toBeNull()
        ->and(Music::find($second->id))->not->toBeNull();
});

it('keeps the survivor value on conflict but absorbs empty fields', function () {
    $collection = Collection::factory()->create();

    $survivor = songInCollections([$collection->id => '5'], ['subtitle' => 'Older Subtitle', 'custom_id' => null]);
    $duplicate = songInCollections([$collection->id => '5'], ['subtitle' => 'Newer Subtitle', 'custom_id' => 'CID-1']);

    artisan('cantores:merge-duplicate-songs')->assertSuccessful();

    $survivor->refresh();
    expect($survivor->subtitle)->toBe('Older Subtitle')
        ->and($survivor->custom_id)->toBe('CID-1')
        ->and(Music::find($duplicate->id))->toBeNull();
});

it('does not group songs that share a collection only without an order number', function () {
    $collection = Collection::factory()->create();

    $first = songInCollections([$collection->id => null], ['title' => 'Song One']);
    $second = songInCollections([$collection->id => null], ['title' => 'Song Two']);

    artisan('cantores:merge-duplicate-songs')->assertSuccessful();

    expect(Music::find($first->id))->not->toBeNull()
        ->and(Music::find($second->id))->not->toBeNull();
});

it('does not merge songs that share a collection under different order numbers', function () {
    $collection = Collection::factory()->create();

    $first = songInCollections([$collection->id => '10']);
    $second = songInCollections([$collection->id => '11']);

    artisan('cantores:merge-duplicate-songs')->assertSuccessful();

    expect(Music::find($first->id))->not->toBeNull()
        ->and(Music::find($second->id))->not->toBeNull();
});

it('repoints authors and absorbs the author type, deduping shared authors', function () {
    $collection = Collection::factory()->create();
    $shared = Author::factory()->create();
    $extra = Author::factory()->create();

    $survivor = songInCollections([$collection->id => '7']);
    $survivor->authors()->attach($shared->id, ['author_type' => null, 'user_id' => $survivor->user_id]);

    $duplicate = songInCollections([$collection->id => '7']);
    $duplicate->authors()->attach($shared->id, ['author_type' => AuthorType::Composer->value, 'user_id' => $duplicate->user_id]);
    $duplicate->authors()->attach($extra->id, ['author_type' => AuthorType::Lyricist->value, 'user_id' => $duplicate->user_id]);

    artisan('cantores:merge-duplicate-songs')->assertSuccessful();

    $authors = $survivor->authors()->get()->keyBy('id');
    expect($authors)->toHaveCount(2)
        ->and($authors[$shared->id]->pivot->author_type)->toBe(AuthorType::Composer)
        ->and($authors[$extra->id]->pivot->author_type)->toBe(AuthorType::Lyricist);
});

it('repoints genres and scripture references without duplicating', function () {
    $collection = Collection::factory()->create();
    $genre = Genre::factory()->create();

    $survivor = songInCollections([$collection->id => '3']);
    MusicScriptureReference::factory()->create(['music_id' => $survivor->id, 'reference' => 'Jn 3,16']);

    $duplicate = songInCollections([$collection->id => '3']);
    $duplicate->genres()->attach($genre->id);
    MusicScriptureReference::factory()->create(['music_id' => $duplicate->id, 'reference' => 'Jn 3,16']);
    MusicScriptureReference::factory()->create(['music_id' => $duplicate->id, 'reference' => 'Zsolt 23']);

    artisan('cantores:merge-duplicate-songs')->assertSuccessful();

    expect($survivor->genres()->pluck('genres.id')->all())->toBe([$genre->id])
        ->and($survivor->scriptureReferences()->pluck('reference')->sort()->values()->all())
        ->toBe(['Jn 3,16', 'Zsolt 23'])
        ->and(MusicScriptureReference::where('music_id', $duplicate->id)->exists())->toBeFalse();
});

it('changes nothing on a dry run', function () {
    $collection = Collection::factory()->create();

    $survivor = songInCollections([$collection->id => '1'], ['title' => 'Keep Me']);
    $duplicate = songInCollections([$collection->id => '1'], ['title' => 'Drop Me']);

    artisan('cantores:merge-duplicate-songs', ['--dry-run' => true])
        ->expectsOutputToContain('Would merge')
        ->assertSuccessful();

    expect(Music::find($survivor->id))->not->toBeNull()
        ->and(Music::find($duplicate->id))->not->toBeNull();
});
