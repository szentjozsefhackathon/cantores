<?php

namespace App\Services;

use RuntimeException;

/**
 * Cuts the incipit for a file-backed score out of its first rendered page.
 *
 * Browser-authored scores get their incipit from the editor's canvas; an
 * uploaded file has no browser in the loop, so the crop happens here. It is a
 * crop, not the page: anchored top-left at half the page width and a sixth of
 * its height, which on A4 portrait is 105 × 49.5 mm — close to the 2.1∶1 of the
 * first-staff-row incipits it sits beside in listings, and well short of a
 * reproduction of the page.
 */
class ScoreFileIncipitCropper
{
    /**
     * The width the client-side generateIncipit() produces, matched here so
     * both kinds of incipit render identically in a listing.
     */
    public const TARGET_WIDTH = 800;

    private const WIDTH_FRACTION = 1 / 2;

    private const HEIGHT_FRACTION = 1 / 6;

    /**
     * @param  string  $pagePng  the first page, rasterised at a resolution
     *                           denser than the target so the crop downscales
     */
    public function crop(string $pagePng): string
    {
        $source = @imagecreatefromstring($pagePng);

        if ($source === false) {
            throw new RuntimeException('Could not read the rendered page image.');
        }

        try {
            $cropWidth = max(1, (int) round(imagesx($source) * self::WIDTH_FRACTION));
            $cropHeight = max(1, (int) round(imagesy($source) * self::HEIGHT_FRACTION));

            $targetWidth = min(self::TARGET_WIDTH, $cropWidth);
            $targetHeight = max(1, (int) round($targetWidth * $cropHeight / $cropWidth));

            $destination = imagecreatetruecolor($targetWidth, $targetHeight);
            imagefill($destination, 0, 0, imagecolorallocate($destination, 255, 255, 255));

            try {
                imagecopyresampled(
                    $destination, $source,
                    0, 0, 0, 0,
                    $targetWidth, $targetHeight,
                    $cropWidth, $cropHeight
                );

                return $this->toPng($destination);
            } finally {
                imagedestroy($destination);
            }
        } finally {
            imagedestroy($source);
        }
    }

    private function toPng(\GdImage $image): string
    {
        ob_start();
        imagepng($image, null, 6);

        return (string) ob_get_clean();
    }
}
