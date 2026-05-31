<?php

use App\Livewire\Pages\Editor\MusicsTable;
use App\Models\Music;
use App\Models\Score;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake();
    $this->user = User::factory()->create();
    $this->user->assignRole('editor');
    $this->actingAs($this->user);
});

test('it shows the public preview incipit in the title column of the search table', function () {
    $music = Music::factory()->create(['user_id' => $this->user->id]);
    $score = Score::factory()->create([
        'user_id' => $this->user->id,
        'music_id' => $music->id,
        'public_preview' => true,
    ]);
    Storage::put($score->incipit_path, 'fake-png-data');

    Livewire::test(MusicsTable::class)
        ->assertSee($music->title)
        ->assertSee(route('scores.public-incipit', $score), false);
});

test('it shows the current user own private incipit in the title column', function () {
    $music = Music::factory()->create(['user_id' => $this->user->id]);
    $score = Score::factory()->create([
        'user_id' => $this->user->id,
        'music_id' => $music->id,
        'public_preview' => false,
    ]);
    Storage::put($score->incipit_path, 'fake-png-data');

    Livewire::test(MusicsTable::class)
        ->assertSee($music->title)
        ->assertSee(route('scores.incipit', $score), false);
});

test('it does not show another user private incipit', function () {
    $other = User::factory()->create();
    $music = Music::factory()->create(['user_id' => $other->id]);
    $score = Score::factory()->create([
        'user_id' => $other->id,
        'music_id' => $music->id,
        'public_preview' => false,
    ]);
    Storage::put($score->incipit_path, 'fake-png-data');

    Livewire::test(MusicsTable::class)
        ->assertSee($music->title)
        ->assertDontSee(route('scores.incipit', $score), false);
});

test('it does not show an incipit when the score has no incipit image', function () {
    $music = Music::factory()->create(['user_id' => $this->user->id]);
    $score = Score::factory()->create([
        'user_id' => $this->user->id,
        'music_id' => $music->id,
        'public_preview' => true,
    ]);

    Livewire::test(MusicsTable::class)
        ->assertSee($music->title)
        ->assertDontSee(route('scores.public-incipit', $score), false);
});
