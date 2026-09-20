<?php

use App\Models\DeviceName;
use App\Models\Presentation;
use App\Models\Projection;
use App\Models\ProjectionSlide;
use App\Models\Score;
use App\Models\Screen;
use App\Models\User;
use App\Services\ShowStream;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

/*
 * The hub carries one person's show to that person's devices. What there is to
 * check is that it is sent when a device's answer would move, that a "still
 * here" does not send it, that what is sent is the answer itself rather than a
 * knock on the door, and that a browser can only listen to its own person.
 */

const PUBLISH_URL = 'http://hub.test/.well-known/mercure';

beforeEach(function () {
    config([
        'services.mercure.publish_url' => PUBLISH_URL,
        'services.mercure.publisher_jwt_key' => 'publisher-key',
        'services.mercure.subscriber_jwt_key' => 'subscriber-key',
    ]);

    freshHub();
});

/**
 * A hub that has heard nothing yet: whatever the setting up published is sent
 * and forgotten, so that what is asserted is only what the test did.
 */
function freshHub(mixed $answer = null): void
{
    app(DeferredCallbackCollection::class)->invoke();

    Http::swap(new Factory);
    Http::fake([PUBLISH_URL => $answer ?? Http::response('urn:uuid:1')]);
}

/** Run what the end of a request would have run. */
function endOfRequest(): void
{
    app(DeferredCallbackCollection::class)->invoke();
}

/**
 * The claims of a token, once its signature has been checked against the key.
 *
 * @return array<string, mixed>
 */
function claimsOf(string $token, string $key): array
{
    [$header, $payload, $signature] = explode('.', $token);

    $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', "{$header}.{$payload}", $key, true)), '+/', '-_'), '=');

    expect($signature)->toBe($expected);

    return json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
}

/**
 * The topics published to, in order.
 *
 * @return list<string>
 */
function publishedTopics(): array
{
    return Http::recorded()
        ->map(fn (array $pair): string => $pair[0]['topic'])
        ->values()
        ->all();
}

function showingDeck(User $user): array
{
    $projection = Projection::factory()->create(['user_id' => $user->id]);
    $entry = ProjectionSlide::factory()->create([
        'projection_id' => $projection->id,
        'score_id' => Score::factory()->abc()->create(['user_id' => $user->id])->id,
    ]);
    $presentation = Presentation::factory()->create([
        'projection_id' => $projection->id,
        'user_id' => $user->id,
    ]);

    freshHub();

    return [$projection, $entry, $presentation->refresh()];
}

it('tells the person when the service moves, privately and signed', function () {
    $user = User::factory()->create();
    [, $entry, $presentation] = showingDeck($user);

    actingAs($user);

    postJson(route('presentations.state.store', $presentation), [
        'entryId' => $entry->id,
        'slideIndex' => 1,
    ])->assertOk();

    $topic = app(ShowStream::class)->topicFor($user);

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request) use ($topic): bool {
        $token = str($request->header('Authorization')[0])->after('Bearer ')->toString();

        return $request->url() === PUBLISH_URL
            && $request['topic'] === $topic
            && $request['private'] === 'on'
            && claimsOf($token, 'publisher-key')['mercure']['publish'] === [$topic];
    });
});

/*
 * And tells them *what* moved, rather than sending every device back to the
 * server to find out. The answer belongs to the person and to none of their
 * devices — which is exactly what makes it publishable — so the copy that goes
 * down the hub is the copy a read would have returned.
 */
it('carries the show itself, stamped so that two frames cannot cross', function () {
    $user = User::factory()->create();
    [$projection, $entry, $presentation] = showingDeck($user);
    $screen = Screen::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    postJson(route('presentations.state.store', $presentation), [
        'entryId' => $entry->id,
        'slideIndex' => 1,
    ])->assertOk();

    $frame = json_decode(Http::recorded()->first()[0]['data'], true);

    expect($frame['at'])->toMatch('/^\d{20}$/')
        ->and($frame['show']['presentationId'])->toBe($presentation->id)
        ->and($frame['show']['title'])->toBe($projection->title)
        ->and($frame['show']['state']['entryId'])->toBe($entry->id)
        ->and($frame['show']['state']['slideIndex'])->toBe(1)
        // Described for the person: every device of theirs, named by device,
        // with what each browser needs to decide which it may be shown.
        ->and($frame['show']['screens'][0]['id'])->toBe($screen->id)
        ->and($frame['show']['screens'][0]['deviceId'])->toBe($screen->device_id)
        ->and($frame['show']['screens'][0])->toHaveKeys(['offered', 'presenting', 'responding']);
});

it('says nothing when the wall only reports that it is still there', function () {
    $user = User::factory()->create();
    [, $entry, $presentation] = showingDeck($user);

    $presentation->applyState(['entryId' => $entry->id, 'slideIndex' => 0], $entry);
    $presentation->forceFill(['last_seen_at' => Carbon::now()->subMinute()])->saveQuietly();
    freshHub();

    actingAs($user);

    postJson(route('presentations.state.store', $presentation), [
        'entryId' => $entry->id,
        'slideIndex' => 0,
    ])->assertOk();

    Http::assertNothingSent();
});

it('publishes once for a request that saves many rows', function () {
    $user = User::factory()->create();
    [$projection] = showingDeck($user);

    ProjectionSlide::factory()->count(3)->create([
        'projection_id' => $projection->id,
        'score_id' => Score::factory()->abc()->create(['user_id' => $user->id])->id,
    ]);

    endOfRequest();

    expect(publishedTopics())->toBe([app(ShowStream::class)->topicFor($user)]);
});

it('tells whoever is showing a deck that it was edited, and nobody else', function () {
    $presenter = User::factory()->create();
    $bystander = User::factory()->create();
    [$projection] = showingDeck($presenter);

    Presentation::factory()->create([
        'projection_id' => $projection->id,
        'user_id' => $bystander->id,
        'ended_at' => Carbon::now(),
    ]);
    freshHub();

    $projection->forceFill(['title' => 'Húsvét'])->save();
    endOfRequest();

    expect(publishedTopics())->toBe([app(ShowStream::class)->topicFor($presenter)]);
});

it('tells the person when a wall is lined up, named, or comes back', function () {
    $user = User::factory()->create();
    $screen = Screen::factory()->create([
        'user_id' => $user->id,
        'last_seen_at' => Carbon::now()->subMinutes(10),
    ]);
    freshHub();

    $screen->touchLastSeen();
    endOfRequest();
    Http::assertSentCount(1);

    Carbon::setTestNow(Carbon::now()->addMinute());
    $screen->touchLastSeen();
    endOfRequest();
    Http::assertSentCount(1);

    $screen->adjustFit(['scale' => 0.9, 'x' => 0, 'y' => 0]);
    endOfRequest();
    Http::assertSentCount(2);

    DeviceName::factory()->create(['user_id' => $user->id, 'device_id' => $screen->device_id]);
    endOfRequest();
    Http::assertSentCount(3);

    Carbon::setTestNow();
});

it('gives a browser a cookie for its own topic only', function () {
    $user = User::factory()->create();

    actingAs($user);

    $response = post(route('show.stream'))->assertOk();

    $topic = app(ShowStream::class)->topicFor($user);

    $response->assertJson(['hubUrl' => ShowStream::HUB_PATH, 'topic' => $topic]);

    $cookie = collect($response->headers->getCookies())->firstWhere(fn ($cookie) => $cookie->getName() === ShowStream::COOKIE);

    expect($cookie)->not->toBeNull()
        ->and($cookie->getPath())->toBe(ShowStream::HUB_PATH)
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getSameSite())->toBe('strict');

    $claims = claimsOf($cookie->getValue(), 'subscriber-key');

    expect($claims['mercure']['subscribe'])->toBe([$topic])
        ->and($claims['exp'])->toBeGreaterThan(now()->getTimestamp());
});

it('leaves the devices polling when the hub is not configured', function () {
    config(['services.mercure.publish_url' => null]);

    $user = User::factory()->create();
    [, $entry, $presentation] = showingDeck($user);

    actingAs($user);

    post(route('show.stream'))
        ->assertOk()
        ->assertExactJson(['hubUrl' => null, 'topic' => null])
        ->assertCookieMissing(ShowStream::COOKIE);

    postJson(route('presentations.state.store', $presentation), ['entryId' => $entry->id, 'slideIndex' => 3])
        ->assertOk();

    Http::assertNothingSent();
});

it('does not let a hub that is down break the request that moved the show', function () {
    $user = User::factory()->create();
    [, $entry, $presentation] = showingDeck($user);
    freshHub(Http::failedConnection());

    actingAs($user);

    postJson(route('presentations.state.store', $presentation), ['entryId' => $entry->id, 'slideIndex' => 2])
        ->assertOk();
});

it('refuses a stream to a guest', function () {
    postJson(route('show.stream'))->assertUnauthorized();
});

it('republishes a resync nudge without changing the presentation version', function () {
    $user = User::factory()->create();
    [, , $presentation] = showingDeck($user);
    $version = $presentation->version;

    actingAs($user);

    postJson(route('presentations.resync', $presentation))
        ->assertAccepted()
        ->assertJsonPath('version', $version);

    expect($presentation->refresh()->version)->toBe($version)
        ->and(publishedTopics())->toBe([app(ShowStream::class)->topicFor($user)]);
});
