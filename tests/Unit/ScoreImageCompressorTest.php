<?php

use App\Services\ScoreImageCompressor;

/**
 * A page of engraved music: black staves and noteheads, anti-aliased, on paper.
 */
function greyPage(int $width = 400, int $height = 300): string
{
    $page = imagecreatetruecolor($width, $height);
    imagefill($page, 0, 0, imagecolorallocate($page, 255, 255, 255));
    imageantialias($page, true);

    for ($staff = 0; $staff < 3; $staff++) {
        $top = 40 + $staff * 90;
        for ($line = 0; $line < 5; $line++) {
            imageline($page, 20, $top + $line * 8, $width - 20, $top + $line * 8, imagecolorallocate($page, 0, 0, 0));
        }
        for ($note = 0; $note < 8; $note++) {
            imagefilledellipse($page, 40 + $note * 40, $top + 8 + ($note % 5) * 4, 9, 7, imagecolorallocate($page, 0, 0, 0));
        }
    }

    ob_start();
    imagepng($page, null, 6);
    $png = (string) ob_get_clean();
    imagedestroy($page);

    return $png;
}

/** The same page with one coloured mark on it, as an annotated scan might be. */
function colouredPage(): string
{
    $page = imagecreatefromstring(greyPage());
    imagefilledrectangle($page, 10, 10, 30, 20, imagecolorallocate($page, 200, 30, 30));

    ob_start();
    imagepng($page, null, 6);
    $png = (string) ob_get_clean();
    imagedestroy($page);

    return $png;
}

/**
 * @return list<array{int, int, int, int}>
 */
function pixelsOf(string $png): array
{
    $image = imagecreatefromstring($png);
    $pixels = [];

    for ($y = 0; $y < imagesy($image); $y++) {
        for ($x = 0; $x < imagesx($image); $x++) {
            $colour = imagecolorat($image, $x, $y);

            if (! imageistruecolor($image)) {
                $rgb = imagecolorsforindex($image, $colour);
                $pixels[] = [$rgb['red'], $rgb['green'], $rgb['blue'], $rgb['alpha']];

                continue;
            }

            $pixels[] = [($colour >> 16) & 0xFF, ($colour >> 8) & 0xFF, $colour & 0xFF, ($colour >> 24) & 0x7F];
        }
    }

    imagedestroy($image);

    return $pixels;
}

// The whole point: three bytes a pixel for a page that holds only greys.
test('a page of music is stored a byte a pixel instead of three', function () {
    $original = greyPage();
    $compressed = (new ScoreImageCompressor)->compress($original);

    expect(strlen($compressed))->toBeLessThan(strlen($original));

    $image = imagecreatefromstring($compressed);
    expect(imageistruecolor($image))->toBeFalse();
});

test('not one pixel of it changes', function () {
    $original = greyPage();

    expect(pixelsOf((new ScoreImageCompressor)->compress($original)))->toBe(pixelsOf($original));
});

// A palette cannot hold an arbitrary colour scan, and quantising one silently
// would be a worse bargain than the bytes are worth.
test('a coloured page keeps its colours', function () {
    $original = colouredPage();

    expect(pixelsOf((new ScoreImageCompressor)->compress($original)))->toBe(pixelsOf($original));
});

test('an image already stored as a palette is left alone', function () {
    $flat = imagecreate(60, 40);
    imagecolorallocate($flat, 255, 255, 255);
    imagefilledrectangle($flat, 5, 5, 40, 30, imagecolorallocate($flat, 0, 0, 0));
    ob_start();
    imagepng($flat, null, 9);
    $original = (string) ob_get_clean();
    imagedestroy($flat);

    expect((new ScoreImageCompressor)->compress($original))->toBe($original);
});

// A compression step must never be the reason an artifact goes missing.
test('anything it cannot read is stored exactly as it arrived', function () {
    $compressor = new ScoreImageCompressor;

    expect($compressor->compress('%PDF-1.7 not an image'))->toBe('%PDF-1.7 not an image');
    expect($compressor->compress(''))->toBe('');

    // A file that opens as a PNG and then falls apart. libpng complains through
    // PHP's error handler, which `@` does not reach under PHPUnit, so the
    // complaint is caught here rather than the guard being weakened to avoid it.
    $truncated = substr(greyPage(), 0, 120);
    set_error_handler(fn (): bool => true);

    try {
        expect($compressor->compress($truncated))->toBe($truncated);
    } finally {
        restore_error_handler();
    }
});

test('a transparent image is not flattened onto a palette', function () {
    $image = imagecreatetruecolor(40, 40);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 255, 255, 255, 127));
    imagefilledrectangle($image, 5, 5, 20, 20, imagecolorallocate($image, 0, 0, 0));
    ob_start();
    imagepng($image, null, 6);
    $original = (string) ob_get_clean();
    imagedestroy($image);

    expect(pixelsOf((new ScoreImageCompressor)->compress($original)))->toBe(pixelsOf($original));
});
