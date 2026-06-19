<?php

use App\Models\Music;
use App\Models\MusicScriptureReference;
use App\Models\User;
use App\ScriptureReferenceType;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->music = Music::factory()->create(['user_id' => $this->user->id, 'is_private' => false]);
    $this->actingAs($this->user);
});

function fakeScriptureApi(array $verses, string $canonical = 'Jn 3,16'): void
{
    Http::fake([
        'https://szentiras.eu/api/idezet/*' => Http::response([
            'keres' => ['hivatkozas' => $canonical],
            'valasz' => [
                'versek' => $verses,
                'forditas' => ['rov' => 'SZIT'],
            ],
        ]),
    ]);
}

test('music editor shows existing scripture references', function () {
    $reference = MusicScriptureReference::factory()->create([
        'music_id' => $this->music->id,
        'reference_type' => ScriptureReferenceType::Exact->value,
        'reference' => 'Jn 3,16',
        'text' => 'Mert úgy szerette Isten a világot...',
    ]);

    Livewire::test('pages::editor.music-editor', ['music' => $this->music])
        ->assertSee($reference->reference)
        ->assertSee('Mert úgy szerette Isten a világot...');
});

test('adds a valid scripture reference with fetched text', function () {
    fakeScriptureApi([['szoveg' => 'Mert úgy szerette Isten a világot...']], 'Jn 3,16');

    Livewire::test('pages::editor.music-editor', ['music' => $this->music])
        ->set('newScriptureReferenceType', ScriptureReferenceType::Exact->value)
        ->set('newScriptureReference', 'Jn3,16')
        ->call('addScriptureReference')
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: __('Scripture reference added.'), type: 'success')
        ->assertSet('newScriptureReferenceType', null)
        ->assertSet('newScriptureReference', null);

    $this->assertDatabaseHas('music_scripture_references', [
        'music_id' => $this->music->id,
        'reference_type' => ScriptureReferenceType::Exact->value,
        'reference' => 'Jn 3,16',
        'text' => 'Mert úgy szerette Isten a világot...',
        'user_id' => $this->user->id,
    ]);
});

test('rejects an invalid reference that returns no text', function () {
    fakeScriptureApi([]);

    Livewire::test('pages::editor.music-editor', ['music' => $this->music])
        ->set('newScriptureReferenceType', ScriptureReferenceType::Exact->value)
        ->set('newScriptureReference', 'Zzz9,9')
        ->call('addScriptureReference')
        ->assertDispatched('toast', message: __('Invalid scripture reference, no text was found.'), type: 'error');

    expect($this->music->scriptureReferences()->count())->toBe(0);
});

test('rejects a duplicate scripture reference', function () {
    MusicScriptureReference::factory()->create([
        'music_id' => $this->music->id,
        'reference' => 'Jn 3,16',
    ]);
    fakeScriptureApi([['szoveg' => 'Szöveg.']], 'Jn 3,16');

    Livewire::test('pages::editor.music-editor', ['music' => $this->music])
        ->set('newScriptureReferenceType', ScriptureReferenceType::Exact->value)
        ->set('newScriptureReference', 'Jn3,16')
        ->call('addScriptureReference')
        ->assertDispatched('toast', message: __('This scripture reference has already been added.'), type: 'error');

    expect($this->music->scriptureReferences()->where('reference', 'Jn 3,16')->count())->toBe(1);
});

test('validates the reference type when adding', function () {
    Livewire::test('pages::editor.music-editor', ['music' => $this->music])
        ->set('newScriptureReferenceType', 'invalid_type')
        ->set('newScriptureReference', 'Jn3,16')
        ->call('addScriptureReference')
        ->assertHasErrors(['newScriptureReferenceType' => 'in']);
});

test('validates the reference is required', function () {
    Livewire::test('pages::editor.music-editor', ['music' => $this->music])
        ->set('newScriptureReferenceType', ScriptureReferenceType::Exact->value)
        ->set('newScriptureReference', '')
        ->call('addScriptureReference')
        ->assertHasErrors(['newScriptureReference' => 'required']);
});

test('refreshes the stored text from the api', function () {
    $reference = MusicScriptureReference::factory()->create([
        'music_id' => $this->music->id,
        'reference' => 'Jn 3,16',
        'text' => 'Régi szöveg.',
    ]);
    fakeScriptureApi([['szoveg' => 'Friss szöveg.']], 'Jn 3,16');

    Livewire::test('pages::editor.music-editor', ['music' => $this->music])
        ->call('refreshScriptureReference', $reference->id)
        ->assertDispatched('toast', message: __('Scripture reference refreshed.'), type: 'success');

    $this->assertDatabaseHas('music_scripture_references', [
        'id' => $reference->id,
        'text' => 'Friss szöveg.',
    ]);
});

test('removes a scripture reference', function () {
    $reference = MusicScriptureReference::factory()->create([
        'music_id' => $this->music->id,
    ]);

    Livewire::test('pages::editor.music-editor', ['music' => $this->music])
        ->call('removeScriptureReference', $reference->id)
        ->assertDispatched('toast', message: __('Scripture reference deleted.'), type: 'success');

    $this->assertDatabaseMissing('music_scripture_references', ['id' => $reference->id]);
});

test('requires authorization to add a scripture reference', function () {
    $otherUser = User::factory()->create();
    $otherUser->syncRoles([]);
    fakeScriptureApi([['szoveg' => 'Szöveg.']]);

    Livewire::actingAs($otherUser)
        ->test('pages::editor.music-editor', ['music' => $this->music])
        ->set('newScriptureReferenceType', ScriptureReferenceType::Exact->value)
        ->set('newScriptureReference', 'Jn3,16')
        ->call('addScriptureReference')
        ->assertForbidden();
});

test('requires authorization to remove a scripture reference', function () {
    $otherUser = User::factory()->create();
    $reference = MusicScriptureReference::factory()->create([
        'music_id' => $this->music->id,
    ]);

    Livewire::actingAs($otherUser)
        ->test('pages::editor.music-editor', ['music' => $this->music])
        ->call('removeScriptureReference', $reference->id)
        ->assertForbidden();
});
