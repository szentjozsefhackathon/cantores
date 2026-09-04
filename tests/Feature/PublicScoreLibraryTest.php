<?php

use App\Enums\ScoreFormat;
use App\Enums\ScoreLicense;
use App\Livewire\Pages\PublicScores;
use App\Models\Music;
use App\Models\Score;
use App\Models\ScorePublication;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\get;

function publishedScore(array $attributes = [], array $publication = []): Score
{
    $score = Score::factory()->create($attributes);

    ScorePublication::factory()->of($score)->approved()->create($publication);

    return $score->fresh();
}

it('lets a guest browse the library', function () {
    $score = publishedScore(['title' => 'Adoro te devote']);

    get(route('public-scores'))
        ->assertOk()
        ->assertSee('Adoro te devote', false);
});

it('lists only approved scores', function () {
    $live = publishedScore(['title' => 'Published Chant']);

    $waiting = Score::factory()->create(['title' => 'Waiting Chant']);
    ScorePublication::factory()->of($waiting)->submitted()->create();

    $gone = Score::factory()->create(['title' => 'Removed Chant']);
    ScorePublication::factory()->of($gone)->takenDown()->create();

    $never = Score::factory()->create(['title' => 'Private Chant']);

    Livewire::test(PublicScores::class)
        ->assertSee($live->title)
        ->assertDontSee('Waiting Chant')
        ->assertDontSee('Removed Chant')
        ->assertDontSee('Private Chant');
});

it('filters by licence', function () {
    publishedScore(['title' => 'Sharealike Piece'], ['license' => ScoreLicense::CcBySa]);
    publishedScore(['title' => 'Domain Piece'], ['license' => ScoreLicense::PublicDomain]);

    Livewire::test(PublicScores::class)
        ->set('license', ScoreLicense::PublicDomain->value)
        ->assertSee('Domain Piece')
        ->assertDontSee('Sharealike Piece');
});

it('finds a published outbound licence as well as the basis', function () {
    publishedScore(['title' => 'My Engraving'], [
        'license' => ScoreLicense::OwnWork,
        'outbound_license' => ScoreLicense::CcBy,
    ]);

    Livewire::test(PublicScores::class)
        ->set('license', ScoreLicense::CcBy->value)
        ->assertSee('My Engraving');
});

it('filters by notation and by title', function () {
    publishedScore(['title' => 'Gregorian Piece', 'format' => ScoreFormat::Gabc]);
    publishedScore(['title' => 'Guitar Piece', 'format' => ScoreFormat::ChordPro]);

    Livewire::test(PublicScores::class)
        ->set('format', ScoreFormat::Gabc->value)
        ->assertSee('Gregorian Piece')
        ->assertDontSee('Guitar Piece');

    Livewire::test(PublicScores::class)
        ->set('search', 'Guitar')
        ->assertSee('Guitar Piece')
        ->assertDontSee('Gregorian Piece');
});

it('finds a score by the title of the music it belongs to', function () {
    $music = Music::factory()->create(['title' => 'Veni Creator Spiritus']);
    publishedScore(['title' => 'Organ setting', 'music_id' => $music->id]);

    Livewire::test(PublicScores::class)
        ->set('search', 'Veni Creator')
        ->assertSee('Organ setting');
});

it('clears its filters', function () {
    publishedScore(['title' => 'Findable Piece']);

    Livewire::test(PublicScores::class)
        ->set('search', 'nothing matches this')
        ->assertDontSee('Findable Piece')
        ->call('resetFilters')
        ->assertSee('Findable Piece');
});

it('is indexable and carries structured data', function () {
    $score = publishedScore(['title' => 'Indexable Chant']);
    $slug = \Illuminate\Support\Str::slug($score->title);

    $response = get(route('public-scores.show', ['score' => $score, 'slug' => $slug]))->assertOk();
    $html = $response->getContent();

    expect($html)->toContain('index, follow')
        ->and($html)->not->toContain('noindex')
        ->and($html)->toContain('application/ld+json')
        ->and($html)->toContain('MusicComposition');
});

it('groups several free scores of the same music into one card', function () {
    $music = Music::factory()->create(['title' => 'Salve Regina']);
    publishedScore(['title' => 'Salve Regina', 'music_id' => $music->id, 'variation_name' => 'Fuvola']);
    publishedScore(['title' => 'Salve Regina', 'music_id' => $music->id, 'variation_name' => 'Kórus']);

    $html = Livewire::test(PublicScores::class)->html();

    // The music heading itself must appear once (not once per score); the
    // title also legitimately shows up elsewhere, e.g. an incipit's alt text.
    expect(substr_count($html, '>Salve Regina<'))->toBe(1)
        ->and($html)->toContain('Fuvola')
        ->and($html)->toContain('Kórus');
});

it('shows the nickname of each score\'s owner', function () {
    $owner = User::factory()->create();
    publishedScore(['title' => 'Attributed Piece', 'user_id' => $owner->id]);

    Livewire::test(PublicScores::class)->assertSee($owner->display_name);
});

it('links a published score from the music page it belongs to', function () {
    $music = Music::factory()->create(['title' => 'Pange Lingua']);
    $published = publishedScore(['title' => 'Chant setting', 'music_id' => $music->id]);
    $private = Score::factory()->create(['title' => 'Unpublished setting', 'music_id' => $music->id]);

    $response = get(route('music-view', $music))->assertOk();

    expect($response->getContent())
        ->toContain('Chant setting')
        ->and($response->getContent())->not->toContain('Unpublished setting');
});
