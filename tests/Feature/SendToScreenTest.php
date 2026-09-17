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
 * this puts the deck up as the show and stays where it is.
 */

it('puts the deck up as the show without leaving the page', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);
    Screen::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(SendToScreen::class, ['projection' => $projection])
        ->call('putUp')
        ->assertHasNoErrors()
        ->assertSee(__('On the screen'));

    expect(Presentation::currentFor($user)?->projection_id)->toBe($projection->id);
});

/*
 * Pressing it for the deck already up changes nothing: the service stays where
 * it had got to.
 */
it('leaves the show where it is when this deck is already up', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);
    Screen::factory()->create(['user_id' => $user->id]);

    $running = Presentation::putUp($user, $projection);

    actingAs($user);

    Livewire::test(SendToScreen::class, ['projection' => $projection])
        ->call('putUp');

    expect(Presentation::query()->where('projection_id', $projection->id)->count())->toBe(1)
        ->and(Presentation::currentFor($user)->id)->toBe($running->id);
});

/*
 * Sent from a row of its own, nested inside the plan-documents list, so
 * putting a deck up has to say so out loud for that page's own "currently
 * projecting" banner to catch up.
 */
it('announces that the show changed', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);
    Screen::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(SendToScreen::class, ['projection' => $projection])
        ->call('putUp')
        ->assertDispatched('presentation-changed');
});

it('refuses to put up somebody else\'s deck', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => User::factory()->create()->id]);

    actingAs($user);

    Livewire::test(SendToScreen::class, ['projection' => $projection])
        ->call('putUp')
        ->assertForbidden();

    expect(Presentation::query()->count())->toBe(0);
});

/*
 * A closed tab ages out rather than depending on an event browsers do not
 * reliably give, so a screen nobody has heard from does not count as on.
 */
it('counts a stale screen as no screen', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);

    Screen::factory()->create([
        'user_id' => $user->id,
        'last_seen_at' => Carbon::now()->subMinutes(Screen::STALE_MINUTES + 1),
    ]);

    actingAs($user);

    Livewire::test(SendToScreen::class, ['projection' => $projection])
        ->assertSee(__('Open a screen'))
        ->assertDontSee(__('Put on screen'));
});

/*
 * The button answers for itself, because the person who pressed it is looking at
 * a laptop and the deck went up somewhere else — but it stops answering when
 * what it claims stops being true.
 */
it('stops saying the deck is on the screen once another deck is up', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);
    $other = Projection::factory()->create(['user_id' => $user->id]);
    Screen::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    $component = Livewire::test(SendToScreen::class, ['projection' => $projection])
        ->call('putUp');

    expect($component->instance()->isOnScreen())->toBeTrue();

    Presentation::putUp($user, $other);

    Livewire::test(SendToScreen::class, ['projection' => $projection])
        ->assertSee(__('Put on screen'))
        ->assertDontSee(__('On the screen'));
});

/*
 * One button, and no list of screens to choose from: every screen shows the
 * same show.
 */
it('offers one button however many screens are on', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);
    Screen::factory()->count(2)->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(SendToScreen::class, ['projection' => $projection])
        ->assertSee(__('Put on screen'))
        ->assertDontSeeHtml('<ui-dropdown');
});

/*
 * The control has to be on the pages where decks are listed, not only inside the
 * remote — that is what makes the two-screen laptop a layout question rather
 * than a second mechanism.
 */
it('offers the way to make a screen from the editor when none is on', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    get(route('projections.edit', ['projection' => $projection]))
        ->assertOk()
        ->assertSee(__('Open a screen'))
        ->assertDontSee(__('Put on screen'));
});

/*
 * And it opens that screen on the deck whose button was pressed: opening the
 * presenter on a deck puts that deck up, so one press is enough.
 */
it('opens the new screen window on the deck rather than empty', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(SendToScreen::class, ['projection' => $projection])
        ->assertSee(route('projections.present', ['projection' => $projection->id]), escape: false)
        ->assertDontSee('href="'.route('projection-screen').'"', escape: false);
});

it('offers the button from the editor and from the document list', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $projection = Projection::factory()->create(['user_id' => $user->id, 'music_plan_id' => $plan->id]);
    Screen::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    get(route('projections.edit', ['projection' => $projection]))
        ->assertOk()
        ->assertSee(__('Put on screen'));

    Livewire::test(PlanDocuments::class)
        ->assertOk()
        ->assertSee(__('Put on screen'));
});

/*
 * The window that was closed.
 *
 * A screen row outlives its window by five minutes, deliberately — a moment of
 * bad signal must not read as a laptop that was never started. On another
 * machine there is nothing to be done about that; on this one there is, because
 * the window is this browser's own and can simply be opened again.
 */

it('opens the screen window when the only screen is this browser', function () {
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
// a button that puts the deck up and nothing more.
it('only puts the deck up when a screen is on somewhere else', function () {
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
