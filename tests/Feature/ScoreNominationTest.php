<?php

use App\Enums\ScoreFileRights;
use App\Enums\ScoreLicense;
use App\Enums\ScorePublicationStatus;
use App\Livewire\Pages\ScoreEditor;
use App\Models\Music;
use App\Models\Score;
use App\Models\ScoreFile;
use App\Models\ScorePublication;
use App\Models\User;
use Livewire\Livewire;

it('lets an owner nominate their score', function () {
    $score = Score::factory()->create();

    Livewire::actingAs($score->user)
        ->test(ScoreEditor::class, ['score' => $score])
        ->set('publicationForm.license', ScoreLicense::PublicDomain->value)
        ->set('publicationForm.edition_is_free', true)
        ->call('submitForPublication')
        ->assertHasNoErrors();

    $publication = $score->fresh()->publication;

    expect($publication->status)->toBe(ScorePublicationStatus::Submitted)
        ->and($publication->submitted_by)->toBe($score->user_id)
        ->and($publication->edition_is_free)->toBeTrue()
        ->and($score->fresh()->isPublished())->toBeFalse();
});

it('asks a public domain claim nothing but the edition affirmation', function () {
    $score = Score::factory()->create();

    Livewire::actingAs($score->user)
        ->test(ScoreEditor::class, ['score' => $score])
        ->set('publicationForm.license', ScoreLicense::PublicDomain->value)
        ->call('submitForPublication')
        ->assertHasErrors(['publicationForm.edition_is_free' => 'accepted'])
        ->assertHasNoErrors('publicationForm.source_url');
});

it('makes a creative commons claim link the copy it came from', function () {
    $score = Score::factory()->create();

    Livewire::actingAs($score->user)
        ->test(ScoreEditor::class, ['score' => $score])
        ->set('publicationForm.license', ScoreLicense::CcBySa->value)
        ->call('submitForPublication')
        ->assertHasErrors(['publicationForm.source_url' => 'required']);
});

it('does not record an edition affirmation the licence never asked for', function () {
    $score = Score::factory()->create();

    Livewire::actingAs($score->user)
        ->test(ScoreEditor::class, ['score' => $score])
        ->set('publicationForm.license', ScoreLicense::PublicDomain->value)
        ->set('publicationForm.edition_is_free', true)
        ->set('publicationForm.license', ScoreLicense::CcBySa->value)
        ->set('publicationForm.source_url', 'https://www.cpdl.org/wiki/index.php/Example')
        ->call('submitForPublication')
        ->assertHasNoErrors();

    expect($score->fresh()->publication->edition_is_free)->toBeFalse();
});

it('makes own work name a licence the public can rely on', function () {
    $score = Score::factory()->create();

    Livewire::actingAs($score->user)
        ->test(ScoreEditor::class, ['score' => $score])
        ->set('publicationForm.license', ScoreLicense::OwnWork->value)
        ->call('submitForPublication')
        ->assertHasErrors(['publicationForm.outbound_license' => 'required']);

    Livewire::actingAs($score->user)
        ->test(ScoreEditor::class, ['score' => $score])
        ->set('publicationForm.license', ScoreLicense::OwnWork->value)
        ->set('publicationForm.outbound_license', ScoreLicense::CcBySa->value)
        ->call('submitForPublication')
        ->assertHasNoErrors();

    expect($score->fresh()->publication->outbound_license)->toBe(ScoreLicense::CcBySa);
});

it('will not accept own work released as own work', function () {
    $score = Score::factory()->create();

    Livewire::actingAs($score->user)
        ->test(ScoreEditor::class, ['score' => $score])
        ->set('publicationForm.license', ScoreLicense::OwnWork->value)
        ->set('publicationForm.outbound_license', ScoreLicense::OwnWork->value)
        ->call('submitForPublication')
        ->assertHasErrors(['publicationForm.outbound_license']);
});

it('makes a permission claim record the permission', function () {
    $score = Score::factory()->create();

    Livewire::actingAs($score->user)
        ->test(ScoreEditor::class, ['score' => $score])
        ->set('publicationForm.license', ScoreLicense::ExplicitPermission->value)
        ->set('publicationForm.outbound_license', ScoreLicense::CcBy->value)
        ->call('submitForPublication')
        ->assertHasErrors(['publicationForm.permission_evidence' => 'required']);
});

it('hides the nomination form when the score has no music', function () {
    $score = Score::factory()->unattached()->create();

    Livewire::actingAs($score->user)
        ->test(ScoreEditor::class, ['score' => $score])
        ->assertSet('canNominate', false);
});

it('hides the nomination form when the music is private', function () {
    $music = Music::factory()->create(['is_private' => true]);
    $score = Score::factory()->create(['music_id' => $music->id]);

    Livewire::actingAs($score->user)
        ->test(ScoreEditor::class, ['score' => $score])
        ->assertSet('canNominate', false);
});

it('lets an owner withdraw a published score', function () {
    $score = Score::factory()->create();
    ScorePublication::factory()->of($score)->approved()->create();

    Livewire::actingAs($score->user)
        ->test(ScoreEditor::class, ['score' => $score])
        ->call('withdrawPublication')
        ->assertHasNoErrors();

    expect($score->fresh()->isPublished())->toBeFalse();
});

it('refuses to flag a bought copy for publication', function () {
    $score = Score::factory()->create();
    $bought = ScoreFile::factory()->ready()->create([
        'score_id' => $score->id,
        'rights' => ScoreFileRights::LicensedCopy,
    ]);

    Livewire::actingAs($score->user)
        ->test(ScoreEditor::class, ['score' => $score])
        ->call('togglePublishedFile', $bought->id);

    expect($bought->fresh()->is_published)->toBeFalse();
});

it('flags a public domain file for publication', function () {
    $score = Score::factory()->create();
    $file = ScoreFile::factory()->ready()->create([
        'score_id' => $score->id,
        'rights' => ScoreFileRights::PublicDomain,
    ]);

    Livewire::actingAs($score->user)
        ->test(ScoreEditor::class, ['score' => $score])
        ->call('togglePublishedFile', $file->id);

    expect($file->fresh()->is_published)->toBeTrue();
});

it('does not let a stranger nominate someone else’s score', function () {
    $score = Score::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test(ScoreEditor::class, ['score' => $score])
        ->assertForbidden();
});

it('keeps the nomination form in a dialog and closes it once it is sent', function () {
    $score = Score::factory()->create();

    Livewire::actingAs($score->user)
        ->test(ScoreEditor::class, ['score' => $score])
        ->assertSeeHtml('data-modal="score-publication"')
        ->assertSeeHtml("fluxModal('score-publication'")
        ->set('publicationForm.license', ScoreLicense::PublicDomain->value)
        ->set('publicationForm.edition_is_free', true)
        ->call('submitForPublication')
        ->assertHasNoErrors()
        ->assertDispatched('score-publication-submitted');
});

it('keeps the nomination dialog open when the licence is missing', function () {
    $score = Score::factory()->create();

    Livewire::actingAs($score->user)
        ->test(ScoreEditor::class, ['score' => $score])
        ->call('submitForPublication')
        ->assertHasErrors('publicationForm.license')
        ->assertNotDispatched('score-publication-submitted');
});

it('drops the nomination dialog once the score is published', function () {
    $score = Score::factory()->create();

    ScorePublication::factory()->create([
        'score_id' => $score->id,
        'submitted_by' => $score->user_id,
        'status' => ScorePublicationStatus::Approved,
        'license' => ScoreLicense::PublicDomain,
    ]);

    Livewire::actingAs($score->user)
        ->test(ScoreEditor::class, ['score' => $score])
        ->assertDontSeeHtml('data-modal="score-publication"')
        ->assertSee(__('Published'));
});
