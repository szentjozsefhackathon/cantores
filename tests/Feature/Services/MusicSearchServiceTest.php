<?php

use App\Models\Collection;
use App\Models\Genre;
use App\Models\Music;
use App\Models\User;
use App\Services\MusicSearchService;

it('returns all music when search is empty', function () {
    Music::factory()->count(3)->create();
    $service = new MusicSearchService;

    $query = $service->search('');
    $results = $query->get();

    expect($results)->toHaveCount(3);
});

it('searches by title', function () {
    $music1 = Music::factory()->create(['title' => 'Ave Maria']);
    $music2 = Music::factory()->create(['title' => 'Gloria in Excelsis']);
    $service = new MusicSearchService;

    $query = $service->search('Ave');
    $results = $query->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($music1->id);
});

it('searches by subtitle', function () {
    $music1 = Music::factory()->create(['subtitle' => 'for choir']);
    $music2 = Music::factory()->create(['subtitle' => 'for organ']);
    $service = new MusicSearchService;

    $query = $service->search('choir');
    $results = $query->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($music1->id);
});

it('searches by custom ID', function () {
    $music1 = Music::factory()->create(['custom_id' => 'BWV 232']);
    $music2 = Music::factory()->create(['custom_id' => 'KV 626']);
    $service = new MusicSearchService;

    $query = $service->search('BWV');
    $results = $query->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($music1->id);
});

it('searches by collection title', function () {
    $user = User::factory()->create();

    $collection1 = Collection::factory()->create(['title' => 'Choral Collection 1', 'user_id' => $user->id, 'abbreviation' => null]);
    $music1 = Music::factory()->create(['title' => 'Collection Title Test 1', 'user_id' => $user->id]);
    $music1->collections()->attach($collection1->id);
    $collection2 = Collection::factory()->create(['title' => 'Choral Collection 2', 'user_id' => $user->id, 'abbreviation' => 'CC2']);
    $music2 = Music::factory()->create(['title' => 'Collection Title Test 2', 'user_id' => $user->id]);
    $music2->collections()->attach($collection2->id);
    $service = new MusicSearchService;

    $query = $service->search('', ['collection_id' => $collection1->id]);
    $results = $query->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($music1->id);
});

it('searches by collection abbreviation', function () {
    $collection = Collection::factory()->create(['abbreviation' => 'GR']);
    $music = Music::factory()->create();
    $music->collections()->attach($collection->id);
    $service = new MusicSearchService;

    $query = $service->search('GR ');
    $results = $query->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($music->id);
});

it('searches by order number in pivot', function () {
    $collection = Collection::factory()->create();
    $music = Music::factory()->create();
    $music->collections()->attach($collection->id, ['order_number' => '123']);
    $service = new MusicSearchService;

    $query = $service->search('123');
    $results = $query->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($music->id);
});

it('applies genre filter', function () {
    $genre = Genre::factory()->create();
    $musicInGenre = Music::factory()->create();
    $musicInGenre->genres()->attach($genre->id);
    $musicOutside = Music::factory()->create();
    $service = new MusicSearchService;

    $query = $service->search('', ['genre_id' => $genre->id]);
    $results = $query->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($musicInGenre->id);
});

it('applies collection filter', function () {
    $collection = Collection::factory()->create();
    $musicInCollection = Music::factory()->create();
    $musicInCollection->collections()->attach($collection->id);
    $musicOutside = Music::factory()->create();
    $service = new MusicSearchService;

    $query = $service->search('', ['collection_id' => $collection->id]);
    $results = $query->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($musicInCollection->id);
});

it('applies user filter', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $music1 = Music::factory()->create(['user_id' => $user1->id]);
    $music2 = Music::factory()->create(['user_id' => $user2->id]);
    $service = new MusicSearchService;

    $query = $service->search('', ['user_id' => $user1->id]);
    $results = $query->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($music1->id);
});

it('combines text search with filters', function () {
    $collection = Collection::factory()->create(['title' => 'Choral Collection']);
    $music1 = Music::factory()->create(['title' => 'Ave Maria']);
    $music1->collections()->attach($collection->id);
    $music2 = Music::factory()->create(['title' => 'Ave Verum']);
    $music2->collections()->attach($collection->id);
    $music3 = Music::factory()->create(['title' => 'Ave Maria']); // not in collection
    $service = new MusicSearchService;

    $query = $service->search('Ave', ['collection_id' => $collection->id]);
    $results = $query->get();

    expect($results)->toHaveCount(2)
        ->and($results->pluck('id'))->toContain($music1->id, $music2->id)
        ->not->toContain($music3->id);
});

it('returns paginated results', function () {
    Music::factory()->count(20)->create();
    $service = new MusicSearchService;

    $paginator = $service->paginate('', [], [], 10);

    expect($paginator)->toBeInstanceOf(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class)
        ->and($paginator->perPage())->toBe(10)
        ->and($paginator->total())->toBe(20);
});

it('applies search via scope', function () {
    Music::factory()->create(['title' => 'Test Music']);
    Music::factory()->create(['title' => 'Other Music']);
    $service = new MusicSearchService;

    $results = Music::search('Test')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->title)->toBe('Test Music');
});
