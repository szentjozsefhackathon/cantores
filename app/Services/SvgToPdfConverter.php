<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class SvgToPdfConverter
{
    public function __construct(
        private readonly string $binary,
        private readonly int $timeout,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (string) config('services.rsvg.bin', 'rsvg-convert'),
            (int) config('services.rsvg.timeout', 30),
        );
    }

    /**
     * Render one or more SVG documents into a single (multi-page) PDF.
     *
     * Each SVG is written to its own file inside an isolated temporary
     * directory. librsvg confines relative/file resource references to that
     * directory, so a hostile SVG cannot read arbitrary server files. No flags
     * enabling network or unrestricted resource loading are passed.
     *
     * @param  list<string>  $svgs
     */
    public function convert(array $svgs): string
    {
        if ($svgs === []) {
            throw new RuntimeException('No SVG input provided.');
        }

        $workDir = $this->makeWorkDir();

        try {
            $inputFiles = [];
            foreach (array_values($svgs) as $index => $svg) {
                $path = $workDir.DIRECTORY_SEPARATOR.'page-'.$index.'.svg';
                file_put_contents($path, $this->expandPositionedText($svg));
                $inputFiles[] = $path;
            }

            $outputFile = $workDir.DIRECTORY_SEPARATOR.'out.pdf';

            $process = new Process([
                $this->binary,
                '--format=pdf',
                '--output',
                $outputFile,
                ...$inputFiles,
            ], $workDir);
            $process->setTimeout($this->timeout);

            try {
                $process->mustRun();
            } catch (ProcessFailedException $e) {
                throw new RuntimeException('PDF conversion failed: '.$process->getErrorOutput(), previous: $e);
            }

            $pdf = @file_get_contents($outputFile);
            if ($pdf === false || $pdf === '' || ! str_starts_with($pdf, '%PDF')) {
                throw new RuntimeException('PDF conversion produced no valid output.');
            }

            return $pdf;
        } finally {
            $this->removeWorkDir($workDir);
        }
    }

    /**
     * Explode list-positioned <text> elements into one <tspan> per glyph.
     *
     * abc2svg positions each music glyph with a coordinate list, e.g.
     * `<text x="8.3,38.9,63.6" y="41,53,50">...</text>`, where the i-th
     * character is placed at the i-th (x, y). librsvg ignores these lists: it
     * positions only the first glyph and advances the rest by font metrics, so
     * the notation collapses into an overlapping cluster. Rewriting each glyph
     * as its own <tspan> with a single x/y — which librsvg does honour —
     * restores the intended layout. SVGs that fail to parse are passed through
     * unchanged so rsvg-convert can surface the error.
     */
    public function expandPositionedText(string $svg): string
    {
        $previous = libxml_use_internal_errors(true);
        $doc = new \DOMDocument;

        try {
            if (! $doc->loadXML($svg, LIBXML_NONET)) {
                return $svg;
            }

            $xpath = new \DOMXPath($doc);
            $xpath->registerNamespace('svg', 'http://www.w3.org/2000/svg');

            $changed = false;

            /** @var \DOMNodeList<\DOMElement> $nodes */
            $nodes = $xpath->query('//svg:text | //svg:tspan');
            foreach (iterator_to_array($nodes) as $node) {
                $changed = $this->splitPositionedTextNode($doc, $node) || $changed;
            }

            if (! $changed) {
                return $svg;
            }

            return (string) $doc->saveXML();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * Split a single text/tspan node whose x or y attribute holds a coordinate
     * list into per-glyph <tspan> children. Returns whether it was rewritten.
     */
    private function splitPositionedTextNode(\DOMDocument $doc, \DOMElement $node): bool
    {
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                return false;
            }
        }

        $xs = $this->parseCoordinateList($node->getAttribute('x'));
        $ys = $this->parseCoordinateList($node->getAttribute('y'));

        if (count($xs) <= 1 && count($ys) <= 1) {
            return false;
        }

        $glyphs = preg_split('//u', $node->textContent, -1, PREG_SPLIT_NO_EMPTY);
        if ($glyphs === false || count($glyphs) <= 1) {
            return false;
        }

        $namespace = 'http://www.w3.org/2000/svg';
        $lastX = array_key_last($xs);
        $lastY = array_key_last($ys);

        while ($node->firstChild) {
            $node->removeChild($node->firstChild);
        }
        $node->removeAttribute('x');
        $node->removeAttribute('y');

        foreach ($glyphs as $index => $glyph) {
            $tspan = $doc->createElementNS($namespace, 'tspan');
            if ($xs !== []) {
                $tspan->setAttribute('x', $xs[$index] ?? $xs[$lastX]);
            }
            if ($ys !== []) {
                $tspan->setAttribute('y', $ys[$index] ?? $ys[$lastY]);
            }
            $tspan->appendChild($doc->createTextNode($glyph));
            $node->appendChild($tspan);
        }

        return true;
    }

    /**
     * Parse an SVG coordinate list ("8.3,38.9 63.6") into its values.
     *
     * @return list<string>
     */
    private function parseCoordinateList(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $parts = preg_split('/[\s,]+/', $value);

        return $parts === false ? [] : array_values($parts);
    }

    private function makeWorkDir(): string
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'svg2pdf-'.bin2hex(random_bytes(8));
        if (! mkdir($dir, 0700) && ! is_dir($dir)) {
            throw new RuntimeException('Unable to create temporary directory for PDF conversion.');
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
