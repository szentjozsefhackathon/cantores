<?php

use App\Models\MusicPlan;
use App\Models\MusicPlanSlot;
use App\Models\MusicPlanSlotPlan;
use App\Models\User;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function attachSlot(MusicPlan $plan, MusicPlanSlot $slot, int $sequence): MusicPlanSlotPlan
{
    $plan->slots()->attach($slot->id, ['sequence' => $sequence]);

    return MusicPlanSlotPlan::where('music_plan_id', $plan->id)
        ->where('music_plan_slot_id', $slot->id)
        ->where('sequence', $sequence)
        ->firstOrFail();
}

test('moveSlotDown swaps sequence of first slot with second slot', function () {
    $user = User::factory()->create();

    $slotA = MusicPlanSlot::factory()->create(['name' => 'Slot A Reorder']);
    $slotB = MusicPlanSlot::factory()->create(['name' => 'Slot B Reorder']);

    $musicPlan = MusicPlan::factory()->create(['user_id' => $user->id]);

    $pivotA = attachSlot($musicPlan, $slotA, 1);
    $pivotB = attachSlot($musicPlan, $slotB, 2);

    Livewire::actingAs($user)
        ->test('music-plan-editor.slot-plan', [
            'slotPlan' => $pivotA,
            'isFirst' => true,
            'isLast' => false,
            'totalSlots' => 2,
        ])
        ->call('moveSlotDown');

    expect(MusicPlanSlotPlan::find($pivotA->id)->sequence)->toBe(2);
    expect(MusicPlanSlotPlan::find($pivotB->id)->sequence)->toBe(1);
});

test('moveSlotUp swaps sequence of second slot with first slot', function () {
    $user = User::factory()->create();

    $slotA = MusicPlanSlot::factory()->create(['name' => 'Slot A Up Reorder']);
    $slotB = MusicPlanSlot::factory()->create(['name' => 'Slot B Up Reorder']);

    $musicPlan = MusicPlan::factory()->create(['user_id' => $user->id]);

    $pivotA = attachSlot($musicPlan, $slotA, 1);
    $pivotB = attachSlot($musicPlan, $slotB, 2);

    Livewire::actingAs($user)
        ->test('music-plan-editor.slot-plan', [
            'slotPlan' => $pivotB,
            'isFirst' => false,
            'isLast' => true,
            'totalSlots' => 2,
        ])
        ->call('moveSlotUp');

    expect(MusicPlanSlotPlan::find($pivotA->id)->sequence)->toBe(2);
    expect(MusicPlanSlotPlan::find($pivotB->id)->sequence)->toBe(1);
});
