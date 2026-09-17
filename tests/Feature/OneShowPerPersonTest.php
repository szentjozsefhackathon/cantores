<?php

use App\Livewire\Pages\PlanDocuments;
use App\Livewire\Pages\ProjectionPresenter;
use App\Models\Presentation;
use App\Models\Projection;
use App\Models\ProjectionSlide;
use App\Models\Screen;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/*
 * One show per person.
 *
 * The cantor's Sunday: the parish laptop shows a deck, the phone follows the
 * same deck and can switch it, and when the other cantor signs the laptop in at
 * eleven the wall is theirs and nothing on the first cantor's phone reaches it.
 * A person has at most one current presentation, every device of theirs follows
 * it, and a screen shows its owner's.
 */

afterEach(function () {
    Carbon::setTestNow();
    Presentation::flushEventListeners();
});

it('ends the previous show when another deck is put up, and starts the new one at the beginning', function () {
    $user = User::factory()->create();
    $first = Projection::factory()->create(['user_id' => $user->id]);
    $second = Projection::factory()->create(['user_id' => $user->id]);

    $running = Presentation::putUp($user, $first);
    $running->forceFill(['entry_id' => 42, 'slide_index' => 3, 'version' => 7])->save();

    $next = Presentation::putUp($user, $second);

    expect($running->refresh()->ended_at)->not->toBeNull()
        ->and($next->id)->not->toBe($running->id)
        ->and($next->projection_id)->toBe($second->id)
        ->and($next->entry_id)->toBeNull()
        ->and($next->slide_index)->toBe(0)
        ->and($next->version)->toBe(1)
        ->and(Presentation::currentFor($user)->id)->toBe($next->id);
});

/*
 * Reloading the wall, pressing Present twice, or picking the deck that is
 * already up must change nothing.
 */
it('returns the same row, position kept, when the deck put up is already the show', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);

    $running = Presentation::putUp($user, $projection);
    $running->forceFill(['entry_id' => 42, 'slide_index' => 3])->save();

    $again = Presentation::putUp($user, $projection);

    expect($again->id)->toBe($running->id)
        ->and($again->slide_index)->toBe(3)
        ->and($again->ended_at)->toBeNull();
});

/*
 * Going back to a deck starts it over: resuming it would need one live row per
 * deck, which is the multi-show model this replaced.
 */
it('starts a deck over when it is put back up after another', function () {
    $user = User::factory()->create();
    $first = Projection::factory()->create(['user_id' => $user->id]);
    $second = Projection::factory()->create(['user_id' => $user->id]);

    $original = Presentation::putUp($user, $first);
    Presentation::putUp($user, $second);

    expect(Presentation::putUp($user, $first)->id)->not->toBe($original->id);
});

it('lets the database hold no more than one un-ended presentation per person', function () {
    $user = User::factory()->create();

    Presentation::factory()->create(['user_id' => $user->id]);

    expect(fn () => Presentation::factory()->create(['user_id' => $user->id]))
        ->toThrow(UniqueConstraintViolationException::class);
});

/*
 * Two devices pressing at once. The index rejects the loser's row, and the
 * loser tries again rather than failing the press.
 */
it('retries a deck put up in a race with another one', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);
    $raced = false;

    // The other device's row lands between this one's read and its insert.
    Presentation::creating(function (Presentation $presentation) use (&$raced, $user): void {
        if ($raced) {
            return;
        }

        $raced = true;

        Presentation::query()->insert([
            'projection_id' => Projection::factory()->create(['user_id' => $user->id])->id,
            'user_id' => $user->id,
            'splash' => Presentation::SPLASH_OFF,
            'started_at' => Carbon::now(),
            'last_seen_at' => Carbon::now(),
        ]);
    });

    $presentation = Presentation::putUp($user, $projection);

    expect($raced)->toBeTrue()
        ->and($presentation->exists)->toBeTrue()
        ->and(Presentation::query()->mine($user)->whereNull('ended_at')->pluck('id')->all())->toBe([$presentation->id]);
});

/*
 * A deck left up on Saturday does not come back on Sunday, and the first deck
 * of Sunday opens on the title card like any first deck.
 */
it('counts a stale show as nothing, and opens the next deck on the title card', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);

    $saturday = Presentation::factory()->stale()->create(['user_id' => $user->id]);

    expect(Presentation::currentFor($user))->toBeNull();

    $sunday = Presentation::putUp($user, $projection);

    expect($sunday->splash)->toBe(Presentation::SPLASH_CARD)
        ->and($saturday->refresh()->ended_at)->not->toBeNull();
});

it('opens the title card on the first deck and not on one replacing it mid-service', function () {
    $user = User::factory()->create();
    $first = Projection::factory()->create(['user_id' => $user->id]);
    $second = Projection::factory()->create(['user_id' => $user->id]);

    expect(Presentation::putUp($user, $first)->splash)->toBe(Presentation::SPLASH_CARD)
        ->and(Presentation::putUp($user, $second)->splash)->toBe(Presentation::SPLASH_OFF);
});

it('keeps a blanked wall blanked when another deck is put up', function () {
    $user = User::factory()->create();
    $first = Projection::factory()->create(['user_id' => $user->id]);
    $second = Projection::factory()->create(['user_id' => $user->id]);

    Presentation::putUp($user, $first)->update(['blanked' => true]);

    expect(Presentation::putUp($user, $second)->blanked)->toBeTrue();
});

it('shows a replacing deck when the wall was not blanked', function () {
    $user = User::factory()->create();
    $first = Projection::factory()->create(['user_id' => $user->id]);
    $second = Projection::factory()->create(['user_id' => $user->id]);

    Presentation::putUp($user, $first);

    expect(Presentation::putUp($user, $second)->blanked)->toBeFalse();
});

it('opens the title card again once the show has been taken down', function () {
    $user = User::factory()->create();
    $first = Projection::factory()->create(['user_id' => $user->id]);
    $second = Projection::factory()->create(['user_id' => $user->id]);

    Presentation::putUp($user, $first);
    Presentation::takeDownFor($user);

    expect(Presentation::putUp($user, $second)->splash)->toBe(Presentation::SPLASH_CARD);
});

/*
 * The bug that started this: a deck picked on the phone must be what the
 * laptop shows.
 */
it('answers the laptop with the deck the phone put up', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    postJson(route('show.state.store'), ['projectionId' => $projection->id])->assertOk();

    getJson(route('show.state'))
        ->assertOk()
        ->assertJsonPath('projectionId', $projection->id);
});

it('changes the laptop\'s answer when Present is pressed on the phone', function () {
    $user = User::factory()->create();
    $onTheWall = Projection::factory()->create(['user_id' => $user->id]);
    $pressed = Projection::factory()->create(['user_id' => $user->id]);

    Presentation::putUp($user, $onTheWall);

    actingAs($user);

    get(route('projections.present', ['projection' => $pressed]));

    getJson(route('show.state'))
        ->assertOk()
        ->assertJsonPath('projectionId', $pressed->id);
});

/*
 * Eleven o'clock: the other cantor signs the parish laptop in. From then on the
 * wall is theirs, and nothing on the first cantor's phone reaches it.
 */
it('hands a shared laptop to the person who signs in on it', function () {
    $early = User::factory()->create();
    $late = User::factory()->create();
    $device = (string) Str::uuid();

    Screen::claimFor($early, $device, 'ten-o-clock');
    Screen::claimFor($late, $device, 'eleven-o-clock');

    $theirs = Presentation::putUp($late, Projection::factory()->create(['user_id' => $late->id]));

    actingAs($early);

    getJson(route('show.state'))
        ->assertOk()
        ->assertJsonPath('screens', []);

    postJson(route('show.state.store'), [
        'projectionId' => Projection::factory()->create(['user_id' => $early->id])->id,
    ])->assertOk();

    expect(Presentation::currentFor($late)->id)->toBe($theirs->id);

    actingAs($late);

    getJson(route('show.state'))
        ->assertOk()
        ->assertJsonPath('presentationId', $theirs->id)
        ->assertJsonCount(1, 'screens');
});

it('lists recently shown decks once each, newest first, and only the person\'s own', function () {
    $user = User::factory()->create();
    $advent = Projection::factory()->create(['user_id' => $user->id, 'title' => 'Advent']);
    $adoration = Projection::factory()->create(['user_id' => $user->id, 'title' => 'Adoration']);
    $christmas = Projection::factory()->create(['user_id' => $user->id, 'title' => 'Christmas']);

    Carbon::setTestNow(Carbon::now()->subDays(2));
    Presentation::putUp($user, $advent);
    Carbon::setTestNow(Carbon::now()->addHour());
    Presentation::putUp($user, $adoration);
    Carbon::setTestNow(Carbon::now()->addHour());
    Presentation::putUp($user, $christmas);
    Carbon::setTestNow(Carbon::now()->addHour());
    Presentation::putUp($user, $advent);
    Carbon::setTestNow();

    $stranger = User::factory()->create();
    Presentation::putUp($stranger, Projection::factory()->create(['user_id' => $stranger->id]));

    expect(Presentation::recentFor($user, 5)->pluck('id')->all())
        ->toBe([$advent->id, $christmas->id, $adoration->id]);
});

it('puts a recently shown deck back up from the document list', function () {
    $user = User::factory()->create();
    $yesterday = Projection::factory()->create(['user_id' => $user->id, 'title' => 'Szentségimádás']);
    $today = Projection::factory()->create(['user_id' => $user->id]);

    Presentation::putUp($user, $yesterday);
    Presentation::putUp($user, $today);

    actingAs($user);

    Livewire::test(PlanDocuments::class)
        ->assertSee(__('Recently shown'))
        ->assertSee('Szentségimádás')
        ->call('putUp', $yesterday->id);

    expect(Presentation::currentFor($user)->projection_id)->toBe($yesterday->id);
});

/*
 * Phones keep bookmarks, and the remote used to be addressed to a screen.
 */
it('sends the old screen-addressed remote to the new one', function () {
    actingAs(User::factory()->create());

    get('/remote/5')->assertRedirect('/remote');
    get('/remote/5/decks')->assertRedirect('/remote/decks');
});

/*
 * Reloading the wall mid-service must land on the hymn. The page is handed where
 * the show stands, so it never draws the beginning of the deck — and so never
 * reports the beginning back to the phone with its first heartbeat.
 */
it('hands a reloaded wall the place the service has reached', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);
    $entry = ProjectionSlide::factory()->create(['projection_id' => $projection->id]);

    $running = Presentation::putUp($user, $projection);
    $running->forceFill(['entry_id' => $entry->id, 'slide_index' => 2, 'splash' => Presentation::SPLASH_OFF, 'version' => 7])->save();

    actingAs($user);

    $state = Livewire::test(ProjectionPresenter::class)->get('state');

    expect($state)->toMatchArray([
        'version' => 7,
        'entryId' => $entry->id,
        'slideIndex' => 2,
        'splash' => Presentation::SPLASH_OFF,
    ]);
});
