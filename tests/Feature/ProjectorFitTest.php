<?php

use App\Models\Presentation;
use App\Models\Projection;
use App\Models\Screen;
use App\Models\User;
use Illuminate\Support\Carbon;

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
 * about the deck, so it is held on the screen — which is what everything here
 * is checking.
 */

it('answers the fit the application has always drawn, for a screen nobody has touched', function () {
    $user = User::factory()->create();
    $screen = Screen::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    getJson(route('screens.state', ['screen' => $screen]))
        ->assertOk()
        // Whole numbers come back through JSON as whole numbers.
        ->assertJsonPath('fit.scale', 1)
        ->assertJsonPath('fit.x', 0)
        ->assertJsonPath('fit.y', 0);
});

it('moves the picture on the wall without touching what is on it', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);
    $presentation = Presentation::factory()->create([
        'user_id' => $user->id,
        'projection_id' => $projection->id,
    ]);
    $screen = Screen::factory()->create([
        'user_id' => $user->id,
        'presentation_id' => $presentation->id,
    ]);

    actingAs($user);

    postJson(route('screens.state.store', ['screen' => $screen]), [
        'fit' => ['scale' => 0.8, 'x' => -0.1, 'y' => 0.06],
    ])
        ->assertOk()
        ->assertJsonPath('fit.scale', 0.8)
        ->assertJsonPath('fit.x', -0.1)
        ->assertJsonPath('presentationId', $presentation->id);

    expect($screen->refresh()->fit_scale)->toBe(0.8)
        ->and($screen->presentation_id)->toBe($presentation->id)
        ->and($presentation->refresh()->version)->toBe(1);
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

    postJson(route('screens.state.store', ['screen' => $screen]), [
        'fit' => ['scale' => 40, 'x' => -12, 'y' => 12],
    ])
        ->assertOk()
        ->assertJsonPath('fit.scale', (int) Screen::FIT_MAX_SCALE)
        ->assertJsonPath('fit.x', (int) -Screen::FIT_MAX_OFFSET)
        ->assertJsonPath('fit.y', (int) Screen::FIT_MAX_OFFSET);
});

/*
 * It is the room that is crooked, not the deck. A screen lined up before Mass is
 * still lined up when the second hymn is put on it, and next Sunday too.
 */
it('keeps the fit when a different deck is put on the screen', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);
    $screen = Screen::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    postJson(route('screens.state.store', ['screen' => $screen]), ['fit' => ['scale' => 0.75, 'y' => 0.08]])
        ->assertOk();

    postJson(route('screens.state.store', ['screen' => $screen]), ['projectionId' => $projection->id])
        ->assertOk()
        ->assertJsonPath('fit.scale', 0.75)
        ->assertJsonPath('fit.y', 0.08);

    postJson(route('screens.state.store', ['screen' => $screen]), ['projectionId' => null])
        ->assertOk()
        ->assertJsonPath('presentationId', null)
        ->assertJsonPath('fit.scale', 0.75);
});

/*
 * A heartbeat says nothing about the picture, and must not quietly re-centre a
 * screen somebody spent the rehearsal lining up.
 */
it('leaves the fit alone in a request that does not mention it', function () {
    $user = User::factory()->create();
    $screen = Screen::factory()->create([
        'user_id' => $user->id,
        'fit_scale' => 0.9,
        'fit_x' => 0.05,
        'fit_y' => -0.05,
        'last_seen_at' => Carbon::now()->subMinutes(2),
    ]);

    actingAs($user);

    postJson(route('screens.state.store', ['screen' => $screen]), [])
        ->assertOk()
        ->assertJsonPath('fit.scale', 0.9)
        ->assertJsonPath('fit.x', 0.05);
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

    postJson(route('screens.state.store', ['screen' => $screen]), ['fit' => ['y' => 0.2]])
        ->assertOk()
        ->assertJsonPath('fit.scale', 0.9)
        ->assertJsonPath('fit.x', 0.05)
        ->assertJsonPath('fit.y', 0.2);
});

it('will not let one cantor line up another one\'s screen', function () {
    $screen = Screen::factory()->create(['user_id' => User::factory()->create()->id]);

    actingAs(User::factory()->create());

    postJson(route('screens.state.store', ['screen' => $screen]), ['fit' => ['scale' => 0.5]])
        ->assertNotFound();

    expect($screen->refresh()->fit_scale)->toBe(1.0);
});
