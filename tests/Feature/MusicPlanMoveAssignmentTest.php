<?php

use App\Models\Celebration;
use App\Models\Music;
use App\Models\MusicPlan;
use App\Models\MusicPlanSlot;
use App\Models\MusicPlanSlotAssignment;
use App\Models\MusicPlanSlotPlan;
use App\Models\User;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Attach a slot to a plan and return the MusicPlanSlotPlan pivot model.
 */
function attachSlotToPlanForMoveTest(MusicPlan $plan, MusicPlanSlot $slot, int $sequence): MusicPlanSlotPlan
{
    $plan->slots()->attach($slot->id, ['sequence' => $sequence]);

    return MusicPlanSlotPlan::where('music_plan_id', $plan->id)
        ->where('music_plan_slot_id', $slot->id)
        ->where('sequence', $sequence)
        ->firstOrFail();
}

test('moveAssignmentToSlot updates music_plan_slot_id to reflect the new slot', function () {
    $user = User::factory()->create();

    $slotA = MusicPlanSlot::factory()->create(['name' => 'Slot A Move', 'priority' => 1]);
    $slotB = MusicPlanSlot::factory()->create(['name' => 'Slot B Move', 'priority' => 2]);

    $musicPlan = MusicPlan::factory()->create(['user_id' => $user->id]);

    $pivotA = attachSlotToPlanForMoveTest($musicPlan, $slotA, 1);
    $pivotB = attachSlotToPlanForMoveTest($musicPlan, $slotB, 2);

    $music = Music::factory()->create(['user_id' => $user->id]);

    $assignment = MusicPlanSlotAssignment::factory()->create([
        'music_plan_slot_plan_id' => $pivotA->id,
        'music_id' => $music->id,
        'music_sequence' => 1,
    ]);

    Livewire::actingAs($user)
        ->test('music-plan-editor.slot-plan', ['slotPlan' => $pivotA, 'isFirst' => true, 'isLast' => false, 'totalSlots' => 2])
        ->call('moveAssignmentToSlot', $assignment->id, $pivotB->id);

    $assignment->refresh();

    expect($assignment->music_plan_slot_plan_id)->toBe($pivotB->id);
});

test('suggestions show music under the new slot after it was moved', function () {
    $user = User::factory()->create();

    $celebration = Celebration::factory()->create([
        'name' => 'Move Test Celebration',
        'season' => 1,
        'week' => 1,
        'day' => 0,
        'readings_code' => 'MOVETEST',
        'year_letter' => 'A',
        'year_parity' => 'I',
    ]);

    $slotA = MusicPlanSlot::factory()->create(['name' => 'Old Slot', 'priority' => 1]);
    $slotB = MusicPlanSlot::factory()->create(['name' => 'New Slot', 'priority' => 2]);

    $musicPlan = MusicPlan::factory()->create([
        'user_id' => $user->id,
        'is_private' => false,
    ]);
    $musicPlan->celebration()->associate($celebration);
    $musicPlan->save();

    $pivotA = attachSlotToPlanForMoveTest($musicPlan, $slotA, 1);
    $pivotB = attachSlotToPlanForMoveTest($musicPlan, $slotB, 2);

    $music = Music::factory()->create(['user_id' => $user->id, 'title' => 'Moved Song']);

    // Simulate a moved assignment: music_plan_slot_plan_id points to slot B's pivot
    // but music_plan_slot_id is stale and still points to slot A (the pre-fix bug state).
    MusicPlanSlotAssignment::factory()->create([
        'music_plan_slot_plan_id' => $pivotB->id,
        'music_id' => $music->id,
        'music_sequence' => 1,
    ]);

    $criteria = [
        'name' => 'Move Test Celebration',
        'season' => 1,
        'week' => 1,
        'day' => 0,
        'readings_code' => 'MOVETEST',
        'year_letter' => 'A',
        'year_parity' => 'I',
    ];

    // Test the suggestions-content component directly, because the music tab uses
    // @island(defer: true) which only loads its content via a subsequent wire:init
    // request — the music titles are not present in the initial HTTP response.
    $component = Livewire::actingAs($user)
        ->test('suggestions-content', ['criteria' => $criteria]);

    $slotMusicMap = $component->get('slotMusicMap');

    // The music should appear under "New Slot" (resolved via musicPlanSlotPlan), not "Old Slot"
    expect($slotMusicMap)->toHaveKey('New Slot');
    expect($slotMusicMap)->not->toHaveKey('Old Slot');
    expect($slotMusicMap['New Slot']['musics'])->toHaveCount(1);
    expect($slotMusicMap['New Slot']['musics'][0]['music']->title)->toBe('Moved Song');
});
