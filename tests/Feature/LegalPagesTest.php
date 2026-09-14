<?php

use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Illuminate\Support\Facades\Route;
use League\CommonMark\Normalizer\SlugNormalizer;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function Pest\Laravel\get;

/**
 * The documents a user is told to read before uploading, lending or publishing.
 */
const LEGAL_DOCUMENTS = ['terms', 'privacy', 'kotta-jogok', 'about', 'guide'];

function legalMarkdown(string $file): string
{
    return file_get_contents(resource_path("markdown/{$file}.md"));
}

it('serves every legal document to a guest', function (string $path) {
    get($path)->assertOk();
})->with(['/terms', '/privacy', '/kotta-jogok', '/about', '/guide']);

it('states the promises the lending feature rests on', function () {
    $terms = legalMarkdown('terms');

    expect($terms)
        ->toContain('A szerzői jogi szabályokat mindenkinek be kell tartania')
        ->toContain('a link gazdája – a kölcsönadó – felel')
        ->toContain('nem nézi át, nem ellenőrzi előzetesen')
        ->toContain('Google Drive')
        ->toContain('Mindig tartson saját biztonsági mentést');
});

it('reserves the right to delete content and accounts without warning', function () {
    $terms = legalMarkdown('terms');

    expect($terms)
        ->toContain('bármilyen más adatot – indoklás és előzetes figyelmeztetés nélkül, bármikor – véglegesen törölni')
        ->toContain('bármely felhasználói fiókot – indoklás és előzetes figyelmeztetés nélkül, bármikor –');
});

it('does not claim the site has nothing to download', function () {
    expect(legalMarkdown('about'))
        ->not->toContain('a honlap nem tartalmaz letölthető anyagokat')
        ->toContain('/ingyenes-kottak');
});

it('tells lenders and borrowers where the loan rules live', function () {
    expect(legalMarkdown('guide'))->toContain('Kölcsönadott');
    expect(legalMarkdown('kotta-jogok'))->toContain('A kölcsönzés nem keletkeztet jogot');
});

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

it('covers every feature area in the guide', function () {
    $guide = legalMarkdown('guide');

    foreach (['Énekrendek', 'Javaslatok', 'Énektár', 'Kottatár', 'kottaszerkesztő',
        'Mappák', 'Kölcsönzés', 'Ingyenes kották', 'Füzetek', 'Vetítés',
        'Távirányító', 'Privát és publikus', 'verifikáció'] as $chapter) {
        expect($guide)->toContain($chapter);
    }
});

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
    get('/guide')->assertOk()->assertSee('id="2-énekrendek"', escape: false);
});

it('keeps the admin manual as the one place the editor and admin screens are documented', function () {
    $manual = file_get_contents(base_path('docs/admin-kezikonyv.md'));

    expect(file_exists(base_path('docs/felhasznaloi-kezikonyv.md')))->toBeFalse();

    foreach (['contributor', 'editor', 'admin', 'scores.publish.review', 'masterdata.maintain',
        'Kotta-közzététel', 'URL whitelist', 'Direktórium', 'Tartalmi statisztika'] as $topic) {
        expect($manual)->toContain($topic);
    }
});

it('documents the slots a music plan is built from', function () {
    expect(legalMarkdown('guide'))
        ->toContain('A mise szokásos slotjai')
        ->toContain('Válaszos zsoltár')
        ->toContain('Agnus Dei');
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
        ->assertSee('kottaszerkesztő', escape: false)
        ->assertSee('rel="canonical"', escape: false);
});
