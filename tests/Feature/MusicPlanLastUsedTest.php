<?php

use App\Models\Celebration;
use App\Models\Music;
use App\Models\MusicPlan;
use App\Models\MusicPlanSlot;
use App\Models\MusicPlanSlotAssignment;
use App\Models\MusicPlanSlotPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Create a dated plan for the user with the given musics in a single slot.
 *
 * @param  array<int, Music>  $musics
 */
function createDatedPlanWithMusics(User $user, ?string $date, array $musics): MusicPlanSlotPlan
{
    $plan = MusicPlan::factory()->create([
        'user_id' => $user->id,
        'celebration_id' => $date === null ? null : Celebration::factory()->create(['actual_date' => $date])->id,
    ]);

    $slot = MusicPlanSlot::factory()->create();
    $plan->slots()->attach($slot->id, ['sequence' => 1]);
    $slotPlan = MusicPlanSlotPlan::where('music_plan_id', $plan->id)->firstOrFail();

    foreach ($musics as $index => $music) {
        MusicPlanSlotAssignment::factory()->create([
            'music_plan_slot_plan_id' => $slotPlan->id,
            'music_id' => $music->id,
            'music_sequence' => $index + 1,
        ]);
    }

    return $slotPlan;
}

test('lastUsedDatesFor returns the latest earlier plan date of the user per music', function () {
    $user = User::factory()->create();
    $music = Music::factory()->create(['user_id' => $user->id]);
    $unusedMusic = Music::factory()->create(['user_id' => $user->id]);

    createDatedPlanWithMusics($user, '2026-06-01', [$music]);
    createDatedPlanWithMusics($user, '2026-08-02', [$music]);
    createDatedPlanWithMusics($user, '2026-10-01', [$music]);
    createDatedPlanWithMusics(User::factory()->create(), '2026-08-20', [$music]);
    $current = createDatedPlanWithMusics($user, '2026-09-01', [$music, $unusedMusic]);

    $dates = $current->musicPlan->lastUsedDatesFor($user, [$music->id, $unusedMusic->id]);

    expect($dates->get($music->id)->toDateString())->toBe('2026-08-02')
        ->and($dates->has($unusedMusic->id))->toBeFalse();
});

test('slot plan card shows days since the music was last in a plan', function () {
    $user = User::factory()->create();
    $music = Music::factory()->create(['user_id' => $user->id]);

    createDatedPlanWithMusics($user, '2026-08-02', [$music]);
    $current = createDatedPlanWithMusics($user, '2026-09-01', [$music]);

    $component = Livewire::actingAs($user)
        ->test('music-plan-editor.slot-plan', ['slotPlan' => $current, 'isFirst' => true, 'isLast' => true, 'totalSlots' => 1]);

    expect($component->get('assignments.0.last_used_days_ago'))->toBe(30)
        ->and($component->get('assignments.0.last_used_date'))->toBe('2026-08-02');
    $component->assertSeeHtml('data-test="last-used"');
});

test('slot plan card has no history badge for a music never used before', function () {
    $user = User::factory()->create();
    $music = Music::factory()->create(['user_id' => $user->id]);

    $current = createDatedPlanWithMusics($user, '2026-09-01', [$music]);

    Livewire::actingAs($user)
        ->test('music-plan-editor.slot-plan', ['slotPlan' => $current, 'isFirst' => true, 'isLast' => true, 'totalSlots' => 1])
        ->assertSet('assignments.0.last_used_days_ago', null)
        ->assertDontSeeHtml('data-test="last-used"');
});
