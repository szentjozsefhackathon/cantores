<?php

use App\Enums\NotificationType;
use App\Enums\ScoreRightsClaimantCapacity;
use App\Enums\ScoreRightsReportStatus;
use App\Livewire\Pages\Editor\ScorePublicationReview;
use App\Livewire\ScoreRightsReportModal;
use App\Models\Notification;
use App\Models\Score;
use App\Models\ScorePublication;
use App\Models\ScoreRightsReport;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

function reportableScore(string $title = 'Reported Chant'): Score
{
    $score = Score::factory()->create(['title' => $title]);
    ScorePublication::factory()->of($score)->approved()->create();

    return $score->fresh();
}

function rightsReviewer(): User
{
    $user = User::factory()->create();
    $user->assignRole('editor');

    return $user;
}

it('offers the report button on the public score page', function () {
    $score = reportableScore();

    get(route('public-scores.show', ['score' => $score, 'slug' => Str::slug($score->title)]))
        ->assertOk()
        ->assertSee(__('Report a rights problem'));
});

it('lets a guest file a report from the score page without an account', function () {
    $score = reportableScore();

    Livewire::test(ScoreRightsReportModal::class, ['score' => $score])
        ->call('openModal')
        ->set('capacity', ScoreRightsClaimantCapacity::RightsHolder->value)
        ->set('claim', 'I engraved this edition in 2019 and never licensed it.')
        ->set('reporterName', 'Anna Kovács')
        ->set('reporterEmail', 'anna@example.test')
        ->call('submit')
        ->assertHasNoErrors();

    $report = ScoreRightsReport::query()->sole();

    expect($report->score_id)->toBe($score->id)
        ->and($report->score_publication_id)->toBe($score->publication->id)
        ->and($report->status)->toBe(ScoreRightsReportStatus::Open)
        ->and($report->reporter_id)->toBeNull()
        ->and($report->reporter_email)->toBe('anna@example.test');
});

it('records the score itself rather than trusting the reporter to name it', function () {
    $score = reportableScore('Ave Verum');

    Livewire::test(ScoreRightsReportModal::class, ['score' => $score])
        ->set('capacity', ScoreRightsClaimantCapacity::Publisher->value)
        ->set('claim', 'This is a scan of our 2015 edition, sold in print.')
        ->set('reporterName', 'A Publisher')
        ->set('reporterEmail', 'rights@example.test')
        ->call('submit');

    expect(ScoreRightsReport::query()->sole()->score->title)->toBe('Ave Verum');
});

it('puts a report in front of everyone who may act on it', function () {
    $editor = rightsReviewer();
    $score = reportableScore();

    Livewire::test(ScoreRightsReportModal::class, ['score' => $score])
        ->set('capacity', ScoreRightsClaimantCapacity::Representative->value)
        ->set('claim', 'I act for the estate, which never gave permission for this.')
        ->set('reporterName', 'Anna Kovács')
        ->set('reporterEmail', 'anna@example.test')
        ->call('submit');

    $notification = Notification::query()->sole();

    expect($notification->type)->toBe(NotificationType::RIGHTS_REPORT)
        ->and($notification->notifiable_id)->toBe($score->id)
        ->and($notification->message)->toContain('anna@example.test')
        ->and($notification->recipients->pluck('id'))->toContain($editor->id);
});

it('refuses a report that says nothing an editor could act on', function () {
    $score = reportableScore();

    Livewire::test(ScoreRightsReportModal::class, ['score' => $score])
        ->set('capacity', '')
        ->set('claim', 'wrong')
        ->set('reporterName', '')
        ->set('reporterEmail', 'not-an-address')
        ->call('submit')
        ->assertHasErrors(['capacity', 'claim', 'reporterName', 'reporterEmail']);

    expect(ScoreRightsReport::query()->count())->toBe(0);
});

it('stops one reporter from flooding the queue', function () {
    $score = reportableScore();

    for ($attempt = 0; $attempt < 10; $attempt++) {
        RateLimiter::hit('score-rights-report:127.0.0.1', 3600);
    }

    Livewire::test(ScoreRightsReportModal::class, ['score' => $score])
        ->set('capacity', ScoreRightsClaimantCapacity::RightsHolder->value)
        ->set('claim', 'I engraved this edition in 2019 and never licensed it.')
        ->set('reporterName', 'Anna Kovács')
        ->set('reporterEmail', 'anna@example.test')
        ->call('submit')
        ->assertHasErrors('claim');

    expect(ScoreRightsReport::query()->count())->toBe(0);
});

it('ties a report to the account of a reporter who happens to be logged in', function () {
    $score = reportableScore();
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ScoreRightsReportModal::class, ['score' => $score])
        ->set('capacity', ScoreRightsClaimantCapacity::Other->value)
        ->set('claim', 'This looks like a scan of a modern hymnal edition.')
        ->set('reporterName', 'Member')
        ->set('reporterEmail', $user->email)
        ->call('submit');

    expect(ScoreRightsReport::query()->sole()->reporter_id)->toBe($user->id);
});

it('shows open complaints to a reviewer working the queue', function () {
    $score = reportableScore();
    ScoreRightsReport::factory()->against($score)->create([
        'claim' => 'This is our engraving, published in 2015.',
    ]);

    Livewire::actingAs(rightsReviewer())
        ->test(ScorePublicationReview::class)
        ->set('status', 'approved')
        ->call('select', $score->publication->id)
        ->assertSee(__('Rights complaints awaiting a decision'))
        ->assertSee('This is our engraving, published in 2015.');
});

it('settles every open complaint when the score is taken down', function () {
    $score = reportableScore();
    $report = ScoreRightsReport::factory()->against($score)->create();
    $editor = rightsReviewer();

    Livewire::actingAs($editor)
        ->test(ScorePublicationReview::class)
        ->call('select', $score->publication->id)
        ->set('takedownReason', 'The 2015 engraving is still protected.')
        ->call('takeDown')
        ->assertHasNoErrors();

    expect($report->fresh())
        ->status->toBe(ScoreRightsReportStatus::Upheld)
        ->handled_by->toBe($editor->id)
        ->resolution_notes->toBe('The 2015 engraving is still protected.');
});

it('records why a complaint was dismissed instead of dropping it', function () {
    $score = reportableScore();
    $report = ScoreRightsReport::factory()->against($score)->create();
    $editor = rightsReviewer();

    Livewire::actingAs($editor)
        ->test(ScorePublicationReview::class)
        ->call('select', $score->publication->id)
        ->set('reportNotes.'.$report->id, 'The 1890 edition is public domain in the EU.')
        ->call('dismissReport', $report->id)
        ->assertHasNoErrors();

    expect($report->fresh())
        ->status->toBe(ScoreRightsReportStatus::Dismissed)
        ->resolution_notes->toBe('The 1890 edition is public domain in the EU.');

    expect($score->fresh()->publication->status->isPublic())->toBeTrue();
});

it('will not dismiss a complaint without a recorded reason', function () {
    $score = reportableScore();
    $report = ScoreRightsReport::factory()->against($score)->create();

    Livewire::actingAs(rightsReviewer())
        ->test(ScorePublicationReview::class)
        ->call('select', $score->publication->id)
        ->call('dismissReport', $report->id)
        ->assertHasErrors('reportNotes.'.$report->id);

    expect($report->fresh()->status)->toBe(ScoreRightsReportStatus::Open);
});

it('keeps complaint handling away from users without the review permission', function () {
    $score = reportableScore();
    $report = ScoreRightsReport::factory()->against($score)->create();

    $contributor = User::factory()->create();
    $contributor->assignRole('contributor');

    actingAs($contributor)
        ->get(route('score-publication-review'))
        ->assertForbidden();

    expect($report->fresh()->status)->toBe(ScoreRightsReportStatus::Open);
});
