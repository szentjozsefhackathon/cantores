<?php

use App\Livewire\LoanLinks;
use App\Models\Booklet;
use App\Models\Loan;
use App\Models\Season;
use App\Models\SeasonEvent;
use App\Models\SeasonEventAttachment;
use App\Services\SeasonSharingService;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * The loans a season keeps for its attached booklets live exactly as long as
 * the season is shared, so recalling the season's link closes them too.
 */
function seasonWithAttachedBooklet(): array
{
    $season = Season::factory()->create();
    $event = SeasonEvent::factory()->create(['season_id' => $season->id]);
    $booklet = Booklet::factory()->create(['user_id' => $season->user_id]);

    $attachment = app(SeasonSharingService::class)->attach($event, $booklet);

    return [$season, $attachment];
}

it('lends nothing while the season itself is not shared', function () {
    [, $attachment] = seasonWithAttachedBooklet();

    expect($attachment->loan_id)->toBeNull();
});

it('lends attached booklets when the season gets a link and recalls them with its last one', function () {
    [$season] = seasonWithAttachedBooklet();

    actingAs($season->user);

    $links = Livewire::test(LoanLinks::class, ['lendable' => $season])->call('lend');

    $bookletLoan = SeasonEventAttachment::query()->sole()->loan;

    expect($bookletLoan->isLive())->toBeTrue();

    $links->call('recall', $season->liveLoans()->sole()->id);

    expect($bookletLoan->fresh()->isLive())->toBeFalse();

    $links->call('lend');

    $renewed = SeasonEventAttachment::query()->sole()->loan;

    expect($renewed->isLive())->toBeTrue()
        ->and($renewed->id)->not->toBe($bookletLoan->id);
});

it('keeps attached booklets lent while another season link is still live', function () {
    [$season] = seasonWithAttachedBooklet();
    $first = Loan::factory()->of($season)->create();
    Loan::factory()->of($season)->create();

    $first->revoke();

    expect(SeasonEventAttachment::query()->sole()->loan->isLive())->toBeTrue();
});

it('recalls the attached booklets\' loans when a date or the season is deleted', function () {
    [$season, $attachment] = seasonWithAttachedBooklet();
    Loan::factory()->of($season)->create();
    $other = app(SeasonSharingService::class)->attach(
        SeasonEvent::factory()->create(['season_id' => $season->id]),
        Booklet::factory()->create(['user_id' => $season->user_id]),
    );

    $first = $attachment->fresh()->loan;
    $second = $other->fresh()->loan;

    $attachment->event->delete();

    expect($first->fresh()->isLive())->toBeFalse()
        ->and($second->fresh()->isLive())->toBeTrue();

    $season->delete();

    expect($second->fresh()->isLive())->toBeFalse();
});
