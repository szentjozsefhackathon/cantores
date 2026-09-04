<?php

use App\Enums\ScorePublicationStatus;
use App\Models\Score;
use App\Models\ScoreFile;
use App\Models\ScorePublication;
use App\Models\ScoreUrl;
use App\Models\User;
use App\Services\ScorePublicationService;
use App\Services\ScoreVersionService;

use function Pest\Laravel\get;

/**
 * Approve a score the way the review screen does, so the publication carries the
 * version and fingerprint everything else here depends on.
 */
function approvePublication(Score $score, array $attributes = []): ScorePublication
{
    $owner = $score->user;
    $reviewer = User::factory()->create();

    $publication = app(ScorePublicationService::class)->submit($score, $owner, [
        'license' => \App\Enums\ScoreLicense::PublicDomain,
        'source_url' => 'https://www.cpdl.org/wiki/index.php/Example',
        ...$attributes,
    ]);

    app(ScorePublicationService::class)->approve($publication, $reviewer);

    return $publication->fresh();
}

it('freezes the published surface when a score is submitted', function () {
    $owner = User::factory()->create();
    $score = Score::factory()->gabc()->create([
        'user_id' => $owner->id,
        'content' => '(c4) Ve(f)ni(g)',
    ]);

    $publication = approvePublication($score);

    expect($publication->approved_version_id)->not->toBeNull()
        ->and($publication->approvedVersion->content)->toBe('(c4) Ve(f)ni(g)');
});

it('keeps the approved version on the shelf while a correction waits in the queue', function () {
    $owner = User::factory()->create();
    $score = Score::factory()->gabc()->create([
        'user_id' => $owner->id,
        'content' => 'a wrong accidental',
    ]);

    $publication = approvePublication($score);

    $score->update(['content' => 'the corrected bar']);

    $publication = $publication->fresh();

    // Back in the queue, but still published: the answer to "there is an error in
    // bar 12" is to fix the error, not to remove the score.
    expect($publication->status)->toBe(ScorePublicationStatus::Approved)
        ->and(ScorePublication::query()->pending()->whereKey($publication->id)->exists())->toBeTrue()
        ->and($publication->approvedVersion->content)->toBe('a wrong accidental')
        ->and($publication->submittedVersion->content)->toBe('the corrected bar')
        ->and($publication->hasUnpublishedChanges())->toBeTrue();
});

it('shows the public the approved version rather than the live score', function () {
    $owner = User::factory()->create();
    $score = Score::factory()->gabc()->create([
        'user_id' => $owner->id,
        'content' => 'approved content',
    ]);

    approvePublication($score);

    $score->update(['content' => 'unreviewed content']);

    get($score->fresh()->publicUrl())
        ->assertOk()
        ->assertSee('approved content')
        ->assertDontSee('unreviewed content');
});

it('sends a typed-source edit back for review', function () {
    $owner = User::factory()->create();
    $score = Score::factory()->gabc()->create(['user_id' => $owner->id, 'content' => 'original']);

    $publication = approvePublication($score);

    expect($publication->status)->toBe(ScorePublicationStatus::Approved);

    $score->update(['content' => 'edited after approval']);

    expect($publication->fresh()->hasUnpublishedChanges())->toBeTrue();
});

it('sends a new score link back for review', function () {
    $owner = User::factory()->create();
    $score = Score::factory()->gabc()->create(['user_id' => $owner->id, 'content' => 'notes']);

    $publication = approvePublication($score);

    ScoreUrl::query()->create([
        'score_id' => $score->id,
        'url' => 'https://example.com/somebody-elses-edition.pdf',
    ]);

    expect($publication->fresh()->hasUnpublishedChanges())->toBeTrue();
});

it('sends a removed score link back for review', function () {
    $owner = User::factory()->create();
    $score = Score::factory()->gabc()->create(['user_id' => $owner->id, 'content' => 'notes']);

    $url = ScoreUrl::query()->create([
        'score_id' => $score->id,
        'url' => 'https://example.com/edition.pdf',
    ]);

    $publication = approvePublication($score->fresh());

    $url->delete();

    expect($publication->fresh()->hasUnpublishedChanges())->toBeTrue();
});

it('leaves an approval alone when only the render settings change', function () {
    $owner = User::factory()->create();
    $score = Score::factory()->gabc()->create(['user_id' => $owner->id, 'content' => 'notes']);

    $publication = approvePublication($score);

    // A transpose changes how the same notes look and cannot introduce anyone
    // else's work, so it is deliberately outside the trigger.
    $score->update(['settings' => ['gabc' => ['staff' => ['size' => 12]]]]);

    expect($publication->fresh()->status)->toBe(ScorePublicationStatus::Approved)
        ->and($publication->fresh()->hasUnpublishedChanges())->toBeFalse();
});

it('keeps the public page readable when a published file is replaced', function () {
    $owner = User::factory()->create();
    $score = Score::factory()->gabc()->create(['user_id' => $owner->id, 'content' => 'notes']);
    $file = ScoreFile::factory()->ready(1)->create([
        'score_id' => $score->id,
        'is_published' => true,
    ]);

    $publication = approvePublication($score->fresh());

    expect($publication->approvedVersion->files->pluck('id')->all())->toBe([$file->id]);

    // Superseding the file must not take the approved page's bytes with it.
    $file->update(['superseded_at' => now(), 'is_published' => false]);

    expect($publication->fresh()->approvedVersion->files->pluck('id')->all())->toBe([$file->id])
        ->and(ScoreFile::query()->whereKey($file->id)->exists())->toBeTrue();
});

it('refreshes the queued version rather than piling up rows while it waits', function () {
    $owner = User::factory()->create();
    $score = Score::factory()->gabc()->create(['user_id' => $owner->id, 'content' => 'one']);

    $publication = approvePublication($score);

    $score->update(['content' => 'two']);
    $firstQueued = $publication->fresh()->submitted_version_id;

    $score->update(['content' => 'three']);

    expect($publication->fresh()->submitted_version_id)->toBe($firstQueued)
        ->and($publication->fresh()->submittedVersion->content)->toBe('three')
        ->and(\App\Models\ScoreVersion::query()->where('score_id', $score->id)->count())->toBe(2);
});

it('drops a queued change on rejection and leaves the published version up', function () {
    $owner = User::factory()->create();
    $reviewer = User::factory()->create();
    $score = Score::factory()->gabc()->create(['user_id' => $owner->id, 'content' => 'approved']);

    $publication = approvePublication($score);
    $score->update(['content' => 'not acceptable']);

    app(ScorePublicationService::class)->reject($publication->fresh(), $reviewer, 'Not free.');

    $publication = $publication->fresh();

    expect($publication->status)->toBe(ScorePublicationStatus::Approved)
        ->and($publication->hasUnpublishedChanges())->toBeFalse()
        ->and($publication->approvedVersion->content)->toBe('approved');
});

it('promotes the queued version when the reviewer approves it', function () {
    $owner = User::factory()->create();
    $reviewer = User::factory()->create();
    $score = Score::factory()->gabc()->create(['user_id' => $owner->id, 'content' => 'approved']);

    $publication = approvePublication($score);
    $score->update(['content' => 'the correction']);

    app(ScorePublicationService::class)->approve($publication->fresh(), $reviewer);

    $publication = $publication->fresh();

    expect($publication->approvedVersion->content)->toBe('the correction')
        ->and($publication->hasUnpublishedChanges())->toBeFalse();

    get($score->fresh()->publicUrl())->assertSee('the correction');
});

it('never versions the private axis', function () {
    $owner = User::factory()->create();
    $score = Score::factory()->gabc()->create(['user_id' => $owner->id, 'content' => 'notes']);

    $score->update(['content' => 'edited']);

    expect(app(ScoreVersionService::class))->not->toBeNull()
        ->and(\App\Models\ScoreVersion::query()->where('score_id', $score->id)->count())->toBe(0);
});
