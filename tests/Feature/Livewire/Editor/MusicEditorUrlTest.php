<?php

use App\Models\Music;
use App\Models\MusicUrl;
use App\Models\User;
use App\Models\WhitelistRule;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->music = Music::factory()->create(['user_id' => $this->user->id]);
    $this->actingAs($this->user);
});

test('music editor shows existing URLs', function () {
    $url = MusicUrl::factory()->create([
        'music_id' => $this->music->id,
        'label' => 'sheet_music',
        'url' => 'https://example.com/sheet.pdf',
    ]);

    Livewire::test('pages.editor.music-editor', ['music' => $this->music])
        ->assertSee($url->url)
        ->assertSee('Sheet Music');
});

test('adds URL with whitelist validation', function () {
    WhitelistRule::factory()->create([
        'hostname' => 'example.com',
        'path_prefix' => '/music',
        'scheme' => 'https',
        'is_active' => true,
    ]);

    Livewire::test('pages.editor.music-editor', ['music' => $this->music])
        ->set('newUrlLabel', 'sheet_music')
        ->set('newUrl', 'https://example.com/music/song.pdf')
        ->call('addUrl')
        ->assertHasNoErrors()
        ->assertDispatched('url-added');

    $this->assertDatabaseHas('music_urls', [
        'music_id' => $this->music->id,
        'label' => 'sheet_music',
        'url' => 'https://example.com/music/song.pdf',
    ]);
});

test('fails to add non-whitelisted URL', function () {
    WhitelistRule::factory()->create([
        'hostname' => 'example.com',
        'path_prefix' => '/music',
        'scheme' => 'https',
        'is_active' => true,
    ]);

    Livewire::test('pages.editor.music-editor', ['music' => $this->music])
        ->set('newUrlLabel', 'sheet_music')
        ->set('newUrl', 'https://not-allowed.com/music/song.pdf')
        ->call('addUrl')
        ->assertHasErrors(['newUrl' => 'WhitelistedUrl']);
});

test('validates URL format when adding', function () {
    Livewire::test('pages.editor.music-editor', ['music' => $this->music])
        ->set('newUrlLabel', 'sheet_music')
        ->set('newUrl', 'not-a-valid-url')
        ->call('addUrl')
        ->assertHasErrors(['newUrl' => 'url']);
});

test('validates label when adding URL', function () {
    Livewire::test('pages.editor.music-editor', ['music' => $this->music])
        ->set('newUrlLabel', 'invalid_label')
        ->set('newUrl', 'https://example.com/music.pdf')
        ->call('addUrl')
        ->assertHasErrors(['newUrlLabel' => 'in']);
});

test('edits existing URL', function () {
    WhitelistRule::factory()->create([
        'hostname' => 'example.com',
        'path_prefix' => '/music',
        'scheme' => 'https',
        'is_active' => true,
    ]);

    $url = MusicUrl::factory()->create([
        'music_id' => $this->music->id,
        'label' => 'sheet_music',
        'url' => 'https://example.com/music/old.pdf',
    ]);

    Livewire::test('pages.editor.music-editor', ['music' => $this->music])
        ->call('editUrl', $url->id)
        ->assertSet('editingUrlId', $url->id)
        ->assertSet('editingUrlLabel', 'sheet_music')
        ->assertSet('editingUrl', 'https://example.com/music/old.pdf')
        ->set('editingUrl', 'https://example.com/music/new.pdf')
        ->call('updateUrl')
        ->assertHasNoErrors()
        ->assertDispatched('url-updated');

    $this->assertDatabaseHas('music_urls', [
        'id' => $url->id,
        'url' => 'https://example.com/music/new.pdf',
    ]);
});

test('fails to edit URL with non-whitelisted URL', function () {
    WhitelistRule::factory()->create([
        'hostname' => 'example.com',
        'path_prefix' => '/music',
        'scheme' => 'https',
        'is_active' => true,
    ]);

    $url = MusicUrl::factory()->create([
        'music_id' => $this->music->id,
        'label' => 'sheet_music',
        'url' => 'https://example.com/music/old.pdf',
    ]);

    Livewire::test('pages.editor.music-editor', ['music' => $this->music])
        ->call('editUrl', $url->id)
        ->set('editingUrl', 'https://not-allowed.com/music/new.pdf')
        ->call('updateUrl')
        ->assertHasErrors(['editingUrl' => 'WhitelistedUrl']);
});

test('deletes URL', function () {
    $url = MusicUrl::factory()->create([
        'music_id' => $this->music->id,
        'label' => 'sheet_music',
        'url' => 'https://example.com/music.pdf',
    ]);

    Livewire::test('pages.editor.music-editor', ['music' => $this->music])
        ->call('deleteUrl', $url->id)
        ->assertDispatched('url-deleted');

    $this->assertDatabaseMissing('music_urls', ['id' => $url->id]);
});

test('cancels URL editing', function () {
    $url = MusicUrl::factory()->create([
        'music_id' => $this->music->id,
        'label' => 'sheet_music',
        'url' => 'https://example.com/music.pdf',
    ]);

    Livewire::test('pages.editor.music-editor', ['music' => $this->music])
        ->call('editUrl', $url->id)
        ->assertSet('editingUrlId', $url->id)
        ->call('cancelEditUrl')
        ->assertSet('editingUrlId', null)
        ->assertSet('editingUrlLabel', null)
        ->assertSet('editingUrl', null);
});

test('requires authorization to add URL', function () {
    $otherUser = User::factory()->create();
    $this->actingAs($otherUser);

    Livewire::test('pages.editor.music-editor', ['music' => $this->music])
        ->set('newUrlLabel', 'sheet_music')
        ->set('newUrl', 'https://example.com/music.pdf')
        ->call('addUrl')
        ->assertForbidden();
});

test('requires authorization to edit URL', function () {
    $otherUser = User::factory()->create();
    $this->actingAs($otherUser);
    $url = MusicUrl::factory()->create(['music_id' => $this->music->id]);

    Livewire::test('pages.editor.music-editor', ['music' => $this->music])
        ->call('editUrl', $url->id)
        ->assertForbidden();
});

test('requires authorization to delete URL', function () {
    $otherUser = User::factory()->create();
    $this->actingAs($otherUser);
    $url = MusicUrl::factory()->create(['music_id' => $this->music->id]);

    Livewire::test('pages.editor.music-editor', ['music' => $this->music])
        ->call('deleteUrl', $url->id)
        ->assertForbidden();
});

test('handles URL with query parameters and fragments', function () {
    WhitelistRule::factory()->create([
        'hostname' => 'example.com',
        'path_prefix' => '/music',
        'scheme' => 'https',
        'is_active' => true,
    ]);

    Livewire::test('pages.editor.music-editor', ['music' => $this->music])
        ->set('newUrlLabel', 'sheet_music')
        ->set('newUrl', 'https://example.com/music/song.pdf?param=value#section')
        ->call('addUrl')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('music_urls', [
        'music_id' => $this->music->id,
        'url' => 'https://example.com/music/song.pdf?param=value#section',
    ]);
});

test('handles URL with different port when allowed', function () {
    WhitelistRule::factory()->create([
        'hostname' => 'example.com',
        'path_prefix' => '/music',
        'scheme' => 'https',
        'allow_any_port' => true,
        'is_active' => true,
    ]);

    Livewire::test('pages.editor.music-editor', ['music' => $this->music])
        ->set('newUrlLabel', 'sheet_music')
        ->set('newUrl', 'https://example.com:8080/music/song.pdf')
        ->call('addUrl')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('music_urls', [
        'music_id' => $this->music->id,
        'url' => 'https://example.com:8080/music/song.pdf',
    ]);
});

test('rejects URL with non-default port when not allowed', function () {
    WhitelistRule::factory()->create([
        'hostname' => 'example.com',
        'path_prefix' => '/music',
        'scheme' => 'https',
        'allow_any_port' => false,
        'is_active' => true,
    ]);

    Livewire::test('pages.editor.music-editor', ['music' => $this->music])
        ->set('newUrlLabel', 'sheet_music')
        ->set('newUrl', 'https://example.com:8080/music/song.pdf')
        ->call('addUrl')
        ->assertHasErrors(['newUrl' => 'WhitelistedUrl']);
});

test('shows appropriate error message for non-whitelisted URL', function () {
    WhitelistRule::factory()->create([
        'hostname' => 'allowed.com',
        'path_prefix' => '/',
        'scheme' => 'https',
        'is_active' => true,
    ]);

    Livewire::test('pages.editor.music-editor', ['music' => $this->music])
        ->set('newUrlLabel', 'sheet_music')
        ->set('newUrl', 'https://not-allowed.com/path')
        ->call('addUrl')
        ->assertHasErrors(['newUrl'])
        ->assertSee('The URL must be whitelisted');
});

test('resets form after adding URL', function () {
    WhitelistRule::factory()->create([
        'hostname' => 'example.com',
        'path_prefix' => '/music',
        'scheme' => 'https',
        'is_active' => true,
    ]);

    Livewire::test('pages.editor.music-editor', ['music' => $this->music])
        ->set('newUrlLabel', 'sheet_music')
        ->set('newUrl', 'https://example.com/music/song.pdf')
        ->call('addUrl')
        ->assertSet('newUrlLabel', null)
        ->assertSet('newUrl', null);
});

test('handles editing non-existent URL gracefully', function () {
    Livewire::test('pages.editor.music-editor', ['music' => $this->music])
        ->call('editUrl', 9999)
        ->assertSet('editingUrlId', null);
});

test('handles updating non-existent URL gracefully', function () {
    Livewire::test('pages.editor.music-editor', ['music' => $this->music])
        ->set('editingUrlId', 9999)
        ->set('editingUrlLabel', 'sheet_music')
        ->set('editingUrl', 'https://example.com/music.pdf')
        ->call('updateUrl')
        ->assertSet('editingUrlId', null);
});
