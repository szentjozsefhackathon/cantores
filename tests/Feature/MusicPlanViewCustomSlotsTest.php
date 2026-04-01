<?php

use App\Models\MusicPlan;
use App\Models\User;

test('custom slots are visible to non-owner on a public music plan view', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $publicPlan = MusicPlan::factory()->create(['user_id' => $owner->id, 'is_private' => false]);

    $customSlot = $publicPlan->createCustomSlot(['name' => 'Egyéni slot vendégeknek', 'priority' => 0]);
    $publicPlan->slots()->attach($customSlot->id, ['sequence' => 1]);

    $response = $this->actingAs($otherUser)->get(route('music-plan-view', $publicPlan));

    $response->assertOk();
    $response->assertSee('Egyéni slot vendégeknek');
});

test('custom slots are visible to guest on a public music plan view', function () {
    $owner = User::factory()->create();
    $publicPlan = MusicPlan::factory()->create(['user_id' => $owner->id, 'is_private' => false]);

    $customSlot = $publicPlan->createCustomSlot(['name' => 'Egyéni slot vendégeknek', 'priority' => 0]);
    $publicPlan->slots()->attach($customSlot->id, ['sequence' => 1]);

    $response = $this->get(route('music-plan-view', $publicPlan));

    $response->assertOk();
    $response->assertSee('Egyéni slot vendégeknek');
});

test('custom slots are hidden from non-owner on a private music plan view', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $privatePlan = MusicPlan::factory()->create(['user_id' => $owner->id, 'is_private' => true]);

    $customSlot = $privatePlan->createCustomSlot(['name' => 'Privát egyéni slot', 'priority' => 0]);
    $privatePlan->slots()->attach($customSlot->id, ['sequence' => 1]);

    $response = $this->actingAs($otherUser)->get(route('music-plan-view', $privatePlan));

    $response->assertForbidden();
});

test('owner sees custom slots on their own private music plan view', function () {
    $owner = User::factory()->create();
    $privatePlan = MusicPlan::factory()->create(['user_id' => $owner->id, 'is_private' => true]);

    $customSlot = $privatePlan->createCustomSlot(['name' => 'Saját egyéni slot', 'priority' => 0]);
    $privatePlan->slots()->attach($customSlot->id, ['sequence' => 1]);

    $response = $this->actingAs($owner)->get(route('music-plan-view', $privatePlan));

    $response->assertOk();
    $response->assertSee('Saját egyéni slot');
});
