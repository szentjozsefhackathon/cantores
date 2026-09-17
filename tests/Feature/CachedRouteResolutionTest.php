<?php

use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;

/**
 * Route::redirect() registers under every HTTP verb. If it is ever declared
 * before a same-path POST route, Laravel's uncached matcher (which
 * overwrites its method+URI lookup table entry) still resolves POST to the
 * later route, but the cached matcher production actually runs (built from
 * this same route collection) tries routes in declaration order and stops at
 * the first method match, resolving POST to the redirect instead. This test
 * exercises that compiled matcher directly so the discrepancy is caught
 * without needing a real `route:cache` run.
 */
it('resolves POST /booklets and /projections to their store routes under the cached route matcher', function (string $uri, string $expectedRoute) {
    $routes = app('router')->getRoutes()->toSymfonyRouteCollection();

    $context = new RequestContext($uri, 'POST');
    $matcher = new UrlMatcher($routes, $context);

    expect($matcher->match($uri)['_route'])->toBe($expectedRoute);
})->with([
    ['/booklets', 'booklets.store'],
    ['/projections', 'projections.store'],
]);
