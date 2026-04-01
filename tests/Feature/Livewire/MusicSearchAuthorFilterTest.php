<?php

use App\Livewire\Pages\Editor\MusicsTable;
use App\Models\Author;
use App\Models\Music;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->assignRole('editor');
    $this->actingAs($this->user);
});

test('author free text filter matches partial names', function () {
    $author = Author::factory()->create(['name' => 'Buxtehude']);
    $musicWithAuthor = Music::factory()->create(['user_id' => $this->user->id]);
    $musicWithAuthor->authors()->attach($author);

    $musicWithoutAuthor = Music::factory()->create(['user_id' => $this->user->id]);

    Livewire::test(MusicsTable::class)
        ->set('authorFreeText', 'Buxte')
        ->assertSee($musicWithAuthor->title)
        ->assertDontSee($musicWithoutAuthor->title);
});

test('author free text filter is case insensitive', function () {
    $author = Author::factory()->create(['name' => 'Buxtehude']);
    $musicWithAuthor = Music::factory()->create(['user_id' => $this->user->id]);
    $musicWithAuthor->authors()->attach($author);

    Livewire::test(MusicsTable::class)
        ->set('authorFreeText', 'buxte')
        ->assertSee($musicWithAuthor->title);
});

test('author free text filter with no match shows no results', function () {
    $author = Author::factory()->create(['name' => 'Buxtehude']);
    $musicWithAuthor = Music::factory()->create(['user_id' => $this->user->id]);
    $musicWithAuthor->authors()->attach($author);

    Livewire::test(MusicsTable::class)
        ->set('authorFreeText', 'Mozart')
        ->assertDontSee($musicWithAuthor->title);
});
