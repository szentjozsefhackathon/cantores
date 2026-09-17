<?php

use App\Livewire\Pages\ProjectionPresenter;
use App\Livewire\Pages\ProjectionRemote;
use App\Livewire\Pages\ProjectionRemoteDecks;
use App\Livewire\Projection\ShowStatus;
use App\Models\DeviceName;
use App\Models\Music;
use App\Models\Presentation;
use App\Models\Projection;
use App\Models\ProjectionSlide;
use App\Models\Score;
use App\Models\Screen;
use App\Models\User;
use App\Support\DeviceId;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/*
 * The phone's half of the Sunday. Both devices are the same person already —
 * the laptop was signed in from this phone with the QR code — so there is
 * nothing to pair and no token to read across the room, and what is left to
 * check is that the phone reaches the *show*, sees the same deck the wall
 * sees, can put another one up, and cannot reach anybody else's.
 */

it('puts the deck up as the show when a deck is opened on the laptop', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    get(route('projections.present', ['projection' => $projection]));

    $presenter = Livewire::test(ProjectionPresenter::class);

    expect(Presentation::query()->live()->mine($user)->count())->toBe(1)
        ->and($presenter->get('presentation')->projection_id)->toBe($projection->id)
        ->and($presenter->get('revision'))->toBe($projection->revision())
        ->and(Presentation::currentFor($user)->id)->toBe($presenter->get('presentation')->id);
});

/*
 * There is nothing to choose before the controls: the phone opens straight onto
 * them, whether or not a wall is on yet.
 */
it('opens straight onto the controls', function () {
    $user = User::factory()->create();
    Screen::factory()->count(2)->create(['user_id' => $user->id]);

    actingAs($user);

    get(route('projection-remote'))
        ->assertOk()
        ->assertSeeHtml('x-data="projectionRemote(');
});

/*
 * The sentence the old list of screens existed to say, now a line on the
 * remote: a phone opened before the laptop is ready says so, and still lets
 * the show be set up.
 */
it('says that no screen is connected rather than blocking anything', function () {
    $user = User::factory()->create();
    Screen::factory()->stale()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(ShowStatus::class)
        ->assertSee(__('No screen connected'));

    get(route('projection-remote'))
        ->assertOk()
        ->assertSee(__('Choose a deck'));
});

// The live screens of the person holding the phone, other than a phone that
// pressed Present a while ago and has since come back to the remote.
it('says which screens the show is on', function () {
    $user = User::factory()->create();

    $church = Screen::factory()->create(['user_id' => $user->id]);
    DeviceName::factory()->create(['user_id' => $user->id, 'device_id' => $church->device_id, 'name' => 'Parish laptop']);

    $home = Screen::factory()->create(['user_id' => $user->id]);
    DeviceName::factory()->create(['user_id' => $user->id, 'device_id' => $home->device_id, 'name' => 'Home laptop']);

    $phone = Screen::factory()->create([
        'user_id' => $user->id,
        'device_id' => DeviceId::current(),
        'last_seen_at' => Carbon::now()->subMinutes(2),
    ]);
    DeviceName::factory()->create(['user_id' => $user->id, 'device_id' => $phone->device_id, 'name' => 'My phone']);

    $stale = Screen::factory()->stale()->create(['user_id' => $user->id]);
    DeviceName::factory()->create(['user_id' => $user->id, 'device_id' => $stale->device_id, 'name' => 'Chapel']);

    $theirs = Screen::factory()->create(['user_id' => User::factory()->create()->id]);
    DeviceName::factory()->create(['user_id' => $theirs->user_id, 'device_id' => $theirs->device_id, 'name' => 'Stranger']);

    actingAs($user);

    Livewire::test(ShowStatus::class)
        ->assertSee('Parish laptop')
        ->assertSee('Home laptop')
        ->assertDontSee('My phone')
        ->assertDontSee('Chapel')
        ->assertDontSee('Stranger')
        ->assertDontSee(__('No screen connected'));
});

/*
 * A laptop with the wall in one window and the remote in the other: the wall is
 * this very browser, and it is where the show is on.
 */
it('says the show is on this browser while its own wall is up', function () {
    $user = User::factory()->create();

    $laptop = Screen::factory()->create(['user_id' => $user->id, 'device_id' => DeviceId::current()]);
    DeviceName::factory()->create(['user_id' => $user->id, 'device_id' => $laptop->device_id, 'name' => 'Parish laptop']);

    actingAs($user);

    Livewire::test(ShowStatus::class)
        ->assertSee('Parish laptop')
        ->assertDontSee(__('No screen connected'));
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

    showing($user, $projection);

    actingAs($user);

    $remote = Livewire::test(ProjectionRemote::class);

    expect($remote->get('entries'))->toHaveCount(1)
        ->and($remote->get('entries')[0]['content'])->toContain('F G A B')
        ->and($remote->get('geometry'))->toMatchArray(['aspectRatio' => '16/9'])
        ->and($remote->get('excluded'))->toBe([$projection->entries()->first()->id => [1]])
        ->and($remote->get('revision'))->toBe($projection->revision());
});

/*
 * No show is a state the remote shows, rather than a page it cannot reach.
 */
it('opens when nothing is up', function () {
    $user = User::factory()->create();

    actingAs($user);

    $remote = Livewire::test(ProjectionRemote::class);

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

    showing($user, $projection);

    actingAs($user);

    expect(Livewire::test(ProjectionRemote::class)->get('entries'))->toBe([]);
});

/*
 * The step that was missing: the phone puts a deck up, and every screen shows
 * it.
 */
it('puts a deck up from the phone', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(ProjectionRemoteDecks::class)
        ->call('present', $projection)
        ->assertRedirect(route('projection-remote'));

    expect(Presentation::currentFor($user)?->projection_id)->toBe($projection->id);
});

/*
 * Switching decks ends the one that was up: a person has one show.
 */
it('switches the show to another deck', function () {
    $user = User::factory()->create();
    $first = Projection::factory()->create(['user_id' => $user->id]);
    $second = Projection::factory()->create(['user_id' => $user->id]);
    $running = showing($user, $first);

    actingAs($user);

    Livewire::test(ProjectionRemoteDecks::class)->call('present', $second);

    expect(Presentation::currentFor($user)->projection_id)->toBe($second->id)
        ->and($running->refresh()->ended_at)->not->toBeNull();
});

it('refuses to put somebody elses deck up', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $stranger->id]);

    actingAs($user);

    Livewire::test(ProjectionRemoteDecks::class)
        ->call('present', $projection)
        ->assertForbidden();

    expect(Presentation::query()->count())->toBe(0);
});

it('offers the recently shown decks above the full list', function () {
    $user = User::factory()->create();
    $shown = Projection::factory()->create(['user_id' => $user->id, 'title' => 'Szentségimádás']);
    Projection::factory()->create(['user_id' => $user->id, 'title' => 'Never shown']);

    Presentation::putUp($user, $shown);
    Presentation::takeDownFor($user);

    actingAs($user);

    $decks = Livewire::test(ProjectionRemoteDecks::class)
        ->assertSeeInOrder([__('Recently shown'), 'Szentségimádás', __('All decks')]);

    expect($decks->instance()->recents()->pluck('id')->all())->toBe([$shown->id]);
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

    showing($user, $projection);

    actingAs($user);

    $entry = Livewire::test(ProjectionRemote::class)->get('entries')[0];

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

    showing($user, $projection);

    actingAs($user);

    expect(Livewire::test(ProjectionRemote::class)->get('entries')[0]['label'])
        ->toBe('Vasárnapi zsoltár');
});

/*
 * A slot's music is often sung from one of several engravings of it, all
 * sharing its title, so the music's name alone leaves three identical rows in
 * the plan. The row carries what the editor's own row shows — the score, the
 * variation, the opening notes — read off the score rather than off the
 * headings, since the deck may print none of it.
 */
it('names the engraving a row is, and not only the music', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);

    $music = Music::factory()->create(['title' => 'Veni Creator']);

    $score = Score::factory()->abc()->create([
        'user_id' => $user->id,
        'music_id' => $music->id,
        'title' => 'Veni Creator Spiritus',
        'variation_name' => 'I. tónus',
    ]);

    // The variation switched off on the slide: the plan still has to say it,
    // because it is the only thing telling this row from the next.
    ProjectionSlide::factory()->create([
        'projection_id' => $projection->id,
        'score_id' => $score->id,
        'show_variation' => false,
    ]);

    showing($user, $projection);

    actingAs($user);

    $entry = Livewire::test(ProjectionRemote::class)->get('entries')[0];

    expect($entry['variation'])->toBeNull()
        ->and($entry['label'])->toBe('Veni Creator')
        ->and($entry['scoreName'])->toBe('Veni Creator Spiritus')
        ->and($entry['variationName'])->toBe('I. tónus')
        ->and($entry)->toHaveKey('incipitUrl');
});

/**
 * A show already up — the state the phone finds on a Sunday when the laptop was
 * started first.
 */
function showing(User $user, Projection $projection): Presentation
{
    return Presentation::factory()->create([
        'projection_id' => $projection->id,
        'user_id' => $user->id,
    ]);
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

    actingAs($user);

    $remote = Livewire::test(ProjectionRemote::class);

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
    showing($user, $projection);

    actingAs($user);

    Livewire::test(ProjectionRemote::class)
        ->assertSeeHtml('x-for="band in outline"')
        ->assertSeeHtml('x-ref="nextBox"')
        ->assertSeeHtml('x-ref="deck"');
});

/*
 * A music the plan does not have is looked for with the same search the editor
 * uses, and what is picked there is handed to the remote's own add.
 */
it('adds music through the shared music search', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);
    showing($user, $projection);

    actingAs($user);

    Livewire::test(ProjectionRemote::class)
        ->assertSeeLivewire('music-search')
        ->assertSeeHtml('x-on:music-selected-remote.window="addMusic($event.detail.musicId)"');

    Livewire::test('music-search', ['selectable' => true, 'source' => '-remote'])
        ->call('selectMusic', 42)
        ->assertDispatched('music-selected-remote', musicId: 42);
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
    showing($user, $projection);

    actingAs($user);

    Livewire::test(ProjectionRemote::class)
        ->assertSeeHtml('<a href="'.route('projections.edit', ['projection' => $projection->id]).'"')
        // And the same address in the JSON payload, where a slash is escaped,
        // so a deck swapped under this window rebinds the link rather than
        // leaving it pointing at the deck that has gone.
        ->assertSeeHtml('projections\\/'.$projection->id.'\\/edit');
});

/*
 * And with nothing up there is no deck to edit, so the button has
 * no address to offer and says nothing rather than guessing at one.
 */
it('offers no editor when nothing is up', function () {
    $user = User::factory()->create();

    actingAs($user);

    Livewire::test(ProjectionRemote::class)
        ->assertSeeHtml('&quot;editUrl&quot;:null');
});

/*
 * Blade compiles a directive written in the page, and leaves one written inside
 * a component's attribute alone — so `@js(...)` on a `<flux:button>` reaches the
 * browser with the `@` still on it, where Alpine expects an expression.
 *
 * Not a quiet failure. The parse error takes down every directive queued behind
 * it, and behind that button is the whole of the thumb: the controls stop being
 * wired to anything, and the card the beamer was lined up against can no longer
 * be walked off the wall. So the page is checked for the mistake rather than for
 * the one button it was first made on.
 */
it('leaves no Blade directive in the markup Alpine has to parse', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);
    showing($user, $projection);

    actingAs($user);

    $html = Livewire::test(ProjectionRemote::class)->html();

    expect($html)->not->toContain('@js(')
        ->and($html)->toContain('confirm(clearText)')
        ->and($html)->toContain('&quot;clearText&quot;:'.e(json_encode(__('Take the deck off every screen and end this projection?'))));
});
