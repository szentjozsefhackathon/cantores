<?php

use App\Enums\ScoreFileRights;
use App\Enums\ScorePublicationStatus;
use App\Livewire\Pages\Editor\ScorePublicationReview;
use App\Models\Score;
use App\Models\ScoreFile;
use App\Models\ScorePublication;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

function reviewerUser(string $role = 'editor'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('keeps the queue away from users without the review permission', function () {
    $contributor = User::factory()->create();
    $contributor->assignRole('contributor');

    actingAs($contributor)
        ->get(route('score-publication-review'))
        ->assertForbidden();
});

it('lets an editor open the queue', function () {
    actingAs(reviewerUser())
        ->get(route('score-publication-review'))
        ->assertOk();
});

it('shows waiting nominations and hides approved ones by default', function () {
    $waiting = Score::factory()->create(['title' => 'Waiting Chant']);
    ScorePublication::factory()->of($waiting)->submitted()->create();

    $live = Score::factory()->create(['title' => 'Live Chant']);
    ScorePublication::factory()->of($live)->approved()->create();

    Livewire::actingAs(reviewerUser())
        ->test(ScorePublicationReview::class)
        ->assertSee('Waiting Chant')
        ->assertDontSee('Live Chant');
});

it('publishes a score when an editor approves it', function () {
    $score = Score::factory()->create();
    $publication = ScorePublication::factory()->of($score)->submitted()->create();
    $editor = reviewerUser();

    Livewire::actingAs($editor)
        ->test(ScorePublicationReview::class)
        ->call('select', $publication->id)
        ->set('decisionNotes', 'Public domain, 1890 edition.')
        ->call('approve')
        ->assertHasNoErrors();

    $publication->refresh();

    expect($publication->status)->toBe(ScorePublicationStatus::Approved)
        ->and($publication->reviewer_id)->toBe($editor->id)
        ->and($publication->self_approved)->toBeFalse()
        ->and($score->fresh()->isPublished())->toBeTrue();
});

it('requires a reason before rejecting', function () {
    $publication = ScorePublication::factory()->submitted()->create();

    Livewire::actingAs(reviewerUser())
        ->test(ScorePublicationReview::class)
        ->call('select', $publication->id)
        ->call('reject')
        ->assertHasErrors(['decisionNotes' => 'required']);

    expect($publication->fresh()->status)->toBe(ScorePublicationStatus::Submitted);
});

it('stops an editor approving their own nomination', function () {
    $editor = reviewerUser();
    $score = Score::factory()->create(['user_id' => $editor->id]);
    $publication = ScorePublication::factory()->of($score)->submitted()->create();

    Livewire::actingAs($editor)
        ->test(ScorePublicationReview::class)
        ->call('select', $publication->id)
        ->call('approve')
        ->assertForbidden();

    expect($publication->fresh()->status)->toBe(ScorePublicationStatus::Submitted);
});

it('lets an admin self-approve but records that they did', function () {
    $admin = reviewerUser('admin');
    $score = Score::factory()->create(['user_id' => $admin->id]);
    $publication = ScorePublication::factory()->of($score)->submitted()->create();

    Livewire::actingAs($admin)
        ->test(ScorePublicationReview::class)
        ->call('select', $publication->id)
        ->call('approve')
        ->assertHasNoErrors();

    expect($publication->fresh()->status)->toBe(ScorePublicationStatus::Approved)
        ->and($publication->fresh()->self_approved)->toBeTrue();
});

it('stops an editor rejecting their own nomination', function () {
    $editor = reviewerUser();
    $score = Score::factory()->create(['user_id' => $editor->id]);
    $publication = ScorePublication::factory()->of($score)->submitted()->create();

    Livewire::actingAs($editor)
        ->test(ScorePublicationReview::class)
        ->call('select', $publication->id)
        ->set('decisionNotes', 'Not a fit.')
        ->call('reject')
        ->assertForbidden();

    expect($publication->fresh()->status)->toBe(ScorePublicationStatus::Submitted);
});

it('lets an admin self-reject their own nomination', function () {
    $admin = reviewerUser('admin');
    $score = Score::factory()->create(['user_id' => $admin->id]);
    $publication = ScorePublication::factory()->of($score)->submitted()->create();

    Livewire::actingAs($admin)
        ->test(ScorePublicationReview::class)
        ->call('select', $publication->id)
        ->set('decisionNotes', 'Not a fit after all.')
        ->call('reject')
        ->assertHasNoErrors();

    expect($publication->fresh()->status)->toBe(ScorePublicationStatus::Rejected);
});

it('requires a reason before taking a published score down', function () {
    $score = Score::factory()->create();
    $publication = ScorePublication::factory()->of($score)->approved()->create();

    Livewire::actingAs(reviewerUser())
        ->test(ScorePublicationReview::class)
        ->call('select', $publication->id)
        ->call('takeDown')
        ->assertHasErrors(['takedownReason' => 'required']);

    Livewire::actingAs(reviewerUser())
        ->test(ScorePublicationReview::class)
        ->call('select', $publication->id)
        ->set('takedownReason', 'The rightholder objected.')
        ->call('takeDown')
        ->assertHasNoErrors();

    expect($publication->fresh()->status)->toBe(ScorePublicationStatus::TakenDown)
        ->and($score->fresh()->isPublished())->toBeFalse();
});

it('shows the reviewer which files would go out and which would not', function () {
    $score = Score::factory()->create();
    ScoreFile::factory()->published()->ready()->create([
        'score_id' => $score->id,
        'rights' => ScoreFileRights::PublicDomain,
        'label' => 'Engraved A4',
    ]);
    ScoreFile::factory()->ready()->create([
        'score_id' => $score->id,
        'rights' => ScoreFileRights::LicensedCopy,
        'label' => 'Bought reference',
        'is_published' => false,
    ]);
    $publication = ScorePublication::factory()->of($score)->submitted()->create();

    Livewire::actingAs(reviewerUser())
        ->test(ScorePublicationReview::class)
        ->call('select', $publication->id)
        ->assertSee('Engraved A4')
        ->assertSee('Bought reference')
        ->assertSee(__('Will be published'))
        ->assertSee(__('Stays private'));
});

it('links the reviewer to the score itself and to each file it would publish', function () {
    $score = Score::factory()->create(['title' => 'Linked Chant']);
    $file = ScoreFile::factory()->published()->ready()->create([
        'score_id' => $score->id,
        'rights' => ScoreFileRights::PublicDomain,
    ]);
    $publication = ScorePublication::factory()->of($score)->submitted()->create();

    Livewire::actingAs(reviewerUser())
        ->test(ScorePublicationReview::class)
        ->call('select', $publication->id)
        ->assertSee($score->publicUrl(), false)
        ->assertSee(
            route('public-scores.file.download', ['score' => $score, 'scoreFile' => $file]),
            false,
        );
});

it('lets a reviewer return a taken down score to the queue', function () {
    $publication = ScorePublication::factory()->takenDown()->create();

    Livewire::actingAs(reviewerUser())
        ->test(ScorePublicationReview::class)
        ->set('status', 'taken_down')
        ->call('select', $publication->id)
        ->call('restore')
        ->assertHasNoErrors();

    expect($publication->fresh()->status)->toBe(ScorePublicationStatus::Submitted);
});
