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

function listedScoreIds(MusicPlan $plan, ?User $viewer): array
{
    return app(MusicPlanScoreListService::class)
        ->forViewer($plan, $viewer)
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
