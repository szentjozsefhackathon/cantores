<?php

use App\Models\MusicPlan;
use App\Models\MusicPlanSlot;
use App\Models\MusicPlanSlotPlan;
use App\Models\User;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('addSlotFromTemplate inserts slot before lower-priority slots', function () {
    $user = User::factory()->create();
    $musicPlan = MusicPlan::factory()->create(['user_id' => $user->id]);

    $slotLow = MusicPlanSlot::factory()->create(['priority' => 10]);
    $slotHigh = MusicPlanSlot::factory()->create(['priority' => 1]);

    // Attach the low-priority slot first (sequence 1)
    $musicPlan->slots()->attach($slotLow->id, ['sequence' => 1]);

    // Now add the high-priority slot via the component
    Livewire::actingAs($user)
        ->test('pages::music-plan.music-plan-editor', ['musicPlan' => $musicPlan])
        ->dispatch('add-slot-from-template', templateId: 0, slotId: $slotHigh->id);

    $highPivot = MusicPlanSlotPlan::where('music_plan_id', $musicPlan->id)
        ->where('music_plan_slot_id', $slotHigh->id)
        ->firstOrFail();

    $lowPivot = MusicPlanSlotPlan::where('music_plan_id', $musicPlan->id)
        ->where('music_plan_slot_id', $slotLow->id)
        ->firstOrFail();

    // High priority (1) should come first (sequence 1), low priority (10) second (sequence 2)
    expect($highPivot->sequence)->toBe(1);
    expect($lowPivot->sequence)->toBe(2);
});

test('addSlotFromTemplate inserts slot after higher-priority slots', function () {
    $user = User::factory()->create();
    $musicPlan = MusicPlan::factory()->create(['user_id' => $user->id]);

    $slotHigh = MusicPlanSlot::factory()->create(['priority' => 1]);
    $slotLow = MusicPlanSlot::factory()->create(['priority' => 10]);

    // Attach the high-priority slot first (sequence 1)
    $musicPlan->slots()->attach($slotHigh->id, ['sequence' => 1]);

    // Now add the low-priority slot via the component
    Livewire::actingAs($user)
        ->test('pages::music-plan.music-plan-editor', ['musicPlan' => $musicPlan])
        ->dispatch('add-slot-from-template', templateId: 0, slotId: $slotLow->id);

    $highPivot = MusicPlanSlotPlan::where('music_plan_id', $musicPlan->id)
        ->where('music_plan_slot_id', $slotHigh->id)
        ->firstOrFail();

    $lowPivot = MusicPlanSlotPlan::where('music_plan_id', $musicPlan->id)
        ->where('music_plan_slot_id', $slotLow->id)
        ->firstOrFail();

    expect($highPivot->sequence)->toBe(1);
    expect($lowPivot->sequence)->toBe(2);
});

test('addSlotFromTemplate inserts slot in correct position among three slots', function () {
    $user = User::factory()->create();
    $musicPlan = MusicPlan::factory()->create(['user_id' => $user->id]);

    $slotA = MusicPlanSlot::factory()->create(['priority' => 1]);
    $slotB = MusicPlanSlot::factory()->create(['priority' => 5]);
    $slotC = MusicPlanSlot::factory()->create(['priority' => 10]);

    // Attach A (priority 1) and C (priority 10); then add B (priority 5) in the middle
    $musicPlan->slots()->attach($slotA->id, ['sequence' => 1]);
    $musicPlan->slots()->attach($slotC->id, ['sequence' => 2]);

    Livewire::actingAs($user)
        ->test('pages::music-plan.music-plan-editor', ['musicPlan' => $musicPlan])
        ->dispatch('add-slot-from-template', templateId: 0, slotId: $slotB->id);

    $pivotA = MusicPlanSlotPlan::where('music_plan_id', $musicPlan->id)->where('music_plan_slot_id', $slotA->id)->firstOrFail();
    $pivotB = MusicPlanSlotPlan::where('music_plan_id', $musicPlan->id)->where('music_plan_slot_id', $slotB->id)->firstOrFail();
    $pivotC = MusicPlanSlotPlan::where('music_plan_id', $musicPlan->id)->where('music_plan_slot_id', $slotC->id)->firstOrFail();

    expect($pivotA->sequence)->toBe(1);
    expect($pivotB->sequence)->toBe(2);
    expect($pivotC->sequence)->toBe(3);
});

test('addSlotFromTemplate does not change the relative order of manually reordered existing slots', function () {
    $user = User::factory()->create();
    $musicPlan = MusicPlan::factory()->create(['user_id' => $user->id]);

    $slotA = MusicPlanSlot::factory()->create(['priority' => 1]);
    $slotB = MusicPlanSlot::factory()->create(['priority' => 5]);
    $slotNew = MusicPlanSlot::factory()->create(['priority' => 3]);

    // User manually put slotB (priority 5) before slotA (priority 1) — against default priority order
    $musicPlan->slots()->attach($slotB->id, ['sequence' => 1]);
    $musicPlan->slots()->attach($slotA->id, ['sequence' => 2]);

    // Adding slotNew (priority 3) — it would fit between A and B by priority,
    // but the existing slots walk in sequence order so it goes before the first
    // slot whose priority > 3. slotB (priority 5) is at sequence 1, so insertAt = 1.
    Livewire::actingAs($user)
        ->test('pages::music-plan.music-plan-editor', ['musicPlan' => $musicPlan])
        ->dispatch('add-slot-from-template', templateId: 0, slotId: $slotNew->id);

    $pivotA = MusicPlanSlotPlan::where('music_plan_id', $musicPlan->id)->where('music_plan_slot_id', $slotA->id)->firstOrFail();
    $pivotB = MusicPlanSlotPlan::where('music_plan_id', $musicPlan->id)->where('music_plan_slot_id', $slotB->id)->firstOrFail();
    $pivotNew = MusicPlanSlotPlan::where('music_plan_id', $musicPlan->id)->where('music_plan_slot_id', $slotNew->id)->firstOrFail();

    // slotNew inserted before slotB (first with priority > 3 in sequence order);
    // slotB and slotA shift up — their relative order is preserved.
    expect($pivotNew->sequence)->toBe(1);
    expect($pivotB->sequence)->toBe(2);
    expect($pivotA->sequence)->toBe(3);
});
