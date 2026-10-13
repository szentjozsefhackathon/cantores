<?php

use App\Models\MusicAssignmentFlag;
use App\Models\MusicPlan;
use App\Models\MusicPlanSlot;
use App\Models\MusicPlanSlotAssignment;
use App\Models\MusicPlanSlotPlan;
use Livewire\Livewire;

it('shows the flag chosen for an assignment', function () {
    $plan = MusicPlan::factory()->create();
    $plan->slots()->attach(MusicPlanSlot::factory()->create()->id, ['sequence' => 1]);
    $slotPlan = MusicPlanSlotPlan::query()->where('music_plan_id', $plan->id)->firstOrFail();
    $flag = MusicAssignmentFlag::query()->where('name', 'important')->firstOrFail();

    MusicPlanSlotAssignment::factory()->create([
        'music_plan_slot_plan_id' => $slotPlan->id,
        'music_assignment_flag_id' => $flag->id,
    ]);

    Livewire::actingAs($plan->user)
        ->test('music-plan-editor.slot-plan', ['slotPlan' => $slotPlan, 'isFirst' => true, 'isLast' => true, 'totalSlots' => 1])
        ->assertSeeHtml('data-flux-text')
        ->assertSee($flag->label());
});
