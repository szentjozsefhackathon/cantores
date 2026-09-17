<?php

use App\Models\Presentation;
use App\Models\Projection;
use App\Models\Screen;
use App\Models\User;
use App\Support\DeviceId;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/*
 * Where the picture lands on the wall.
 *
 * The presenter fits a deck into the projector and centres it, and one of the
 * churches is the room where that is not the end of the matter: a 4:3 beamer on
 * a square screen hung high, a deck built 1:1 for the glass, and the whole thing
 * still landing above the heads it was meant for. Nothing about that is a fact
 * about the deck or the show, so it is held on the screen — the one thing still
 * addressed to a device — which is what everything here is checking.
 */

/** A screen's entry in the show's answer. */
function screenInShow(Screen $screen): array
{
    return collect(getJson(route('show.state'))->assertOk()->json('screens'))
        ->firstWhere('id', $screen->id);
}

it('answers the fit the application has always drawn, for a screen nobody has touched', function () {
    $user = User::factory()->create();
    $screen = Screen::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    // Whole numbers come back through JSON as whole numbers.
    expect(screenInShow($screen)['fit'])->toBe(['scale' => 1, 'x' => 0, 'y' => 0]);
});

it('moves the picture on the wall without touching the show', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);
    $presentation = Presentation::factory()->create([
        'user_id' => $user->id,
        'projection_id' => $projection->id,
    ]);
    $screen = Screen::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    postJson(route('screens.fit', ['screen' => $screen]), [
        'fit' => ['scale' => 0.8, 'x' => -0.1, 'y' => 0.06],
    ])
        ->assertOk()
        ->assertJsonPath('fit.scale', 0.8)
        ->assertJsonPath('fit.x', -0.1);

    expect($screen->refresh()->fit_scale)->toBe(0.8)
        ->and($presentation->refresh()->version)->toBe(1)
        ->and($presentation->ended_at)->toBeNull();
});

/*
 * Every projector is hung differently, so a nudge lands on the wall it was
 * aimed at and on no other.
 */
it('writes the fit only to the screen it was aimed at', function () {
    $user = User::factory()->create();
    $church = Screen::factory()->create(['user_id' => $user->id]);
    $home = Screen::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    postJson(route('screens.fit', ['screen' => $church]), ['fit' => ['y' => 0.2]])->assertOk();

    expect($church->refresh()->fit_y)->toBe(0.2)
        ->and($home->refresh()->fit_y)->toBe(0.0);
});

/*
 * The wall reads its own fit back from the show's answer.
 */
it('gives the wall its own fit in the show answer', function () {
    $user = User::factory()->create();
    $wall = Screen::factory()->create([
        'user_id' => $user->id,
        'device_id' => DeviceId::current(),
        'fit_scale' => 0.75,
    ]);

    actingAs($user);

    $own = collect(getJson(route('show.state'))->json('screens'))->firstWhere('isThisDevice', true);

    expect($own['id'])->toBe($wall->id)
        ->and($own['fit']['scale'])->toBe(0.75);
});

/*
 * The phone is across the building from the projector, and the person pressing
 * an arrow cannot see that the tenth press did nothing. A picture driven off the
 * edge of itself is one somebody has to walk to the laptop to rescue.
 */
it('clamps a picture that has been pushed past the edge of the screen', function () {
    $user = User::factory()->create();
    $screen = Screen::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    postJson(route('screens.fit', ['screen' => $screen]), [
        'fit' => ['scale' => 40, 'x' => -12, 'y' => 12],
    ])
        ->assertOk()
        ->assertJsonPath('fit.scale', (int) Screen::FIT_MAX_SCALE)
        ->assertJsonPath('fit.x', (int) -Screen::FIT_MAX_OFFSET)
        ->assertJsonPath('fit.y', (int) Screen::FIT_MAX_OFFSET);
});

/*
 * It is the room that is crooked, not the deck. A screen lined up before Mass is
 * still lined up when the second hymn is put up, and when the show is taken
 * down.
 */
it('keeps the fit when a different deck is put up', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);
    $screen = Screen::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    postJson(route('screens.fit', ['screen' => $screen]), ['fit' => ['scale' => 0.75, 'y' => 0.08]])
        ->assertOk();

    postJson(route('show.state.store'), ['projectionId' => $projection->id])->assertOk();

    expect(screenInShow($screen)['fit'])->toMatchArray(['scale' => 0.75, 'y' => 0.08]);

    postJson(route('show.state.store'), ['projectionId' => null])->assertOk();

    expect(screenInShow($screen)['fit']['scale'])->toBe(0.75);
});

/*
 * Half a nudge: the panel sends the whole fit, but a client that sends one field
 * moves one field.
 */
it('moves only what a request names', function () {
    $user = User::factory()->create();
    $screen = Screen::factory()->create([
        'user_id' => $user->id,
        'fit_scale' => 0.9,
        'fit_x' => 0.05,
    ]);

    actingAs($user);

    postJson(route('screens.fit', ['screen' => $screen]), ['fit' => ['y' => 0.2]])
        ->assertOk()
        ->assertJsonPath('fit.scale', 0.9)
        ->assertJsonPath('fit.x', 0.05)
        ->assertJsonPath('fit.y', 0.2);
});

it('refuses a nudge that says nothing about the fit', function () {
    $user = User::factory()->create();
    $screen = Screen::factory()->create(['user_id' => $user->id, 'fit_scale' => 0.9]);

    actingAs($user);

    postJson(route('screens.fit', ['screen' => $screen]), [])->assertUnprocessable();

    expect($screen->refresh()->fit_scale)->toBe(0.9);
});

it('will not let one cantor line up another one\'s screen', function () {
    $screen = Screen::factory()->create(['user_id' => User::factory()->create()->id]);

    actingAs(User::factory()->create());

    postJson(route('screens.fit', ['screen' => $screen]), ['fit' => ['scale' => 0.5]])
        ->assertNotFound();

    expect($screen->refresh()->fit_scale)->toBe(1.0);
});
