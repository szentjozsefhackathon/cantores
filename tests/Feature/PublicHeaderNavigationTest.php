<?php

use App\Models\Music;
use App\Models\User;

test('the public header offers the Kottatár dropdown on mobile as well as on desktop', function () {
    $content = $this->get(route('home'))->assertOk()->getContent();

    expect(substr_count($content, 'Kottatár'))->toBe(2)
        ->and(substr_count($content, route('public-scores')))->toBe(2)
        ->and(substr_count($content, route('score.preview')))->toBe(2);
});

test('the mobile header hides its labels below sm instead of overflowing the row', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('max-sm:sr-only', false)
        ->assertSee('Énektár')
        ->assertSee('Énekrendek');
});

test('a signed in visitor keeps the dashboard button in the public header', function () {
    $music = Music::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('music-view', $music))
        ->assertOk()
        ->assertSee('Irányítópult');
});
