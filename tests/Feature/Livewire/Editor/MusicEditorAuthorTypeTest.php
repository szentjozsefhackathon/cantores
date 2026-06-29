<?php

use App\AuthorType;
use App\Models\Author;
use App\Models\Music;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->music = Music::factory()->create(['user_id' => $this->user->id, 'is_private' => false]);
    $this->author = Author::factory()->create(['user_id' => $this->user->id, 'is_private' => false]);
    $this->actingAs($this->user);
});

test('adds an author with a composer type', function () {
    Livewire::test('pages::editor.music-editor', ['music' => $this->music])
        ->set('selectedAuthorId', $this->author->id)
        ->set('selectedAuthorType', AuthorType::Composer->value)
        ->call('addAuthor')
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: __('Author added.'), type: 'success');

    $this->assertDatabaseHas('author_music', [
        'music_id' => $this->music->id,
        'author_id' => $this->author->id,
        'author_type' => AuthorType::Composer->value,
    ]);
});

test('adds an author without a type', function () {
    Livewire::test('pages::editor.music-editor', ['music' => $this->music])
        ->set('selectedAuthorId', $this->author->id)
        ->call('addAuthor')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('author_music', [
        'music_id' => $this->music->id,
        'author_id' => $this->author->id,
        'author_type' => null,
    ]);
});

test('rejects an invalid author type', function () {
    Livewire::test('pages::editor.music-editor', ['music' => $this->music])
        ->set('selectedAuthorId', $this->author->id)
        ->set('selectedAuthorType', 'arranger')
        ->call('addAuthor')
        ->assertHasErrors('selectedAuthorType');

    $this->assertDatabaseMissing('author_music', [
        'music_id' => $this->music->id,
        'author_id' => $this->author->id,
    ]);
});

test('resets the type field after adding', function () {
    Livewire::test('pages::editor.music-editor', ['music' => $this->music])
        ->set('selectedAuthorId', $this->author->id)
        ->set('selectedAuthorType', AuthorType::Lyricist->value)
        ->call('addAuthor')
        ->assertSet('selectedAuthorId', null)
        ->assertSet('selectedAuthorType', null);
});

test('displays the type icon for a typed author', function () {
    $this->music->authors()->attach($this->author->id, [
        'user_id' => $this->user->id,
        'author_type' => AuthorType::Lyricist->value,
    ]);

    Livewire::test('pages::editor.music-editor', ['music' => $this->music])
        ->assertSeeHtml('M15.707 21.293');
});
