<?php

use App\Models\Music;
use App\Models\Score;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->editor = User::factory()->create();
    $this->editor->assignRole('editor');
    $this->music = Music::factory()->create(['user_id' => $this->editor->id, 'is_private' => false]);
    $this->actingAs($this->editor);
});

test('editor sees public preview scores section', function () {
    $score = Score::factory()->create([
        'user_id' => $this->editor->id,
        'music_id' => $this->music->id,
        'public_preview' => true,
    ]);

    Livewire::test('pages::editor.music-editor', ['music' => $this->music])
        ->assertSee($score->title)
        ->assertSee(__('Public Preview Scores'));
});

test('editor sees empty state when no public preview scores exist', function () {
    Livewire::test('pages::editor.music-editor', ['music' => $this->music])
        ->assertSee(__('No scores are currently marked as public preview for this music.'));
});

test('editor can revoke public preview on a score', function () {
    $score = Score::factory()->create([
        'user_id' => $this->editor->id,
        'music_id' => $this->music->id,
        'public_preview' => true,
    ]);

    Livewire::test('pages::editor.music-editor', ['music' => $this->music])
        ->call('revokePublicPreview', $score->id)
        ->assertDispatched('public-preview-revoked');

    expect($score->fresh()->public_preview)->toBeFalse();
});

test('revokePublicPreview fails for score not linked to this music', function () {
    $otherMusic = Music::factory()->create();
    $score = Score::factory()->create([
        'user_id' => $this->editor->id,
        'music_id' => $otherMusic->id,
        'public_preview' => true,
    ]);

    Livewire::test('pages::editor.music-editor', ['music' => $this->music])
        ->call('revokePublicPreview', $score->id)
        ->assertStatus(404);
});
