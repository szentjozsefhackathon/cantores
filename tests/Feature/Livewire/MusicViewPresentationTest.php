<?php

use App\Livewire\Pages\MusicView;
use App\Models\Author;
use App\Models\Collection;
use App\Models\Genre;
use App\Models\Music;
use App\Models\MusicScriptureReference;
use App\Models\MusicUrl;
use App\Models\Score;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\Process\Process;

uses(RefreshDatabase::class);

it('retains public previews while protecting private scores on the detail page', function (string $viewer) {
    Storage::fake();
    $owner = User::factory()->create();
    $music = Music::factory()->create(['is_private' => false]);
    $public = Score::factory()->create(['music_id' => $music->id, 'user_id' => $owner->id, 'public_preview' => true]);
    $private = Score::factory()->create(['music_id' => $music->id, 'user_id' => $owner->id, 'public_preview' => false]);
    Storage::put($public->incipit_path, 'preview');
    Storage::put($private->incipit_path, 'private');

    if ($viewer !== 'guest') {
        $this->actingAs($viewer === 'owner' ? $owner : User::factory()->create());
    }

    $page = Livewire::test(MusicView::class, ['music' => $music])
        ->assertSee($public->publicIncipitUrl(), false);

    if ($viewer === 'owner') {
        $page->assertSee($private->title)->assertSee($private->incipitUrl(), false);
    } else {
        $page->assertDontSee($private->title)->assertDontSee($private->incipitUrl(), false);
    }
})->with(['guest', 'owner', 'other']);

it('shows thumbnails for visible authors and collections without exposing private entries', function () {
    $music = Music::factory()->create(['is_private' => false]);
    $author = Author::factory()->create(['is_private' => false, 'avatar' => 'portrait']);
    $collection = Collection::factory()->create(['is_private' => false, 'cover' => 'cover']);
    $privateAuthor = Author::factory()->create(['is_private' => true, 'avatar' => 'private-portrait']);
    $privateCollection = Collection::factory()->create(['is_private' => true, 'cover' => 'private-cover']);
    $music->authors()->attach([$author->id, $privateAuthor->id]);
    $music->collections()->attach([$collection->id, $privateCollection->id]);

    Livewire::test(MusicView::class, ['music' => $music])
        ->assertSee($author->name)
        ->assertSee($author->avatarThumbUrl(), false)
        ->assertSee($collection->title)
        ->assertSee($collection->coverThumbUrl(), false)
        ->assertDontSee($privateAuthor->name)
        ->assertDontSee($privateAuthor->avatarThumbUrl(), false)
        ->assertDontSee($privateCollection->title)
        ->assertDontSee($privateCollection->coverThumbUrl(), false);
});

it('keeps names and collection locations when thumbnails are missing', function () {
    $music = Music::factory()->create(['is_private' => false]);
    $author = Author::factory()->create(['is_private' => false, 'avatar' => null]);
    $collection = Collection::factory()->create(['is_private' => false, 'cover' => null]);
    $music->authors()->attach($author);
    $music->collections()->attach($collection, ['page_number' => 123, 'order_number' => 45]);

    Livewire::test(MusicView::class, ['music' => $music])
        ->assertSee($author->name)
        ->assertSee($collection->title)
        ->assertSee(__('Page').': 123')
        ->assertSee(__('Order').': 45')
        ->assertDontSee(__('External Links'))
        ->assertDontSee(__('No external links available for this music piece.'));
});

it('orders the page from what identifies the music down to the viewer own content', function () {
    Storage::fake();
    $user = User::factory()->create();
    $this->actingAs($user);
    $music = Music::factory()->create(['user_id' => $user->id, 'is_private' => false]);
    $author = Author::factory()->create(['name' => 'Sillye Jenő', 'is_private' => false]);
    $collection = Collection::factory()->create(['title' => 'Éneklő Egyház – hierarchy fixture', 'is_private' => false]);
    $music->authors()->attach($author);
    $music->genres()->attach(Genre::firstOrCreate(['name' => 'guitarist']));
    $music->collections()->attach($collection);
    MusicUrl::factory()->create(['music_id' => $music->id, 'label' => 'audio', 'url' => 'https://example.com/recording']);
    $score = Score::factory()->abc()->create(['music_id' => $music->id, 'user_id' => $user->id, 'title' => 'Orgonakíséret']);
    Storage::put($score->incipit_path, 'preview');

    $html = Livewire::test(MusicView::class, ['music' => $music])->html();

    $positions = collect([
        $music->title,
        $author->name,
        __('Guitarist'),
        $score->incipitUrl(),
        $collection->title,
        __('External Links'),
        __('My Private Scores'),
    ])->map(function (string $needle) use ($html) {
        expect($html)->toContain($needle);

        return strpos($html, $needle);
    });

    expect($positions->all())->toBe($positions->sort()->values()->all());
});

it('keeps music details readable at mobile and desktop widths', function (string $scenario) {
    if (! getenv('PLAYWRIGHT_MODULE')) {
        $this->markTestSkipped('Set PLAYWRIGHT_MODULE to run browser layout checks.');
    }

    Storage::fake();
    $user = User::factory()->create();
    $this->actingAs($user);
    $music = Music::factory()->create([
        'user_id' => $user->id,
        'title' => 'Krisztus, ki vagy nap és világ',
        'subtitle' => str_repeat('LongSubtitle', 12),
        'is_private' => false,
    ]);
    $music->authors()->attach(Author::factory()->create(['name' => 'Johann Sebastian Bach', 'is_private' => false, 'avatar' => 'portrait']));
    $music->genres()->attach(Genre::firstOrCreate(['name' => 'guitarist']));
    $music->collections()->attach(Collection::factory()->create(['title' => 'Éneklő Egyház – layout fixture', 'is_private' => false, 'cover' => 'cover']), ['page_number' => 123, 'order_number' => 45]);

    foreach (range(1, 12) as $index) {
        $music->collections()->attach(Collection::factory()->create(['title' => 'Layout fixture collection '.$index, 'is_private' => false, 'cover' => null]));
    }

    MusicScriptureReference::factory()->create(['music_id' => $music->id, 'reference' => 'Jn 3,16', 'text' => 'Mert úgy szerette Isten a világot…']);

    if ($scenario === 'with-resources') {
        MusicUrl::factory()->create(['music_id' => $music->id, 'label' => 'sheet_music', 'url' => 'https://example.com/score.pdf']);
        MusicUrl::factory()->create(['music_id' => $music->id, 'label' => 'audio', 'url' => 'https://example.com/recording']);
    }

    $score = Score::factory()->abc()->create(['music_id' => $music->id, 'user_id' => $user->id, 'title' => 'Orgonakíséret']);

    Storage::put($score->incipit_path, 'preview');

    $html = Livewire::test(MusicView::class, ['music' => $music])->html();
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
    $css = file_get_contents(public_path('build/'.$manifest['resources/js/app.js']['css'][0]));
    $document = '<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><style>'.$css.'</style></head><body>'.$html.'</body></html>';
    $process = new Process(['node', base_path('tests/Unit/music-view-layout.cjs')], null, ['MUSIC_VIEW_SCENARIO' => $scenario]);
    $process->setInput($document);
    $process->setTimeout(60);
    $process->run();

    expect($process->getErrorOutput())->toBe('');
    expect($process->isSuccessful())->toBeTrue($process->getOutput());
})->with(['with-resources', 'references-only']);
