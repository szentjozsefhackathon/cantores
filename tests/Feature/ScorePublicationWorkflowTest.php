<?php

use App\Enums\ScoreFileRights;
use App\Enums\ScoreLicense;
use App\Enums\ScorePublicationStatus;
use App\Models\Score;
use App\Models\ScoreFile;
use App\Models\ScorePublication;
use App\Models\User;
use App\Services\ScorePublicationService;

beforeEach(function () {
    $this->service = app(ScorePublicationService::class);
});

function reviewer(): User
{
    $user = User::factory()->create();
    $user->assignRole('editor');

    return $user;
}

it('records the nominator when a score is submitted', function () {
    $score = Score::factory()->create();

    $publication = $this->service->submit($score, $score->user, [
        'license' => ScoreLicense::CcBySa,
        'source_url' => 'https://www.cpdl.org/wiki/index.php/Example',
    ]);

    expect($publication->status)->toBe(ScorePublicationStatus::Submitted)
        ->and($publication->submitted_by)->toBe($score->user_id)
        ->and($publication->submitted_at)->not->toBeNull()
        ->and($score->fresh()->isPublished())->toBeFalse();
});

it('records the reviewer and publishes on approval', function () {
    $score = Score::factory()->create();
    $publication = ScorePublication::factory()->of($score)->submitted()->create();
    $editor = reviewer();

    $this->service->approve($publication, $editor, 'Clearly public domain.');

    $publication->refresh();

    expect($publication->status)->toBe(ScorePublicationStatus::Approved)
        ->and($publication->reviewer_id)->toBe($editor->id)
        ->and($publication->reviewed_at)->not->toBeNull()
        ->and($publication->review_notes)->toBe('Clearly public domain.')
        ->and($publication->published_at)->not->toBeNull()
        ->and($score->fresh()->isPublished())->toBeTrue();
});

it('turns on the incipit preview when a score is published', function () {
    $score = Score::factory()->create(['public_preview' => false]);
    $publication = ScorePublication::factory()->of($score)->submitted()->create();

    $this->service->approve($publication, reviewer());

    expect($score->fresh()->public_preview)->toBeTrue();
});

it('refuses to approve a score with nothing to publish', function () {
    $score = Score::factory()->linksOnly()->create();
    $publication = ScorePublication::factory()->of($score)->submitted()->create();

    expect(fn () => $this->service->approve($publication, reviewer()))
        ->toThrow(RuntimeException::class);
});

it('keeps a rejection out of the library and records the notes', function () {
    $score = Score::factory()->create();
    $publication = ScorePublication::factory()->of($score)->submitted()->create();

    $this->service->reject($publication, reviewer(), 'The edition is from 1961.');

    $publication->refresh();

    expect($publication->status)->toBe(ScorePublicationStatus::Rejected)
        ->and($publication->review_notes)->toBe('The edition is from 1961.')
        ->and($publication->published_at)->toBeNull()
        ->and($score->fresh()->isPublished())->toBeFalse();
});

it('lets the owner resubmit after a rejection', function () {
    $score = Score::factory()->create();
    ScorePublication::factory()->of($score)->rejected()->create();

    $publication = $this->service->submit($score->fresh(), $score->user, [
        'license' => ScoreLicense::PublicDomain,
        'edition_is_free' => true,
    ]);

    expect($publication->status)->toBe(ScorePublicationStatus::Submitted)
        ->and($publication->reviewer_id)->toBeNull()
        ->and($publication->review_notes)->toBeNull();
});

it('unpublishes but keeps the reviewer when the owner withdraws', function () {
    $score = Score::factory()->create();
    $publication = ScorePublication::factory()->of($score)->submitted()->create();
    $editor = reviewer();
    $this->service->approve($publication, $editor);

    $this->service->withdraw($publication->fresh());

    $publication->refresh();

    expect($publication->status)->toBe(ScorePublicationStatus::Withdrawn)
        ->and($publication->unpublished_at)->not->toBeNull()
        ->and($publication->reviewer_id)->toBe($editor->id)
        ->and($score->fresh()->isPublished())->toBeFalse();
});

it('records a reason on takedown and stops the owner resubmitting', function () {
    $score = Score::factory()->create();
    $publication = ScorePublication::factory()->of($score)->submitted()->create();
    $this->service->approve($publication, reviewer());

    $this->service->takeDown($publication->fresh(), reviewer(), 'Rightholder complaint.');

    $publication->refresh();

    expect($publication->status)->toBe(ScorePublicationStatus::TakenDown)
        ->and($publication->takedown_reason)->toBe('Rightholder complaint.')
        ->and($score->fresh()->isPublished())->toBeFalse();

    expect(fn () => $this->service->submit($score->fresh(), $score->user, [
        'license' => ScoreLicense::CcBySa,
    ]))->toThrow(RuntimeException::class);
});

it('returns a taken down score to the queue when a reviewer restores it', function () {
    $score = Score::factory()->create();
    $publication = ScorePublication::factory()->of($score)->takenDown()->create();

    $this->service->restore($publication, reviewer());

    expect($publication->fresh()->status)->toBe(ScorePublicationStatus::Submitted);
});

it('writes an audit row for every decision', function () {
    // The project disables auditing in the console (config/audit.php), so the
    // legal trail has to be switched on explicitly to be exercised here.
    config(['audit.console' => true]);

    $score = Score::factory()->create();
    $publication = ScorePublication::factory()->of($score)->submitted()->create();

    $this->service->approve($publication, reviewer());
    $this->service->takeDown($publication->fresh(), reviewer(), 'Complaint.');

    $audits = $publication->fresh()->audits()->get();

    expect($audits->count())->toBeGreaterThanOrEqual(2)
        ->and($audits->pluck('new_values')->flatMap(fn ($values) => array_keys($values)))
        ->toContain('status');
});

it('queues a change for review when a published file changes after approval', function () {
    $score = Score::factory()->create();
    $file = ScoreFile::factory()->published()->ready()->create([
        'score_id' => $score->id,
        'rights' => ScoreFileRights::PublicDomain,
    ]);
    $publication = ScorePublication::factory()->of($score)->submitted()->create();

    $this->service->approve($publication, reviewer());
    expect($publication->fresh()->status)->toBe(ScorePublicationStatus::Approved);

    $file->update(['checksum' => hash('sha256', 'something else entirely')]);

    // The approved version stays on the shelf while the change waits: taking the
    // score down would be answering "there is an error in bar 12" by removing the
    // score. It is in the review queue all the same.
    $publication = $publication->fresh();

    expect($publication->status)->toBe(ScorePublicationStatus::Approved)
        ->and($score->fresh()->isPublished())->toBeTrue()
        ->and($publication->hasUnpublishedChanges())->toBeTrue()
        ->and(ScorePublication::query()->pending()->whereKey($publication->id)->exists())->toBeTrue();
});

it('unpublishes a score approved before versioning existed when it changes', function () {
    $score = Score::factory()->create();
    $file = ScoreFile::factory()->published()->ready()->create([
        'score_id' => $score->id,
        'rights' => ScoreFileRights::PublicDomain,
    ]);
    $publication = ScorePublication::factory()->of($score)->submitted()->create();

    $this->service->approve($publication, reviewer());

    // No snapshot for the public to fall back on, so the old behaviour stands.
    $publication->fresh()->forceFill(['approved_version_id' => null])->saveQuietly();

    $file->update(['checksum' => hash('sha256', 'something else entirely')]);

    expect($publication->fresh()->status)->toBe(ScorePublicationStatus::Submitted)
        ->and($score->fresh()->isPublished())->toBeFalse();
});
