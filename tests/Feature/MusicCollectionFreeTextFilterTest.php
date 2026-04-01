<?php

use App\Livewire\Pages\Editor\MusicsTable;
use App\Models\Collection;
use App\Models\Music;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->assignRole('editor');
    $this->actingAs($this->user);
});

test('collectionFreeText filter finds music by exact alphanumeric order number like 75B', function () {
    $collection = Collection::factory()->create([
        'user_id' => $this->user->id,
        'abbreviation' => 'SZVU',
        'title' => 'Szüveges',
    ]);

    $matchingMusic = Music::factory()->create(['user_id' => $this->user->id]);
    $matchingMusic->collections()->attach($collection->id, ['order_number' => '75B', 'user_id' => $this->user->id]);

    $otherMusic = Music::factory()->create(['user_id' => $this->user->id]);
    $otherMusic->collections()->attach($collection->id, ['order_number' => '76', 'user_id' => $this->user->id]);

    Livewire::test(MusicsTable::class)
        ->set('collectionFreeText', 'SZVU 75B')
        ->assertSee($matchingMusic->title)
        ->assertDontSee($otherMusic->title);
});

test('collectionFreeText filter finds music by numeric prefix without letter suffix', function () {
    $collection = Collection::factory()->create([
        'user_id' => $this->user->id,
        'abbreviation' => 'SZVU',
        'title' => 'Szüveges',
    ]);

    $music75B = Music::factory()->create(['user_id' => $this->user->id]);
    $music75B->collections()->attach($collection->id, ['order_number' => '75B', 'user_id' => $this->user->id]);

    $music75C = Music::factory()->create(['user_id' => $this->user->id]);
    $music75C->collections()->attach($collection->id, ['order_number' => '75C', 'user_id' => $this->user->id]);

    $music750 = Music::factory()->create(['user_id' => $this->user->id]);
    $music750->collections()->attach($collection->id, ['order_number' => '750', 'user_id' => $this->user->id]);

    // "SZVU 75" should match 75B and 75C but NOT 750
    Livewire::test(MusicsTable::class)
        ->set('collectionFreeText', 'SZVU 75')
        ->assertSee($music75B->title)
        ->assertSee($music75C->title)
        ->assertDontSee($music750->title);
});

test('collectionFreeText filter with only abbreviation matches all music in collection', function () {
    $collection = Collection::factory()->create([
        'user_id' => $this->user->id,
        'abbreviation' => 'SZVU',
        'title' => 'Szüveges',
    ]);

    $unrelatedCollection = Collection::factory()->create([
        'user_id' => $this->user->id,
        'abbreviation' => 'OTHER',
        'title' => 'Other',
    ]);

    $musicInCollection = Music::factory()->create(['user_id' => $this->user->id]);
    $musicInCollection->collections()->attach($collection->id, ['order_number' => '75B', 'user_id' => $this->user->id]);

    $musicInOtherCollection = Music::factory()->create(['user_id' => $this->user->id]);
    $musicInOtherCollection->collections()->attach($unrelatedCollection->id, ['order_number' => '1', 'user_id' => $this->user->id]);

    Livewire::test(MusicsTable::class)
        ->set('collectionFreeText', 'SZVU')
        ->assertSee($musicInCollection->title)
        ->assertDontSee($musicInOtherCollection->title);
});
