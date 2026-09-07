<?php

namespace App\Services;

/**
 * Stores a rendered artifact as small as it will go without changing a pixel.
 *
 * Engraved music is line art: a page of it holds a few hundred greys and no
 * colour at all, yet both poppler and GD hand it over as 24-bit RGB, three
 * bytes for every pixel of a page that is mostly paper. Re-encoded as a palette
 * PNG it is a byte a pixel and identical, which is worth about half the volume
 * of `score-files/` — and more than that on the booklet strips, which are the
 * densest thing stored per page.
 *
 * Nothing here is allowed to lose anything. The palette is only used when the
 * image is opaque greyscale, in which case a 256-step ramp reproduces every
 * pixel exactly; a colour scan keeps its channels and gets nothing but a harder
 * squeeze from deflate, and anything carrying transparency is not touched at
 * all. An artifact that cannot be read, or that comes out no smaller, is stored
 * exactly as it arrived — a compression step must never be the reason a score
 * is missing.
 */
class ScoreImageCompressor
{
    /** Slowest deflate. These are written once in a queue job and read forever. */
    private const LEVEL = 9;

    /** A palette holds no more than this, which is also every level of grey. */
    private const GREYS = 256;

    private const GREY = 'grey';

    private const COLOUR = 'colour';

    private const ALPHA = 'alpha';

    public function compress(string $png): string
    {
        if (! str_starts_with($png, "\x89PNG")) {
            return $png;
        }

        $image = @imagecreatefromstring($png);

        if ($image === false) {
            return $png;
        }

        try {
            $encoded = match ($this->inspect($image)) {
                self::GREY => $this->asGreyPalette($image),
                self::COLOUR => $this->encode($image),
                default => null,
            };

            return $encoded !== null && strlen($encoded) < strlen($png) ? $encoded : $png;
        } finally {
            imagedestroy($image);
        }
    }

    /**
     * What the image is made of, in one pass.
     *
     * A palette image already costs a byte a pixel, so it is reported as
     * transparent and left alone whatever it holds. For a truecolor one this is
     * a full scan, which is the price of being sure: a sampled answer would
     * eventually quantise somebody's colour manuscript. It gives up the moment
     * it meets a pixel that is not fully opaque, because GD reconstructs an
     * image without knowing it had an alpha channel and re-encoding one would
     * flatten it.
     *
     * @return self::GREY|self::COLOUR|self::ALPHA
     */
    private function inspect(\GdImage $image): string
    {
        if (! imageistruecolor($image)) {
            return self::ALPHA;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $grey = true;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $colour = imagecolorat($image, $x, $y);

                if (($colour >> 24) !== 0) {
                    return self::ALPHA;
                }

                $red = ($colour >> 16) & 0xFF;

                if ($red !== (($colour >> 8) & 0xFF) || $red !== ($colour & 0xFF)) {
                    $grey = false;
                }
            }
        }

        return $grey ? self::GREY : self::COLOUR;
    }

    /**
     * The same image over a full ramp of greys.
     *
     * The ramp is allocated before anything is copied, so every grey in the
     * source finds itself already in the palette and GD's nearest-colour match
     * is the identity.
     */
    private function asGreyPalette(\GdImage $image): ?string
    {
        $flat = imagecreate(imagesx($image), imagesy($image));

        try {
            for ($grey = 0; $grey < self::GREYS; $grey++) {
                imagecolorallocate($flat, $grey, $grey, $grey);
            }

            imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

            return $this->encode($flat);
        } finally {
            imagedestroy($flat);
        }
    }

    private function encode(\GdImage $image): ?string
    {
        ob_start();

        if (! imagepng($image, null, self::LEVEL)) {
            ob_end_clean();

            return null;
        }

        return (string) ob_get_clean();
    }
}
