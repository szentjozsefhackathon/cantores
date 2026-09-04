<?php

use App\Enums\ScoreFileRights;
use App\Models\Score;
use App\Models\ScoreFile;
use App\Models\ScorePublication;
use App\Models\User;
use App\Services\ScoreFileStorage;
use App\Services\ScorePublicationService;
use Illuminate\Support\Js;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * A published score with one publishable, rendered file whose page images and
 * source bytes actually exist on the fake disk.
 *
 * @return array{0: Score, 1: ScoreFile}
 */
function publishedScoreWithFile(): array
{
    $score = Score::factory()->create();

    $file = ScoreFile::factory()->published()->ready()->create([
        'score_id' => $score->id,
        'rights' => ScoreFileRights::PublicDomain,
    ]);

    $storage = app(ScoreFileStorage::class);
    $storage->put($file->path, 'source-bytes');
    $storage->put($file->pagePath(1), 'page-one-bytes');

    $publication = ScorePublication::factory()->of($score)->submitted()->create();

    $editor = User::factory()->create();
    $editor->assignRole('editor');
    app(ScorePublicationService::class)->approve($publication, $editor);

    return [$score->fresh(), $file->fresh()];
}

function showUrl(Score $score): string
{
    return route('public-scores.show', ['score' => $score, 'slug' => Str::slug($score->title)]);
}

it('lets a guest read a published score', function () {
    [$score] = publishedScoreWithFile();

    get(showUrl($score))
        ->assertOk()
        ->assertSee($score->title, false);
});

it('lets a guest fetch page images and download the file', function () {
    [$score, $file] = publishedScoreWithFile();

    get(route('public-scores.file.page', ['score' => $score, 'scoreFile' => $file, 'page' => 1]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    $download = get(route('public-scores.file.download', ['score' => $score, 'scoreFile' => $file]))
        ->assertOk();

    expect($download->headers->get('Content-Disposition'))->toContain('attachment')
        ->and($download->headers->get('X-Robots-Tag'))->toBe('noindex');
});

it('does not let a public artifact outlive a takedown in caches', function () {
    [$score, $file] = publishedScoreWithFile();

    $response = get(route('public-scores.file.page', ['score' => $score, 'scoreFile' => $file, 'page' => 1]));
    $cacheControl = $response->headers->get('Cache-Control');

    expect($cacheControl)->toContain('public')
        ->and($cacheControl)->not->toContain('immutable')
        ->and($cacheControl)->toContain('must-revalidate');
});

it('hides every surface of a score that was never nominated', function () {
    $score = Score::factory()->create();
    $file = ScoreFile::factory()->ready()->create(['score_id' => $score->id]);

    get(showUrl($score))->assertNotFound();
    get(route('public-scores.file.page', ['score' => $score, 'scoreFile' => $file, 'page' => 1]))->assertNotFound();
    get(route('public-scores.file.download', ['score' => $score, 'scoreFile' => $file]))->assertNotFound();
});

it('hides a score in every non-public state', function (string $state) {
    $score = Score::factory()->create();
    ScorePublication::factory()->of($score)->{$state}()->create();

    get(showUrl($score))->assertNotFound();
})->with(['submitted', 'rejected', 'withdrawn']);

it('answers 410 for a taken down score so search engines drop it', function () {
    $score = Score::factory()->create();
    ScorePublication::factory()->of($score)->takenDown()->create();

    get(showUrl($score))->assertStatus(410);
});

it('answers 404, never 410, for a taken down score’s files', function () {
    [$score, $file] = publishedScoreWithFile();
    $score->publication->update(['status' => \App\Enums\ScorePublicationStatus::TakenDown]);

    get(route('public-scores.file.page', ['score' => $score->fresh(), 'scoreFile' => $file, 'page' => 1]))
        ->assertNotFound();
    get(route('public-scores.file.download', ['score' => $score->fresh(), 'scoreFile' => $file]))
        ->assertNotFound();
});

it('hides a file of a published score that was not flagged for publication', function () {
    [$score] = publishedScoreWithFile();

    $private = ScoreFile::factory()->ready()->create([
        'score_id' => $score->id,
        'rights' => ScoreFileRights::LicensedCopy,
        'is_published' => false,
    ]);

    get(route('public-scores.file.page', ['score' => $score, 'scoreFile' => $private, 'page' => 1]))->assertNotFound();
    get(route('public-scores.file.download', ['score' => $score, 'scoreFile' => $private]))->assertNotFound();
});

it('hides a flagged file whose declared rights forbid publication', function () {
    [$score] = publishedScoreWithFile();

    $bought = ScoreFile::factory()->published()->ready()->create([
        'score_id' => $score->id,
        'rights' => ScoreFileRights::LicensedCopy,
    ]);

    get(route('public-scores.file.download', ['score' => $score->fresh(), 'scoreFile' => $bought]))->assertNotFound();
});

it('refuses a file that belongs to another score', function () {
    [$score] = publishedScoreWithFile();
    [, $otherFile] = publishedScoreWithFile();

    get(route('public-scores.file.download', ['score' => $score, 'scoreFile' => $otherFile]))->assertNotFound();
});

it('refuses a page beyond the render', function () {
    [$score, $file] = publishedScoreWithFile();

    get(route('public-scores.file.page', ['score' => $score, 'scoreFile' => $file, 'page' => 99]))->assertNotFound();
});

it('turns a live url into a 404 the moment the score is withdrawn', function () {
    [$score, $file] = publishedScoreWithFile();

    get(route('public-scores.file.download', ['score' => $score, 'scoreFile' => $file]))->assertOk();

    app(ScorePublicationService::class)->withdraw($score->publication);

    get(route('public-scores.file.download', ['score' => $score->fresh(), 'scoreFile' => $file]))->assertNotFound();
    get(showUrl($score->fresh()))->assertNotFound();
});

it('does not widen the authenticated file routes for a non-owner', function () {
    [$score, $file] = publishedScoreWithFile();
    $stranger = User::factory()->create();

    actingAs($stranger)
        ->get(route('scores.file.download', ['score' => $score, 'scoreFile' => $file]))
        ->assertForbidden();
});

it('redirects to the canonical slug', function () {
    [$score] = publishedScoreWithFile();

    get(route('public-scores.show', ['score' => $score, 'slug' => 'wrong-slug']))
        ->assertRedirect(showUrl($score));

    get(route('public-scores.show', ['score' => $score]))
        ->assertRedirect(showUrl($score));
});

/**
 * A nomination waiting in the review queue, with one offered file whose bytes
 * exist on the fake disk.
 *
 * @return array{0: Score, 1: ScoreFile}
 */
function nominatedScoreWithFile(): array
{
    $score = Score::factory()->create(['title' => 'Nominated Chant']);

    $file = ScoreFile::factory()->published()->ready()->create([
        'score_id' => $score->id,
        'rights' => ScoreFileRights::PublicDomain,
    ]);

    $storage = app(ScoreFileStorage::class);
    $storage->put($file->path, 'source-bytes');
    $storage->put($file->pagePath(1), 'page-one-bytes');

    ScorePublication::factory()->of($score)->submitted()->create();

    return [$score->fresh(), $file->fresh()];
}

function publicationReviewer(): User
{
    $user = User::factory()->create();
    $user->assignRole('editor');

    return $user;
}

it('lets a reviewer read the public page of a nomination that is not live yet', function () {
    [$score] = nominatedScoreWithFile();

    actingAs(publicationReviewer())
        ->get(showUrl($score))
        ->assertOk()
        ->assertSee('Nominated Chant', false)
        ->assertSee('noindex, nofollow', false);
});

it('lets a reviewer fetch the pages and files of a nomination', function () {
    [$score, $file] = nominatedScoreWithFile();

    actingAs(publicationReviewer())
        ->get(route('public-scores.file.page', ['score' => $score, 'scoreFile' => $file, 'page' => 1]))
        ->assertOk();

    actingAs(publicationReviewer())
        ->get(route('public-scores.file.download', ['score' => $score, 'scoreFile' => $file]))
        ->assertOk();
});

it('lets a reviewer read a taken down score so they can judge a restore', function () {
    [$score, $file] = publishedScoreWithFile();
    $score->publication->update(['status' => \App\Enums\ScorePublicationStatus::TakenDown]);

    actingAs(publicationReviewer())
        ->get(showUrl($score->fresh()))
        ->assertOk();

    actingAs(publicationReviewer())
        ->get(route('public-scores.file.download', ['score' => $score->fresh(), 'scoreFile' => $file]))
        ->assertOk();
});

it('keeps a nomination hidden from a signed-in user without the review permission', function () {
    [$score, $file] = nominatedScoreWithFile();

    $contributor = User::factory()->create();
    $contributor->assignRole('contributor');

    actingAs($contributor)->get(showUrl($score))->assertNotFound();
    actingAs($contributor)
        ->get(route('public-scores.file.download', ['score' => $score, 'scoreFile' => $file]))
        ->assertNotFound();
});

it('gives a reviewer nothing on a score that was never nominated', function () {
    $score = Score::factory()->create();
    $file = ScoreFile::factory()->published()->ready()->create([
        'score_id' => $score->id,
        'rights' => ScoreFileRights::PublicDomain,
    ]);

    actingAs(publicationReviewer())->get(showUrl($score))->assertNotFound();
    actingAs(publicationReviewer())
        ->get(route('public-scores.file.download', ['score' => $score, 'scoreFile' => $file]))
        ->assertNotFound();
});

it('does not show a reviewer a nominated file that stays private', function () {
    [$score] = nominatedScoreWithFile();

    $bought = ScoreFile::factory()->published()->ready()->create([
        'score_id' => $score->id,
        'rights' => ScoreFileRights::LicensedCopy,
    ]);

    actingAs(publicationReviewer())
        ->get(route('public-scores.file.download', ['score' => $score, 'scoreFile' => $bought]))
        ->assertNotFound();
});

it('keeps a previewed nomination out of the public library listing', function () {
    [$score] = nominatedScoreWithFile();

    actingAs(publicationReviewer())
        ->get(route('public-scores'))
        ->assertOk()
        ->assertDontSee('Nominated Chant', false);
});

it('labels every entry of the export menu on a public score', function () {
    $score = Score::factory()->abc()->create();
    ScorePublication::factory()->of($score)->approved()->create();

    get(showUrl($score))
        ->assertOk()
        ->assertSee('exportText: '.Js::from(__('Export')), false)
        ->assertSee('exportPdfText: '.Js::from(__('Export PDF')), false);
});

it('does not clip the export menu inside a scrolling preview', function () {
    $score = Score::factory()->abc()->create();
    ScorePublication::factory()->of($score)->approved()->create();

    // Each rendered page gets its own horizontal scroller in JS; an outer one
    // would only cut off the export dropdown.
    get(showUrl($score))
        ->assertOk()
        ->assertDontSee('x-ref="abcPreview" class="min-h-16 space-y-4 overflow-x-auto"', false)
        ->assertSee('x-ref="abcPreview" class="min-h-16 space-y-4" wire:ignore', false);
});

it('keeps the display controls collapsed so the score leads the page', function () {
    $score = Score::factory()->abc()->create();
    ScorePublication::factory()->of($score)->approved()->create();

    $html = get(showUrl($score))->assertOk()->getContent();

    expect($html)->toContain('x-data="{ showSettings: false }"')
        ->and($html)->toContain(__('Display settings'))
        // The toolbars sit inside the disclosure, above every preview.
        ->and(strpos($html, 'x-model="abcLyricSize"'))->toBeLessThan(strpos($html, 'x-ref="abcPreview"'));
});
