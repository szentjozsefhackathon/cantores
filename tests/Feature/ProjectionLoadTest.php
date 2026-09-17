<?php

use App\Models\Presentation;
use App\Models\Projection;
use App\Models\ProjectionSlide;
use App\Models\Score;
use App\Models\Screen;
use App\Models\User;
use App\Support\DeviceId;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\withSession;

/*
 * What a Sunday costs.
 *
 * Every other test of this feature asks whether the two devices agree; this one
 * asks what agreeing costs, because the answer is multiplied by every parish
 * running a service at the same hour. A wall and a phone each read the show
 * about once a second for the length of a Mass, so a query added here is a
 * query added a hundred times a second, and a row written here is a row written
 * a hundred times a second for as long as anybody is singing.
 *
 * The numbers below are therefore budgets rather than observations. They are
 * allowed to be argued down and not to drift up.
 */

/**
 * A screen with a deck on it, in the state a Mass leaves it in: a live
 * presentation, a deck of several rows, and this browser claiming to be the
 * wall.
 *
 * @return array{0: User, 1: Screen, 2: Presentation, 3: Projection}
 */
function runningService(int $rows = 8): array
{
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);

    foreach (range(1, $rows) as $sequence) {
        ProjectionSlide::factory()->create([
            'projection_id' => $projection->id,
            'score_id' => Score::factory()->create(['user_id' => $user->id])->id,
            'sequence' => $sequence,
        ]);
    }

    $presentation = Presentation::factory()->create([
        'projection_id' => $projection->id,
        'user_id' => $user->id,
    ]);

    $screen = Screen::factory()->create([
        'user_id' => $user->id,
        'device_id' => (string) Str::uuid(),
    ]);

    return [$user, $screen, $presentation, $projection];
}

/*
 * The budget. Five reads, and not one of them a write: the screens that are on
 * and their names, the show, the deck it names, and the deck's rows. The join
 * that used to make a sixth is answered from the cache now.
 */
it('reads the show within its query budget', function () {
    [$user] = runningService();

    actingAs($user);

    // The first read of a deck fills the revision cache; the budget is what
    // every read after it costs, which is what a Mass is made of.
    getJson(route('show.state'))->assertOk();

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    getJson(route('show.state'))->assertOk();

    $writes = array_filter($queries, fn (string $sql): bool => str_starts_with($sql, 'update'));

    expect($queries)->toHaveCount(5)
        ->and($writes)->toBeEmpty();
});

/*
 * The column this saves is read against a five minute window, so writing it
 * every second was three hundred times more often than anything asks.
 */
it('does not write last_seen_at on every poll from the wall', function () {
    [$user, $screen] = runningService();

    $device = (string) Str::uuid();
    $screen->forceFill(['device_id' => $device])->save();

    actingAs($user);
    // DeviceId falls back to the session for a browser whose cookie has not
    // travelled, which is the shape a test request has.
    withSession([DeviceId::COOKIE => $device]);

    $screen->forceFill(['last_seen_at' => Carbon::now()])->save();
    $before = $screen->fresh()->last_seen_at;

    Carbon::setTestNow(Carbon::now()->addSeconds(5));
    getJson(route('show.state'))->assertOk();

    expect($screen->fresh()->last_seen_at->equalTo($before))->toBeTrue();
});

/*
 * And still writes it before the screen could be aged out of anybody's list.
 */
it('writes last_seen_at once the saved write would start to matter', function () {
    [$user, $screen] = runningService();

    $device = (string) Str::uuid();
    $screen->forceFill(['device_id' => $device, 'last_seen_at' => Carbon::now()])->save();

    actingAs($user);
    // DeviceId falls back to the session for a browser whose cookie has not
    // travelled, which is the shape a test request has.
    withSession([DeviceId::COOKIE => $device]);

    $before = $screen->fresh()->last_seen_at;

    Carbon::setTestNow(Carbon::now()->addSeconds(Screen::SEEN_EVERY_SECONDS + 1));
    getJson(route('show.state'))->assertOk();

    expect($screen->fresh()->last_seen_at->gt($before))->toBeTrue()
        ->and(Screen::SEEN_EVERY_SECONDS)->toBeLessThan(Screen::STALE_MINUTES * 60);
});

/*
 * The revision is the one join on the polling path, and it is asked twice a
 * second per service. Asking it twice in a row must cost one query.
 */
it('answers the deck revision from the cache between polls', function () {
    [, , , $projection] = runningService();

    $first = $projection->revision();

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    expect($projection->revision())->toBe($first)
        ->and($queries)->toBe(0);
});

/*
 * Cached, but never in the way of the half hour before a Mass, which is when a
 * deck actually changes. A save to the deck forgets it by name.
 */
it('forgets the cached revision when a row of the deck is saved', function () {
    [$user, , , $projection] = runningService();

    $before = $projection->revision();

    Carbon::setTestNow(Carbon::now()->addMinute());

    ProjectionSlide::factory()->create([
        'projection_id' => $projection->id,
        'score_id' => Score::factory()->create(['user_id' => $user->id])->id,
        'sequence' => 99,
    ]);

    expect($projection->revision())->not->toBe($before);
});

it('forgets the cached revision when the deck itself is saved', function () {
    [, , , $projection] = runningService();

    $before = $projection->revision();

    Carbon::setTestNow(Carbon::now()->addMinute());
    $projection->forceFill(['title' => 'Another name'])->save();

    expect($projection->revision())->not->toBe($before);
});

/*
 * A ceiling on a client that has lost its place, not on a cantor. What the
 * limiter must not do is share a bucket with anything else the same person
 * does — which is exactly what the inline `throttle:20,1` form would have made
 * it do, since that form keys on the user and nothing else.
 */
it('stops a client that is asking in a loop', function () {
    [$user] = runningService();

    actingAs($user);

    RateLimiter::increment(md5('projection-poll'.$user->id), 60, 300);

    getJson(route('show.state'))->assertStatus(429);
});

it('keeps the polling budget out of every other throttled route', function () {
    [$user] = runningService();

    actingAs($user);

    // A Mass worth of polling, spent all at once.
    RateLimiter::increment(md5('projection-poll'.$user->id), 60, 299);

    getJson(route('show.state'))->assertOk();

    // The score export allows this same person twenty attempts a minute, under
    // its own inline `throttle:20,1`. That form keys on the user and nothing
    // else, so had the polling been spelled the same way the two would be one
    // bucket — and two polls a second would have spent the export's twenty
    // within ten seconds of the first hymn starting.
    expect(RateLimiter::attempts(sha1((string) $user->id)))->toBe(0);
});

/*
 * Laravel writes the current URL into the session as "where this person was"
 * on every plain GET, and a `fetch` is a plain GET unless it says otherwise.
 * Left unsaid, a wall spends a Mass telling its own session that the last page
 * it was on was a JSON endpoint — once a second, dirtying the session every
 * time, and pointing every redirect-back at `/show/state`.
 */
it('does not become the page the wall was last on', function () {
    [$user] = runningService();

    actingAs($user);
    withSession(['_previous' => ['url' => route('plan-documents')]]);

    // The header the two clients send; see jsonRequests() in projection-follow.js.
    getJson(route('show.state'), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk();

    expect(session('_previous.url'))->toBe(route('plan-documents'));
});

afterEach(function () {
    Carbon::setTestNow();
    Cache::flush();
});
