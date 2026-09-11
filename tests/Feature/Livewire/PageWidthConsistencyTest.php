<?php

use App\Livewire\Pages\AuthorView;
use App\Livewire\Pages\CollectionView;
use App\Livewire\Pages\MusicView;
use App\Livewire\Pages\MyMusicPlans;
use App\Models\Author;
use App\Models\Collection;
use App\Models\Music;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * The header and footer in layouts/app/main.blade.php centre the site chrome in a
 * fixed column. A reading page that picks a wider container makes the menu look
 * indented and the content sprawling, so the pages below share that column.
 */
it('keeps the reading pages as wide as the site chrome', function (string $page) {
    $chrome = file_get_contents(resource_path('views/layouts/app/main.blade.php'));
    expect($chrome)->toContain('lg:max-w-4xl');

    $user = User::factory()->create();
    $this->actingAs($user);

    $component = match ($page) {
        'music' => Livewire::test(MusicView::class, ['music' => Music::factory()->create()]),
        'collection' => Livewire::test(CollectionView::class, ['collection' => Collection::factory()->create()]),
        'author' => Livewire::test(AuthorView::class, ['author' => Author::factory()->create()]),
        'my music plans' => Livewire::test(MyMusicPlans::class),
    };

    $html = $component->html();

    expect($html)->toContain('lg:max-w-4xl');
    expect($html)->not->toContain('max-w-7xl');
})->with(['music', 'collection', 'author', 'my music plans']);
