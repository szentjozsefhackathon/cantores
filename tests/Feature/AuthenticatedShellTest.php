<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Signed in visitors navigate by the sidebar, so every page they can reach puts
 * it there. Only the pages that own the whole screen — the projection output and
 * the remote that drives it — keep the bare public shell.
 */
it('gives a signed in visitor the sidebar on ordinary pages', function (string $route) {
    $this->actingAs(User::factory()->create());

    $this->get(route($route))
        ->assertOk()
        ->assertSee('data-flux-sidebar', false);
})->with(['guide', 'about', 'music-database', 'music-plans', 'public-scores', 'abc.guide', 'aretino.guide']);

it('gives a guest the public header instead of the sidebar', function (string $route) {
    $this->get(route($route))
        ->assertOk()
        ->assertDontSee('data-flux-sidebar', false)
        ->assertSee('Énekrendek');
})->with(['guide', 'music-database', 'music-plans', 'public-scores']);

it('keeps the projection pages on the bare shell even when signed in', function (string $route) {
    $this->actingAs(User::factory()->create());

    $this->get(route($route))
        ->assertOk()
        ->assertDontSee('data-flux-sidebar', false);
})->with(['projection-screen', 'projection-remote']);
