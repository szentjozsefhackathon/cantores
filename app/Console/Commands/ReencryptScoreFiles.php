<?php

namespace App\Console\Commands;

use App\Services\ScoreFileCipher;
use App\Services\ScoreFileStorage;
use Illuminate\Console\Command;

/**
 * Rewrites the library into the binary envelope ScoreFileCipher writes.
 *
 * Laravel's envelope base64s twice and costs 1.78 bytes on disk per byte of
 * score; the new one costs thirty-two bytes per file. Nothing needs this to
 * have run — an artifact written the old way still opens — so it is a command
 * rather than a migration, and it can be taken in batches with `--limit` while
 * the site is up.
 *
 * The disk is walked rather than the database, so artifacts belonging to
 * superseded files are converted too: they are kept alive by approved versions
 * and are as large as anything else.
 *
 * Each file is written beside itself and then renamed over the original, so a
 * worker killed mid-run leaves either the old envelope or the new one and never
 * half of either. The staging directory is cleared on the way in, which is also
 * what tidies up after a run that was interrupted.
 */
class ReencryptScoreFiles extends Command
{
    /** Outside `score-files/` so a stray temporary is never mistaken for an artifact. */
    private const STAGING = 'reencrypting';

    protected $signature = 'scores:reencrypt
                            {--limit=0 : Stop after this many files}
                            {--dry-run : Report what would be rewritten, and change nothing}';

    protected $description = 'Rewrite score file artifacts in the compact encryption envelope';

    public function handle(ScoreFileStorage $storage, ScoreFileCipher $cipher): int
    {
        $disk = $storage->disk();
        $disk->deleteDirectory(self::STAGING);

        $pending = [];
        $limit = (int) $this->option('limit');

        foreach ($disk->allFiles('score-files') as $path) {
            if ($cipher->isOwnEnvelope($this->headerOf($disk, $path))) {
                continue;
            }

            $pending[] = $path;

            if ($limit > 0 && count($pending) >= $limit) {
                break;
            }
        }

        if ($pending === []) {
            $this->info('Every artifact is already stored in the compact envelope.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info(count($pending).' artifact(s) would be rewritten.');

            return self::SUCCESS;
        }

        $before = 0;
        $after = 0;
        $failed = [];

        $this->withProgressBar($pending, function (string $path) use ($disk, $storage, $cipher, &$before, &$after, &$failed): void {
            try {
                $plaintext = $storage->get($path);
                $staged = self::STAGING.'/'.sha1($path);

                $disk->put($staged, $cipher->encrypt($plaintext));

                $before += (int) $disk->size($path);
                $after += (int) $disk->size($staged);

                $disk->move($staged, $path);
            } catch (\Throwable $e) {
                $failed[$path] = $e->getMessage();
            }
        });

        $this->newLine(2);
        $disk->deleteDirectory(self::STAGING);

        $this->info(sprintf(
            'Rewrote %d artifact(s): %s -> %s on disk.',
            count($pending) - count($failed),
            $this->humanBytes($before),
            $this->humanBytes($after),
        ));

        foreach ($failed as $path => $message) {
            $this->error("{$path}: {$message}");
        }

        return $failed === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Enough of a file to tell which envelope it is in.
     *
     * Read rather than fetched whole: a source upload runs to 25 MB and there
     * is no reason to hold one in memory to look at its first four bytes.
     */
    private function headerOf(\Illuminate\Contracts\Filesystem\Filesystem $disk, string $path): string
    {
        $stream = $disk->readStream($path);

        if (! is_resource($stream)) {
            return '';
        }

        try {
            return (string) fread($stream, ScoreFileCipher::OVERHEAD_BYTES);
        } finally {
            fclose($stream);
        }
    }

    private function humanBytes(int $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024 || $unit === 'GB') {
                return round($bytes, 1).' '.$unit;
            }

            $bytes /= 1024;
        }

        return $bytes.' B';
    }
}
