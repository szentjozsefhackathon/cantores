<?php

use App\Models\Music;
use App\Models\MusicUrl;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->music = Music::factory()->create(['user_id' => $this->user->id]);
    $this->actingAs($this->user);
});

test('music view displays URLs when present', function () {
    $url = MusicUrl::factory()->create([
        'music_id' => $this->music->id,
        'label' => 'sheet_music',
        'url' => 'https://example.com/sheet.pdf',
    ]);

    Livewire::test(\App\Livewire\Pages\MusicView::class, ['music' => $this->music])
        ->assertSee('External Links')
        ->assertSee($url->url)
        ->assertSee('Sheet Music');
});

test('music view shows no URLs placeholder when empty', function () {
    Livewire::test(\App\Livewire\Pages\MusicView::class, ['music' => $this->music])
        ->assertSee('External Links')
        ->assertSee('No external links available for this music piece.');
});

test('URLs are displayed as clickable links', function () {
    $url = MusicUrl::factory()->create([
        'music_id' => $this->music->id,
        'label' => 'audio',
        'url' => 'https://example.com/audio.mp3',
    ]);

    Livewire::test(\App\Livewire\Pages\MusicView::class, ['music' => $this->music])
        ->assertSeeHtml('href="https://example.com/audio.mp3"')
        ->assertSeeHtml('target="_blank"')
        ->assertSeeHtml('rel="noopener noreferrer"');
});

test('label mapping works correctly for all label types', function ($label, $expectedText) {
    $url = MusicUrl::factory()->create([
        'music_id' => $this->music->id,
        'label' => $label,
        'url' => 'https://example.com/test',
    ]);

    Livewire::test(\App\Livewire\Pages\MusicView::class, ['music' => $this->music])
        ->assertSee($expectedText);
})->with([
    ['sheet_music', 'Sheet Music'],
    ['audio', 'Audio'],
    ['video', 'Video'],
    ['text', 'Text'],
    ['information', 'Information'],
]);

test('URLs are truncated for display', function () {
    $longUrl = 'https://example.com/very/long/path/to/a/document/that/exceeds/the/character/limit/for/display.pdf';
    $url = MusicUrl::factory()->create([
        'music_id' => $this->music->id,
        'label' => 'sheet_music',
        'url' => $longUrl,
    ]);

    Livewire::test(\App\Livewire\Pages\MusicView::class, ['music' => $this->music])
        ->assertSee('...');
});

test('URLs display with correct icons based on label', function ($label, $expectedIcon) {
    $url = MusicUrl::factory()->create([
        'music_id' => $this->music->id,
        'label' => $label,
        'url' => 'https://example.com/test',
    ]);

    Livewire::test(\App\Livewire\Pages\MusicView::class, ['music' => $this->music])
        ->assertSeeHtml($expectedIcon);
})->with([
    ['sheet_music', 'document-text'],
    ['audio', 'music'],
    ['video', 'video-camera'],
    ['text', 'book-open-text'],
    ['information', 'information-circle'],
]);

test('URLs display with correct colors based on label', function ($label, $expectedColor) {
    $url = MusicUrl::factory()->create([
        'music_id' => $this->music->id,
        'label' => $label,
        'url' => 'https://example.com/test',
    ]);

    Livewire::test(\App\Livewire\Pages\MusicView::class, ['music' => $this->music])
        ->assertSeeHtml('text-'.$expectedColor.'-500');
})->with([
    ['sheet_music', 'blue'],
    ['audio', 'green'],
    ['video', 'purple'],
    ['text', 'amber'],
    ['information', 'zinc'],
]);

test('multiple URLs are displayed in grid layout', function () {
    MusicUrl::factory()->count(5)->create(['music_id' => $this->music->id]);

    Livewire::test(\App\Livewire\Pages\MusicView::class, ['music' => $this->music])
        ->assertSeeHtml('grid-cols-1 md:grid-cols-2 lg:grid-cols-3');
});

test('guest can view URLs on public music', function () {
    $music = Music::factory()->create(['is_private' => false]);
    $url = MusicUrl::factory()->create([
        'music_id' => $music->id,
        'label' => 'sheet_music',
        'url' => 'https://example.com/sheet.pdf',
    ]);

    auth()->logout();

    Livewire::test(\App\Livewire\Pages\MusicView::class, ['music' => $music])
        ->assertSee($url->url)
        ->assertSee('Sheet Music');
});

test('guest cannot view URLs on private music', function () {
    $music = Music::factory()->create(['is_private' => true, 'user_id' => $this->user->id]);
    MusicUrl::factory()->create(['music_id' => $music->id]);

    auth()->logout();

    Livewire::test(\App\Livewire\Pages\MusicView::class, ['music' => $music])
        ->assertForbidden();
});

test('non-owner cannot view URLs on private music', function () {
    $otherUser = User::factory()->create();
    $music = Music::factory()->create(['is_private' => true, 'user_id' => $this->user->id]);
    MusicUrl::factory()->create(['music_id' => $music->id]);

    $this->actingAs($otherUser);

    Livewire::test(\App\Livewire\Pages\MusicView::class, ['music' => $music])
        ->assertForbidden();
});

test('owner can view URLs on private music', function () {
    $music = Music::factory()->create(['is_private' => true, 'user_id' => $this->user->id]);
    $url = MusicUrl::factory()->create([
        'music_id' => $music->id,
        'label' => 'sheet_music',
        'url' => 'https://example.com/sheet.pdf',
    ]);

    Livewire::test(\App\Livewire\Pages\MusicView::class, ['music' => $music])
        ->assertSee($url->url);
});

test('admin can view URLs on any music', function () {
    $admin = User::factory()->create();
    $adminRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin->assignRole($adminRole);

    $music = Music::factory()->create(['is_private' => true, 'user_id' => $this->user->id]);
    $url = MusicUrl::factory()->create(['music_id' => $music->id]);

    $this->actingAs($admin);

    Livewire::test(\App\Livewire\Pages\MusicView::class, ['music' => $music])
        ->assertSee($url->url);
});

test('URLs are loaded with music relationship', function () {
    $url = MusicUrl::factory()->create(['music_id' => $this->music->id]);

    // The component loads music with URLs relationship in mount()
    Livewire::test(\App\Livewire\Pages\MusicView::class, ['music' => $this->music])
        ->assertSet('music.urls.0.id', $url->id);
});

test('handles URL with special characters in display', function () {
    $url = MusicUrl::factory()->create([
        'music_id' => $this->music->id,
        'label' => 'sheet_music',
        'url' => 'https://example.com/song%20with%20spaces.pdf?param=value&other=test#section',
    ]);

    Livewire::test(\App\Livewire\Pages\MusicView::class, ['music' => $this->music])
        ->assertSee('song%20with%20spaces.pdf');
});

test('external link icon is shown for each URL', function () {
    $url = MusicUrl::factory()->create(['music_id' => $this->music->id]);

    Livewire::test(\App\Livewire\Pages\MusicView::class, ['music' => $this->music])
        ->assertSeeHtml('external-link');
});
