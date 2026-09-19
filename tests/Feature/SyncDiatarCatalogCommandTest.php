<?php

use Illuminate\Support\Facades\Http;

use function Pest\Laravel\artisan;

it('reports progress while synchronizing the catalogue', function () {
    config([
        'diatar.repository' => 'test/repository',
        'diatar.branch' => 'main',
        'diatar.api_url' => 'https://api.example.test',
        'diatar.raw_url' => 'https://raw.example.test',
        'diatar.retry_attempts' => 1,
        'diatar.retry_delay_ms' => 0,
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://api.example.test/repos/test/repository/commits/main' => Http::response(['sha' => 'revision-one']),
        'https://api.example.test/repos/test/repository/git/trees/revision-one*' => Http::response([
            'truncated' => false,
            'tree' => [['path' => 'books/ordinary.dtx', 'type' => 'blob', 'sha' => 'blob-one']],
        ]),
        'https://raw.example.test/test/repository/revision-one/books/ordinary.dtx' => Http::response(
            file_get_contents(base_path('tests/Fixtures/Diatar/ordinary.dtx')),
        ),
    ]);

    artisan('cantores:sync-diatar-catalog')
        ->expectsOutputToContain('Fetching the Diatár repository catalogue...')
        ->expectsOutputToContain('Processing 1 catalogue file...')
        ->assertSuccessful();

    Http::assertSentCount(3);
});
