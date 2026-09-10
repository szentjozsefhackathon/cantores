<?php

namespace App\Support;

use App\Models\Booklet;

/**
 * How a booklet's page tiles onto one A4 sheet.
 *
 * The whole of the arithmetic behind both imposed exports, and the one place
 * that decides whether a booklet can be imposed at all. Nothing here scales
 * anything: a page is placed on the sheet at exactly the size it was engraved,
 * because the point of imposing an A5 booklet onto A4 is to print the A5 pages
 * that were laid out, not slightly smaller ones with wider margins. That is
 * possible because the A series halves: two A5 pages side by side are 296 mm
 * across against A4's 297, and four A6 pages fill a portrait A4 the same way.
 *
 * Two pages make a leaf, and the axis they share is the fold. A portrait page
 * pairs left and right — the fold is vertical, as in any book; a landscape page
 * pairs top and bottom. Whatever room is left over on the sheet takes further
 * copies of that same leaf, so an A6 booklet prints two identical leaves per A4
 * and one cut gives two booklets rather than one and a wasted half.
 */
final class ImpositionLayout
{
    /**
     * The sheet is always A4: it is what a parish printer has in it.
     */
    private const SHEET_SHORT_EDGE_MM = 210.0;

    private const SHEET_LONG_EDGE_MM = 297.0;

    /**
     * Slack for the millimetre a pair of A5 pages is narrower than A4, and for
     * the floating-point dust in the halving.
     */
    private const TOLERANCE_MM = 0.001;

    public function __construct(
        public readonly float $sheetWidthMm,
        public readonly float $sheetHeightMm,
        public readonly float $pageWidthMm,
        public readonly float $pageHeightMm,
        public readonly bool $pairsSideBySide,
        public readonly int $copies,
    ) {}

    /**
     * The layout for this booklet, or null when its pages are already the size
     * of the sheet and there is nothing to impose.
     */
    public static function for(Booklet $booklet): ?self
    {
        $page = $booklet->pageMm();

        return self::fit($page['width'], $page['height']);
    }

    /**
     * The best of the two sheet orientations for a page of this size, or null
     * when a pair of them fits on neither.
     *
     * Best means most copies of the leaf: an A6 page pairs onto a landscape A4
     * as well as a portrait one, but portrait takes the leaf twice.
     */
    public static function fit(float $pageWidthMm, float $pageHeightMm): ?self
    {
        if ($pageWidthMm <= 0 || $pageHeightMm <= 0) {
            return null;
        }

        $sideBySide = $pageWidthMm <= $pageHeightMm;
        $leafWidth = $sideBySide ? 2 * $pageWidthMm : $pageWidthMm;
        $leafHeight = $sideBySide ? $pageHeightMm : 2 * $pageHeightMm;

        $best = null;

        $sheets = [
            [self::SHEET_SHORT_EDGE_MM, self::SHEET_LONG_EDGE_MM],
            [self::SHEET_LONG_EDGE_MM, self::SHEET_SHORT_EDGE_MM],
        ];

        foreach ($sheets as [$sheetWidth, $sheetHeight]) {
            if ($leafWidth > $sheetWidth + self::TOLERANCE_MM
                || $leafHeight > $sheetHeight + self::TOLERANCE_MM) {
                continue;
            }

            $copies = $sideBySide
                ? (int) floor(($sheetHeight + self::TOLERANCE_MM) / $pageHeightMm)
                : (int) floor(($sheetWidth + self::TOLERANCE_MM) / $pageWidthMm);

            if ($copies < 1) {
                continue;
            }

            if ($best === null || $copies > $best->copies) {
                $best = new self($sheetWidth, $sheetHeight, $pageWidthMm, $pageHeightMm, $sideBySide, $copies);
            }
        }

        return $best;
    }

    /**
     * Where one page of a leaf goes, in sheet millimetres from its top left.
     *
     * The block of leaves is centred, so the millimetre A4 has over a pair of
     * A5 pages is shared between the two outer edges rather than left at one.
     *
     * @param  int  $pageInLeaf  0 for the first page of the leaf (left, or top)
     * @param  int  $copy  which repeat of the leaf on this sheet
     * @return array{x: float, y: float}
     */
    public function slotOrigin(int $pageInLeaf, int $copy): array
    {
        $blockWidth = $this->pairsSideBySide
            ? 2 * $this->pageWidthMm
            : $this->pageWidthMm * $this->copies;

        $blockHeight = $this->pairsSideBySide
            ? $this->pageHeightMm * $this->copies
            : 2 * $this->pageHeightMm;

        $originX = ($this->sheetWidthMm - $blockWidth) / 2;
        $originY = ($this->sheetHeightMm - $blockHeight) / 2;

        return $this->pairsSideBySide
            ? [
                'x' => $originX + $pageInLeaf * $this->pageWidthMm,
                'y' => $originY + $copy * $this->pageHeightMm,
            ]
            : [
                'x' => $originX + $copy * $this->pageWidthMm,
                'y' => $originY + $pageInLeaf * $this->pageHeightMm,
            ];
    }
}
