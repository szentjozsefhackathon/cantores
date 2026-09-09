<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Put the session past the Turnstile gate that guards every lending link, the way
 * a guest does by answering the challenge once.
 *
 * @see \App\Http\Middleware\EnsureVisitorIsHuman
 */
function passHumanCheck(): void
{
    app(\App\Services\HumanVerificationService::class)->markVerified();
}

/**
 * The Alpine directives on an element, by name.
 *
 * What Alpine watches for changes: it re-runs any directive whose attribute it
 * sees change, so a directive that carries something changeable is a component
 * that rebuilds itself.
 *
 * @return array<string, string>
 */
function alpineDirectivesOf(string $html): array
{
    preg_match('/<div[^>]*>/', $html, $root);

    preg_match_all('/(x-[a-z-]+(?::[a-z._-]+)?)="([^"]*)"/', $root[0] ?? '', $found, PREG_SET_ORDER);

    return collect($found)->mapWithKeys(fn (array $one): array => [$one[1] => $one[2]])->all();
}
