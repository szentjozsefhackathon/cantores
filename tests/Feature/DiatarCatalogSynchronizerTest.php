<?php

use App\Enums\DiatarSyncStatus;
use App\Models\DiatarBook;
use App\Models\DiatarSlide;
use App\Models\DiatarSong;
use App\Models\DiatarSyncRun;
use App\Services\Diatar\DiatarCatalogSynchronizer;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'diatar.repository' => 'test/repository',
        'diatar.branch' => 'main',
        'diatar.api_url' => 'https://api.example.test',
        'diatar.raw_url' => 'https://raw.example.test',
        'diatar.retry_attempts' => 1,
        'diatar.retry_delay_ms' => 0,
    ]);
});

it('publishes every safe entry and stores no source lyrics', function () {
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

    $run = app(DiatarCatalogSynchronizer::class)->synchronize();

    expect($run->status)->toBe(DiatarSyncStatus::Completed)
        ->and($run->indexed_count)->toBe(1)
        ->and(DiatarBook::first()->songs)->toHaveCount(2)
        ->and(DiatarBook::first()->songs->flatMap->slides)->toHaveCount(3)
        ->and(json_encode(DiatarBook::with('songs.slides')->get()->toArray()))->not->toContain('kitalált tesztsor');
    Http::assertSentCount(3);
});

it('keeps the published catalogue unchanged when the repository cannot be reached', function () {
    $book = DiatarBook::factory()->create(['source_path' => 'existing.dtx', 'available' => true]);
    Http::preventStrayRequests();
    Http::fake([
        'https://api.example.test/repos/test/repository/commits/main' => Http::response([], 503),
    ]);

    expect(fn () => app(DiatarCatalogSynchronizer::class)->synchronize())
        ->toThrow(RequestException::class)
        ->and($book->fresh()->available)->toBeTrue()
        ->and(DiatarSyncRun::query()->latest('id')->first()->status)->toBe(DiatarSyncStatus::Failed);
});

it('marks missing books unavailable only after an authoritative tree is fetched', function () {
    $book = DiatarBook::factory()->create(['source_path' => 'removed.dtx', 'available' => true]);
    Http::preventStrayRequests();
    Http::fake([
        'https://api.example.test/repos/test/repository/commits/main' => Http::response(['sha' => 'revision-two']),
        'https://api.example.test/repos/test/repository/git/trees/revision-two*' => Http::response([
            'truncated' => false,
            'tree' => [],
        ]),
    ]);

    $run = app(DiatarCatalogSynchronizer::class)->synchronize();

    expect($run->status)->toBe(DiatarSyncStatus::Completed)
        ->and($book->fresh()->available)->toBeFalse()
        ->and($book->fresh()->unavailable_reason)->toBe('missing_from_authoritative_tree');
});

it('keeps prior metadata but makes an existing malformed source unavailable', function () {
    $book = DiatarBook::factory()->create([
        'source_path' => 'broken.dtx',
        'title' => 'Previously published title',
        'available' => true,
    ]);
    $song = DiatarSong::factory()->for($book, 'book')->create(['source_order' => 1]);
    $slide = DiatarSlide::factory()->for($song, 'song')->create(['source_order' => 1]);
    Http::preventStrayRequests();
    Http::fake([
        'https://api.example.test/repos/test/repository/commits/main' => Http::response(['sha' => 'revision-three']),
        'https://api.example.test/repos/test/repository/git/trees/revision-three*' => Http::response([
            'truncated' => false,
            'tree' => [['path' => 'broken.dtx', 'type' => 'blob', 'sha' => 'blob-three']],
        ]),
        'https://raw.example.test/test/repository/revision-three/broken.dtx' => Http::response('not a DTX file'),
    ]);

    $run = app(DiatarCatalogSynchronizer::class)->synchronize();

    expect($run->status)->toBe(DiatarSyncStatus::CompletedWithWarnings)
        ->and($book->fresh()->available)->toBeFalse()
        ->and($book->fresh()->title)->toBe('Previously published title')
        ->and($song->fresh()->exists)->toBeTrue()
        ->and($song->fresh()->available)->toBeFalse()
        ->and($slide->fresh()->is_exportable)->toBeFalse();
});
