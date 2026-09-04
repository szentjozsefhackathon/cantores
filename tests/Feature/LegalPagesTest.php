<?php

use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Illuminate\Support\Facades\Route;
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
