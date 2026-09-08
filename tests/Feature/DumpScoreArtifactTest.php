<?php

use App\Models\ScoreFile;
use App\Services\ScoreFileStorage;
use Illuminate\Support\Facades\Storage;

/**
 * Reading one artifact back out of the library by hand.
 *
 * What matters is that the bytes come back exactly as they went in, through
 * both the encryption envelope and the gzip a page vector adds, and that a path
 * typed at a terminal cannot reach outside score-files/.
 */
beforeEach(function () {
    Storage::fake('private');

    $this->scoreFile = ScoreFile::factory()->create();
    $this->out = tempnam(sys_get_temp_dir(), 'dump');
});

afterEach(function () {
    @unlink($this->out);
});

it('decrypts an artifact', function () {
    app(ScoreFileStorage::class)->put($this->scoreFile->pagePath(1), 'a page of music');

    $this->artisan('scores:dump', [
        'path' => $this->scoreFile->pagePath(1),
        '--out' => $this->out,
    ])->assertSuccessful();

    expect(file_get_contents($this->out))->toBe('a page of music');
});

it('ungzips a page vector, and leaves it gzipped when asked', function () {
    $svg = '<svg viewBox="0 0 595 842"></svg>';
    app(ScoreFileStorage::class)->put($this->scoreFile->pageVectorPath(1), gzencode($svg, 9));

    $this->artisan('scores:dump', [
        'path' => $this->scoreFile->pageVectorPath(1),
        '--out' => $this->out,
    ])->assertSuccessful();

    expect(file_get_contents($this->out))->toBe($svg);

    $this->artisan('scores:dump', [
        'path' => $this->scoreFile->pageVectorPath(1),
        '--out' => $this->out,
        '--raw' => true,
    ])->assertSuccessful();

    expect(gzdecode((string) file_get_contents($this->out)))->toBe($svg);
});

it('takes a path with or without the score-files prefix', function () {
    app(ScoreFileStorage::class)->put($this->scoreFile->pagePath(1), 'a page of music');

    $this->artisan('scores:dump', [
        'path' => "{$this->scoreFile->id}/page-1.png",
        '--out' => $this->out,
    ])->assertSuccessful();

    expect(file_get_contents($this->out))->toBe('a page of music');
});

it('fails on a missing artifact rather than writing an empty file', function () {
    $this->artisan('scores:dump', [
        'path' => $this->scoreFile->pagePath(99),
        '--out' => $this->out,
    ])->assertFailed();

    expect(file_get_contents($this->out))->toBe('');
});

it('refuses a path that climbs out of the library', function () {
    $this->artisan('scores:dump', ['path' => '../../.env'])
        ->assertFailed();
})->throws(RuntimeException::class);
