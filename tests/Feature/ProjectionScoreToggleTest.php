<?php

use App\Livewire\Pages\ProjectionRemote;
use App\Models\Music;
use App\Models\MusicPlan;
use App\Models\MusicPlanSlot;
use App\Models\MusicPlanSlotAssignment;
use App\Models\MusicPlanSlotPlan;
use App\Models\Presentation;
use App\Models\Projection;
use App\Models\ProjectionSlide;
use App\Models\Score;
use App\Models\Screen;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

/*
 * The remote's own way to add a music's other engravings to today's deck, or
 * take one already in it back out — reached from the phone rather than from the
 * editor, but writing through the very same service.
 */

/**
 * A plan with one slot holding one music, and two scores of it: `$first` chosen
 * into the projection already, `$second` not.
 *
 * @return array{0: Projection, 1: MusicPlanSlotAssignment, 2: Score, 3: Score}
 */
function projectionWithAnUnchosenScore(User $user): array
{
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $slot = MusicPlanSlot::factory()->create(['name' => 'Communion']);
    $slotPlan = MusicPlanSlotPlan::factory()->create([
        'music_plan_id' => $plan->id,
        'music_plan_slot_id' => $slot->id,
    ]);

    $music = Music::factory()->create(['user_id' => $user->id, 'title' => 'Ave verum']);
    $assignment = MusicPlanSlotAssignment::factory()->create([
        'music_plan_slot_plan_id' => $slotPlan->id,
        'music_id' => $music->id,
    ]);

    $first = Score::factory()->abc()->create(['user_id' => $user->id, 'music_id' => $music->id, 'title' => 'Ave verum – I']);
    $second = Score::factory()->abc()->create(['user_id' => $user->id, 'music_id' => $music->id, 'title' => 'Ave verum – II']);

    $projection = Projection::factory()->create(['user_id' => $user->id, 'music_plan_id' => $plan->id]);

    ProjectionSlide::factory()->create([
        'projection_id' => $projection->id,
        'score_id' => $first->id,
        'music_plan_slot_assignment_id' => $assignment->id,
        'music_plan_slot_plan_id' => $slotPlan->id,
    ]);

    return [$projection, $assignment, $first, $second];
}

/**
 * The music node for this assignment, wherever it stands in the outline tree.
 *
 * @param  list<array<string, mixed>>  $outline
 * @return array<string, mixed>|null
 */
function musicNodeFor(array $outline, int $assignmentId): ?array
{
    foreach ($outline as $node) {
        if ($node['kind'] === 'music' && $node['assignmentId'] === $assignmentId) {
            return $node;
        }

        if (isset($node['children'])) {
            $found = musicNodeFor($node['children'], $assignmentId);

            if ($found !== null) {
                return $found;
            }
        }
    }

    return null;
}

it('offers the remote the music\'s other engraving, not the one already chosen', function () {
    $user = User::factory()->create();
    [$projection, $assignment, , $second] = projectionWithAnUnchosenScore($user);

    $screen = Screen::factory()->create(['user_id' => $user->id]);
    $screen->point(Presentation::factory()->create(['projection_id' => $projection->id, 'user_id' => $user->id]));

    actingAs($user);

    $outline = Livewire::test(ProjectionRemote::class, ['screen' => $screen])->get('outline');
    $music = musicNodeFor($outline, $assignment->id);

    expect($music)->not->toBeNull()
        ->and(collect($music['offers'])->pluck('scoreId')->all())->toBe([$second->id]);
});

it('lists a slot the deck has taken nothing from at all', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $slot = MusicPlanSlot::factory()->create(['name' => 'Recessional']);
    MusicPlanSlotPlan::factory()->create(['music_plan_id' => $plan->id, 'music_plan_slot_id' => $slot->id]);

    $projection = Projection::factory()->create(['user_id' => $user->id, 'music_plan_id' => $plan->id]);
    $screen = Screen::factory()->create(['user_id' => $user->id]);
    $screen->point(Presentation::factory()->create(['projection_id' => $projection->id, 'user_id' => $user->id]));

    actingAs($user);

    $outline = Livewire::test(ProjectionRemote::class, ['screen' => $screen])->get('outline');

    expect(collect($outline)->pluck('name'))->toContain('Recessional');
});

it('lists a music the deck has not sung from, alongside its offers', function () {
    $user = User::factory()->create();
    [$projection, $assignment] = projectionWithAnUnchosenScore($user);

    // A second music in the same slot, with a score nobody has chosen yet.
    $slotPlanId = $assignment->music_plan_slot_plan_id;
    $untouched = Music::factory()->create(['user_id' => $user->id, 'title' => 'Agnus Dei']);
    $untouchedAssignment = MusicPlanSlotAssignment::factory()->create([
        'music_plan_slot_plan_id' => $slotPlanId,
        'music_id' => $untouched->id,
    ]);
    $untouchedScore = Score::factory()->abc()->create(['user_id' => $user->id, 'music_id' => $untouched->id]);

    $screen = Screen::factory()->create(['user_id' => $user->id]);
    $screen->point(Presentation::factory()->create(['projection_id' => $projection->id, 'user_id' => $user->id]));

    actingAs($user);

    $outline = Livewire::test(ProjectionRemote::class, ['screen' => $screen])->get('outline');
    $music = musicNodeFor($outline, $untouchedAssignment->id);

    expect($music)->not->toBeNull()
        ->and($music['children'])->toBe([])
        ->and(collect($music['offers'])->pluck('scoreId')->all())->toBe([$untouchedScore->id]);
});

it('adds an offered score to the deck when toggled from the remote', function () {
    $user = User::factory()->create();
    [$projection, $assignment, , $second] = projectionWithAnUnchosenScore($user);

    actingAs($user);

    postJson(route('projections.score-toggle', ['projection' => $projection]), [
        'scoreId' => $second->id,
        'assignmentId' => $assignment->id,
    ])->assertOk();

    expect($projection->entries()->where('score_id', $second->id)->exists())->toBeTrue();
});

it('removes a score from the deck when it is toggled again from the remote', function () {
    $user = User::factory()->create();
    [$projection, $assignment, $first] = projectionWithAnUnchosenScore($user);

    actingAs($user);

    postJson(route('projections.score-toggle', ['projection' => $projection]), [
        'scoreId' => $first->id,
        'assignmentId' => $assignment->id,
    ])->assertOk();

    expect($projection->entries()->where('score_id', $first->id)->exists())->toBeFalse();
});

it('hands the toggle endpoint\'s answer the deck\'s fresh payload', function () {
    $user = User::factory()->create();
    [$projection, $assignment, , $second] = projectionWithAnUnchosenScore($user);

    actingAs($user);

    $response = postJson(route('projections.score-toggle', ['projection' => $projection]), [
        'scoreId' => $second->id,
        'assignmentId' => $assignment->id,
    ])->assertOk();

    expect($response->json('entries'))->toHaveCount(2)
        ->and($response->json('revision'))->toBe($projection->fresh()->revision());
});

it('refuses to toggle a score on somebody elses projection', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    [$projection, $assignment, , $second] = projectionWithAnUnchosenScore($owner);

    actingAs($stranger);

    postJson(route('projections.score-toggle', ['projection' => $projection]), [
        'scoreId' => $second->id,
        'assignmentId' => $assignment->id,
    ])->assertNotFound();

    expect($projection->entries()->where('score_id', $second->id)->exists())->toBeFalse();
});

it('refuses to add a score the viewer cannot read through the remote', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create();
    [$projection, $assignment] = projectionWithAnUnchosenScore($user);
    $theirs = Score::factory()->abc()->create(['user_id' => $stranger->id]);

    actingAs($user);

    postJson(route('projections.score-toggle', ['projection' => $projection]), [
        'scoreId' => $theirs->id,
        'assignmentId' => $assignment->id,
    ])->assertOk();

    expect($projection->entries()->where('score_id', $theirs->id)->exists())->toBeFalse();
});
