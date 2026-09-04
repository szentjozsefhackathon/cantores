<?php

use App\Http\Controllers\SitemapController;
use App\Models\Score;
use App\Models\ScorePublication;
use App\Models\User;
use App\Services\ScorePublicationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

use function Pest\Laravel\get;

beforeEach(function () {
    Cache::forget(SitemapController::CACHE_KEY);
});

function publicScoreUrl(Score $score): string
{
    return route('public-scores.show', [
        'score' => $score,
        'slug' => Str::slug($score->title) ?: 'kotta',
    ]);
}

it('serves xml', function () {
    get(route('sitemap'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml')
        ->assertSee('<urlset', false);
});

it('lists a published score and omits an unpublished one', function () {
    $published = Score::factory()->create(['title' => 'Published Chant']);
    ScorePublication::factory()->of($published)->approved()->create();

    $waiting = Score::factory()->create(['title' => 'Waiting Chant']);
    ScorePublication::factory()->of($waiting)->submitted()->create();

    $response = get(route('sitemap'))->assertOk();

    expect($response->getContent())
        ->toContain(publicScoreUrl($published->fresh()))
        ->and($response->getContent())->not->toContain(publicScoreUrl($waiting->fresh()));
});

it('never advertises a secret link path', function () {
    $content = get(route('sitemap'))->assertOk()->getContent();

    expect($content)->not->toContain('/s/')
        ->and($content)->not->toContain('/share/')
        ->and($content)->not->toContain('/f/');
});

it('drops a score from the sitemap once it is unpublished', function () {
    $score = Score::factory()->create(['title' => 'Temporary Chant']);
    $publication = ScorePublication::factory()->of($score)->submitted()->create();

    $editor = User::factory()->create();
    $editor->assignRole('editor');
    app(ScorePublicationService::class)->approve($publication, $editor);

    expect(get(route('sitemap'))->getContent())->toContain(publicScoreUrl($score->fresh()));

    app(ScorePublicationService::class)->takeDown($publication->fresh(), $editor, 'Complaint.');

    // The service flushes the cache, so this must not be a stale hit.
    expect(get(route('sitemap'))->getContent())->not->toContain(publicScoreUrl($score->fresh()));
});

it('points robots.txt at the sitemap and blocks secret link paths', function () {
    $robots = file_get_contents(public_path('robots.txt'));

    expect($robots)->toContain('Sitemap:')
        ->and($robots)->toContain('Disallow: /s/')
        ->and($robots)->toContain('Disallow: /share/');
});
