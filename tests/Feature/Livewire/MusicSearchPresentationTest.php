<?php

use App\Models\Author;
use App\Models\Music;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->assignRole('editor');
    $this->actingAs($this->user);
});

test('music search summarizes long author lists and can add the result', function () {
    $music = Music::factory()->create(['user_id' => $this->user->id]);
    $authors = Author::factory()->count(5)->create();
    $music->authors()->attach($authors);
    $displayAuthors = $music->authors()->get();

    Livewire::test('music-search', ['selectable' => true, 'source' => '-editor'])
        ->assertSee($music->title)
        ->assertSee($displayAuthors[0]->name)
        ->assertSee($displayAuthors[1]->name)
        ->assertDontSee($displayAuthors->pluck('name')->join(', '))
        ->assertSee('+3')
        ->assertSee(__('Add'))
        ->call('selectMusic', $music->id)
        ->assertDispatched('music-selected-editor', musicId: $music->id);
});

test('browsing music without selection does not offer an add action', function () {
    $music = Music::factory()->create(['user_id' => $this->user->id]);

    Livewire::test('music-search')
        ->assertSee($music->title)
        ->assertDontSee(__('Add'));
});

test('music search buttons fit their columns at mobile and desktop widths', function (string $mode) {
    if (! getenv('PLAYWRIGHT_MODULE')) {
        $this->markTestSkipped('Set PLAYWRIGHT_MODULE to the installed Playwright package to run browser layout checks.');
    }

    $music = Music::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Layout fixture '.str_repeat('long title ', 12),
        'subtitle' => str_repeat('LongSubtitle', 15),
        'is_private' => false,
    ]);
    $music->authors()->attach(Author::factory()->count(5)->sequence(fn ($sequence) => ['name' => str_repeat('Long author ', 10).$sequence->index])->create());
    $music->tags()->attach(\App\Models\MusicTag::create(['name' => str_repeat('Long tag ', 10), 'type' => \App\Enums\MusicTagType::Liturgy]));

    $music->collections()->attach(\App\Models\Collection::factory()->create([
        'title' => str_repeat('Long collection ', 12),
        'abbreviation' => null,
        'is_private' => false,
    ]));

    if ($mode === 'manage-compact') {
        $music->update(['title' => 'Short title', 'subtitle' => null]);
        $music->authors()->detach();
        $music->tags()->detach();
        $music->collections()->detach();
    }

    if ($mode !== 'select') {
        $this->user->givePermissionTo('content.edit.published');
    }

    $html = $mode === 'select'
        ? Livewire::test('music-search', ['selectable' => true])->html()
        : Livewire::test(\App\Livewire\Pages\Editor\MusicsTable::class)->html();
    $modal = \Illuminate\Support\Facades\Blade::render(
        '<flux:modal name="layout-test" class="max-w-4xl">{!! $html !!}</flux:modal>',
        ['html' => $html],
    );
    if ($mode !== 'select') {
        $modal = '<main>'.$html.'</main>';
    }
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
    $css = file_get_contents(public_path('build/'.$manifest['resources/js/app.js']['css'][0]));
    $document = '<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><style>'.$css.'</style></head><body>'.$modal.'</body></html>';

    $process = new \Symfony\Component\Process\Process(['node', base_path('tests/Unit/music-search-layout.cjs')]);
    $process->setInput($document);
    $process->setTimeout(60);
    $process->run();

    expect($process->getErrorOutput())->toBe('');
    expect($process->isSuccessful())->toBeTrue($process->getOutput());
})->with(['select', 'manage', 'manage-compact']);

test('advanced music tools are available on the music page instead of search results', function () {
    $music = Music::factory()->create(['user_id' => $this->user->id]);

    Livewire::test(\App\Livewire\Pages\Editor\MusicsTable::class)
        ->assertSee($music->title)
        ->assertDontSee(__('Quick View'))
        ->assertDontSee(__('View Audit Log'))
        ->assertDontSee(__('Report Error'));

    Livewire::test(\App\Livewire\Pages\MusicView::class, ['music' => $music])
        ->assertSee($music->title)
        ->assertSee(__('View Audit Log'))
        ->assertSee(__('Report an Issue'))
        ->assertSeeLivewire(\App\Livewire\Pages\Editor\MusicAuditModal::class);
});
