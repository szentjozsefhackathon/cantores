<?php

use App\Livewire\Pages\ProjectionPresenter;
use App\Models\DevicePairing;
use App\Models\Presentation;
use App\Models\Projection;
use App\Models\Screen;
use App\Models\User;
use App\Services\ShowState;
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
 *
 * The answer belongs to the person and to none of their devices, which is what
 * lets the hub carry it — so it names each screen's device rather than picking
 * one out as this one, and each screen brings the two facts a browser needs to
 * decide whether it may be shown it.
 */
it('lists the live screens of the person asking, and names the device each is', function () {
    $user = User::factory()->create();

    $wall = Screen::factory()->create(['user_id' => $user->id]);
    $here = Screen::factory()->create(['user_id' => $user->id, 'device_id' => DeviceId::current()]);
    Screen::factory()->stale()->create(['user_id' => $user->id]);
    Screen::factory()->create(['user_id' => User::factory()->create()->id]);

    actingAs($user);

    $screens = collect(getJson(route('show.state'))->assertOk()->json('screens'))->keyBy('id');

    expect($screens->keys()->all())->toEqualCanonicalizing([$wall->id, $here->id])
        ->and($screens[$wall->id]['deviceId'])->toBe($wall->device_id)
        ->and($screens[$here->id]['deviceId'])->toBe(DeviceId::current())
        ->and($screens[$wall->id]['offered'])->toBeTrue()
        ->and($screens[$wall->id]['presenting'])->toBeTrue()
        ->and($screens[$wall->id]['responding'])->toBeTrue()
        ->and($screens[$wall->id]['label'])->toBe($wall->label())
        ->and($screens[$wall->id]['fitUrl'])->toBe(route('screens.fit', ['screen' => $wall->id]));
});

/*
 * And a wall that has stopped answering says so as a fact rather than as a
 * timestamp, because this body is compared with its own last version on every
 * poll: a clock in it would make every quiet poll a full answer down the wire.
 */
it('says when a screen has stopped answering, without carrying a clock', function () {
    $user = User::factory()->create();
    $silent = Screen::factory()->create([
        'user_id' => $user->id,
        'last_seen_at' => Carbon::now()->subSeconds(Screen::SILENT_SECONDS + 5),
    ]);

    actingAs($user);

    $screen = collect(getJson(route('show.state'))->assertOk()->json('screens'))->firstWhere('id', $silent->id);

    expect($screen['responding'])->toBeFalse()
        ->and($screen)->not->toHaveKey('appliedAt');
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

    getJson(route('show.state', ['screen' => 1]))->assertOk();

    expect($screen->refresh()->last_seen_at->diffInSeconds(Carbon::now()))->toBeLessThan(5)
        ->and($screen->isLive())->toBeTrue();
});

/*
 * The remote reads the show from the very laptop the wall is on, too. Only the
 * wall's own read is its heartbeat, or a closed wall would stay on for as long
 * as the remote beside it was open.
 */
it('is not kept alive by the remote on the same browser', function () {
    $user = User::factory()->create();
    $screen = Screen::factory()->create([
        'user_id' => $user->id,
        'device_id' => DeviceId::current(),
        'last_seen_at' => Carbon::now()->subMinutes(2),
    ]);

    actingAs($user);

    $screens = collect(getJson(route('show.state'))->assertOk()->json('screens'))->keyBy('id');

    // Listed, because the answer is the same for every device of this
    // person's — and listed as not presenting, which is what the browser on
    // this device reads to leave it out of the walls it draws.
    expect($screen->refresh()->last_seen_at->diffInMinutes(Carbon::now()))->toBeGreaterThanOrEqual(1)
        ->and($screens[$screen->id]['presenting'])->toBeFalse()
        ->and(ShowState::screensFor($user)->pluck('id')->all())->toBe([]);
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

it('acknowledges rendered state only for this browser screen without moving the presentation', function () {
    $user = User::factory()->create();
    $presentation = Presentation::factory()->create(['user_id' => $user->id, 'version' => 7]);
    $screen = Screen::factory()->create([
        'user_id' => $user->id,
        'device_id' => DeviceId::current(),
    ]);
    $revision = $presentation->projection->revision();

    actingAs($user);

    postJson(route('screens.ack', $screen), [
        'presentationId' => $presentation->id,
        'appliedVersion' => 7,
        'drawnRevision' => $revision,
    ])->assertOk()
        ->assertJsonPath('presentationId', $presentation->id)
        ->assertJsonPath('appliedVersion', 7)
        ->assertJsonPath('drawnRevision', $revision);

    expect($presentation->refresh()->version)->toBe(7)
        ->and($screen->refresh()->applied_presentation_id)->toBe($presentation->id)
        ->and($screen->applied_at)->not->toBeNull();

    getJson(route('show.state'))
        ->assertOk()
        ->assertJsonPath('screens.0.appliedPresentationId', $presentation->id)
        ->assertJsonPath('screens.0.appliedVersion', 7)
        ->assertJsonPath('screens.0.drawnRevision', $revision);
});

it('answers 404 when one device acknowledges another device screen', function () {
    $user = User::factory()->create();
    $presentation = Presentation::factory()->create(['user_id' => $user->id]);
    $screen = Screen::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    postJson(route('screens.ack', $screen), [
        'presentationId' => $presentation->id,
        'appliedVersion' => $presentation->version,
        'drawnRevision' => $presentation->projection->revision(),
    ])->assertNotFound();

    expect($screen->refresh()->applied_presentation_id)->toBeNull();
});

it('keeps acknowledgements for two screens independent', function () {
    $user = User::factory()->create();
    $presentation = Presentation::factory()->create(['user_id' => $user->id, 'version' => 9]);
    $first = Screen::factory()->create(['user_id' => $user->id]);
    $second = Screen::factory()->create(['user_id' => $user->id]);

    $first->acknowledge($presentation, 9, 'new-revision');
    $second->acknowledge($presentation, 8, 'old-revision');

    expect($first->refresh()->applied_version)->toBe(9)
        ->and($first->drawn_revision)->toBe('new-revision')
        ->and($second->refresh()->applied_version)->toBe(8)
        ->and($second->drawn_revision)->toBe('old-revision');
});
