<?php

use App\Models\User;

test('the sidebar groups the nav into énekrendek, énektár and kottatár', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSeeInOrder([
            'Énekrendek',
            'Közzétett énekrendek',
            'Saját énekrendek',
            'Énektár',
            'Énekek keresése',
            'Gyűjtemények',
            'Szerzők',
            'Kottatár',
            'Ingyenes kották',
            'Kölcsönzések',
            'Kottáim',
            'Mappáim',
        ]);
});

test('every section leads with public material before my own', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSeeInOrder([
            route('music-plans'),
            route('my-music-plans'),
        ], false)
        ->assertSeeInOrder([
            route('public-scores'),
            route('scores'),
        ], false);
});

test('the sidebar no longer offers the music database landing page', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee(route('music-database'));
});
