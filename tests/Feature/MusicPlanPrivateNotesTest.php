<?php

use App\Models\MusicPlan;
use App\Models\User;

test('music plan private notes can be saved', function () {
    $user = User::factory()->create();
    $musicPlan = MusicPlan::factory()->create(['user_id' => $user->id]);

    $musicPlan->update(['private_notes' => 'Test private notes']);

    $this->assertDatabaseHas('music_plans', [
        'id' => $musicPlan->id,
        'private_notes' => 'Test private notes',
    ]);
});

test('private notes are visible to owner in editor', function () {
    $user = User::factory()->create();
    $musicPlan = MusicPlan::factory()->create([
        'user_id' => $user->id,
        'private_notes' => 'Secret notes',
    ]);

    $this->actingAs($user)
        ->get("/music-plan/{$musicPlan->id}")
        ->assertOk()
        ->assertSee('Secret notes', false);
});

test('private notes are not visible to other users in view', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $musicPlan = MusicPlan::factory()->create([
        'user_id' => $owner->id,
        'private_notes' => 'Secret notes',
        'is_private' => false, // public plan
    ]);

    $this->actingAs($otherUser)
        ->get("/music-plan/{$musicPlan->id}/view")
        ->assertOk()
        ->assertDontSee('Secret notes');
});

test('private notes are visible to owner in view', function () {
    $owner = User::factory()->create();
    $musicPlan = MusicPlan::factory()->create([
        'user_id' => $owner->id,
        'private_notes' => 'Secret notes',
    ]);

    $this->actingAs($owner)
        ->get("/music-plan/{$musicPlan->id}/view")
        ->assertOk()
        ->assertSee('Privát megjegyzéseid')
        ->assertSee('Secret notes');
});