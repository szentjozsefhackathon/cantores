<?php

use App\Livewire\Pages\Editor\MusicsTable;
use App\Models\Music;
use App\Models\MusicScriptureReference;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->assignRole('editor');
    $this->actingAs($this->user);
});

test('scriptureFilter matches songs by reference prefix', function () {
    $matching = Music::factory()->create(['user_id' => $this->user->id]);
    MusicScriptureReference::factory()->create([
        'music_id' => $matching->id,
        'reference' => 'Jn 3,16',
    ]);

    $other = Music::factory()->create(['user_id' => $this->user->id]);
    MusicScriptureReference::factory()->create([
        'music_id' => $other->id,
        'reference' => 'Mt 1',
    ]);

    Livewire::test(MusicsTable::class)
        ->set('scriptureFilter', 'Jn 3')
        ->assertSee($matching->title)
        ->assertDontSee($other->title);
});

test('scriptureFilter matches an exact reference', function () {
    $matching = Music::factory()->create(['user_id' => $this->user->id]);
    MusicScriptureReference::factory()->create([
        'music_id' => $matching->id,
        'reference' => 'Jn 3,16',
    ]);

    $other = Music::factory()->create(['user_id' => $this->user->id]);
    MusicScriptureReference::factory()->create([
        'music_id' => $other->id,
        'reference' => 'Jn 4,1',
    ]);

    Livewire::test(MusicsTable::class)
        ->set('scriptureFilter', 'Jn 3,16')
        ->assertSee($matching->title)
        ->assertDontSee($other->title);
});

test('empty scriptureFilter does not filter out songs', function () {
    $withRef = Music::factory()->create(['user_id' => $this->user->id]);
    MusicScriptureReference::factory()->create([
        'music_id' => $withRef->id,
        'reference' => 'Jn 3,16',
    ]);

    $withoutRef = Music::factory()->create(['user_id' => $this->user->id]);

    Livewire::test(MusicsTable::class)
        ->set('scriptureFilter', '')
        ->assertSee($withRef->title)
        ->assertSee($withoutRef->title);
});
