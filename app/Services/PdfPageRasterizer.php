<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Rasterises a PDF into page images with poppler's `pdftoppm`.
 *
 * Shares the shape of SvgToPdfConverter and MuseScoreRenderer: an isolated
 * 0700 working directory, an argv array so nothing reaches a shell, a hard
 * timeout, and output validated by its magic bytes rather than by exit code.
 */
class PdfPageRasterizer
{
    /** The dpi the reading view's page images are rendered at. */
    public const VIEW_DPI = 150;

    public function __construct(
        private readonly string $binary,
        private readonly int $timeout,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (string) config('services.pdftoppm.bin', 'pdftoppm'),
            (int) config('services.pdftoppm.timeout', 180),
        );
    }

    /**
     * Rasterise every page, in order.
     *
     * @return list<string> PNG bytes, one per page
     */
    public function rasterize(string $pdf, int $dpi = self::VIEW_DPI): array
    {
        return $this->run($pdf, $dpi, null, null);
    }

    /**
     * Rasterise a single page on its own, so it can be rendered at a different
     * resolution from the rest — the incipit crop wants a denser page 1 than
     * the reading view needs.
     */
    public function rasterizePage(string $pdf, int $page, int $dpi = self::VIEW_DPI): string
    {
        $pages = $this->run($pdf, $dpi, $page, $page);

        if ($pages === []) {
            throw new RuntimeException("PDF page {$page} could not be rasterised.");
        }

        return $pages[0];
    }

    /**
     * @return list<string>
     */
    private function run(string $pdf, int $dpi, ?int $firstPage, ?int $lastPage): array
    {
        if (! str_starts_with($pdf, '%PDF')) {
            throw new RuntimeException('Rasterisation input is not a PDF.');
        }

        $workDir = $this->makeWorkDir();

        try {
            $inputFile = $workDir.DIRECTORY_SEPARATOR.'in.pdf';
            if (file_put_contents($inputFile, $pdf) === false) {
                throw new RuntimeException('Unable to stage the PDF for rasterisation.');
            }

            $arguments = [$this->binary, '-png', '-r', (string) $dpi];
            if ($firstPage !== null) {
                $arguments[] = '-f';
                $arguments[] = (string) $firstPage;
            }
            if ($lastPage !== null) {
                $arguments[] = '-l';
                $arguments[] = (string) $lastPage;
            }
            $arguments[] = $inputFile;
            $arguments[] = $workDir.DIRECTORY_SEPARATOR.'page';

            $process = new Process($arguments, $workDir);
            $process->setTimeout($this->timeout);

            try {
                $process->mustRun();
            } catch (ProcessFailedException $e) {
                throw new RuntimeException('PDF rasterisation failed: '.$process->getErrorOutput(), previous: $e);
            }

            $files = glob($workDir.DIRECTORY_SEPARATOR.'page-*.png') ?: [];
            sort($files, SORT_NATURAL);

            $pages = [];
            foreach ($files as $file) {
                $bytes = @file_get_contents($file);
                if ($bytes === false || ! str_starts_with($bytes, "\x89PNG")) {
                    throw new RuntimeException('PDF rasterisation produced no valid page image.');
                }
                $pages[] = $bytes;
            }

            if ($pages === []) {
                throw new RuntimeException('PDF rasterisation produced no pages.');
            }

            return $pages;
        } finally {
            $this->removeWorkDir($workDir);
        }
    }

    private function makeWorkDir(): string
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pdfpages-'.bin2hex(random_bytes(8));
        if (! mkdir($dir, 0700) && ! is_dir($dir)) {
            throw new RuntimeException('Unable to create temporary directory for rasterisation.');
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
