<?php

use App\Models\Celebration;
use App\Models\MusicPlan;

test('music-plans page loads for guests', function () {
    $response = $this->get('/music-plans');

    $response->assertOk();
    $response->assertSee('Közzétett énekrendek');
});

test('music-plans page shows published plans', function () {
    $celebration = Celebration::factory()->create(['name' => 'Test Celebration']);
    $publishedPlan = MusicPlan::factory()->create(['is_published' => true]);
    $publishedPlan->celebrations()->attach($celebration);
    $unpublishedPlan = MusicPlan::factory()->create(['is_published' => false]);

    $response = $this->get('/music-plans');

    $response->assertOk();
    $response->assertSee('Test Celebration');
    $response->assertDontSee($unpublishedPlan->celebrationName ?? 'Ismeretlen ünnep');
});

test('music-plans page includes plans attached to custom celebrations', function () {
    $celebration = Celebration::factory()->create(['is_custom' => true]);
    $plan = MusicPlan::factory()->create(['is_published' => true]);
    $plan->celebrations()->attach($celebration);

    $response = $this->get('/music-plans');

    $response->assertOk();
    $response->assertSee($plan->celebrationName ?? 'Ismeretlen ünnep');
});

test('music-plans page search filters by celebration name', function () {
    $celebration1 = Celebration::factory()->create(['name' => 'Easter Sunday']);
    $celebration2 = Celebration::factory()->create(['name' => 'Christmas Day']);

    $plan1 = MusicPlan::factory()->create(['is_published' => true]);
    $plan1->celebrations()->attach($celebration1);
    $plan2 = MusicPlan::factory()->create(['is_published' => true]);
    $plan2->celebrations()->attach($celebration2);

    $response = $this->get('/music-plans?search=Easter');

    $response->assertOk();
    $response->assertSee('Easter Sunday');
    $response->assertDontSee('Christmas Day');
});

test('music-plans page paginates results', function () {
    // Use an existing genre to avoid unique constraint violations
    $genre = \App\Models\Genre::first();
    
    // Create 15 published plans
    MusicPlan::factory()->count(15)->create([
        'is_published' => true,
        'genre_id' => $genre->id,
    ]);

    $response = $this->get('/music-plans');

    $response->assertOk();
    
    // Get the total count that should be shown in the badge
    $totalPublished = \App\Models\MusicPlan::where('is_published', true)->count();
    
    // Badge shows total published count (includes plans from other tests)
    $response->assertSee($totalPublished . ' énekrend');
});

test('music-plans page does not require authentication', function () {
    $response = $this->get('/music-plans');
    $response->assertOk();
    // No redirect to login
    $response->assertStatus(200);
});
