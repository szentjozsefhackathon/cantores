<?php

use App\Livewire\Projection\RemoteControlLink;
use App\Models\Presentation;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/*
 * The navbar's way back to the remote while a show is up.
 */

it('offers the remote while a show is up', function () {
    $user = User::factory()->create();
    $presentation = Presentation::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(RemoteControlLink::class)
        ->assertSeeHtml(route('projection-remote'));
});

it('says nothing when no show is up', function () {
    $user = User::factory()->create();

    actingAs($user);

    Livewire::test(RemoteControlLink::class)
        ->assertDontSeeHtml(route('projection-remote'));
});

it('says nothing to a stale presentation nobody has heard from', function () {
    $user = User::factory()->create();
    Presentation::factory()->stale()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(RemoteControlLink::class)
        ->assertDontSeeHtml(route('projection-remote'));
});

// The floating button is placed outside the mobile-only header so it isn't
// nested inside a parent that is itself `lg:hidden` — each half is rendered
// by its own instance, and each must carry only its own button.
it('renders only the icon asked for', function () {
    $user = User::factory()->create();
    Presentation::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(RemoteControlLink::class, ['only' => 'mobile'])
        ->assertSeeHtml(route('projection-remote'))
        ->assertDontSeeHtml('fixed top-4');

    Livewire::test(RemoteControlLink::class, ['only' => 'floating'])
        ->assertSeeHtml(route('projection-remote'))
        ->assertSeeHtml('fixed top-4');
});
