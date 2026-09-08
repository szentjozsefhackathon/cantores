<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Vectorises one PDF page into an SVG with poppler's `pdftocairo -svg`.
 *
 * A sibling of PdfPageRasterizer, and the same package: `pdftocairo` ships with
 * `pdftoppm` in `poppler-utils`, already installed in the renderer image. Shape
 * is shared too — an isolated 0700 working directory, an argv array so nothing
 * reaches a shell, a hard timeout, and output validated by its own bytes (an
 * `<svg` root) rather than by exit code.
 *
 * One invocation per page: poppler writes a single page per SVG file, so this
 * is the same per-page process cost as the 300 dpi rasterisation a vector strip
 * replaces. The caller decides per file whether the vector form is worth
 * keeping (RenderScoreFileJob measures it against the compressed page PNG); a
 * scan has nothing to vectorise and stays on the raster path.
 */
class PdfPageVectorizer
{
    public function __construct(
        private readonly string $binary,
        private readonly int $timeout,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (string) config('services.pdftocairo.bin', 'pdftocairo'),
            (int) config('services.pdftocairo.timeout', 180),
        );
    }

    /**
     * Vectorise a single page, returned as SVG bytes.
     */
    public function vectorizePage(string $pdf, int $page): string
    {
        if (! str_starts_with($pdf, '%PDF')) {
            throw new RuntimeException('Vectorisation input is not a PDF.');
        }

        if ($page < 1) {
            throw new RuntimeException("Cannot vectorise page {$page}.");
        }

        $workDir = $this->makeWorkDir();

        try {
            $inputFile = $workDir.DIRECTORY_SEPARATOR.'in.pdf';
            if (file_put_contents($inputFile, $pdf) === false) {
                throw new RuntimeException('Unable to stage the PDF for vectorisation.');
            }

            $outputFile = $workDir.DIRECTORY_SEPARATOR.'page.svg';

            $process = new Process([
                $this->binary,
                '-svg',
                '-f', (string) $page,
                '-l', (string) $page,
                $inputFile,
                $outputFile,
            ], $workDir);
            $process->setTimeout($this->timeout);

            try {
                $process->mustRun();
            } catch (ProcessFailedException $e) {
                throw new RuntimeException('PDF vectorisation failed: '.$process->getErrorOutput(), previous: $e);
            }

            $svg = @file_get_contents($outputFile);

            if ($svg === false || $svg === '') {
                throw new RuntimeException('PDF vectorisation produced no output.');
            }

            $this->assertIsSvg($svg);

            return $svg;
        } finally {
            $this->removeWorkDir($workDir);
        }
    }

    /**
     * Accept the output only if it parses as XML with an `<svg` root, so a
     * poppler that wrote an error page or a truncated file is caught here rather
     * than downstream in the browser or rsvg-convert.
     */
    private function assertIsSvg(string $svg): void
    {
        if (! str_contains($svg, '<svg')) {
            throw new RuntimeException('PDF vectorisation output is not an SVG document.');
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $document = simplexml_load_string($svg);

            if ($document === false || strtolower($document->getName()) !== 'svg') {
                throw new RuntimeException('PDF vectorisation output is not a well-formed SVG document.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function makeWorkDir(): string
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pdfvector-'.bin2hex(random_bytes(8));
        if (! mkdir($dir, 0700) && ! is_dir($dir)) {
            throw new RuntimeException('Unable to create temporary directory for vectorisation.');
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
