<?php

use App\Livewire\Pages\ProjectionPresenter;
use App\Models\DevicePairing;
use App\Models\Presentation;
use App\Models\Projection;
use App\Models\Screen;
use App\Models\User;
use App\Support\DeviceId;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/*
 * The screen, and the show it shows.
 *
 * A screen is a device facing a room and points at nothing: it shows its
 * owner's show. What is checked here is that a browser opening the screen page
 * becomes a screen, that the show's answer says what is up and which screens
 * are on, and that putting a deck up and taking it down are the same write from
 * any device.
 */

it('claims a screen for the browser that opens the screen page', function () {
    $user = User::factory()->create();

    actingAs($user);

    $screen = Livewire::test(ProjectionPresenter::class)->get('screen');

    expect(Screen::query()->count())->toBe(1)
        ->and($screen->user_id)->toBe($user->id);
});

/*
 * The session cookie is what makes a browser that browser, so reloading is the
 * same screen rather than a second one appearing every time somebody presses F5.
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
 * has to go with it, rather than staying live and listed as a wall that can no
 * longer read anything.
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

/*
 * One read answers both questions, so a wall polling once a second is polling
 * once a second and not twice.
 */
it('carries the presentation state inside the show answer', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);
    $presentation = Presentation::factory()->create([
        'projection_id' => $projection->id,
        'user_id' => $user->id,
    ]);

    actingAs($user);

    getJson(route('show.state'))
        ->assertOk()
        ->assertJsonPath('presentationId', $presentation->id)
        ->assertJsonPath('title', $projection->title)
        ->assertJsonPath('state.version', $presentation->version)
        ->assertJsonPath('stateUrl', route('presentations.state', ['presentation' => $presentation->id]))
        // The remote's deck pane offers the editor, and the deck in a show is
        // swapped without that page reloading, so the address travels with the
        // other two rather than being baked in at mount.
        ->assertJsonPath('editUrl', route('projections.edit', ['projection' => $projection->id]));
});

it('reads as showing nothing when the show has ended', function () {
    $user = User::factory()->create();
    $presentation = Presentation::factory()->create(['user_id' => $user->id]);

    $presentation->end();

    actingAs($user);

    getJson(route('show.state'))
        ->assertOk()
        ->assertJsonPath('presentationId', null)
        ->assertJsonPath('state', null);
});

it('never answers with somebody else\'s show', function () {
    $user = User::factory()->create();
    Presentation::factory()->create(['user_id' => User::factory()->create()->id]);

    actingAs($user);

    getJson(route('show.state'))
        ->assertOk()
        ->assertJsonPath('presentationId', null);
});

it('puts a deck up and takes it down again', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    postJson(route('show.state.store'), ['projectionId' => $projection->id])
        ->assertOk()
        ->assertJsonPath('projectionId', $projection->id);

    $presentation = Presentation::currentFor($user);

    expect($presentation)->not->toBeNull();

    postJson(route('show.state.store'), ['projectionId' => null])
        ->assertOk()
        ->assertJsonPath('presentationId', null);

    // Taking the show down is the deliberate end of the service — the one
    // gesture that means it, as against leaving the remote, which means only
    // that this phone is done driving.
    expect($presentation->refresh()->ended_at)->not->toBeNull()
        ->and(Presentation::currentFor($user))->toBeNull();
});

it('refuses to put up a deck somebody else owns', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => User::factory()->create()->id]);

    actingAs($user);

    postJson(route('show.state.store'), ['projectionId' => $projection->id])
        ->assertNotFound();

    expect(Presentation::query()->count())->toBe(0);
});

/*
 * A request that names no deck is not a way to end a service by accident.
 */
it('refuses a write that does not say which deck', function () {
    $user = User::factory()->create();
    $presentation = Presentation::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    postJson(route('show.state.store'), [])->assertUnprocessable();

    expect($presentation->refresh()->ended_at)->toBeNull();
});

/*
 * The screens that are on travel with the show: the remote says where it is on
 * and aims the fit panel from them, and the wall reads its own fit back.
 */
it('lists the live screens of the person asking, and says which one is this device', function () {
    $user = User::factory()->create();

    $wall = Screen::factory()->create(['user_id' => $user->id]);
    $here = Screen::factory()->create(['user_id' => $user->id, 'device_id' => DeviceId::current()]);
    Screen::factory()->stale()->create(['user_id' => $user->id]);
    Screen::factory()->create(['user_id' => User::factory()->create()->id]);

    actingAs($user);

    $screens = collect(getJson(route('show.state'))->assertOk()->json('screens'))->keyBy('id');

    expect($screens->keys()->all())->toEqualCanonicalizing([$wall->id, $here->id])
        ->and($screens[$wall->id]['isThisDevice'])->toBeFalse()
        ->and($screens[$here->id]['isThisDevice'])->toBeTrue()
        ->and($screens[$wall->id]['label'])->toBe($wall->label())
        ->and($screens[$wall->id]['fitUrl'])->toBe(route('screens.fit', ['screen' => $wall->id]));
});

/*
 * The read doubles as the screen's heartbeat, but only for the browser that *is*
 * the screen. The phone reads this too, about once a second, and a phone polling
 * while a laptop has been closed must not keep the laptop looking alive — which
 * is the whole difference between a heartbeat and a page view.
 */
it('is not kept alive by a phone reading the show', function () {
    $user = User::factory()->create();
    $screen = Screen::factory()->create([
        'user_id' => $user->id,
        'last_seen_at' => Carbon::now()->subMinutes(2),
    ]);

    actingAs($user);

    getJson(route('show.state'))->assertOk();

    expect($screen->refresh()->last_seen_at->diffInMinutes(Carbon::now()))
        ->toBeGreaterThanOrEqual(1);
});

it('is kept alive by the browser that is the screen', function () {
    $user = User::factory()->create();
    $screen = Screen::factory()->create([
        'user_id' => $user->id,
        'device_id' => DeviceId::current(),
        'last_seen_at' => Carbon::now()->subMinutes(4),
    ]);

    actingAs($user);

    getJson(route('show.state'))->assertOk();

    expect($screen->refresh()->last_seen_at->diffInSeconds(Carbon::now()))->toBeLessThan(5)
        ->and($screen->isLive())->toBeTrue();
});

/*
 * A deck set up from the phone before the laptop is on must still be up when
 * the laptop arrives, because somebody was watching it.
 */
it('keeps the show alive while somebody is reading it', function () {
    $user = User::factory()->create();
    $presentation = Presentation::factory()->create([
        'user_id' => $user->id,
        'last_seen_at' => Carbon::now()->subMinutes(4),
    ]);

    actingAs($user);

    getJson(route('show.state'))->assertOk();

    expect($presentation->refresh()->isLive())->toBeTrue()
        ->and($presentation->last_seen_at->diffInSeconds(Carbon::now()))->toBeLessThan(5);
});

it('shows the waiting screen when nothing is up', function () {
    $user = User::factory()->create();

    actingAs($user);

    get(route('projection-screen'))
        ->assertOk()
        ->assertSee(__('This screen is waiting for a deck.'));
});
