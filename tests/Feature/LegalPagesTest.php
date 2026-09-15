<?php

use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Illuminate\Support\Facades\Route;
use League\CommonMark\Normalizer\SlugNormalizer;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function Pest\Laravel\get;

/**
 * The documents a user is told to read before uploading, lending or publishing.
 * What they say is editorial and changes without notice, so the tests below check
 * only what the application depends on: that the pages serve, that their links
 * and anchors resolve, and that the guide is crawlable.
 */
const LEGAL_DOCUMENTS = ['terms', 'privacy', 'kotta-jogok', 'about', 'guide'];

function legalMarkdown(string $file): string
{
    return file_get_contents(resource_path("markdown/{$file}.md"));
}

it('serves every legal document to a guest', function (string $path) {
    get($path)->assertOk();
})->with(['/terms', '/privacy', '/kotta-jogok', '/about', '/guide']);

it('only links to paths the router can answer', function (string $file) {
    preg_match_all('#\]\((/[^)\#\s]*)#', legalMarkdown($file), $matches);

    expect($matches[1])->not->toBeEmpty();

    foreach (array_unique($matches[1]) as $path) {
        try {
            Route::getRoutes()->match(Request::create($path, 'GET'));
        } catch (NotFoundHttpException|UrlGenerationException $e) {
            $this->fail("{$file}.md links to {$path}, which no route answers.");
        }
    }
})->with(LEGAL_DOCUMENTS);

it('gives the guide a table of contents whose links land on its own headings', function () {
    $guide = legalMarkdown('guide');

    preg_match_all('/^## (.+)$/m', $guide, $headings);
    preg_match_all('/\]\(#([^)]+)\)/', $guide, $anchors);

    $normalizer = new SlugNormalizer;
    $slugs = array_map(fn (string $heading): string => $normalizer->normalize($heading), $headings[1]);

    expect($anchors[1])->not->toBeEmpty();

    foreach (array_unique($anchors[1]) as $anchor) {
        expect($slugs)->toContain($anchor);
    }
});

it('renders the guide headings with anchors the table of contents can reach', function () {
    $normalizer = new SlugNormalizer;
    preg_match_all('/^## (.+)$/m', legalMarkdown('guide'), $headings);

    expect($headings[1])->not->toBeEmpty();

    $response = get('/guide')->assertOk();

    foreach ($headings[1] as $heading) {
        $response->assertSee('id="'.$normalizer->normalize($heading).'"', escape: false);
    }
});

it('keeps the admin manual as the one file the editor and admin screens are documented in', function () {
    expect(file_exists(base_path('docs/admin-kezikonyv.md')))->toBeTrue();
    expect(file_exists(base_path('docs/felhasznaloi-kezikonyv.md')))->toBeFalse();
});

it('links the guide and the legal documents from every public page a signed-out visitor lands on', function (string $path) {
    $response = get($path)->assertOk();

    foreach (['guide', 'about', 'score-rights', 'terms', 'privacy'] as $name) {
        $response->assertSee(route($name), escape: false);
    }
})->with(['/', '/ingyenes-kottak', '/about']);

it('gives the guide a crawlable title and description', function () {
    get('/guide')
        ->assertOk()
        ->assertSee('<title>'.config('app.name').' – Útmutató</title>', escape: false)
        ->assertSee('name="description"', escape: false)
        ->assertSee('rel="canonical"', escape: false);
});
