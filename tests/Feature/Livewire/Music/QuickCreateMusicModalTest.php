<?php

use App\Facades\GenreContext;
use App\Livewire\Music\QuickCreateMusicModal;
use App\Models\Genre;
use App\Models\User;
use Livewire\Livewire;

it('auto-selects the current genre when opening the modal', function () {
    $user = User::factory()->create();
    $genre = Genre::firstOrCreate(['name' => 'organist']);
    $this->actingAs($user);

    GenreContext::set($genre->id);

    Livewire::test(QuickCreateMusicModal::class)
        ->call('openModal')
        ->assertSet('selectedGenres', [$genre->id]);
});

it('does not pre-select any genre when there is no current genre', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    GenreContext::clear();

    Livewire::test(QuickCreateMusicModal::class)
        ->call('openModal')
        ->assertSet('selectedGenres', []);
});
