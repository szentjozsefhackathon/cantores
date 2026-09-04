<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class SvgToPdfConverter
{
    /**
     * Millimetres per CSS pixel: the editor lays scores out in user units
     * where 1 unit = 1 px at 96 dpi.
     */
    private const MM_PER_PX = 25.4 / 96;

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
     * When $credit is given it is stamped along the foot of every page, so a
     * score published under a licence that requires attribution carries its
     * credit in the file itself rather than only on the page it came from.
     *
     * @param  list<string>  $svgs
     */
    public function convert(array $svgs, ?string $credit = null): string
    {
        if ($svgs === []) {
            throw new RuntimeException('No SVG input provided.');
        }

        $workDir = $this->makeWorkDir();

        try {
            $inputFiles = [];
            foreach (array_values($svgs) as $index => $svg) {
                $path = $workDir.DIRECTORY_SEPARATOR.'page-'.$index.'.svg';
                $prepared = $this->normalizePhysicalSize($this->expandPositionedText($svg));
                $prepared = $this->stampCredit($prepared, $credit);
                file_put_contents($path, $prepared);
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
     * Restate the document's intrinsic size as the physical size of its viewBox.
     *
     * Scores are laid out in user units where 1 unit = 1 px at 96 dpi, so a
     * 170 mm staff width becomes a 643 unit viewBox. The editor then multiplies
     * the root width/height by the preview zoom (120% by default), and
     * rsvg-convert maps those unitless pixels straight to PDF points — a page
     * that prints 204 mm wide instead of 170 mm. Replacing width/height with
     * the viewBox size expressed in millimetres drops the screen zoom and
     * states the size in a unit that survives the trip to PDF unchanged, so
     * the printed score measures what the editor promised.
     *
     * Documents that already declare a physical unit, or that carry no usable
     * viewBox, are returned untouched.
     */
    /**
     * Append a small credit line to the foot of an SVG page.
     *
     * Inserted immediately before the closing tag so it paints over everything
     * already drawn, and positioned from the document's own height so it lands
     * inside the page whatever size the editor produced.
     */
    public function stampCredit(string $svg, ?string $credit): string
    {
        if ($credit === null || trim($credit) === '') {
            return $svg;
        }

        $close = strripos($svg, '</svg>');

        if ($close === false) {
            return $svg;
        }

        $text = htmlspecialchars($credit, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $element = '<text x="50%" y="99%" text-anchor="middle" '
            .'font-family="sans-serif" font-size="7" fill="#666" '
            .'style="font-family:sans-serif;font-size:7px;fill:#666">'
            .$text
            .'</text>';

        return substr($svg, 0, $close).$element.substr($svg, $close);
    }

    public function normalizePhysicalSize(string $svg): string
    {
        $previous = libxml_use_internal_errors(true);
        $doc = new \DOMDocument;

        try {
            if (! $doc->loadXML($svg, LIBXML_NONET)) {
                return $svg;
            }

            $root = $doc->documentElement;
            if (! $root instanceof \DOMElement || $root->localName !== 'svg') {
                return $svg;
            }

            if ($this->hasPhysicalUnit($root->getAttribute('width'))
                || $this->hasPhysicalUnit($root->getAttribute('height'))) {
                return $svg;
            }

            $box = $this->parseCoordinateList($root->getAttribute('viewBox'));
            if (count($box) !== 4) {
                return $svg;
            }

            $width = (float) $box[2];
            $height = (float) $box[3];
            if ($width <= 0 || $height <= 0) {
                return $svg;
            }

            $root->setAttribute('width', $this->millimetres($width));
            $root->setAttribute('height', $this->millimetres($height));

            return (string) $doc->saveXML();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * Whether a length is expressed in an absolute unit other than pixels.
     */
    private function hasPhysicalUnit(string $length): bool
    {
        return (bool) preg_match('/(mm|cm|in|pt|pc|q)\s*$/i', trim($length));
    }

    /**
     * Format a pixel length as a millimetre length, trimmed of trailing zeros.
     */
    private function millimetres(float $pixels): string
    {
        $value = rtrim(rtrim(number_format($pixels * self::MM_PER_PX, 4, '.', ''), '0'), '.');

        return ($value === '' ? '0' : $value).'mm';
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
