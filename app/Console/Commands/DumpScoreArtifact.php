<?php

namespace App\Console\Commands;

use App\Services\ScoreFileStorage;
use Illuminate\Console\Command;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Hands back one stored artifact in the form it was put in.
 *
 * Everything under `score-files/` is encrypted (ScoreFileCipher) and a page
 * vector is gzipped inside that envelope as well, so `zcat` on the file itself
 * shows nothing and there is no way to look at a render without the
 * application's key. This is that way: decrypt, ungzip a `.svgz` unless asked
 * not to, and write the bytes out.
 *
 * For inspecting a render that came out wrong — is the SVG's viewBox what the
 * strips index into, did MuseScore actually produce the source that was
 * uploaded — rather than for anything the application does itself.
 */
class DumpScoreArtifact extends Command
{
    protected $signature = 'scores:dump
                            {path : Artifact path, with or without the score-files/ prefix (e.g. 13/page-1.svgz)}
                            {--out= : Write to this file rather than to stdout}
                            {--raw : Leave a .svgz gzipped}';

    protected $description = 'Decrypt one stored score file artifact and write it out';

    public function handle(ScoreFileStorage $storage): int
    {
        $path = $this->qualify((string) $this->argument('path'));

        if (! $storage->exists($path)) {
            $this->error("No such artifact: {$path}");

            return self::FAILURE;
        }

        try {
            $bytes = $storage->get($path);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (str_ends_with($path, '.svgz') && ! $this->option('raw')) {
            $svg = gzdecode($bytes);

            if ($svg === false) {
                $this->error("Decrypted, but not gzip: {$path}");

                return self::FAILURE;
            }

            $bytes = $svg;
        }

        $out = $this->option('out');

        if ($out === null) {
            // Raw, because an artifact is binary and Symfony would otherwise
            // read stray angle brackets in an SVG as output formatting.
            $this->output->write($bytes, false, OutputInterface::OUTPUT_RAW);

            return self::SUCCESS;
        }

        if (file_put_contents($out, $bytes) === false) {
            $this->error("Unable to write {$out}.");

            return self::FAILURE;
        }

        $this->info(sprintf('%s -> %s (%d bytes)', $path, $out, strlen($bytes)));

        return self::SUCCESS;
    }

    /**
     * The full disk path, whether or not the caller typed the prefix.
     *
     * `13/page-1.svgz` is what a directory listing reads like and is the shorter
     * thing to type; a path copied out of a log or an exception already carries
     * `score-files/`. Both are accepted, and neither can escape the directory.
     */
    private function qualify(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $path = str_starts_with($path, 'score-files/') ? substr($path, strlen('score-files/')) : $path;

        if (in_array('..', explode('/', $path), true)) {
            throw new RuntimeException('An artifact path may not climb out of score-files/.');
        }

        return 'score-files/'.$path;
    }
}
