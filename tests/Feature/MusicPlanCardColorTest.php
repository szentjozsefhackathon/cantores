<?php

use App\Models\Celebration;
use App\Models\MusicPlan;
use App\Models\User;
use Livewire\Livewire;

test('card renders the liturgical color accent of its celebration', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $celebration = Celebration::factory()->liturgical()->create([
        'color_text' => 'piros',
    ]);

    $plan = MusicPlan::factory()->create([
        'user_id' => $user->id,
        'genre_id' => null,
        'celebration_id' => $celebration->id,
    ]);

    Livewire::test('music-plan-card', ['musicPlan' => $plan, 'readonly' => true])
        ->assertSeeHtml('border-red-500! dark:border-red-400!');
});

test('card falls back to a neutral accent without a celebration', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $plan = MusicPlan::factory()->create([
        'user_id' => $user->id,
        'genre_id' => null,
        'celebration_id' => null,
    ]);

    Livewire::test('music-plan-card', ['musicPlan' => $plan, 'readonly' => true])
        ->assertSeeHtml('border-l-neutral-200! dark:border-l-neutral-800!');
});

test('extended card renders the liturgical color accent of its celebration', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $celebration = Celebration::factory()->liturgical()->create([
        'color_text' => 'piros',
    ]);

    $plan = MusicPlan::factory()->create([
        'user_id' => $user->id,
        'genre_id' => null,
        'celebration_id' => $celebration->id,
    ]);

    Livewire::test('music-plan-card-extended', ['musicPlan' => $plan])
        ->assertSeeHtml('border-red-500! dark:border-red-400!');
});

test('extended card falls back to a neutral accent without a celebration', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $plan = MusicPlan::factory()->create([
        'user_id' => $user->id,
        'genre_id' => null,
        'celebration_id' => null,
    ]);

    Livewire::test('music-plan-card-extended', ['musicPlan' => $plan])
        ->assertSeeHtml('border-l-gray-200! dark:border-l-gray-700!');
});
