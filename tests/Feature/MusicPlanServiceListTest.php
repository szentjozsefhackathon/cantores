<?php

use App\Models\Loan;
use App\Models\Music;
use App\Models\MusicPlan;
use App\Models\MusicPlanSlotAssignment;
use App\Models\MusicPlanSlotPlan;
use App\Models\Score;
use App\Models\User;
use App\Services\LoanKeepingService;
use App\Services\MusicPlanScoreListService;
use Illuminate\Support\Facades\Storage;

/**
 * The service list: a plan opened before a service, showing for each music every
 * score the person reading it may actually see.
 */
function planForMusic(User $owner, Music $music): MusicPlan
{
    $plan = MusicPlan::factory()->create(['user_id' => $owner->id]);
    $slotPlan = MusicPlanSlotPlan::factory()->create(['music_plan_id' => $plan->id]);
    MusicPlanSlotAssignment::factory()->create([
        'music_plan_slot_plan_id' => $slotPlan->id,
        'music_id' => $music->id,
    ]);

    return $plan->fresh();
}

function listedScoreIds(MusicPlan $plan, ?User $viewer, ?Loan $openLoan = null): array
{
    return app(MusicPlanScoreListService::class)
        ->forViewer($plan, $viewer, $openLoan)
        ->flatten(1)
        ->pluck('id')
        ->all();
}

it('shows the owner their own scores, the ones they kept, and the public library', function () {
    $owner = User::factory()->create();
    $lender = User::factory()->create();
    $stranger = User::factory()->create();

    $music = Music::factory()->create(['user_id' => $owner->id]);
    $plan = planForMusic($owner, $music);

    $own = Score::factory()->create(['user_id' => $owner->id, 'music_id' => $music->id]);
    $borrowed = Score::factory()->create(['user_id' => $lender->id, 'music_id' => $music->id]);
    $published = Score::factory()->create(['user_id' => $stranger->id, 'music_id' => $music->id]);
    $invisible = Score::factory()->create(['user_id' => $stranger->id, 'music_id' => $music->id]);

    \App\Models\ScorePublication::factory()->of($published)->approved()->create();

    app(LoanKeepingService::class)->keep(Loan::factory()->of($borrowed)->create(), $owner);

    expect(listedScoreIds($plan, $owner))
        ->toEqualCanonicalizing([$own->id, $borrowed->id, $published->id])
        ->not->toContain($invisible->id);
});

it('shows a guest only the public library', function () {
    $owner = User::factory()->create();
    $music = Music::factory()->create(['user_id' => $owner->id]);
    $plan = planForMusic($owner, $music);

    $private = Score::factory()->create(['user_id' => $owner->id, 'music_id' => $music->id]);
    $published = Score::factory()->create(['user_id' => $owner->id, 'music_id' => $music->id]);
    \App\Models\ScorePublication::factory()->of($published)->approved()->create();

    expect(listedScoreIds($plan, null))
        ->toBe([$published->id])
        ->not->toContain($private->id);
});

it('marks a borrowed entry with its owner and its expiry', function () {
    $reader = User::factory()->create();
    $lender = User::factory()->create();

    $music = Music::factory()->create(['user_id' => $reader->id]);
    $plan = planForMusic($reader, $music);

    $borrowed = Score::factory()->create([
        'user_id' => $lender->id,
        'music_id' => $music->id,
        'title' => 'Kölcsönkapott',
    ]);

    $loan = Loan::factory()->of($borrowed)->create(['expires_at' => now()->addWeek()]);
    app(LoanKeepingService::class)->keep($loan, $reader);

    $entry = app(MusicPlanScoreListService::class)->forViewer($plan, $reader)->flatten(1)->first();

    expect($entry['is_borrowed'])->toBeTrue()
        ->and($entry['owner_name'])->toBe($lender->displayName)
        ->and($entry['expires_at'])->not->toBeNull()
        ->and($entry['url'])->toContain($loan->token)
        // Read before a service, so what matters is whether it has moved since.
        ->and($entry['changed_at'])->not->toBeNull();
});

it('drops a borrowed entry the moment its loan is recalled', function () {
    $reader = User::factory()->create();
    $lender = User::factory()->create();

    $music = Music::factory()->create(['user_id' => $reader->id]);
    $plan = planForMusic($reader, $music);

    $borrowed = Score::factory()->create(['user_id' => $lender->id, 'music_id' => $music->id]);
    $loan = Loan::factory()->of($borrowed)->create();
    app(LoanKeepingService::class)->keep($loan, $reader);

    expect(listedScoreIds($plan, $reader))->toBe([$borrowed->id]);

    $loan->revoke();

    expect(listedScoreIds($plan, $reader))->toBe([]);
});

/**
 * The lending link as a fourth axis. What it reaches is added to what the reader
 * already holds — it does not replace it, and it does not widen anything else.
 */
it('adds what the lending link reaches to what the reader already holds', function () {
    $lender = User::factory()->create();
    $reader = User::factory()->create();

    $music = Music::factory()->create(['user_id' => $lender->id]);
    $plan = planForMusic($lender, $music);

    $lent = Score::factory()->create(['user_id' => $lender->id, 'music_id' => $music->id]);
    $readersOwn = Score::factory()->create(['user_id' => $reader->id, 'music_id' => $music->id]);

    $loan = Loan::factory()->of($plan)->create();

    expect(listedScoreIds($plan, $reader, $loan))
        ->toEqualCanonicalizing([$lent->id, $readersOwn->id]);

    // Without the link the lender's score is not the reader's to see.
    expect(listedScoreIds($plan, $reader))->toBe([$readersOwn->id]);
});

it('opens the lent scores to a guest holding the link', function () {
    $lender = User::factory()->create();

    $music = Music::factory()->create(['user_id' => $lender->id]);
    $plan = planForMusic($lender, $music);

    $lent = Score::factory()->create(['user_id' => $lender->id, 'music_id' => $music->id]);
    $loan = Loan::factory()->of($plan)->create();

    expect(listedScoreIds($plan, null, $loan))->toBe([$lent->id])
        ->and(listedScoreIds($plan, null))->toBe([]);
});

it('reads a score through the link the reader arrived on, not one they kept', function () {
    $lender = User::factory()->create();
    $reader = User::factory()->create();

    $music = Music::factory()->create(['user_id' => $lender->id]);
    $plan = planForMusic($lender, $music);
    $score = Score::factory()->create(['user_id' => $lender->id, 'music_id' => $music->id]);

    $directLoan = Loan::factory()->of($score)->create();
    app(LoanKeepingService::class)->keep($directLoan, $reader);

    $planLoan = Loan::factory()->of($plan)->create();

    $entry = app(MusicPlanScoreListService::class)
        ->forViewer($plan, $reader, $planLoan)
        ->flatten(1)
        ->first();

    expect($entry['url'])->toContain($planLoan->token)
        ->and($entry['url'])->not->toContain($directLoan->token);
});

it('ignores a lending link that does not lend this plan', function () {
    $lender = User::factory()->create();
    $other = User::factory()->create();

    $music = Music::factory()->create(['user_id' => $lender->id]);
    $plan = planForMusic($lender, $music);

    // Somebody else's plan, naming the same music and reaching their own score.
    $otherPlan = planForMusic($other, $music);
    $otherScore = Score::factory()->create(['user_id' => $other->id, 'music_id' => $music->id]);
    $otherLoan = Loan::factory()->of($otherPlan)->create();

    expect(listedScoreIds($plan, null, $otherLoan))
        ->not->toContain($otherScore->id)
        ->toBe([]);
});

it('draws an incipit for an own score and a published one, not only a borrowed one', function () {
    Storage::fake();

    $reader = User::factory()->create();
    $stranger = User::factory()->create();

    $music = Music::factory()->create(['user_id' => $reader->id]);
    $plan = planForMusic($reader, $music);

    $own = Score::factory()->create(['user_id' => $reader->id, 'music_id' => $music->id]);
    $published = Score::factory()->create(['user_id' => $stranger->id, 'music_id' => $music->id]);
    \App\Models\ScorePublication::factory()->of($published)->approved()->create();

    Storage::put($own->incipit_path, 'fake-png-data');
    Storage::put($published->incipit_path, 'fake-png-data');

    $entries = app(MusicPlanScoreListService::class)
        ->forViewer($plan, $reader)
        ->flatten(1)
        ->keyBy('id');

    expect($entries[$own->id]['incipit_url'])->toContain(route('scores.incipit', $own))
        ->and($entries[$published->id]['incipit_url'])->toContain(route('scores.public-incipit', $published));
});
