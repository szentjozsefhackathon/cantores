<?php

namespace App\Services;

use App\Enums\BookletImposition;
use App\Models\Booklet;
use App\Support\ImpositionLayout;
use DOMDocument;
use DOMElement;
use RuntimeException;

/**
 * Lays a booklet's engraved pages out on printer's sheets.
 *
 * The browser engraves one SVG per booklet page and the converter turns a stack
 * of them into a PDF of that size. This stands between the two for the two
 * imposed exports: it nests those page documents, untouched and unscaled, into a
 * sheet document the size of an A4, in the order the printer needs them.
 *
 * Nothing is resized on the way — see ImpositionLayout — and nothing is
 * re-engraved. A page arrives as a complete SVG document with its own viewBox in
 * pixels, and an SVG document nested in another with a width and height in the
 * parent's units is scaled by exactly the ratio between the two, which for a
 * page stated at its real size on a sheet stated in millimetres is the same
 * millimetres-per-pixel the whole application is built on. So the margins on the
 * printed page are the margins the cantor set, to the micron.
 */
class BookletImposer
{
    private const SVG_NS = 'http://www.w3.org/2000/svg';

    /**
     * @param  list<string>  $pages  one SVG document per booklet page
     * @return list<string> one SVG document per sheet
     */
    public function impose(array $pages, Booklet $booklet, BookletImposition $imposition): array
    {
        $pages = array_values($pages);

        if (! $imposition->imposes() || $pages === []) {
            return $pages;
        }

        $layout = ImpositionLayout::for($booklet);

        if (! $layout instanceof ImpositionLayout) {
            return $pages;
        }

        $leaves = $imposition === BookletImposition::Booklet
            ? self::bookletLeaves(count($pages))
            : self::readingLeaves(count($pages));

        $placed = 0;
        $sheets = [];

        foreach ($leaves as $leaf) {
            $sheets[] = $this->sheet($leaf, $pages, $layout, $placed);
        }

        return $sheets;
    }

    /**
     * The leaves of a straight n-up print: the pages in the order they are read,
     * two to a leaf.
     *
     * The last leaf of an odd booklet is half empty, which is what it should be —
     * this is n-up printing, not a booklet, and nobody folds it.
     *
     * @return list<array{0: int|null, 1: int|null}> one-based page numbers, null for a blank
     */
    public static function readingLeaves(int $pageCount): array
    {
        $leaves = [];

        for ($page = 1; $page <= $pageCount; $page += 2) {
            $leaves[] = [$page, $page + 1 <= $pageCount ? $page + 1 : null];
        }

        return $leaves;
    }

    /**
     * The leaves of a saddle-stitched booklet: the order a stack of sheets has
     * to be printed in so that folding it down the middle reads straight
     * through.
     *
     * The count is rounded up to a multiple of four, because a folded sheet is
     * four pages whether or not there is anything on them, and the blanks land
     * at the back where they belong.
     *
     * Each sheet takes the outermost pages still unplaced. The front of the
     * first sheet is the last page beside the first — the back cover next to the
     * front cover — and its back is page two beside the second to last. So the
     * leaves alternate: the outer side of a sheet reads high, low and the inner
     * side low, high.
     *
     * @return list<array{0: int|null, 1: int|null}> one-based page numbers, null for a blank
     */
    public static function bookletLeaves(int $pageCount): array
    {
        if ($pageCount < 1) {
            return [];
        }

        $padded = (int) (ceil($pageCount / 4) * 4);
        $leaves = [];

        for ($index = 0; $index < $padded / 2; $index++) {
            $low = $index + 1;
            $high = $padded - $index;

            $leaf = $index % 2 === 0 ? [$high, $low] : [$low, $high];

            $leaves[] = [
                $leaf[0] > $pageCount ? null : $leaf[0],
                $leaf[1] > $pageCount ? null : $leaf[1],
            ];
        }

        return $leaves;
    }

    /**
     * One sheet: white paper with a leaf on it, repeated as often as it fits.
     *
     * @param  array{0: int|null, 1: int|null}  $leaf
     * @param  list<string>  $pages
     * @param  int  $placed  how many pages have been placed across the whole export, so far
     */
    private function sheet(array $leaf, array $pages, ImpositionLayout $layout, int &$placed): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');

        $sheet = $doc->createElementNS(self::SVG_NS, 'svg');
        $doc->appendChild($sheet);

        $sheet->setAttribute('viewBox', '0 0 '.self::number($layout->sheetWidthMm).' '.self::number($layout->sheetHeightMm));
        $sheet->setAttribute('width', self::number($layout->sheetWidthMm).'mm');
        $sheet->setAttribute('height', self::number($layout->sheetHeightMm).'mm');

        $paper = $doc->createElementNS(self::SVG_NS, 'rect');
        $paper->setAttribute('x', '0');
        $paper->setAttribute('y', '0');
        $paper->setAttribute('width', self::number($layout->sheetWidthMm));
        $paper->setAttribute('height', self::number($layout->sheetHeightMm));
        $paper->setAttribute('fill', '#ffffff');
        $sheet->appendChild($paper);

        for ($copy = 0; $copy < $layout->copies; $copy++) {
            foreach ($leaf as $pageInLeaf => $pageNumber) {
                if ($pageNumber === null) {
                    continue;
                }

                $origin = $layout->slotOrigin($pageInLeaf, $copy);

                $sheet->appendChild($this->placedPage(
                    $doc,
                    $pages[$pageNumber - 1],
                    'im'.$placed++,
                    $origin['x'],
                    $origin['y'],
                    $layout,
                ));
            }
        }

        return (string) $doc->saveXML();
    }

    /**
     * One booklet page as a node of the sheet, at its own physical size.
     *
     * @throws RuntimeException when the page is not parseable XML — a sheet with
     *                          a page silently missing from it is worse than a
     *                          download that says it failed.
     */
    private function placedPage(
        DOMDocument $doc,
        string $page,
        string $prefix,
        float $x,
        float $y,
        ImpositionLayout $layout,
    ): DOMElement {
        $root = $this->parsePage($this->scopeIds($page, $prefix));

        /** @var DOMElement $imported */
        $imported = $doc->importNode($root, true);

        if ($imported->getAttribute('viewBox') === '') {
            $imported->setAttribute('viewBox', '0 0 '
                .self::number((float) $imported->getAttribute('width')).' '
                .self::number((float) $imported->getAttribute('height')));
        }

        $imported->setAttribute('x', self::number($x));
        $imported->setAttribute('y', self::number($y));
        $imported->setAttribute('width', self::number($layout->pageWidthMm));
        $imported->setAttribute('height', self::number($layout->pageHeightMm));

        // A page composed to a different aspect than the paper is centred and
        // shrunk to fit rather than stretched: too small is a page that still
        // reads, too wide is a page whose staves are the wrong shape.
        $imported->setAttribute('preserveAspectRatio', 'xMidYMid meet');

        return $imported;
    }

    private function parsePage(string $page): DOMElement
    {
        $previous = libxml_use_internal_errors(true);
        $doc = new DOMDocument;

        try {
            if (! $doc->loadXML($page, LIBXML_NONET)) {
                throw new RuntimeException('A booklet page could not be parsed for imposition.');
            }

            $root = $doc->documentElement;

            if (! $root instanceof DOMElement || $root->localName !== 'svg') {
                throw new RuntimeException('A booklet page is not an SVG document.');
            }

            return $root;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * Make a page's ids its own before it is put on a sheet beside another.
     *
     * Two pages on one sheet are one XML document, and both of them name their
     * glyph symbols the way the renderer that drew them does — so without this
     * the second page's <use> would draw the first page's glyph. It matters most
     * where the sheet carries the same page twice, which is every A6 sheet.
     */
    private function scopeIds(string $markup, string $prefix): string
    {
        return (string) preg_replace(
            ['/\bid="([^"]+)"/', '/href="#([^"]+)"/', '/url\(#([^)]+)\)/'],
            ['id="'.$prefix.'-$1"', 'href="#'.$prefix.'-$1"', 'url(#'.$prefix.'-$1)'],
            $markup,
        );
    }

    /**
     * A millimetre length, without the trailing zeros a printer's sheet would be
     * measured to.
     */
    private static function number(float $value): string
    {
        $formatted = rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');

        return $formatted === '' || $formatted === '-' ? '0' : $formatted;
    }
}
