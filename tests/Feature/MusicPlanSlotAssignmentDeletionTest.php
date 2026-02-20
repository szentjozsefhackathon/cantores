<?php

use App\Enums\MusicScopeType;
use App\Models\Genre;
use App\Models\Music;
use App\Models\MusicPlan;
use App\Models\MusicPlanSlot;
use App\Models\MusicPlanSlotAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('deleting song assignment from slot in one plan does not affect other plans', function () {
    // Create test data
    $user = User::factory()->create();
    $genre = Genre::firstOrCreate(['name' => 'Test Genre']);

    $musicPlan1 = MusicPlan::factory()->create([
        'user_id' => $user->id,
        'genre_id' => $genre->id,
    ]);

    $musicPlan2 = MusicPlan::factory()->create([
        'user_id' => $user->id,
        'genre_id' => $genre->id,
    ]);

    $slot = MusicPlanSlot::factory()->create();
    $music = Music::factory()->create(['user_id' => $user->id]);

    // Attach slot to both plans
    $musicPlan1->slots()->attach($slot->id, ['sequence' => 1]);
    $musicPlan2->slots()->attach($slot->id, ['sequence' => 1]);

    // Get pivot rows
    $pivot1 = DB::table('music_plan_slot_plan')
        ->where('music_plan_id', $musicPlan1->id)
        ->where('music_plan_slot_id', $slot->id)
        ->where('sequence', 1)
        ->first();
    $pivot2 = DB::table('music_plan_slot_plan')
        ->where('music_plan_id', $musicPlan2->id)
        ->where('music_plan_slot_id', $slot->id)
        ->where('sequence', 1)
        ->first();

    // Create assignments in both plans
    $assignment1 = MusicPlanSlotAssignment::create([
        'music_plan_slot_plan_id' => $pivot1->id,
        'music_plan_id' => $musicPlan1->id,
        'music_plan_slot_id' => $slot->id,
        'music_id' => $music->id,
        'music_sequence' => 1,
    ]);

    $assignment2 = MusicPlanSlotAssignment::create([
        'music_plan_slot_plan_id' => $pivot2->id,
        'music_plan_id' => $musicPlan2->id,
        'music_plan_slot_id' => $slot->id,
        'music_id' => $music->id,
        'music_sequence' => 1,
    ]);

    // Verify both assignments exist (plus 2 from seeder)
    expect(MusicPlanSlotAssignment::count())->toBe(4);
    expect($assignment1->exists())->toBeTrue();
    expect($assignment2->exists())->toBeTrue();

    // Delete assignment from plan1 only
    $assignment1->delete();

    // Verify assignment1 is deleted but assignment2 still exists (total becomes 3)
    expect(MusicPlanSlotAssignment::count())->toBe(3);
    expect(MusicPlanSlotAssignment::find($assignment1->id))->toBeNull();
    expect(MusicPlanSlotAssignment::find($assignment2->id))->not()->toBeNull();
});

test('detaching slot from plan deletes assignments only for that plan', function () {
    $user = User::factory()->create();
    $genre = Genre::firstOrCreate(['name' => 'Test Genre']);

    $musicPlan1 = MusicPlan::factory()->create([
        'user_id' => $user->id,
        'genre_id' => $genre->id,
    ]);

    $musicPlan2 = MusicPlan::factory()->create([
        'user_id' => $user->id,
        'genre_id' => $genre->id,
    ]);

    $slot = MusicPlanSlot::factory()->create();
    $music = Music::factory()->create(['user_id' => $user->id]);

    // Attach slot to both plans
    $musicPlan1->slots()->attach($slot->id, ['sequence' => 1]);
    $musicPlan2->slots()->attach($slot->id, ['sequence' => 1]);

    // Get pivot rows
    $pivot1 = DB::table('music_plan_slot_plan')
        ->where('music_plan_id', $musicPlan1->id)
        ->where('music_plan_slot_id', $slot->id)
        ->where('sequence', 1)
        ->first();
    $pivot2 = DB::table('music_plan_slot_plan')
        ->where('music_plan_id', $musicPlan2->id)
        ->where('music_plan_slot_id', $slot->id)
        ->where('sequence', 1)
        ->first();

    // Create assignments in both plans
    $assignment1 = MusicPlanSlotAssignment::create([
        'music_plan_slot_plan_id' => $pivot1->id,
        'music_plan_id' => $musicPlan1->id,
        'music_plan_slot_id' => $slot->id,
        'music_id' => $music->id,
        'music_sequence' => 1,
    ]);

    $assignment2 = MusicPlanSlotAssignment::create([
        'music_plan_slot_plan_id' => $pivot2->id,
        'music_plan_id' => $musicPlan2->id,
        'music_plan_slot_id' => $slot->id,
        'music_id' => $music->id,
        'music_sequence' => 1,
    ]);

    // Detach slot from plan1
    $musicPlan1->detachSlot($slot);

    // Verify assignment1 is deleted but assignment2 still exists
    expect(MusicPlanSlotAssignment::where('music_plan_slot_plan_id', $pivot1->id)->count())->toBe(0);
    expect(MusicPlanSlotAssignment::where('music_plan_slot_plan_id', $pivot2->id)->count())->toBe(1);
    expect(MusicPlanSlotAssignment::find($assignment2->id))->not()->toBeNull();

    // Verify slot is detached from plan1 but still attached to plan2
    expect($musicPlan1->slots()->where('music_plan_slot_id', $slot->id)->exists())->toBeFalse();
    expect($musicPlan2->slots()->where('music_plan_slot_id', $slot->id)->exists())->toBeTrue();
});

test('hard deleting slot with assignments is prevented by foreign key constraint', function () {
    // This test expects an exception when trying to force delete a slot that has assignments
    $user = User::factory()->create();
    $genre = Genre::firstOrCreate(['name' => 'Test Genre']);

    $musicPlan = MusicPlan::factory()->create([
        'user_id' => $user->id,
        'genre_id' => $genre->id,
    ]);

    $slot = MusicPlanSlot::factory()->create();
    $music = Music::factory()->create(['user_id' => $user->id]);

    // Attach slot and create assignment
    $musicPlan->slots()->attach($slot->id, ['sequence' => 1]);

    // Get pivot row
    $pivot = DB::table('music_plan_slot_plan')
        ->where('music_plan_id', $musicPlan->id)
        ->where('music_plan_slot_id', $slot->id)
        ->where('sequence', 1)
        ->first();

    MusicPlanSlotAssignment::create([
        'music_plan_slot_plan_id' => $pivot->id,
        'music_plan_id' => $musicPlan->id,
        'music_plan_slot_id' => $slot->id,
        'music_id' => $music->id,
        'music_sequence' => 1,
    ]);

    // Try to force delete the slot - should fail due to foreign key constraint
    // We expect an exception (QueryException with foreign key violation)
    expect(fn () => $slot->forceDelete())->toThrow(\Illuminate\Database\QueryException::class);
});

test('soft deleting slot does not delete assignments', function () {
    $user = User::factory()->create();
    $genre = Genre::firstOrCreate(['name' => 'Test Genre']);

    $musicPlan = MusicPlan::factory()->create([
        'user_id' => $user->id,
        'genre_id' => $genre->id,
    ]);

    $slot = MusicPlanSlot::factory()->create();
    $music = Music::factory()->create(['user_id' => $user->id]);

    // Attach slot and create assignment
    $musicPlan->slots()->attach($slot->id, ['sequence' => 1]);

    // Get pivot row
    $pivot = DB::table('music_plan_slot_plan')
        ->where('music_plan_id', $musicPlan->id)
        ->where('music_plan_slot_id', $slot->id)
        ->where('sequence', 1)
        ->first();

    $assignment = MusicPlanSlotAssignment::create([
        'music_plan_slot_plan_id' => $pivot->id,
        'music_plan_id' => $musicPlan->id,
        'music_plan_slot_id' => $slot->id,
        'music_id' => $music->id,
        'music_sequence' => 1,
    ]);

    // Soft delete the slot
    $slot->delete();

    // Verify slot is soft deleted (deleted_at is not null)
    $freshSlot = $slot->fresh();
    expect($freshSlot)->not()->toBeNull();
    expect($freshSlot->deleted_at)->not()->toBeNull();

    // Verify assignment still exists
    expect(MusicPlanSlotAssignment::find($assignment->id))->not()->toBeNull();
});

test('scope fields can be set and retrieved', function () {
    $user = User::factory()->create();
    $genre = Genre::firstOrCreate(['name' => 'Test Genre']);
    $musicPlan = MusicPlan::factory()->create([
        'user_id' => $user->id,
        'genre_id' => $genre->id,
    ]);
    $slot = MusicPlanSlot::factory()->create();
    $music = Music::factory()->create(['user_id' => $user->id]);

    // Attach slot to plan
    $musicPlan->slots()->attach($slot->id, ['sequence' => 1]);

    // Get pivot row
    $pivot = DB::table('music_plan_slot_plan')
        ->where('music_plan_id', $musicPlan->id)
        ->where('music_plan_slot_id', $slot->id)
        ->where('sequence', 1)
        ->first();

    $assignment = MusicPlanSlotAssignment::create([
        'music_plan_slot_plan_id' => $pivot->id,
        'music_plan_id' => $musicPlan->id,
        'music_plan_slot_id' => $slot->id,
        'music_id' => $music->id,
        'music_sequence' => 1,
    ]);

    // Add a scope
    $assignment->scopes()->create([
        'scope_number' => 2,
        'scope_type' => MusicScopeType::VERSE,
    ]);

    $freshAssignment = MusicPlanSlotAssignment::with('scopes')->find($assignment->id);
    expect($freshAssignment->scopes)->toHaveCount(1);
    expect($freshAssignment->scopes->first()->scope_number)->toBe(2);
    expect($freshAssignment->scopes->first()->scope_type)->toBe(MusicScopeType::VERSE);
    expect($freshAssignment->scope_label)->toBe('Verse 2');

    // Test multiple scopes
    $assignment->scopes()->create([
        'scope_number' => 3,
        'scope_type' => MusicScopeType::MOVEMENT,
    ]);
    $freshAssignment->refresh();
    expect($freshAssignment->scopes)->toHaveCount(2);
    expect($freshAssignment->scope_label)->toBe('Verse 2, Movement 3');

    // Test nullable scope (no scopes)
    $assignment2 = MusicPlanSlotAssignment::create([
        'music_plan_slot_plan_id' => $pivot->id,
        'music_plan_id' => $musicPlan->id,
        'music_plan_slot_id' => $slot->id,
        'music_id' => $music->id,
        'music_sequence' => 2,
    ]);
    expect($assignment2->scope_label)->toBe('');
});
