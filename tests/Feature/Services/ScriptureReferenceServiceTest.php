<?php

use App\Services\ScriptureReferenceService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
});

function scriptureApiResponse(string $reference, array $verses): array
{
    return [
        'keres' => ['feladat' => 'idezet', 'hivatkozas' => $reference, 'forma' => 'json'],
        'valasz' => [
            'versek' => $verses,
            'forditas' => ['nev' => 'Szent István Társulati Biblia', 'rov' => 'SZIT'],
        ],
    ];
}

test('a valid reference returns the canonical reference and joined text', function () {
    Http::fake([
        'https://szentiras.eu/api/idezet/*' => Http::response(scriptureApiResponse('Jn 3,16', [
            ['szoveg' => 'Mert úgy szerette Isten a világot...', 'hely' => ['szep' => 'Jn 3,16']],
        ])),
    ]);

    $result = app(ScriptureReferenceService::class)->lookup('Jn3,16');

    expect($result)->toBe([
        'reference' => 'Jn 3,16',
        'text' => 'Mert úgy szerette Isten a világot...',
    ]);
});

test('multiple verses are joined with newlines', function () {
    Http::fake([
        'https://szentiras.eu/api/idezet/*' => Http::response(scriptureApiResponse('Jn 3,16-17', [
            ['szoveg' => 'Első vers.'],
            ['szoveg' => 'Második vers.'],
        ])),
    ]);

    $result = app(ScriptureReferenceService::class)->lookup('Jn3,16-17');

    expect($result['text'])->toBe("Első vers.\nMásodik vers.");
});

test('the stored text is capped at 1000 characters', function () {
    Http::fake([
        'https://szentiras.eu/api/idezet/*' => Http::response(scriptureApiResponse('Zsolt 119', [
            ['szoveg' => str_repeat('a', 2000)],
        ])),
    ]);

    $result = app(ScriptureReferenceService::class)->lookup('Zsolt119');

    expect(mb_strlen($result['text']))->toBe(1000)
        ->and($result['text'])->toEndWith('…');
});

test('an invalid reference with no verses returns null', function () {
    Http::fake([
        'https://szentiras.eu/api/idezet/*' => Http::response(scriptureApiResponse('Zzz 9,9', [])),
    ]);

    expect(app(ScriptureReferenceService::class)->lookup('Zzz9,9'))->toBeNull();
});

test('a failed request returns null', function () {
    Http::fake([
        'https://szentiras.eu/api/idezet/*' => Http::response(null, 500),
    ]);

    expect(app(ScriptureReferenceService::class)->lookup('Jn3,16'))->toBeNull();
});

test('the api key is sent as an X-API-Key header', function () {
    config()->set('services.szentiras.key', 'test-key-123');
    Http::fake([
        'https://szentiras.eu/api/idezet/*' => Http::response(scriptureApiResponse('Jn 3,16', [
            ['szoveg' => 'Szöveg.'],
        ])),
    ]);

    app(ScriptureReferenceService::class)->lookup('Jn3,16');

    Http::assertSent(fn ($request) => $request->hasHeader('X-API-Key', 'test-key-123'));
});

test('lookups are cached and not refetched', function () {
    Http::fake([
        'https://szentiras.eu/api/idezet/*' => Http::response(scriptureApiResponse('Jn 3,16', [
            ['szoveg' => 'Szöveg.'],
        ])),
    ]);

    $service = app(ScriptureReferenceService::class);
    $service->lookup('Jn3,16');
    $service->lookup('Jn3,16');

    Http::assertSentCount(1);
});

test('an empty reference returns null without calling the api', function () {
    Http::fake();

    expect(app(ScriptureReferenceService::class)->lookup('   '))->toBeNull();
    Http::assertNothingSent();
});
