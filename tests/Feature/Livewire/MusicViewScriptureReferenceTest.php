<?php

use App\Models\Music;
use App\Models\MusicScriptureReference;
use App\Models\User;
use App\ScriptureReferenceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->music = Music::factory()->create(['user_id' => $this->user->id, 'is_private' => false]);
    $this->actingAs($this->user);
});

test('music view displays scripture references with reference and text', function () {
    $reference = MusicScriptureReference::factory()->create([
        'music_id' => $this->music->id,
        'reference_type' => ScriptureReferenceType::Exact->value,
        'reference' => 'Jn 3,16',
        'text' => 'Mert úgy szerette Isten a világot...',
    ]);

    Livewire::test(\App\Livewire\Pages\MusicView::class, ['music' => $this->music])
        ->assertSee(__('Scripture References'))
        ->assertSee($reference->reference)
        ->assertSee($reference->text);
});

test('scripture references link to szentiras.eu in a new window', function () {
    MusicScriptureReference::factory()->create([
        'music_id' => $this->music->id,
        'reference' => 'Jn 3,16',
    ]);

    Livewire::test(\App\Livewire\Pages\MusicView::class, ['music' => $this->music])
        ->assertSeeHtml('href="https://szentiras.eu/'.rawurlencode('Jn 3,16').'"')
        ->assertSeeHtml('target="_blank"')
        ->assertSeeHtml('rel="noopener noreferrer"');
});

test('music view hides scripture references section when none exist', function () {
    Livewire::test(\App\Livewire\Pages\MusicView::class, ['music' => $this->music])
        ->assertDontSee(__('Scripture References'));
});

test('guest can view scripture references on public music', function () {
    $reference = MusicScriptureReference::factory()->create([
        'music_id' => $this->music->id,
        'reference' => 'Jn 3,16',
    ]);

    auth()->logout();

    Livewire::test(\App\Livewire\Pages\MusicView::class, ['music' => $this->music])
        ->assertSee($reference->reference);
});
