<?php

use App\Models\ScoreFile;
use App\Services\ScoreFileCipher;
use App\Services\ScoreFileStorage;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

/**
 * Moving the library off Laravel's envelope and onto the compact one.
 *
 * The old format still opens, so nothing here is urgent; what these tests hold
 * down is that the rewrite is faithful, that it can be interrupted and resumed,
 * and that an artifact whose key is gone is reported rather than destroyed.
 */

/** An artifact stored the way the library was written before ScoreFileCipher. */
function legacyArtifact(string $path, string $bytes): void
{
    Storage::disk(ScoreFileStorage::DISK)->put($path, Crypt::encryptString($bytes));
}

it('rewrites a legacy artifact and reads back the same bytes', function () {
    Storage::fake('private');

    $scoreFile = ScoreFile::factory()->create();
    legacyArtifact($scoreFile->pagePath(1), 'a page of music');

    $this->artisan('scores:reencrypt')->assertSuccessful();

    $disk = Storage::disk('private');
    $stored = (string) $disk->get($scoreFile->pagePath(1));

    expect(app(ScoreFileCipher::class)->isOwnEnvelope($stored))->toBeTrue()
        ->and(app(ScoreFileStorage::class)->get($scoreFile->pagePath(1)))->toBe('a page of music');
});

it('leaves nothing behind in the staging directory', function () {
    Storage::fake('private');

    $scoreFile = ScoreFile::factory()->create();
    legacyArtifact($scoreFile->pagePath(1), 'a page of music');

    $this->artisan('scores:reencrypt')->assertSuccessful();

    expect(Storage::disk('private')->allFiles())->toBe([$scoreFile->pagePath(1)]);
});

it('makes the artifact smaller', function () {
    Storage::fake('private');

    $scoreFile = ScoreFile::factory()->create();
    $bytes = random_bytes(50_000);
    legacyArtifact($scoreFile->pagePath(1), $bytes);

    $disk = Storage::disk('private');
    $before = (int) $disk->size($scoreFile->pagePath(1));

    $this->artisan('scores:reencrypt')->assertSuccessful();

    expect((int) $disk->size($scoreFile->pagePath(1)))
        ->toBe(strlen($bytes) + ScoreFileCipher::OVERHEAD_BYTES)
        ->toBeLessThan((int) ($before * 0.6));
});

it('does not touch an artifact it has already rewritten', function () {
    Storage::fake('private');

    $scoreFile = ScoreFile::factory()->create();
    app(ScoreFileStorage::class)->put($scoreFile->pagePath(1), 'a page of music');

    $disk = Storage::disk('private');
    $before = (string) $disk->get($scoreFile->pagePath(1));

    $this->artisan('scores:reencrypt')
        ->expectsOutputToContain('already stored in the compact envelope')
        ->assertSuccessful();

    expect((string) $disk->get($scoreFile->pagePath(1)))->toBe($before);
});

// Run in batches while the site is up, then run again to pick up the rest.
it('stops at the limit and finishes on the next run', function () {
    Storage::fake('private');

    $scoreFile = ScoreFile::factory()->create();
    foreach (range(1, 4) as $page) {
        legacyArtifact($scoreFile->pagePath($page), "page {$page}");
    }

    $cipher = app(ScoreFileCipher::class);
    $disk = Storage::disk('private');
    $converted = fn (): int => count(array_filter(
        $disk->allFiles('score-files'),
        fn (string $path): bool => $cipher->isOwnEnvelope((string) $disk->get($path))
    ));

    $this->artisan('scores:reencrypt --limit=2')->assertSuccessful();
    expect($converted())->toBe(2);

    $this->artisan('scores:reencrypt')->assertSuccessful();
    expect($converted())->toBe(4);
});

it('changes nothing on a dry run', function () {
    Storage::fake('private');

    $scoreFile = ScoreFile::factory()->create();
    legacyArtifact($scoreFile->pagePath(1), 'a page of music');

    $disk = Storage::disk('private');
    $before = (string) $disk->get($scoreFile->pagePath(1));

    $this->artisan('scores:reencrypt --dry-run')
        ->expectsOutputToContain('1 artifact(s) would be rewritten')
        ->assertSuccessful();

    expect((string) $disk->get($scoreFile->pagePath(1)))->toBe($before);
});

// A file written under a key that is no longer held is not the command's to
// throw away: it reports it and moves on, leaving the bytes where they are.
it('reports an artifact it cannot open and leaves it alone', function () {
    Storage::fake('private');

    $scoreFile = ScoreFile::factory()->create();
    legacyArtifact($scoreFile->pagePath(1), 'readable');
    Storage::disk('private')->put($scoreFile->pagePath(2), Crypt::encryptString('unreadable').'tampered');

    $disk = Storage::disk('private');
    $damaged = (string) $disk->get($scoreFile->pagePath(2));

    $this->artisan('scores:reencrypt')->assertFailed();

    expect(app(ScoreFileStorage::class)->get($scoreFile->pagePath(1)))->toBe('readable')
        ->and((string) $disk->get($scoreFile->pagePath(2)))->toBe($damaged);
});

// Every artifact of a superseded file is kept alive by an approved version, and
// weighs the same as any other.
it('converts the artifacts of superseded files too', function () {
    Storage::fake('private');

    $scoreFile = ScoreFile::factory()->create(['superseded_at' => now()]);
    legacyArtifact($scoreFile->pagePath(1), 'an older engraving');

    $this->artisan('scores:reencrypt')->assertSuccessful();

    expect(app(ScoreFileStorage::class)->get($scoreFile->pagePath(1)))->toBe('an older engraving');
});
