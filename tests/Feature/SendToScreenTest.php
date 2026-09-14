<?php

use App\Livewire\Pages\PlanDocuments;
use App\Livewire\Projection\SendToScreen;
use App\Models\MusicPlan;
use App\Models\Presentation;
use App\Models\Projection;
use App\Models\Screen;
use App\Models\User;
use App\Support\DeviceId;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/*
 * The laptop's half of the two-screen Sunday: the projector is the second
 * display, and the window being worked in has to go on showing the music plan
 * and the slides while the room reads the deck. Present takes over this window;
 * this takes over the screen, and that is the whole difference under test.
 */

it('points the waiting screen at the deck without ending up showing it', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);
    $screen = Screen::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(SendToScreen::class, ['projection' => $projection])
        ->call('send', $screen->id)
        ->assertHasNoErrors();

    $screen->refresh();

    expect($screen->showing()?->projection_id)->toBe($projection->id);
});

/*
 * Joined rather than started afresh, everywhere a deck goes up: a laptop already
 * showing it and a window putting it there must land on the same row, or the two
 * follow different presentations and the remote drives the wrong one.
 */
it('joins the presentation the screen is already showing rather than starting a second', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);
    $screen = Screen::factory()->create(['user_id' => $user->id]);

    $running = Presentation::resumeFor($projection, $user, null);
    $screen->point($running);

    actingAs($user);

    Livewire::test(SendToScreen::class, ['projection' => $projection])
        ->call('send', $screen->id);

    expect(Presentation::query()->where('projection_id', $projection->id)->count())->toBe(1)
        ->and($screen->refresh()->presentation_id)->toBe($running->id);
});

/*
 * A screen somebody else is facing a room with is not a thing this account may
 * know exists — 404 rather than 403, as at the endpoints.
 */
it('refuses a screen belonging to somebody else', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);
    $theirs = Screen::factory()->create(['user_id' => User::factory()->create()->id]);

    actingAs($user);

    Livewire::test(SendToScreen::class, ['projection' => $projection])
        ->call('send', $theirs->id)
        ->assertStatus(404);

    expect($theirs->refresh()->presentation_id)->toBeNull();
});

/*
 * A closed tab ages out rather than depending on an event browsers do not
 * reliably give, so a screen nobody has heard from is not offered.
 */
it('leaves a stale screen out of the list', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);

    Screen::factory()->create([
        'user_id' => $user->id,
        'last_seen_at' => Carbon::now()->subMinutes(Screen::STALE_MINUTES + 1),
    ]);

    actingAs($user);

    expect(Livewire::test(SendToScreen::class, ['projection' => $projection])->instance()->screens())
        ->toBeEmpty();
});

/*
 * The button answers for itself, because the person who pressed it is looking at
 * a laptop and the deck went up somewhere else — but it stops answering when
 * what it claims stops being true.
 */
it('stops reporting the deck as sent once the screen is showing something else', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);
    $other = Projection::factory()->create(['user_id' => $user->id]);
    $screen = Screen::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    $component = Livewire::test(SendToScreen::class, ['projection' => $projection])
        ->call('send', $screen->id);

    expect($component->instance()->sentScreen())->not->toBeNull();

    $screen->refresh()->point(Presentation::resumeFor($other, $user, null));

    expect(Livewire::test(SendToScreen::class, ['projection' => $projection, 'sentScreenId' => $screen->id])
        ->instance()->sentScreen())->toBeNull();
});

/*
 * The control has to be on the pages where decks are listed, not only inside the
 * remote — that is what makes the two-screen laptop a layout question rather
 * than a second mechanism. These render the real blade, including the Flux
 * dropdown, which the component tests above never reach.
 */
it('offers the way to make a screen from the editor when none is waiting', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    get(route('projections.edit', ['projection' => $projection]))
        ->assertOk()
        ->assertSee(__('Open a screen'))
        ->assertDontSee(__('Send to screen'));
});

it('offers the waiting screen from the editor and from the document list', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $projection = Projection::factory()->create(['user_id' => $user->id, 'music_plan_id' => $plan->id]);
    Screen::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    get(route('projections.edit', ['projection' => $projection]))
        ->assertOk()
        ->assertSee(__('Send to screen'));

    Livewire::test(PlanDocuments::class)
        ->assertOk()
        ->assertSee(__('Send to screen'));
});

/*
 * The window that was closed.
 *
 * A screen row outlives its window by five minutes, deliberately — a moment of
 * bad signal must not read as a laptop that was never started. On another
 * machine there is nothing to be done about that; on this one there is, because
 * the window is this browser's own and can simply be opened again.
 */

it('offers to open the screen window when the screen is this browser', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);

    Screen::factory()->create([
        'user_id' => $user->id,
        'device_id' => DeviceId::current(),
    ]);

    actingAs($user);

    Livewire::test(SendToScreen::class, ['projection' => $projection])
        ->assertSee(route('projections.present', ['projection' => $projection->id]), escape: false)
        ->assertSee(SendToScreen::WINDOW, escape: false);
});

// Another machine has no window this browser can open, so the control stays
// what it was: a button that points a deck and nothing more.
it('only points the deck when the screen is somewhere else', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);

    Screen::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(SendToScreen::class, ['projection' => $projection])
        ->assertDontSee(route('projections.present', ['projection' => $projection->id]), escape: false);
});

// Named, so that clicking it twice brings the wall forward instead of leaving a
// second copy of it behind the first.
it('opens the screen window under one name', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(SendToScreen::class, ['projection' => $projection])
        ->assertSee(SendToScreen::WINDOW, escape: false)
        ->assertDontSee('target="_blank"', escape: false);
});
