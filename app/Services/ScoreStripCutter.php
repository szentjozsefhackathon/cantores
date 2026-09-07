<?php

namespace App\Services;

use RuntimeException;

/**
 * Cuts a rendered page into the strips a booklet flows.
 *
 * The bands arrive as fractions from ScorePageBander, which reads them off the
 * reading-resolution page; the cutting happens here at printing resolution, so
 * the analysis is cheap and the ink is not.
 *
 * Every strip of one file is cut to the same horizontal window, which is the
 * union of what all its pages use. That is what keeps a booklet from rippling:
 * scaled to the page, systems that shared a left margin in the source still
 * share one on the sheet.
 */
class ScoreStripCutter
{
    /**
     * Cut one page.
     *
     * @param  array{left: float, right: float}  $window  as fractions of the page width
     * @param  list<array{top: float, bottom: float}>  $bands  as fractions of the page height
     * @return list<array{png: string, width: int, height: int}>
     */
    public function cut(string $pagePng, array $window, array $bands): array
    {
        if (! str_starts_with($pagePng, "\x89PNG")) {
            throw new RuntimeException('Strip cutting input is not a PNG.');
        }

        $page = @imagecreatefromstring($pagePng);

        if ($page === false) {
            throw new RuntimeException('Could not read the rendered page image.');
        }

        try {
            $pageWidth = imagesx($page);
            $pageHeight = imagesy($page);

            $left = (int) floor($window['left'] * $pageWidth);
            $right = (int) ceil($window['right'] * $pageWidth);
            $width = max(1, min($pageWidth - $left, $right - $left));

            $strips = [];

            foreach ($bands as $band) {
                $top = (int) floor($band['top'] * $pageHeight);
                $bottom = (int) ceil($band['bottom'] * $pageHeight);
                $height = max(1, min($pageHeight - $top, $bottom - $top));

                $strips[] = [
                    'png' => $this->crop($page, $left, $top, $width, $height),
                    'width' => $width,
                    'height' => $height,
                ];
            }

            return $strips;
        } finally {
            imagedestroy($page);
        }
    }

    /**
     * One band, on paper rather than on transparency: a strip is composited
     * onto a booklet page, and an alpha channel would carry the source's
     * background through as a hole.
     */
    private function crop(\GdImage $page, int $x, int $y, int $width, int $height): string
    {
        $strip = imagecreatetruecolor($width, $height);

        try {
            imagefill($strip, 0, 0, imagecolorallocate($strip, 255, 255, 255));
            imagecopy($strip, $page, 0, 0, $x, $y, $width, $height);

            ob_start();
            imagepng($strip, null, 6);

            return (string) ob_get_clean();
        } finally {
            imagedestroy($strip);
        }
    }
}
