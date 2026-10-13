<?php

use App\Livewire\Pages\ScoreEditor;
use App\Models\Score;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

it('shows the cheatsheets as tables', function () {
    $score = Score::factory()->create();

    Livewire::actingAs($score->user)
        ->test(ScoreEditor::class, ['score' => $score])
        ->assertSeeHtml('<td><code>K:G</code></td>');
});

it('converts a cheatsheet once rather than on every render', function () {
    $score = Score::factory()->create();
    $key = 'docs.html.docs/abc-cheatsheet.md.'.filemtime(base_path('docs/abc-cheatsheet.md'));

    $component = Livewire::actingAs($score->user)->test(ScoreEditor::class, ['score' => $score]);

    expect(Cache::get($key))->toContain('<code>K:G</code>');

    Cache::forever($key, '<p>already converted</p>');

    $component->set('publicationForm.edition_is_free', true)
        ->assertSeeHtml('<p>already converted</p>');
});
