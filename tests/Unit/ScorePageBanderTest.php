<?php

use App\Services\ScorePageBander;
use App\Services\ScoreStripCutter;

/**
 * Pages are drawn here rather than rendered, so the tests need neither MuseScore
 * nor poppler and the answer is known before it is measured: the staves are put
 * at stated pixel rows, and what comes back is checked against them.
 */

/**
 * A staff: five lines a stated spacing apart, spanning the text block, with
 * barlines at each end and a scatter of noteheads and stems above.
 */
function drawStaff(GdImage $page, int $top, int $left, int $right, float $spacing): void
{
    $black = imagecolorallocate($page, 0, 0, 0);
    $bottom = (int) round($top + 4 * $spacing);

    for ($line = 0; $line < 5; $line++) {
        $y = (int) round($top + $line * $spacing);
        imagefilledrectangle($page, $left, $y, $right, $y + 1, $black);
    }

    imagefilledrectangle($page, $left, $top, $left + 2, $bottom, $black);
    imagefilledrectangle($page, $right - 2, $top, $right, $bottom, $black);

    for ($x = $left + 30; $x < $right - 20; $x += 40) {
        $head = (int) round($top + (($x / 40) % 5) * $spacing / 2);
        imagefilledellipse($page, $x, $head, (int) round($spacing * 1.3), (int) round($spacing), $black);
        imagefilledrectangle($page, $x + 4, (int) round($head - $spacing * 3), $x + 5, $head, $black);
    }
}

/**
 * A line of text: short marks along the baseline, sparse the way lyrics are.
 */
function drawTextLine(GdImage $page, int $top, int $left, int $width, int $height): void
{
    $black = imagecolorallocate($page, 0, 0, 0);

    for ($x = $left; $x < $left + $width; $x += 14) {
        imagefilledrectangle($page, $x, $top, $x + 6, $top + $height, $black);
    }
}

function blankPage(int $width = 2480, int $height = 3508): GdImage
{
    $page = imagecreatetruecolor($width, $height);
    imagefill($page, 0, 0, imagecolorallocate($page, 255, 255, 255));

    return $page;
}

function pngOf(GdImage $page): string
{
    ob_start();
    imagepng($page);
    $bytes = (string) ob_get_clean();
    imagedestroy($page);

    return $bytes;
}

/**
 * The page the rest of this file measures: four systems, each with a lyric line
 * a little way below it, plus a page number at the foot.
 */
function engravedPage(float $spacing = 19.0): string
{
    $page = blankPage();

    foreach ([520, 880, 1350, 1940] as $top) {
        drawStaff($page, $top, 210, 2250, $spacing);
        drawTextLine($page, (int) round($top + 4 * $spacing + 45), 210, 900, 22);
    }

    drawTextLine($page, 3300, 1220, 30, 24);

    return pngOf($page);
}

it('finds one band per system', function () {
    $result = (new ScorePageBander)->analyse(engravedPage());

    expect($result['bands'])->toHaveCount(4);
});

it('recovers the staff line spacing from the page', function () {
    $result = (new ScorePageBander)->analyse(engravedPage(19.0));

    expect($result['spacing'] * $result['height'])->toBeGreaterThan(18.0)
        ->and($result['spacing'] * $result['height'])->toBeLessThan(20.0);
});

it('keeps a lyric line with the staff it belongs to', function () {
    $result = (new ScorePageBander)->analyse(engravedPage());

    // The first system's staff starts at 520 and its lyrics end below 645.
    $first = $result['bands'][0];

    expect($first['top'] * $result['height'])->toBeLessThan(520)
        ->and($first['bottom'] * $result['height'])->toBeGreaterThan(645);
});

it('keeps a braced system whole', function () {
    $page = blankPage();
    $black = imagecolorallocate($page, 0, 0, 0);

    // Two staves joined by a brace and by barlines running through the gap
    // between them, which is what a piano system is.
    drawStaff($page, 900, 210, 2250, 19.0);
    drawStaff($page, 1150, 210, 2250, 19.0);
    imagefilledrectangle($page, 195, 900, 202, 1226, $black);
    imagefilledrectangle($page, 210, 900, 212, 1226, $black);

    drawStaff($page, 1600, 210, 2250, 19.0);

    $result = (new ScorePageBander)->analyse(pngOf($page));

    expect($result['bands'])->toHaveCount(2)
        ->and($result['bands'][0]['top'] * $result['height'])->toBeLessThan(900)
        ->and($result['bands'][0]['bottom'] * $result['height'])->toBeGreaterThan(1226);
});

it('drops the page number', function () {
    $withNumber = (new ScorePageBander)->analyse(engravedPage());

    $page = blankPage();
    foreach ([520, 880, 1350, 1940] as $top) {
        drawStaff($page, $top, 210, 2250, 19.0);
        drawTextLine($page, (int) round($top + 4 * 19.0 + 45), 210, 900, 22);
    }
    $withoutNumber = (new ScorePageBander)->analyse(pngOf($page));

    expect($withNumber['bands'])->toHaveCount(count($withoutNumber['bands']));
});

it('leaves a page whole when it cannot find staff lines', function () {
    $page = blankPage();

    // Prose: no line spans the page, so there is no unit to merge by and
    // nothing to be gained by guessing where the systems are.
    for ($top = 400; $top < 2000; $top += 60) {
        drawTextLine($page, $top, 210, 1800, 24);
    }

    $result = (new ScorePageBander)->analyse(pngOf($page));

    expect($result['spacing'])->toBeNull()
        ->and($result['bands'])->toHaveCount(1);
});

// The whole scheme rests on this: bands are found on the page poppler rendered
// for reading and used to cut the one it rendered for printing. Both are the
// same document at different resolutions, which is what is reproduced here.
it('reports the same bands whatever resolution the page was rendered at', function () {
    $png = engravedPage();

    $dense = imagecreatefromstring($png);
    $half = imagecreatetruecolor((int) (imagesx($dense) / 2), (int) (imagesy($dense) / 2));
    imagefill($half, 0, 0, imagecolorallocate($half, 255, 255, 255));
    imagecopyresampled(
        $half, $dense, 0, 0, 0, 0,
        imagesx($half), imagesy($half), imagesx($dense), imagesy($dense)
    );
    imagedestroy($dense);

    $printing = (new ScorePageBander)->analyse($png);
    $reading = (new ScorePageBander)->analyse(pngOf($half));

    expect($reading['bands'])->toHaveCount(count($printing['bands']));

    foreach ($printing['bands'] as $i => $band) {
        expect(abs($reading['bands'][$i]['top'] - $band['top']))->toBeLessThan(0.005)
            ->and(abs($reading['bands'][$i]['bottom'] - $band['bottom']))->toBeLessThan(0.005);
    }
});

it('trims the margins off the window', function () {
    $result = (new ScorePageBander)->analyse(engravedPage());

    expect($result['left'])->toBeGreaterThan(0.07)
        ->and($result['left'])->toBeLessThan(0.09)
        ->and($result['right'])->toBeGreaterThan(0.90)
        ->and($result['right'])->toBeLessThan(0.92);
});

it('finds nothing on a blank page', function () {
    $result = (new ScorePageBander)->analyse(pngOf(blankPage(600, 800)));

    expect($result['bands'])->toBe([]);
});

it('refuses input that is not an image', function () {
    expect(fn () => (new ScorePageBander)->analyse('not a png'))
        ->toThrow(RuntimeException::class);
});

it('cuts every strip to one width, at the height its band asked for', function () {
    $png = engravedPage();
    $result = (new ScorePageBander)->analyse($png);

    $strips = (new ScoreStripCutter)->cut(
        $png,
        ['left' => $result['left'], 'right' => $result['right']],
        $result['bands'],
    );

    expect($strips)->toHaveCount(4)
        ->and(array_unique(array_column($strips, 'width')))->toHaveCount(1);

    foreach ($strips as $i => $strip) {
        $band = $result['bands'][$i];
        $expected = ($band['bottom'] - $band['top']) * $result['height'];

        expect($strip['height'])->toBeGreaterThan($expected - 2)
            ->and($strip['height'])->toBeLessThan($expected + 2)
            ->and($strip['png'])->toStartWith("\x89PNG");
    }
});

it('cuts a strip that carries ink rather than blank paper', function () {
    $png = engravedPage();
    $result = (new ScorePageBander)->analyse($png);

    $strips = (new ScoreStripCutter)->cut(
        $png,
        ['left' => $result['left'], 'right' => $result['right']],
        $result['bands'],
    );

    $strip = imagecreatefromstring($strips[0]['png']);
    $dark = 0;

    for ($y = 0; $y < imagesy($strip); $y += 3) {
        for ($x = 0; $x < imagesx($strip); $x += 3) {
            if ((imagecolorat($strip, $x, $y) & 0xFF) < 128) {
                $dark++;
            }
        }
    }

    imagedestroy($strip);

    expect($dark)->toBeGreaterThan(100);
});
