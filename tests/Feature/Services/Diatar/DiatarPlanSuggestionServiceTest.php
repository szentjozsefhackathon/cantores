<?php

use App\Models\Collection;
use App\Models\DiatarBook;
use App\Models\DiatarMusicBinding;
use App\Models\DiatarSlide;
use App\Models\DiatarSong;
use App\Models\DiatarSyncRun;
use App\Models\Music;
use App\Models\MusicPlan;
use App\Models\MusicPlanSlotAssignment;
use App\Models\MusicPlanSlotPlan;
use App\Services\Diatar\DiatarPlanSuggestionService;
use Illuminate\Support\Facades\DB;

function diatarPlanWithMusic(Music $music, int $assignmentCount = 1): MusicPlan
{
    $plan = MusicPlan::factory()->create(['is_private' => false]);
    $slotPlan = MusicPlanSlotPlan::factory()->for($plan)->create(['sequence' => 1]);

    foreach (range(1, $assignmentCount) as $sequence) {
        MusicPlanSlotAssignment::factory()->for($slotPlan)->for($music)->create(['music_sequence' => $sequence]);
    }

    return $plan;
}

function diatarSong(DiatarBook $book, string $reference, string $id = 'AAAABBBB'): DiatarSong
{
    $song = DiatarSong::factory()->for($book, 'book')->create([
        'title' => $reference.' Próbaének',
        'reference' => $reference,
        'source_order' => 1,
    ]);
    DiatarSlide::factory()->for($song, 'song')->create([
        'source_order' => 1,
        'external_id' => $id,
        'verse_name' => '1',
    ]);

    return $song;
}

it('selects an exact reference from the default book', function () {
    DiatarSyncRun::factory()->create();
    $music = Music::factory()->create();
    $collection = Collection::factory()->create(['priority' => 1, 'is_verified' => true]);
    $music->collections()->attach($collection, ['order_number' => '230']);
    $book = DiatarBook::factory()->create(['source_order' => 1]);
    $collection->diatarBooks()->attach($book, ['is_default' => true]);
    $song = diatarSong($book, '230');
    $plan = diatarPlanWithMusic($music);

    $result = app(DiatarPlanSuggestionService::class)->forPlan($plan);

    expect($result['rows'][0]['status'])->toBe('resolved')
        ->and($result['rows'][0]['selected_song_id'])->toBe($song->id)
        ->and($result['rows'][0]['candidates'][0]['label'])->toContain($book->source_path);
});

it('falls back from a subset default to an associated alternative', function () {
    DiatarSyncRun::factory()->create();
    $music = Music::factory()->create();
    $collection = Collection::factory()->create();
    $music->collections()->attach($collection, ['order_number' => '12']);
    $defaultBook = DiatarBook::factory()->create(['source_order' => 1]);
    $alternativeBook = DiatarBook::factory()->create(['source_order' => 2]);
    $collection->diatarBooks()->attach($defaultBook, ['is_default' => true]);
    $collection->diatarBooks()->attach($alternativeBook, ['is_default' => false]);
    $song = diatarSong($alternativeBook, '12');
    $plan = diatarPlanWithMusic($music);

    $result = app(DiatarPlanSuggestionService::class)->forPlan($plan);

    expect($result['rows'][0]['selected_song_id'])->toBe($song->id);
});

it('prefers an active exceptional binding with its curated repeated order', function () {
    DiatarSyncRun::factory()->create();
    $music = Music::factory()->create();
    $book = DiatarBook::factory()->create();
    $song = diatarSong($book, '77');
    $slide = $song->slides()->first();
    $binding = DiatarMusicBinding::factory()->for($music)->for($song, 'song')->create();
    $binding->slides()->createMany([
        ['diatar_slide_id' => $slide->id, 'sequence' => 1],
        ['diatar_slide_id' => $slide->id, 'sequence' => 2],
    ]);
    $plan = diatarPlanWithMusic($music);

    $result = app(DiatarPlanSuggestionService::class)->forPlan($plan);

    expect($result['rows'][0]['selected_song_id'])->toBe($song->id)
        ->and($result['rows'][0]['candidates'][0]['explicit'])->toBeTrue()
        ->and(array_column($result['rows'][0]['candidates'][0]['slides'], 'id'))->toBe([$slide->id, $slide->id]);
});

it('returns an unresolved result for a missing collection reference', function () {
    DiatarSyncRun::factory()->create();
    $music = Music::factory()->create();
    $collection = Collection::factory()->create();
    $music->collections()->attach($collection, ['order_number' => null]);
    $plan = diatarPlanWithMusic($music);

    $result = app(DiatarPlanSuggestionService::class)->forPlan($plan);

    expect($result['rows'][0]['status'])->toBe('unresolved')
        ->and($result['rows'][0]['selected_song_id'])->toBeNull();
});

it('does not choose between indistinguishable songs in one book', function () {
    DiatarSyncRun::factory()->create();
    $music = Music::factory()->create();
    $collection = Collection::factory()->create();
    $music->collections()->attach($collection, ['order_number' => '42']);
    $book = DiatarBook::factory()->create();
    $collection->diatarBooks()->attach($book, ['is_default' => true]);
    diatarSong($book, '42', '1111AAAA');
    $otherSong = DiatarSong::factory()->for($book, 'book')->create([
        'title' => '42 Másik próbaének',
        'reference' => '42',
        'source_order' => 2,
    ]);
    DiatarSlide::factory()->for($otherSong, 'song')->create([
        'source_order' => 1,
        'external_id' => '2222BBBB',
    ]);
    $plan = diatarPlanWithMusic($music);

    $result = app(DiatarPlanSuggestionService::class)->forPlan($plan);

    expect($result['rows'][0]['status'])->toBe('ambiguous')
        ->and($result['rows'][0]['selected_song_id'])->toBeNull()
        ->and($result['rows'][0]['candidates'])->toHaveCount(2)
        ->and(array_column($result['rows'][0]['candidates'], 'song_id'))->toContain($otherSong->id);
});

it('resolves repeated assignments within a fixed query bound', function () {
    DiatarSyncRun::factory()->create();
    $music = Music::factory()->create();
    $collection = Collection::factory()->create();
    $music->collections()->attach($collection, ['order_number' => '5']);
    $book = DiatarBook::factory()->create();
    $collection->diatarBooks()->attach($book, ['is_default' => true]);
    diatarSong($book, '5');
    $plan = diatarPlanWithMusic($music, 25);
    DB::flushQueryLog();
    DB::enableQueryLog();

    $result = app(DiatarPlanSuggestionService::class)->forPlan($plan);

    expect($result['rows'])->toHaveCount(25)
        ->and(count(DB::getQueryLog()))->toBeLessThan(15);
});
