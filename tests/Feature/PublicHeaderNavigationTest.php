<?php

use App\Models\Music;
use App\Models\User;

test('the public header offers the Kottatár dropdown on mobile as well as on desktop', function () {
    $content = $this->get(route('home'))->assertOk()->getContent();
    $header = substr($content, 0, strpos($content, '</header>'));

    expect(substr_count($header, 'Kottatár'))->toBe(2)
        ->and(substr_count($header, route('public-scores')))->toBe(2)
        ->and(substr_count($header, route('score.preview')))->toBe(2);
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

test('the public shell and its content share the same widened container', function () {
    $this->get(route('guide'))
        ->assertOk()
        ->assertSee('lg:max-w-6xl', false)
        ->assertSee('max-w-5xl', false)
        ->assertDontSee('lg:max-w-4xl', false);
});
