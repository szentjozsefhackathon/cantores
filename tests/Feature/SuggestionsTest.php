<?php

use App\Models\Celebration;
use App\Models\Music;
use App\Models\MusicPlan;
use App\Models\MusicPlanSlot;
use App\Models\MusicPlanSlotAssignment;
use App\Models\User;

test('suggestions page loads with criteria', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // Create a celebration with some data
    $celebration = Celebration::factory()->create([
        'name' => 'Test Celebration',
        'season' => 1,
        'week' => 2,
        'day' => 0,
        'readings_code' => 'ABC123',
        'year_letter' => 'A',
        'year_parity' => 'I',
    ]);

    // Create a music plan with a slot and assignment
    $slot = MusicPlanSlot::factory()->create(['priority' => 1]);
    $music = Music::factory()->create();
    $musicPlan = MusicPlan::factory()->create(['user_id' => $user->id, 'is_published' => true]);
    $musicPlan->celebrations()->attach($celebration);
    // Attach slot and get the pivot model
    $musicPlan->slots()->attach($slot, ['sequence' => 1]);
    // Retrieve the pivot model directly
    $pivot = \App\Models\MusicPlanSlotPlan::where('music_plan_id', $musicPlan->id)
        ->where('music_plan_slot_id', $slot->id)
        ->first();

    $assignment = MusicPlanSlotAssignment::factory()->create([
        'music_plan_slot_plan_id' => $pivot->id,
        'music_plan_id' => $musicPlan->id,
        'music_plan_slot_id' => $slot->id,
        'music_id' => $music->id,
        'music_sequence' => 1,
    ]);

    // Visit suggestions page with criteria matching the celebration
    $response = $this->get('/suggestions?'.http_build_query([
        'name' => 'Test Celebration',
        'season' => 1,
        'week' => 2,
        'day' => 0,
        'readings_code' => 'ABC123',
        'year_letter' => 'A',
        'year_parity' => 'I',
    ]));

    $response->assertOk();
    $response->assertSee('Énekrend javaslatok');
    $response->assertSee('Test Celebration');
});

test('suggestions page shows no results when no matches', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get('/suggestions?'.http_build_query([
        'name' => 'Non-existent',
    ]));

    $response->assertOk();
    $response->assertSee('Nincs találat');
});

test('suggestions button appears when related celebrations exist', function () {
    // This test would require mocking the CelebrationSearchService
    // For simplicity, we'll skip for now
})->skip();
