<?php

use App\Models\Music;
use App\Models\MusicPlan;
use App\Models\MusicPlanSlot;
use App\Models\MusicPlanSlotAssignment;
use App\Models\MusicPlanSlotPlan;
use App\Models\User;

function attachMusicToPublicPlan(MusicPlan $plan, Music $music, string $slotName = 'Communio'): void
{
    $slot = MusicPlanSlot::factory()->create(['name' => $slotName]);
    $pivotPlan = MusicPlanSlotPlan::factory()->create([
        'music_plan_id' => $plan->id,
        'music_plan_slot_id' => $slot->id,
        'sequence' => 1,
    ]);
    MusicPlanSlotAssignment::factory()->create([
        'music_plan_slot_plan_id' => $pivotPlan->id,
        'music_id' => $music->id,
        'music_sequence' => 1,
    ]);
}

test('guest cannot see private music assigned to a public music plan', function () {
    $owner = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $owner->id, 'is_private' => false]);
    $privateMusic = Music::factory()->private()->create(['user_id' => $owner->id, 'title' => 'Titkos ének']);
    attachMusicToPublicPlan($plan, $privateMusic);

    $response = $this->get(route('music-plan-view', $plan));

    $response->assertOk();
    $response->assertDontSee('Titkos ének');
});

test('non-owner cannot see private music assigned to a public music plan', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $owner->id, 'is_private' => false]);
    $privateMusic = Music::factory()->private()->create(['user_id' => $owner->id, 'title' => 'Privát ének']);
    attachMusicToPublicPlan($plan, $privateMusic);

    $response = $this->actingAs($otherUser)->get(route('music-plan-view', $plan));

    $response->assertOk();
    $response->assertDontSee('Privát ének');
});

test('owner can see their private music on their own plan view', function () {
    $owner = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $owner->id, 'is_private' => false]);
    $privateMusic = Music::factory()->private()->create(['user_id' => $owner->id, 'title' => 'Saját titkos ének']);
    attachMusicToPublicPlan($plan, $privateMusic);

    $response = $this->actingAs($owner)->get(route('music-plan-view', $plan));

    $response->assertOk();
    $response->assertSee('Saját titkos ének');
});

test('public music is visible to guests on a public music plan view', function () {
    $owner = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $owner->id, 'is_private' => false]);
    $publicMusic = Music::factory()->create(['user_id' => $owner->id, 'title' => 'Nyilvános ének', 'is_private' => false]);
    attachMusicToPublicPlan($plan, $publicMusic);

    $response = $this->get(route('music-plan-view', $plan));

    $response->assertOk();
    $response->assertSee('Nyilvános ének');
});
