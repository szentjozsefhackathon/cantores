<?php

use App\Services\SvgToPdfConverter;

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

/**
 * Stand in for rsvg-convert, and see what it was handed.
 *
 * The conversion itself is a subprocess and a binary that may not be installed;
 * what a test of the export usually wants is the stack of SVG documents the
 * browser's pages became on the way to it.
 */
function fakeConverter(?callable $onConvert = null): void
{
    $fake = new class($onConvert) extends SvgToPdfConverter
    {
        public function __construct(private $onConvert)
        {
            parent::__construct('rsvg-convert', 30);
        }

        public function convert(array $svgs, ?string $credit = null): string
        {
            if ($this->onConvert !== null) {
                ($this->onConvert)($svgs, $credit);
            }

            return '%PDF-1.4 fake';
        }
    };

    app()->instance(SvgToPdfConverter::class, $fake);
}

/**
 * A PDF page's declared size, in points. Cairo writes the page dictionary into a
 * compressed object stream, so it has to be inflated before /MediaBox is there
 * to read.
 *
 * @return array{0: float, 1: float}|null
 */
function pdfPageSize(string $pdf): ?array
{
    preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $streams);

    foreach ($streams[1] as $stream) {
        $inflated = @gzuncompress($stream);

        if ($inflated !== false && preg_match('/MediaBox\s*\[\s*0\s+0\s+([\d.]+)\s+([\d.]+)\s*\]/', $inflated, $box)) {
            return [(float) $box[1], (float) $box[2]];
        }
    }

    return null;
}
