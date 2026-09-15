<?php

use App\Livewire\Pages\ProjectionPresenter;
use App\Livewire\Pages\ProjectionRemote;
use App\Livewire\Pages\ProjectionRemoteDecks;
use App\Livewire\Pages\ProjectionRemoteList;
use App\Models\Music;
use App\Models\Presentation;
use App\Models\Projection;
use App\Models\ProjectionSlide;
use App\Models\Score;
use App\Models\Screen;
use App\Models\User;
use App\Support\DeviceId;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/*
 * The phone's half of the Sunday. Both devices are the same person already —
 * the laptop was signed in from this phone with the QR code — so there is
 * nothing to pair and no token to read across the room, and what is left to
 * check is that the phone reaches the *screen*, sees the same deck the wall
 * sees, can put another one up, and cannot reach anybody else's.
 */

it('starts a presentation and points the screen at it when a deck is opened on the laptop', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    $presenter = Livewire::test(ProjectionPresenter::class, ['projection' => $projection]);

    expect(Presentation::query()->live()->mine($user)->count())->toBe(1)
        ->and($presenter->get('presentation')->projection_id)->toBe($projection->id)
        ->and($presenter->get('revision'))->toBe($projection->revision())
        ->and($presenter->get('screen')->presentation_id)->toBe($presenter->get('presentation')->id);
});

/*
 * One screen facing a room is the normal Sunday, and then there is nothing to
 * ask.
 */
it('goes straight to the only screen', function () {
    $user = User::factory()->create();
    $screen = Screen::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    get(route('projection-remote'))
        ->assertRedirect(route('projection-remote.control', ['screen' => $screen->id]));
});

/*
 * The sentence this page exists to be able to say. Before a screen was a thing
 * at all, a phone opened before the laptop was ready could only show an empty
 * list and leave the cantor guessing.
 */
it('says that no screen is waiting rather than showing an empty list', function () {
    $user = User::factory()->create();
    Screen::factory()->stale()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(ProjectionRemoteList::class)
        ->assertOk()
        ->assertSee(__('No screen is waiting yet'));
});

// Only this person's own screens, and only the ones still there.
it('lists only the live screens of the person holding the phone', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create();

    $waiting = Screen::factory()->create(['user_id' => $user->id]);
    Screen::factory()->create(['user_id' => $user->id]);
    Screen::factory()->stale()->create(['user_id' => $user->id]);
    Screen::factory()->create(['user_id' => $stranger->id]);

    actingAs($user);

    $listed = Livewire::test(ProjectionRemoteList::class)->instance()->screens();

    expect($listed)->toHaveCount(2)
        ->and($listed->pluck('id'))->toContain($waiting->id);
});

// The laptop showing the deck on the projector is the same device as the window
// the remote is opened in, so leaving this browser out of the list left the one
// screen a desktop has unaddressable. It is listed, and said to be this device.
it('lists the screen this browser itself is', function () {
    $user = User::factory()->create();

    $chapel = Screen::factory()->create(['user_id' => $user->id]);
    $church = Screen::factory()->create(['user_id' => $user->id]);
    $here = Screen::factory()->create([
        'user_id' => $user->id,
        'device_id' => DeviceId::current(),
    ]);

    actingAs($user);

    $component = Livewire::test(ProjectionRemoteList::class);

    expect($component->instance()->screens()->pluck('id')->all())
        ->toEqualCanonicalizing([$chapel->id, $church->id, $here->id]);

    $component->assertSee(__('This device'));
});

// Listed, but never the screen somebody is thrown into: a phone that pressed
// Present is a screen like any other, and driving itself is never what the
// cantor reaching for the remote meant.
it('does not enter this browser\'s own screen without asking', function () {
    $user = User::factory()->create();

    Screen::factory()->create([
        'user_id' => $user->id,
        'device_id' => DeviceId::current(),
    ]);

    actingAs($user);

    Livewire::test(ProjectionRemoteList::class)
        ->assertOk()
        ->assertNoRedirect();
});

// And so the wall is still the one live screen, entered without asking.
it('enters the only other screen without asking even when this browser is one too', function () {
    $user = User::factory()->create();

    $wall = Screen::factory()->create(['user_id' => $user->id]);
    Screen::factory()->create([
        'user_id' => $user->id,
        'device_id' => DeviceId::current(),
    ]);

    actingAs($user);

    Livewire::test(ProjectionRemoteList::class)
        ->assertRedirect(route('projection-remote.control', ['screen' => $wall->id]));
});

/*
 * What the phone shows is what the wall shows: the same payload, drawn through
 * the same renderer, rather than a description of it.
 */
it('hands the phone the same deck the wall is drawing', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);

    $score = Score::factory()->abc()->create([
        'user_id' => $user->id,
        'content' => "X:1\nK:F\nF G A B|\n",
    ]);

    ProjectionSlide::factory()->create([
        'projection_id' => $projection->id,
        'score_id' => $score->id,
        'excluded_slides' => ['16/9' => [1]],
    ]);

    $screen = screenShowing($user, $projection);

    actingAs($user);

    $remote = Livewire::test(ProjectionRemote::class, ['screen' => $screen]);

    expect($remote->get('entries'))->toHaveCount(1)
        ->and($remote->get('entries')[0]['content'])->toContain('F G A B')
        ->and($remote->get('geometry'))->toMatchArray(['aspectRatio' => '16/9'])
        ->and($remote->get('excluded'))->toBe([$projection->entries()->first()->id => [1]])
        ->and($remote->get('revision'))->toBe($projection->revision());
});

/*
 * A screen with nothing on it is a state the remote can now show, rather than a
 * page it cannot reach.
 */
it('opens on a screen that is showing nothing', function () {
    $user = User::factory()->create();
    $screen = Screen::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    $remote = Livewire::test(ProjectionRemote::class, ['screen' => $screen]);

    expect($remote->get('presentation'))->toBeNull()
        ->and($remote->get('entries'))->toBe([]);

    $remote->assertSee(__('Choose a deck'));
});

// A score that stopped being readable between Thursday and Sunday is no more on
// the phone than it is on the wall.
it('leaves out a score the phone may no longer read', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);

    ProjectionSlide::factory()->create([
        'projection_id' => $projection->id,
        'score_id' => Score::factory()->abc()->create(['user_id' => $stranger->id])->id,
    ]);

    $screen = screenShowing($user, $projection);

    actingAs($user);

    expect(Livewire::test(ProjectionRemote::class, ['screen' => $screen])->get('entries'))->toBe([]);
});

it('refuses to drive somebody elses screen', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $screen = Screen::factory()->create(['user_id' => $owner->id]);

    actingAs($stranger);

    get(route('projection-remote.control', ['screen' => $screen]))->assertNotFound();
    get(route('projection-remote.decks', ['screen' => $screen]))->assertNotFound();
});

/*
 * The step that was missing: the phone puts a deck up, rather than following one
 * the laptop had already chosen.
 */
it('puts a deck on the screen from the phone', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);
    $screen = Screen::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(ProjectionRemoteDecks::class, ['screen' => $screen])
        ->call('present', $projection)
        ->assertRedirect(route('projection-remote.control', ['screen' => $screen->id]));

    $screen->refresh();

    expect($screen->showing())->not->toBeNull()
        ->and($screen->showing()->projection_id)->toBe($projection->id);
});

/*
 * Switching decks joins the presentation rather than starting a second, and ends
 * nothing: only clearing the screen ends a service.
 */
it('switches the screen to another deck', function () {
    $user = User::factory()->create();
    $first = Projection::factory()->create(['user_id' => $user->id]);
    $second = Projection::factory()->create(['user_id' => $user->id]);
    $screen = screenShowing($user, $first);

    actingAs($user);

    Livewire::test(ProjectionRemoteDecks::class, ['screen' => $screen])->call('present', $second);

    $screen->refresh();

    expect($screen->showing()->projection_id)->toBe($second->id)
        ->and(Presentation::query()->where('projection_id', $first->id)->first()->ended_at)->toBeNull();
});

it('refuses to put somebody elses deck on the screen', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $stranger->id]);
    $screen = Screen::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(ProjectionRemoteDecks::class, ['screen' => $screen])
        ->call('present', $projection)
        ->assertForbidden();

    expect($screen->refresh()->presentation_id)->toBeNull();
});

/*
 * The list behind the swipe is read to find one row out of thirty — the
 * Communion hymn, while the Offertory is still being played — and a row it
 * cannot name is a row nobody can find. The printed heading will not do it: a
 * deck whose author switched every heading off prints nothing at all, and used
 * to leave the phone showing a column of "Dia".
 */
it('names every row for the phone even when the deck prints no headings at all', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);

    $music = Music::factory()->create(['title' => 'Ave maris stella']);

    $score = Score::factory()->abc()->create([
        'user_id' => $user->id,
        'music_id' => $music->id,
        'title' => 'Ave maris stella – orgonakíséret',
    ]);

    // Every heading the slide could print, switched off — the factory's default
    // and the common case for a deck of scores that name themselves.
    ProjectionSlide::factory()->create([
        'projection_id' => $projection->id,
        'score_id' => $score->id,
        'show_slot' => false,
        'show_music_title' => false,
    ]);

    $screen = screenShowing($user, $projection);

    actingAs($user);

    $entry = Livewire::test(ProjectionRemote::class, ['screen' => $screen])->get('entries')[0];

    expect($entry['music'])->toBeNull()
        ->and($entry['label'])->toBe('Ave maris stella');
});

// A score chosen from no plan and belonging to no music still has its own name.
it('falls back to the score-s own title when there is no music behind it', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);

    $score = Score::factory()->abc()->create([
        'user_id' => $user->id,
        'music_id' => null,
        'title' => 'Vasárnapi zsoltár',
    ]);

    ProjectionSlide::factory()->create([
        'projection_id' => $projection->id,
        'score_id' => $score->id,
    ]);

    $screen = screenShowing($user, $projection);

    actingAs($user);

    expect(Livewire::test(ProjectionRemote::class, ['screen' => $screen])->get('entries')[0]['label'])
        ->toBe('Vasárnapi zsoltár');
});

/**
 * A screen with a deck already on it — the state the phone finds on a Sunday
 * when the laptop was started first.
 */
function screenShowing(User $user, Projection $projection): Screen
{
    $screen = Screen::factory()->create(['user_id' => $user->id]);

    $screen->point(Presentation::factory()->create([
        'projection_id' => $projection->id,
        'user_id' => $user->id,
    ]));

    return $screen;
}

/*
 * The organ bench again. A press is made without looking and is therefore made
 * twice as often as it is meant, so each of the three controls under the thumb
 * refuses a second press for half a second and says so in blue for exactly as
 * long as it is refusing. The rule itself lives in Alpine — what a test can hold
 * still is that the three controls are wired to it.
 */
it('locks the thumb controls against a second press and lights them while they are locked', function () {
    $user = User::factory()->create();
    $screen = Screen::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    $remote = Livewire::test(ProjectionRemote::class, ['screen' => $screen]);

    // Matched rather than compared, so that moving the three bands about the
    // page — which the two-pane desktop layout does — cannot fail a test about
    // what the controls are wired to.
    foreach (['previous', 'next', 'blank'] as $control) {
        expect($remote->html())->toMatch("/pressed === '{$control}'\s*\?\s*'border-blue-500 bg-blue-600/");
    }
});

/*
 * The other window of the desktop arrangement: the deck on the projector, the
 * remote beside it. A laptop has room for three columns — the deck read as the
 * service it came from down the left, the service itself in the middle with the
 * next slide full size under the controls, and every slide of the deck down the
 * right — which a phone with one thumb free has not.
 */
it('gives the laptop the plan, the service and the deck side by side', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);
    $screen = screenShowing($user, $projection);

    actingAs($user);

    Livewire::test(ProjectionRemote::class, ['screen' => $screen])
        ->assertSeeHtml('x-for="slot in outline"')
        ->assertSeeHtml('x-ref="nextBox"')
        ->assertSeeHtml('x-ref="deck"');
});

/*
 * And the person reading ahead in the deck is the person who may want the deck
 * itself changed, so the editor is one link away rather than a search away — a
 * real link, opened in a tab of its own, because a button bound to an address
 * is a button that goes nowhere.
 */
it('offers a way into the deck’s own editor', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);
    $screen = screenShowing($user, $projection);

    actingAs($user);

    Livewire::test(ProjectionRemote::class, ['screen' => $screen])
        ->assertSeeHtml('<a href="'.route('projections.edit', ['projection' => $projection->id]).'"')
        // And the same address in the JSON payload, where a slash is escaped,
        // so a deck swapped under this window rebinds the link rather than
        // leaving it pointing at the deck that has gone.
        ->assertSeeHtml('projections\\/'.$projection->id.'\\/edit');
});

/*
 * And on a screen showing nothing there is no deck to edit, so the button has
 * no address to offer and says nothing rather than guessing at one.
 */
it('offers no editor for a screen showing nothing', function () {
    $user = User::factory()->create();
    $screen = Screen::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(ProjectionRemote::class, ['screen' => $screen])
        ->assertSeeHtml('&quot;editUrl&quot;:null');
});
