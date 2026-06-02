<?php

use App\Models\Music;
use App\Models\User;
use App\Services\CelebrationSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('music card renders the relevance popup with each scoring reason', function () {
    $this->actingAs(User::factory()->create());

    $music = Music::factory()->create();

    $reasons = [
        ['label' => __('Same celebration name'), 'points' => 10],
        ['label' => __('Same readings'), 'points' => 5],
    ];

    Livewire::test('music-card', [
        'music' => $music,
        'score' => 15,
        'score_reasons' => $reasons,
    ])
        ->assertSee(__('Why this relevance score?'))
        ->assertSee(__('Relevance'))
        ->assertSee(__('Same celebration name'))
        ->assertSee('+10')
        ->assertSee(__('Same readings'))
        ->assertSee('+5');
});

test('music card popup falls back to a generic message without reasons', function () {
    $this->actingAs(User::factory()->create());

    $music = Music::factory()->create();

    Livewire::test('music-card', [
        'music' => $music,
        'score' => 3,
    ])
        ->assertSee(__('Why this relevance score?'))
        ->assertSee(__('This suggestion comes from a music plan for a related celebration.'));
});

test('music card omits the relevance trigger when no score is given', function () {
    $this->actingAs(User::factory()->create());

    $music = Music::factory()->create();

    Livewire::test('music-card', ['music' => $music])
        ->assertDontSee(__('Why this relevance score?'));
});

test('score breakdown points sum to the relevance score', function () {
    $service = app(CelebrationSearchService::class);

    $celebration = new App\Models\Celebration([
        'name' => 'Test Celebration',
        'season' => 1,
        'week' => 2,
        'day' => 0,
        'readings_code' => 'ABC123',
        'year_letter' => 'A',
        'year_parity' => 'I',
    ]);

    // String criteria mirror the query string values that must be cast.
    $criteria = [
        'name' => 'Test Celebration',
        'season' => '1',
        'week' => '2',
        'day' => '0',
        'readings_code' => 'ABC123',
        'year_letter' => 'A',
        'year_parity' => 'I',
    ];

    $breakdown = $service->scoreBreakdown($celebration, $criteria);

    expect(array_column($breakdown, 'points'))->toBe([5, 10, 2, 1]);
    expect(array_sum(array_column($breakdown, 'points')))->toBe(18);
});

test('score breakdown only contains matched rules', function () {
    $service = app(CelebrationSearchService::class);

    $celebration = new App\Models\Celebration([
        'name' => 'Different name',
        'season' => 1,
        'week' => 2,
        'day' => 3,
        'readings_code' => 'ABC123',
        'year_parity' => 'I',
    ]);

    $breakdown = $service->scoreBreakdown($celebration, [
        'name' => 'Test Celebration',
        'season' => 1,
        'week' => 2,
        'day' => 3,
        'readings_code' => 'ABC123',
        'year_parity' => 'I',
    ]);

    expect(array_sum(array_column($breakdown, 'points')))->toBe(8);
    expect(array_column($breakdown, 'label'))->not->toContain(__('Same celebration name'));
});
