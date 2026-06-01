<?php

use App\Models\User;
use App\Services\LiturgicalInfoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

test('the dashboard defers the liturgical info component instead of blocking on the remote fetch', function () {
    // Fail loudly if the remote-backed service is touched during the initial dashboard request.
    app()->instance(LiturgicalInfoService::class, Mockery::mock(LiturgicalInfoService::class, function (MockInterface $mock) {
        $mock->shouldReceive('getCelebrations')->never();
    }));

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Liturgikus információk betöltése…');
});
