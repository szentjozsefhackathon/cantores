<?php

use App\Models\City;
use App\Models\FirstName;
use App\Models\MusicPlan;
use App\Models\User;

beforeEach(function () {
    // Ensure we have at least two unique city/first_name combinations
    $this->city1 = City::firstOrCreate(['name' => 'Test City A']);
    $this->city2 = City::firstOrCreate(['name' => 'Test City B']);
    $this->firstName1 = FirstName::firstOrCreate(['name' => 'Test First A'], ['gender' => 'male']);
    $this->firstName2 = FirstName::firstOrCreate(['name' => 'Test First B'], ['gender' => 'female']);
});

test('owner can view their own music plan', function () {
    $user = User::factory()->create([
        'city_id' => $this->city1->id,
        'first_name_id' => $this->firstName1->id,
    ]);
    $musicPlan = MusicPlan::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get(route('music-plan-editor', $musicPlan));

    $response->assertOk();
    $response->assertSee($musicPlan->celebration_name);
});

test('non-owner cannot view another user\'s music plan', function () {
    $owner = User::factory()->create([
        'city_id' => $this->city1->id,
        'first_name_id' => $this->firstName1->id,
    ]);
    $intruder = User::factory()->create([
        'city_id' => $this->city2->id,
        'first_name_id' => $this->firstName2->id,
    ]);
    $musicPlan = MusicPlan::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($intruder)->get(route('music-plan-editor', $musicPlan));

    $response->assertForbidden();
});

test('guest cannot view any music plan', function () {
    $musicPlan = MusicPlan::factory()->create();

    $response = $this->get(route('music-plan-editor', $musicPlan));

    $response->assertRedirectToRoute('login');
});
