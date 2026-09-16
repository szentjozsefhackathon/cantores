<?php

use App\Livewire\Pages\ProjectionPresenter;
use App\Models\Presentation;
use App\Models\Projection;
use App\Models\ProjectionSlide;
use App\Models\Score;
use App\Models\Screen;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/*
 * The title card: what the room looks at between the projector window being
 * opened and the service starting.
 *
 * The window is opened on the laptop and then dragged onto the beamer, and for
 * those seconds the congregation reads whatever is on the page. What there is to
 * check is that it is a card and not the first hymn; that it ends on the first
 * thing anybody does, from either device; and that it never comes back over a
 * service already under way, because both the wall's heartbeat and a second
 * window joining mid-hymn would otherwise be in a position to put it there.
 */

function splashDeck(User $user): array
{
    $projection = Projection::factory()->create(['user_id' => $user->id]);

    $entry = ProjectionSlide::factory()->create([
        'projection_id' => $projection->id,
        'score_id' => Score::factory()->abc()->create(['user_id' => $user->id])->id,
    ]);

    return [$projection, $entry];
}

it('opens a presenter window on the card rather than on the first slide', function () {
    $user = User::factory()->create();
    [$projection] = splashDeck($user);

    actingAs($user);

    $presenter = Livewire::test(ProjectionPresenter::class, ['projection' => $projection]);

    expect($presenter->get('presentation')->splash)->toBe(Presentation::SPLASH_CARD);
});

/*
 * The whole point of the card being a fact about the presentation rather than
 * about the browser: the first press comes as often from the phone at the organ
 * as from the laptop, and the other device has to hear about it.
 */
it('walks the opening card to dark to deck, leaving the service on the first slide', function () {
    $user = User::factory()->create();
    [$projection, $entry] = splashDeck($user);

    $presentation = Presentation::factory()->create([
        'projection_id' => $projection->id,
        'user_id' => $user->id,
        'splash' => Presentation::SPLASH_CARD,
    ]);

    actingAs($user);

    getJson(route('presentations.state', $presentation))
        ->assertOk()
        ->assertJson(['splash' => Presentation::SPLASH_CARD]);

    // The phone's first press blacks the wall out and moves the deck nowhere.
    postJson(route('presentations.state.store', $presentation), [
        'entryId' => $entry->id,
        'slideIndex' => 0,
        'splash' => Presentation::SPLASH_DARK,
    ])->assertOk()->assertJson([
        'splash' => Presentation::SPLASH_DARK,
        'entryId' => $entry->id,
        'slideIndex' => 0,
    ]);

    // And the second starts the deck, on the slide the first press did not use
    // up.
    postJson(route('presentations.state.store', $presentation), [
        'entryId' => $entry->id,
        'slideIndex' => 0,
        'splash' => Presentation::SPLASH_OFF,
    ])->assertOk()->assertJson([
        'splash' => Presentation::SPLASH_OFF,
        'entryId' => $entry->id,
        'slideIndex' => 0,
    ]);
});

/*
 * The card ending is a change like any other, and the device that did not do it
 * finds out the way it finds out about everything else.
 */
it('moves the version on every step of the opening', function () {
    $user = User::factory()->create();
    [$projection] = splashDeck($user);

    $presentation = Presentation::factory()->create([
        'projection_id' => $projection->id,
        'user_id' => $user->id,
        'splash' => Presentation::SPLASH_CARD,
        'version' => 4,
    ]);

    actingAs($user);

    postJson(route('presentations.state.store', $presentation), ['splash' => Presentation::SPLASH_DARK])
        ->assertOk()
        ->assertJson(['version' => 5, 'splash' => Presentation::SPLASH_DARK]);

    postJson(route('presentations.state.store', $presentation), ['splash' => Presentation::SPLASH_OFF])
        ->assertOk()
        ->assertJson(['version' => 6, 'splash' => Presentation::SPLASH_OFF]);
});

/*
 * The latch. The wall reports every ten seconds whether anything happened or
 * not, and a heartbeat sent a moment before the phone's press arrives a moment
 * after it — which, without this, would put the card back over the hymn the room
 * had just been given.
 */
it('never walks the opening backwards', function () {
    $user = User::factory()->create();
    [$projection, $entry] = splashDeck($user);

    $presentation = Presentation::factory()->create([
        'projection_id' => $projection->id,
        'user_id' => $user->id,
        'splash' => Presentation::SPLASH_CARD,
    ]);

    actingAs($user);

    postJson(route('presentations.state.store', $presentation), ['splash' => Presentation::SPLASH_OFF])->assertOk();

    // The wall's heartbeat, sent a moment before the phone's press and landing a
    // moment after it.
    postJson(route('presentations.state.store', $presentation), [
        'entryId' => $entry->id,
        'splash' => Presentation::SPLASH_CARD,
    ])->assertOk()->assertJson(['splash' => Presentation::SPLASH_OFF]);

    postJson(route('presentations.state.store', $presentation), ['splash' => Presentation::SPLASH_DARK])
        ->assertOk()
        ->assertJson(['splash' => Presentation::SPLASH_OFF]);
});

/*
 * A heartbeat that says nothing about the opening says nothing about the
 * opening. The wall reports its address ten seconds after the page loaded, long
 * before anyone has pressed anything.
 */
it('leaves the card up for a heartbeat that only reports an address', function () {
    $user = User::factory()->create();
    [$projection, $entry] = splashDeck($user);

    $presentation = Presentation::factory()->create([
        'projection_id' => $projection->id,
        'user_id' => $user->id,
        'splash' => Presentation::SPLASH_CARD,
    ]);

    actingAs($user);

    postJson(route('presentations.state.store', $presentation), [
        'entryId' => $entry->id,
        'slideIndex' => 0,
        'blanked' => false,
    ])->assertOk()->assertJson(['splash' => Presentation::SPLASH_CARD]);
});

/*
 * A second window on a service already running joins the row rather than
 * starting one, so the card is not reopened over a hymn the room is singing.
 */
it('does not put the card back when a second window joins a running service', function () {
    $user = User::factory()->create();
    [$projection] = splashDeck($user);

    $presentation = Presentation::factory()->create([
        'projection_id' => $projection->id,
        'user_id' => $user->id,
        'splash' => Presentation::SPLASH_OFF,
    ]);

    actingAs($user);

    Livewire::test(ProjectionPresenter::class, ['projection' => $projection]);

    expect($presentation->fresh()->splash)->toBe(Presentation::SPLASH_OFF);
});

/*
 * The card belongs to a screen with nothing on it — the beamer is about to be
 * lined up against whatever goes up next, and that is as true of a bare screen
 * a phone points at a deck for the first time as it is of the wall's own
 * window. This is also what lets "Remove from screen" hand the projector back
 * a card to line up against: it leaves the screen exactly this empty.
 */
it('shows the opening for a deck pointed at an empty screen from the phone', function () {
    $user = User::factory()->create();
    [$projection] = splashDeck($user);

    $screen = Screen::factory()->create([
        'user_id' => $user->id,
        'last_seen_at' => Carbon::now(),
    ]);

    actingAs($user);

    postJson(route('screens.state.store', $screen), ['projectionId' => $projection->id])
        ->assertOk()
        ->assertJson(['state' => ['splash' => Presentation::SPLASH_CARD]]);
});

/*
 * A deck a phone puts on a screen that is already facing the room is not that:
 * the wall is already lined up, and an extra press mid-service — switching
 * from one deck to the next — buys nobody anything.
 */
it('shows no opening for a deck pointed at a screen already showing something', function () {
    $user = User::factory()->create();
    [$firstProjection] = splashDeck($user);
    [$secondProjection] = splashDeck($user);

    $showing = Presentation::factory()->create([
        'projection_id' => $firstProjection->id,
        'user_id' => $user->id,
        'splash' => Presentation::SPLASH_OFF,
    ]);

    $screen = Screen::factory()->create([
        'user_id' => $user->id,
        'presentation_id' => $showing->id,
        'last_seen_at' => Carbon::now(),
    ]);

    actingAs($user);

    postJson(route('screens.state.store', $screen), ['projectionId' => $secondProjection->id])
        ->assertOk()
        ->assertJson(['state' => ['splash' => Presentation::SPLASH_OFF]]);
});
