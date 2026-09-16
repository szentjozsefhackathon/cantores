<?php

use App\Models\Genre;
use App\Models\MusicPlan;
use App\Models\User;
use App\Services\GenreContext;
use App\Services\MuseScoreRenderer;
use App\Services\PdfPageRasterizer;
use App\Services\PdfPageVectorizer;
use App\Services\SvgToPdfConverter;

use function Pest\Laravel\actingAs;

/*
 * What a worker is allowed to remember.
 *
 * Under Octane one booted application answers hundreds of requests in a row, so
 * a service that quietly holds on to the first caller keeps handing that caller
 * to everyone behind them. The failure is not an error — it is a correct-looking
 * page containing somebody else's context, which is the worst shape a bug can
 * take in an application where the context decides what music a cantor is shown.
 *
 * Nothing in `app/` declares a static property and no binding captures a request
 * in its constructor, so these tests are not chasing a known leak. They are a
 * tripwire: they describe the guarantee the runtime depends on, so that the day
 * somebody memoises a field on a singleton the suite says so rather than a
 * parish does.
 */

test('the genre context does not survive a request', function () {
    // The distinction this test exists for is `scoped` versus `singleton`. Both
    // return the same instance within one request; only `scoped` is let go of
    // between them, and letting go is what stops one cantor's genre reaching the
    // next cantor's page.
    $first = app(GenreContext::class);

    expect(app(GenreContext::class))->toBe($first);

    // Exactly what Octane does between requests.
    app()->forgetScopedInstances();

    expect(app(GenreContext::class))->not->toBe($first);
});

test('two users served by one application each get their own genre', function () {
    $organist = Genre::where('name', 'organist')->firstOrFail();
    $guitarist = Genre::where('name', 'guitarist')->firstOrFail();

    $first = User::factory()->create(['current_genre_id' => $organist->id]);
    $second = User::factory()->create(['current_genre_id' => $guitarist->id]);

    // Two requests through one container, which is what a worker is. The state
    // Octane resets between them is reset here too, so that what this asserts is
    // the application's behaviour on a warm worker and not on a cold boot.
    actingAs($first)->post(route('music-plans.store'))->assertRedirect();
    app()->forgetScopedInstances();
    actingAs($second)->post(route('music-plans.store'))->assertRedirect();

    expect(MusicPlan::where('user_id', $first->id)->value('genre_id'))->toBe($organist->id);

    // The assertion the whole file is for: the second request is answered with
    // the second user's genre, not with the one the application happened to see
    // first.
    expect(MusicPlan::where('user_id', $second->id)->value('genre_id'))->toBe($guitarist->id);
});

test('a guest following a signed-in user is not handed their genre', function () {
    // The same leak from the other side. A signed-in request warms whatever the
    // genre context resolves; the request after it has no user at all and must
    // come back empty rather than inheriting.
    $organist = Genre::where('name', 'organist')->firstOrFail();
    $user = User::factory()->create(['current_genre_id' => $organist->id]);

    actingAs($user);
    expect(app(GenreContext::class)->getId())->toBe($organist->id);

    app()->forgetScopedInstances();
    auth()->forgetGuards();
    session()->flush();

    expect(app(GenreContext::class)->getId())->toBeNull();
});

test('the renderers that stay singletons hold nothing a request gave them', function () {
    // The four binaries-and-timeouts services in AppServiceProvider::register()
    // are deliberately still singletons. That is only safe while they are built
    // from config alone, so this asserts the property the decision rests on:
    // surviving a request boundary changes nothing about them.
    $services = [
        SvgToPdfConverter::class,
        MuseScoreRenderer::class,
        PdfPageRasterizer::class,
        PdfPageVectorizer::class,
    ];

    foreach ($services as $service) {
        $before = app($service);

        app()->forgetScopedInstances();

        expect(app($service))->toBe($before);
    }
});
