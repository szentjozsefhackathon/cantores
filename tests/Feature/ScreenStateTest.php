<?php

use App\Livewire\Pages\ProjectionPresenter;
use App\Models\DevicePairing;
use App\Models\Presentation;
use App\Models\Projection;
use App\Models\Screen;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/*
 * The screen: the noun the first pass left out.
 *
 * Everything here turns on one field. While a presentation could only be reached
 * through the URL a laptop had opened, a phone could not say "nothing is on the
 * screen", could not put a deck up, and could not take one off; with an address
 * for the wall, all three are the same write.
 */

it('claims a screen for the browser that opens the screen page', function () {
    $user = User::factory()->create();

    actingAs($user);

    $screen = Livewire::test(ProjectionPresenter::class)->get('screen');

    expect(Screen::query()->count())->toBe(1)
        ->and($screen->user_id)->toBe($user->id)
        ->and($screen->presentation_id)->toBeNull();
});

/*
 * The session cookie is what makes a browser that browser, so reloading is the
 * same screen rather than a second one appearing in the phone's list every time
 * somebody presses F5.
 */
it('re-claims the same screen when the page is reloaded', function () {
    $user = User::factory()->create();

    actingAs($user);

    $first = Livewire::test(ProjectionPresenter::class)->get('screen');
    $second = Livewire::test(ProjectionPresenter::class)->get('screen');

    expect(Screen::query()->count())->toBe(1)
        ->and($second->id)->toBe($first->id);
});

it('ages a screen out of the live scope on last_seen_at', function () {
    $user = User::factory()->create();
    Screen::factory()->stale()->create(['user_id' => $user->id]);

    expect(Screen::query()->live()->mine($user)->count())->toBe(0);
});

/*
 * Revoking a borrowed screen from the phone signs that laptop out. The screen
 * has to go with it, rather than staying live in the list and pointing at a
 * browser that can no longer read anything.
 */
it('drops a screen whose device pairing has been revoked', function () {
    $user = User::factory()->create();
    $pairing = DevicePairing::factory()->create(['user_id' => $user->id]);
    $screen = Screen::factory()->create([
        'user_id' => $user->id,
        'device_pairing_id' => $pairing->id,
    ]);

    expect(Screen::query()->live()->mine($user)->count())->toBe(1)
        ->and($screen->isLive())->toBeTrue();

    $pairing->revoke();

    expect(Screen::query()->live()->mine($user)->count())->toBe(0)
        ->and($screen->load('devicePairing')->isLive())->toBeFalse();
});

it('answers 404 for a screen that is not yours', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $screen = Screen::factory()->create(['user_id' => $owner->id]);

    actingAs($stranger);

    getJson(route('screens.state', ['screen' => $screen]))->assertNotFound();
    postJson(route('screens.state.store', ['screen' => $screen]), ['projectionId' => null])->assertNotFound();
});

/*
 * One read answers both questions, so a wall polling once a second is polling
 * once a second and not twice.
 */
it('carries the presentation state inside the screen answer', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);
    $presentation = Presentation::factory()->create([
        'projection_id' => $projection->id,
        'user_id' => $user->id,
    ]);

    $screen = Screen::factory()->create(['user_id' => $user->id]);
    $screen->point($presentation);

    actingAs($user);

    getJson(route('screens.state', ['screen' => $screen]))
        ->assertOk()
        ->assertJsonPath('presentationId', $presentation->id)
        ->assertJsonPath('title', $projection->title)
        ->assertJsonPath('state.version', $presentation->version)
        ->assertJsonPath('stateUrl', route('presentations.state', ['presentation' => $presentation->id]))
        // The remote's deck pane offers the editor, and the deck on a screen is
        // swapped without that page reloading, so the address travels with the
        // other two rather than being baked in at mount.
        ->assertJsonPath('editUrl', route('projections.edit', ['projection' => $projection->id]));
});

it('reads as showing nothing when the presentation on it has ended', function () {
    $user = User::factory()->create();
    $presentation = Presentation::factory()->create(['user_id' => $user->id]);
    $screen = Screen::factory()->create(['user_id' => $user->id]);
    $screen->point($presentation);

    $presentation->end();

    actingAs($user);

    getJson(route('screens.state', ['screen' => $screen->refresh()]))
        ->assertOk()
        ->assertJsonPath('presentationId', null)
        ->assertJsonPath('state', null);
});

it('puts a deck on the screen and takes it off again', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);
    $screen = Screen::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    postJson(route('screens.state.store', ['screen' => $screen]), ['projectionId' => $projection->id])
        ->assertOk()
        ->assertJsonPath('projectionId', $projection->id);

    $presentation = $screen->refresh()->presentation;

    expect($presentation)->not->toBeNull();

    postJson(route('screens.state.store', ['screen' => $screen]), ['projectionId' => null])
        ->assertOk()
        ->assertJsonPath('presentationId', null);

    // Clearing the screen is the deliberate end of the service — the one gesture
    // that means it, as against leaving the remote, which means only that this
    // phone is done driving.
    expect($screen->refresh()->presentation_id)->toBeNull()
        ->and($presentation->refresh()->ended_at)->not->toBeNull();
});

it('refuses to put a deck somebody else owns on the screen', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $stranger->id]);
    $screen = Screen::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    postJson(route('screens.state.store', ['screen' => $screen]), ['projectionId' => $projection->id])
        ->assertNotFound();

    expect($screen->refresh()->presentation_id)->toBeNull();
});

/*
 * A request that names no deck says only "still here", and must not take the
 * deck off the wall by saying nothing.
 */
it('leaves the screen alone when the write names no deck', function () {
    $user = User::factory()->create();
    $presentation = Presentation::factory()->create(['user_id' => $user->id]);
    $screen = Screen::factory()->create(['user_id' => $user->id]);
    $screen->point($presentation);

    actingAs($user);

    postJson(route('screens.state.store', ['screen' => $screen]), [])
        ->assertOk()
        ->assertJsonPath('presentationId', $presentation->id);

    expect($presentation->refresh()->ended_at)->toBeNull();
});

/*
 * The read doubles as the screen's heartbeat, but only for the browser that *is*
 * the screen. The phone reads this too, about once a second, and a phone polling
 * a laptop that has been closed must not keep the laptop looking alive — which
 * is the whole difference between a heartbeat and a page view.
 */
it('is not kept alive by a phone reading it', function () {
    $user = User::factory()->create();
    $screen = Screen::factory()->create([
        'user_id' => $user->id,
        'last_seen_at' => Carbon::now()->subMinutes(2),
    ]);

    actingAs($user);

    // A different browser, and so a different session: the phone.
    getJson(route('screens.state', ['screen' => $screen]))->assertOk();

    expect($screen->refresh()->last_seen_at->diffInMinutes(Carbon::now()))
        ->toBeGreaterThanOrEqual(1);
});

/*
 * And the other half of it: the screen's own browser does keep it alive, both by
 * being open on the page and by polling from it.
 */
it('is kept alive by the browser that is the screen', function () {
    $user = User::factory()->create();

    actingAs($user);

    $screen = Livewire::test(ProjectionPresenter::class)->get('screen');

    $screen->forceFill(['last_seen_at' => Carbon::now()->subMinutes(4)])->save();

    $screen->touchLastSeen();

    expect($screen->refresh()->last_seen_at->diffInSeconds(Carbon::now()))->toBeLessThan(5)
        ->and($screen->isLive())->toBeTrue();
});

/*
 * The laptop driven by hand is still a screen, so a phone picked up afterwards
 * finds the deck without anybody having to say it twice.
 */
it('points the screen at the deck a laptop opens by hand', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    $presenter = Livewire::test(ProjectionPresenter::class, ['projection' => $projection]);

    expect($presenter->get('screen')->presentation_id)->toBe($presenter->get('presentation')->id);
});

it('shows the waiting screen when nothing has been put on it', function () {
    $user = User::factory()->create();

    actingAs($user);

    get(route('projection-screen'))
        ->assertOk()
        ->assertSee(__('This screen is waiting for a deck.'));
});
