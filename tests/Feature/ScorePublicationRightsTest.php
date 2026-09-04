<?php

use App\Enums\ScoreFileRights;
use App\Enums\ScoreLicense;
use App\Models\Score;
use App\Models\ScoreFile;
use App\Models\ScorePublication;
use App\Models\User;
use App\Services\ScorePublicationService;

it('only lets storage declarations that permit publication through', function () {
    expect(ScoreFileRights::OwnWork->mayBePublished())->toBeTrue()
        ->and(ScoreFileRights::PublicDomain->mayBePublished())->toBeTrue()
        ->and(ScoreFileRights::PermissionHeld->mayBePublished())->toBeTrue()
        ->and(ScoreFileRights::LicensedCopy->mayBePublished())->toBeFalse()
        ->and(ScoreFileRights::Unknown->mayBePublished())->toBeFalse();
});

it('keeps a bought copy out of the published files even if it is flagged', function () {
    $score = Score::factory()->create();

    ScoreFile::factory()->published()->ready()->create([
        'score_id' => $score->id,
        'rights' => ScoreFileRights::PublicDomain,
    ]);

    // Flagged, but its declared rights forbid it — the flag must not be enough.
    $bought = ScoreFile::factory()->published()->ready()->create([
        'score_id' => $score->id,
        'rights' => ScoreFileRights::LicensedCopy,
    ]);

    $published = $score->fresh()->publishedFiles();

    expect($published)->toHaveCount(1)
        ->and($published->pluck('id'))->not->toContain($bought->id);
});

it('lets an owner publish their engraving while keeping a bought copy private', function () {
    $score = Score::factory()->create();

    $own = ScoreFile::factory()->published()->ready()->create([
        'score_id' => $score->id,
        'rights' => ScoreFileRights::OwnWork,
    ]);
    $reference = ScoreFile::factory()->ready()->create([
        'score_id' => $score->id,
        'rights' => ScoreFileRights::LicensedCopy,
        'is_published' => false,
    ]);

    $publication = ScorePublication::factory()->of($score)->submitted()->create();
    $editor = User::factory()->create();
    $editor->assignRole('editor');

    app(ScorePublicationService::class)->approve($publication, $editor);

    expect($score->fresh()->isPublished())->toBeTrue()
        ->and($score->fresh()->publishedFiles()->pluck('id')->all())->toBe([$own->id])
        ->and($reference->fresh()->isPubliclyAvailable())->toBeFalse();
});

it('reports a file as publicly available only when every condition holds', function () {
    $score = Score::factory()->create();
    $file = ScoreFile::factory()->published()->ready()->create([
        'score_id' => $score->id,
        'rights' => ScoreFileRights::PublicDomain,
    ]);

    // Flagged and rightful, but the score is not published yet.
    expect($file->fresh()->isPubliclyAvailable())->toBeFalse();

    $publication = ScorePublication::factory()->of($score)->submitted()->create();
    $editor = User::factory()->create();
    $editor->assignRole('editor');
    app(ScorePublicationService::class)->approve($publication, $editor);

    expect($file->fresh()->isPubliclyAvailable())->toBeTrue();
});

it('knows which licences a downloader can actually rely on', function () {
    expect(ScoreLicense::CcBySa->isRedistributable())->toBeTrue()
        ->and(ScoreLicense::PublicDomain->isRedistributable())->toBeTrue()
        ->and(ScoreLicense::OwnWork->isRedistributable())->toBeFalse()
        ->and(ScoreLicense::ExplicitPermission->isRedistributable())->toBeFalse()
        ->and(ScoreLicense::OwnWork->requiresOutboundLicense())->toBeTrue()
        ->and(ScoreLicense::ExplicitPermission->requiresPermissionEvidence())->toBeTrue()
        ->and(ScoreLicense::PublicDomain->requiresEditionAffirmation())->toBeTrue()
        ->and(ScoreLicense::PublicDomain->requiresSourceUrl())->toBeFalse()
        ->and(ScoreLicense::CcBySa->requiresSourceUrl())->toBeTrue();

    expect(ScoreLicense::redistributableCases())
        ->not->toContain(ScoreLicense::OwnWork)
        ->and(ScoreLicense::redistributableCases())->toContain(ScoreLicense::CcBy);
});

it('falls back to the outbound licence for own work', function () {
    $publication = ScorePublication::factory()->ownWork()->create();

    expect($publication->effectiveLicense())->toBe(ScoreLicense::CcBySa);

    $cc = ScorePublication::factory()->create(['license' => ScoreLicense::CcBy, 'outbound_license' => null]);

    expect($cc->effectiveLicense())->toBe(ScoreLicense::CcBy);
});
