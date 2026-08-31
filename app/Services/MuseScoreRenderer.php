<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Engraves a MuseScore-readable source (.mscz, MusicXML, MIDI) into a PDF.
 *
 * Runs `mscore-render`, the Xvfb wrapper in docker/musescore/: MuseScore 4
 * initialises the xcb plugin whatever QT_QPA_PLATFORM says, so a throwaway X
 * server is the only reliable headless path.
 *
 * The input is attacker-controlled and parsed by MuseScore's Qt stack, so the
 * process gets a hard timeout, an isolated 0700 working directory it cannot
 * escape into anything interesting, and no network flags. Container-level
 * limits (mem_limit, pids_limit, non-root user) do the rest.
 */
class MuseScoreRenderer
{
    public function __construct(
        private readonly string $binary,
        private readonly int $timeout,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (string) config('services.musescore.bin', 'mscore-render'),
            (int) config('services.musescore.timeout', 180),
        );
    }

    /**
     * Render source bytes to PDF bytes.
     *
     * @param  string  $extension  the source's file extension, which is how
     *                             MuseScore decides on an importer
     */
    public function render(string $source, string $extension): string
    {
        if ($source === '') {
            throw new RuntimeException('No score source provided.');
        }

        $workDir = $this->makeWorkDir();

        try {
            $inputFile = $workDir.DIRECTORY_SEPARATOR.'source.'.$this->sanitizeExtension($extension);
            $outputFile = $workDir.DIRECTORY_SEPARATOR.'out.pdf';

            if (file_put_contents($inputFile, $source) === false) {
                throw new RuntimeException('Unable to stage the score source for rendering.');
            }

            $process = new Process([
                $this->binary,
                '--export-to',
                $outputFile,
                $inputFile,
            ], $workDir);
            $process->setTimeout($this->timeout);

            try {
                $process->mustRun();
            } catch (ProcessFailedException $e) {
                throw new RuntimeException('Score rendering failed: '.$process->getErrorOutput(), previous: $e);
            }

            $pdf = @file_get_contents($outputFile);
            if ($pdf === false || $pdf === '' || ! str_starts_with($pdf, '%PDF')) {
                throw new RuntimeException('Score rendering produced no valid PDF.');
            }

            return $pdf;
        } finally {
            $this->removeWorkDir($workDir);
        }
    }

    /**
     * MuseScore picks its importer from the extension, so it reaches a command
     * line — keep it to the characters an extension can legitimately contain.
     */
    private function sanitizeExtension(string $extension): string
    {
        $clean = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $extension) ?? '');

        if ($clean === '') {
            throw new RuntimeException('Score source has no usable file extension.');
        }

        return $clean;
    }

    private function makeWorkDir(): string
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'mscore-'.bin2hex(random_bytes(8));
        if (! mkdir($dir, 0700) && ! is_dir($dir)) {
            throw new RuntimeException('Unable to create temporary directory for score rendering.');
        }

        return $dir;
    }

    private function removeWorkDir(string $dir): void
    {
        foreach (glob($dir.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }
}
