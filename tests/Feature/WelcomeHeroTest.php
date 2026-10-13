<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows a guest the front page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertViewIs('welcome');
});

it('sends a signed in visitor to their dashboard instead of the front page', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('home'))->assertRedirect(route('dashboard'));
});
