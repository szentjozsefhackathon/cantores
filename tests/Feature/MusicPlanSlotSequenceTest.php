<?php

use App\Models\Music;
use App\Models\MusicPlan;
use App\Models\MusicPlanSlot;
use App\Models\MusicPlanSlotAssignment;
use App\Models\Realm;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('slot assignments respect sequence numbers and music attachments', function () {
    // Create user and realm
    $user = User::factory()->create();
    $realm = Realm::firstOrCreate(['name' => 'Test Realm']);

    // Create 'Slot 1' and 'Slot 2'
    $slot1 = MusicPlanSlot::factory()->create(['name' => 'Slot 1']);
    $slot2 = MusicPlanSlot::factory()->create(['name' => 'Slot 2']);

    // Create 'MusicPlan 1'
    $musicPlan = MusicPlan::factory()->create([
        'user_id' => $user->id,
        'realm_id' => $realm->id,
    ]);

    // Assign 'Slot 1' with sequence number 1
    $musicPlan->slots()->attach($slot1->id, ['sequence' => 1]);

    // Assign 'Slot 2' with sequence number 2
    $musicPlan->slots()->attach($slot2->id, ['sequence' => 2]);

    // Assign 'Slot 1' again with sequence number 3
    $musicPlan->slots()->attach($slot1->id, ['sequence' => 3]);

    // Assert Music Plan 1 contains Slot 1, Slot 2, Slot 1 in order of Sequence number
    $orderedSlots = $musicPlan->slots()->orderByPivot('sequence')->get();
    expect($orderedSlots->pluck('id')->toArray())->toBe([
        $slot1->id,
        $slot2->id,
        $slot1->id,
    ]);

    // Create 'Music 1', 'Music 2', 'Music 3'
    $music1 = Music::factory()->create(['user_id' => $user->id]);

    // Get pivot rows for slot occurrences
    $pivot1 = DB::table('music_plan_slot_plan')
        ->where('music_plan_id', $musicPlan->id)
        ->where('music_plan_slot_id', $slot1->id)
        ->where('sequence', 1)
        ->first();
    $pivot2 = DB::table('music_plan_slot_plan')
        ->where('music_plan_id', $musicPlan->id)
        ->where('music_plan_slot_id', $slot2->id)
        ->where('sequence', 2)
        ->first();
    $pivot3 = DB::table('music_plan_slot_plan')
        ->where('music_plan_id', $musicPlan->id)
        ->where('music_plan_slot_id', $slot1->id)
        ->where('sequence', 3)
        ->first();

    // Attach 'Music 1' to 'Slot 1' first occurrence (pivot1)
    MusicPlanSlotAssignment::create([
        'music_plan_slot_plan_id' => $pivot1->id,
        'music_plan_id' => $musicPlan->id,
        'music_plan_slot_id' => $slot1->id,
        'music_id' => $music1->id,
        'music_sequence' => 1,
    ]);

    // Assert rule 1: in Music Plan 1, Slot 1 at sequence 1 contains 'Music'
    $assignment = MusicPlanSlotAssignment::where('music_plan_slot_plan_id', $pivot1->id)->first();
    expect($assignment)->not->toBeNull();
    expect($assignment->music_id)->toBe($music1->id);

    // Assert rule 2: Slot 2 contains nothing
    $slot2Assignment = MusicPlanSlotAssignment::where('music_plan_slot_plan_id', $pivot2->id)->first();
    expect($slot2Assignment)->toBeNull();

    // Assert rule 3: Slot 1 at sequence 3 contains nothing
    $slot1Sequence3Assignment = MusicPlanSlotAssignment::where('music_plan_slot_plan_id', $pivot3->id)->first();
    expect($slot1Sequence3Assignment)->toBeNull();
});

test('multiple music attachments per slot occurrence with ordering', function () {
    // Create user and realm
    $user = User::factory()->create();
    $realm = Realm::firstOrCreate(['name' => 'Test Realm']);

    // Create 'Slot 1' and 'Slot 2'
    $slot1 = MusicPlanSlot::factory()->create(['name' => 'Slot 1']);
    $slot2 = MusicPlanSlot::factory()->create(['name' => 'Slot 2']);

    // Create 'MusicPlan 1'
    $musicPlan = MusicPlan::factory()->create([
        'user_id' => $user->id,
        'realm_id' => $realm->id,
    ]);

    // Assign 'Slot 1' with sequence number 1
    $musicPlan->slots()->attach($slot1->id, ['sequence' => 1]);
    // Assign 'Slot 2' with sequence number 2
    $musicPlan->slots()->attach($slot2->id, ['sequence' => 2]);
    // Assign 'Slot 1' again with sequence number 3
    $musicPlan->slots()->attach($slot1->id, ['sequence' => 3]);

    // Get pivot rows
    $pivot1 = DB::table('music_plan_slot_plan')
        ->where('music_plan_id', $musicPlan->id)
        ->where('music_plan_slot_id', $slot1->id)
        ->where('sequence', 1)
        ->first();
    $pivot2 = DB::table('music_plan_slot_plan')
        ->where('music_plan_id', $musicPlan->id)
        ->where('music_plan_slot_id', $slot2->id)
        ->where('sequence', 2)
        ->first();
    $pivot3 = DB::table('music_plan_slot_plan')
        ->where('music_plan_id', $musicPlan->id)
        ->where('music_plan_slot_id', $slot1->id)
        ->where('sequence', 3)
        ->first();

    // Create 'Music 1', 'Music 2', 'Music 3'
    $music1 = Music::factory()->create(['user_id' => $user->id]);
    $music2 = Music::factory()->create(['user_id' => $user->id]);
    $music3 = Music::factory()->create(['user_id' => $user->id]);

    // Attach 'Music 1' to 'Slot 1' (sequence 1)
    MusicPlanSlotAssignment::create([
        'music_plan_slot_plan_id' => $pivot1->id,
        'music_plan_id' => $musicPlan->id,
        'music_plan_slot_id' => $slot1->id,
        'music_id' => $music1->id,
        'music_sequence' => 1,
    ]);
    // Also attach 'Music 2' to this slot in the plan (sequence 1)
    MusicPlanSlotAssignment::create([
        'music_plan_slot_plan_id' => $pivot1->id,
        'music_plan_id' => $musicPlan->id,
        'music_plan_slot_id' => $slot1->id,
        'music_id' => $music2->id,
        'music_sequence' => 2,
    ]);

    // Attach 'Music 2' to 'Slot 1' (sequence 3)
    MusicPlanSlotAssignment::create([
        'music_plan_slot_plan_id' => $pivot3->id,
        'music_plan_id' => $musicPlan->id,
        'music_plan_slot_id' => $slot1->id,
        'music_id' => $music2->id,
        'music_sequence' => 1,
    ]);
    // Also attach 'Music 1' to this slot in the plan (sequence 3)
    MusicPlanSlotAssignment::create([
        'music_plan_slot_plan_id' => $pivot3->id,
        'music_plan_id' => $musicPlan->id,
        'music_plan_slot_id' => $slot1->id,
        'music_id' => $music1->id,
        'music_sequence' => 2,
    ]);

    // Assert rule 1: in Music Plan 1, Slot 1 at sequence 1 contains 'Music 1' and 'Music 2', in this sequence order
    $assignmentsSeq1 = MusicPlanSlotAssignment::where('music_plan_slot_plan_id', $pivot1->id)
        ->orderBy('music_sequence')
        ->get();
    expect($assignmentsSeq1)->toHaveCount(2);
    expect($assignmentsSeq1[0]->music_id)->toBe($music1->id);
    expect($assignmentsSeq1[1]->music_id)->toBe($music2->id);

    // Assert rule 2: Slot 2 contains nothing
    $slot2Assignments = MusicPlanSlotAssignment::where('music_plan_slot_plan_id', $pivot2->id)->get();
    expect($slot2Assignments)->toBeEmpty();

    // Assert rule 3: Slot 1 at sequence 3 contains 'Music 2' and 'Music 1' in this sequence order
    $assignmentsSeq3 = MusicPlanSlotAssignment::where('music_plan_slot_plan_id', $pivot3->id)
        ->orderBy('music_sequence')
        ->get();
    expect($assignmentsSeq3)->toHaveCount(2);
    expect($assignmentsSeq3[0]->music_id)->toBe($music2->id);
    expect($assignmentsSeq3[1]->music_id)->toBe($music1->id);
});
