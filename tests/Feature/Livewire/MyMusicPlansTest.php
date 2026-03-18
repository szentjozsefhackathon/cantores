<?php

use App\Models\Celebration;
use App\Models\MusicPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('my music plans page requires authentication', function () {
    auth()->logout();

    $this->get('/my-music-plans')->assertRedirect('/login');
});

test('my music plans page loads for authenticated user', function () {
    $this->get('/my-music-plans')->assertOk();
});

test('only shows plans owned by the authenticated user', function () {
    $otherUser = User::factory()->create();
    $celebration = Celebration::factory()->create(['name' => 'My Celebration']);
    $otherCelebration = Celebration::factory()->create(['name' => 'Other Celebration']);

    MusicPlan::factory()->create(['user_id' => $this->user->id, 'celebration_id' => $celebration->id]);
    MusicPlan::factory()->create(['user_id' => $otherUser->id, 'is_private' => false, 'celebration_id' => $otherCelebration->id]);

    $component = Livewire::test(\App\Livewire\Pages\MyMusicPlans::class);
    $celebrations = $component->instance()->getCelebrationsWithPlans();

    expect($celebrations->pluck('id')->all())->toContain($celebration->id);
    expect($celebrations->pluck('id')->all())->not->toContain($otherCelebration->id);
});

test('search filters celebrations by name', function () {
    $celebration1 = Celebration::factory()->create(['name' => 'Easter Sunday']);
    $celebration2 = Celebration::factory()->create(['name' => 'Christmas Day']);

    MusicPlan::factory()->create(['user_id' => $this->user->id, 'celebration_id' => $celebration1->id]);
    MusicPlan::factory()->create(['user_id' => $this->user->id, 'celebration_id' => $celebration2->id]);

    $component = Livewire::test(\App\Livewire\Pages\MyMusicPlans::class)
        ->set('search', 'Easter');

    $celebrations = $component->instance()->getCelebrationsWithPlans();

    expect($celebrations->pluck('id')->all())->toContain($celebration1->id);
    expect($celebrations->pluck('id')->all())->not->toContain($celebration2->id);
});

test('results are paginated', function () {
    Celebration::factory()
        ->count(15)
        ->sequence(fn ($seq) => ['name' => "Celebration {$seq->index}"])
        ->create()
        ->each(function ($celebration) {
            MusicPlan::factory()->create([
                'user_id' => $this->user->id,
                'celebration_id' => $celebration->id,
            ]);
        });

    $component = Livewire::test(\App\Livewire\Pages\MyMusicPlans::class);
    $celebrations = $component->instance()->getCelebrationsWithPlans();

    expect($celebrations->currentPage())->toBe(1);
    expect($celebrations->count())->toBe(10);
    expect($celebrations->total())->toBe(15);
    expect($celebrations->hasPages())->toBeTrue();
});

test('music plans are eager loaded on paginated celebrations', function () {
    $celebration = Celebration::factory()->create();
    MusicPlan::factory()->count(3)->create([
        'user_id' => $this->user->id,
        'celebration_id' => $celebration->id,
    ]);

    $component = Livewire::test(\App\Livewire\Pages\MyMusicPlans::class);
    $celebrations = $component->instance()->getCelebrationsWithPlans();

    expect($celebrations->first()->relationLoaded('musicPlans'))->toBeTrue();
    expect($celebrations->first()->musicPlans)->toHaveCount(3);
});

test('searching returns only matching celebrations', function () {
    $matching = Celebration::factory()->create(['name' => 'Advent Sunday']);
    $nonMatching = Celebration::factory()->create(['name' => 'Pentecost']);

    MusicPlan::factory()->create(['user_id' => $this->user->id, 'celebration_id' => $matching->id]);
    MusicPlan::factory()->create(['user_id' => $this->user->id, 'celebration_id' => $nonMatching->id]);

    $component = Livewire::test(\App\Livewire\Pages\MyMusicPlans::class)
        ->set('search', 'Advent');

    $celebrations = $component->instance()->getCelebrationsWithPlans();

    expect($celebrations->pluck('id')->all())->toContain($matching->id);
    expect($celebrations->pluck('id')->all())->not->toContain($nonMatching->id);
});
