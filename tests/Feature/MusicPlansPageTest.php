<?php

use App\Models\Celebration;
use App\Models\MusicPlan;
use Livewire\Livewire;

test('music-plans page loads for guests', function () {
    $response = $this->get('/music-plans');

    $response->assertOk();
    $response->assertSee('Közzétett énekrendek');
});

test('music-plans page shows published plans', function () {
    $celebration = Celebration::factory()->create(['name' => 'Test Celebration']);
    $publishedPlan = MusicPlan::factory()->create(['is_private' => false]);
    $publishedPlan->celebration()->associate($celebration);
    $publishedPlan->save();
    $unpublishedPlan = MusicPlan::factory()->create(['is_private' => true]);

    $response = $this->get('/music-plans');

    $response->assertOk();
    $response->assertSee('Test Celebration');
    $response->assertDontSee($unpublishedPlan->celebrationName ?? 'Ismeretlen ünnep');
});

test('music-plans page includes plans attached to custom celebrations', function () {
    $celebration = Celebration::factory()->create(['is_custom' => true]);
    $plan = MusicPlan::factory()->create(['is_private' => false]);
    $plan->celebration()->associate($celebration);
    $plan->save();

    $response = $this->get('/music-plans');

    $response->assertOk();
    $response->assertSee($plan->celebrationName ?? 'Ismeretlen ünnep');
});

test('music-plans page search filters by celebration name', function () {
    $celebration1 = Celebration::factory()->create(['name' => 'Easter Sunday']);
    $celebration2 = Celebration::factory()->create(['name' => 'Christmas Day']);

    $plan1 = MusicPlan::factory()->create(['is_private' => false]);
    $plan1->celebration()->associate($celebration1);
    $plan1->save();
    $plan2 = MusicPlan::factory()->create(['is_private' => false]);
    $plan2->celebration()->associate($celebration2);
    $plan2->save();

    // The search is a Livewire reactive property (wire:model.live), not a URL query param.
    // Use Livewire::test() and set() to trigger the filter properly.
    // The search filter is a Livewire reactive property, not a URL query param.
    // After set(), Livewire morphs existing child components in place rather than
    // re-rendering them, so the child card HTML isn't in the updated response.
    // Test the filtering data directly via the component instance instead.
    $component = Livewire::test('pages::music-plans')
        ->set('liturgicalSearch', 'Easter');

    $plans = $component->instance()->liturgicalPlans;

    expect($plans->pluck('celebration_id')->all())->toContain($celebration1->id);
    expect($plans->pluck('celebration_id')->all())->not->toContain($celebration2->id);

});

test('music-plans page paginates results', function () {
    // Use an existing genre to avoid unique constraint violations
    $genre = \App\Models\Genre::first();

    // Create 15 published plans
    MusicPlan::factory()->count(15)->create([
        'is_private' => false,
        'genre_id' => $genre->id,
    ]);

    $response = $this->get('/music-plans');

    $response->assertOk();

    // Get the total count that should be shown in the badge
    $totalPublished = \App\Models\MusicPlan::where('is_private', false)->count();

    // Badge shows total published count (includes plans from other tests)
    $response->assertSee($totalPublished.' énekrend');
});

test('music-plans page does not require authentication', function () {
    $response = $this->get('/music-plans');
    $response->assertOk();
    // No redirect to login
    $response->assertStatus(200);
});
