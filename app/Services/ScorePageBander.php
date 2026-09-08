<?php

namespace App\Services;

use RuntimeException;

/**
 * Finds the systems on a rendered page.
 *
 * A MuseScore export is usually three or four systems on a mostly empty page,
 * and dropping that page into a booklet whole wastes most of a sheet. Cut into
 * systems, the same music flows like every other format: a system is a block
 * with a height, so it shares a page with an antiphon without either knowing
 * about the other.
 *
 * The method is a horizontal projection profile — count ink per pixel row, and
 * read off the runs. Two things make that work better here than it sounds. A
 * braced system protects itself, because the brace and the barlines run through
 * the rows between its staves, so those rows are never blank and the staves are
 * never parted. And the page states its own unit of measurement: the five staff
 * lines are the darkest thing on it, so their spacing can be recovered and
 * everything else expressed as a multiple of it, whatever the dpi or the
 * engraver's staff size.
 *
 * That unit is what decides the merges. The gaps on a page are three-modal —
 * within a system, from a staff down to its lyrics, and from one system to the
 * next — and no two-class split of the distribution lands reliably between the
 * second and the third. One staff height does, at any scale.
 *
 * Everything comes back as fractions of the page rather than pixels, so a page
 * analysed at reading resolution can be cut at printing resolution.
 */
class ScorePageBander
{
    /** Sampling windows across the width. Ink is localised, so a narrow window keeps a page number visible. */
    private const COLUMNS = 64;

    /**
     * How much ink one sampling window must hold before it counts as ink at all,
     * measured in fully dark pixels of the page as rendered.
     *
     * A window is an area average, so a thin stroke is diluted by however much of
     * the page the window spans: at 64 columns across a 1240 pixel page, a note
     * stem or a ledger line is one dark pixel in nineteen and averages to 0.05.
     * A threshold stated as a fixed share of the window would therefore mean
     * something different at every page size — and at the sizes poppler actually
     * renders, it read the rows below a staff that carry only stems as blank
     * paper and cut the notes hanging off the bottom of a system away. Stated in
     * pixels and divided by the window's own size, it means the same thing at
     * every resolution. Half a pixel, so that a stroke smeared across two by
     * anti-aliasing still counts as the one pixel of ink it is.
     */
    private const WINDOW_INK_PIXELS = 0.5;

    /** How dark a whole row must be, averaged, to be a staff line. */
    private const STAFF_ROW_INK = 0.4;

    /** Runs closer than this many staff heights belong to the same system. */
    private const MERGE_STAFF_HEIGHTS = 1.0;

    /** A staff is four spaces tall. */
    private const STAFF_HEIGHT_IN_SPACES = 4;

    /** Below this many staff-line rows, the page is not trusted to be engraved music. */
    private const MIN_STAFF_ROWS = 8;

    /** How far a spacing may sit from the median and still count as agreeing with it. */
    private const SPACING_TOLERANCE = 0.25;

    /** The share of spacings that must agree before the median is believed. */
    private const SPACING_CONSENSUS = 0.6;

    /** Breathing room around a cut, so anti-aliased edges are not shaved. */
    private const PAD_IN_SPACES = 0.35;

    /** A band shorter than this share of the page is dust. */
    private const MIN_BAND_HEIGHT = 0.003;

    /** Where a running foot lives, as a share of the page measured from the bottom. */
    private const FOOT_ZONE = 0.08;

    /** A running foot is no taller than this many staff heights. */
    private const FOOT_MAX_STAFF_HEIGHTS = 1.5;

    /**
     * Read one rendered page.
     *
     * @return array{
     *     width: int,
     *     height: int,
     *     left: float,
     *     right: float,
     *     spacing: float|null,
     *     bands: list<array{top: float, bottom: float}>
     * } the window and the bands, as fractions of the page
     */
    public function analyse(string $pagePng): array
    {
        if (! str_starts_with($pagePng, "\x89PNG")) {
            throw new RuntimeException('Band detection input is not a PNG.');
        }

        $page = @imagecreatefromstring($pagePng);

        if ($page === false) {
            throw new RuntimeException('Could not read the rendered page image.');
        }

        try {
            $width = imagesx($page);
            $height = imagesy($page);

            $rows = $this->rowProfile($page, $height);
            $spacing = $this->staffSpacing($rows);
            [$left, $right] = $this->inkColumns($page, $width, $height);

            $bands = $this->bandsOf($rows, $height, $spacing, $this->inkThreshold($width / self::COLUMNS));

            return [
                'width' => $width,
                'height' => $height,
                'left' => $left / $width,
                'right' => ($right + 1) / $width,
                'spacing' => $spacing === null ? null : $spacing / $height,
                'bands' => array_map(fn (array $band): array => [
                    'top' => $band[0] / $height,
                    'bottom' => ($band[1] + 1) / $height,
                ], $bands),
            ];
        } finally {
            imagedestroy($page);
        }
    }

    /**
     * The page squeezed to a few columns, full height.
     *
     * Nothing is scaled vertically, so every row keeps its own identity; the
     * horizontal squeeze is what makes the scan affordable in PHP. Each window
     * is reported twice: the darkest one in a row says whether there is ink
     * anywhere, and the average over the row says whether it is a staff line.
     *
     * @return list<array{max: float, mean: float}>
     */
    private function rowProfile(\GdImage $page, int $height): array
    {
        $strip = $this->squeeze($page, self::COLUMNS, $height);

        try {
            $rows = [];

            for ($y = 0; $y < $height; $y++) {
                $max = 0.0;
                $sum = 0.0;

                for ($x = 0; $x < self::COLUMNS; $x++) {
                    $ink = $this->inkAt($strip, $x, $y);
                    $max = max($max, $ink);
                    $sum += $ink;
                }

                $rows[] = ['max' => $max, 'mean' => $sum / self::COLUMNS];
            }

            return $rows;
        } finally {
            imagedestroy($strip);
        }
    }

    /**
     * The leftmost and rightmost columns carrying ink.
     *
     * Squeezed the other way — full width, a few rows — because only the
     * horizontal extent is wanted and the vertical detail is not.
     *
     * @return array{int, int}
     */
    private function inkColumns(\GdImage $page, int $width, int $height): array
    {
        $strip = $this->squeeze($page, $width, min(self::COLUMNS, $height));

        try {
            $rows = imagesy($strip);
            $threshold = $this->inkThreshold($height / $rows);
            $left = null;
            $right = null;

            for ($x = 0; $x < $width; $x++) {
                $max = 0.0;
                for ($y = 0; $y < $rows; $y++) {
                    $max = max($max, $this->inkAt($strip, $x, $y));
                }

                if ($max >= $threshold) {
                    $left ??= $x;
                    $right = $x;
                }
            }

            return [$left ?? 0, $right ?? $width - 1];
        } finally {
            imagedestroy($strip);
        }
    }

    /**
     * The staff line spacing in pixels, or null when the page does not read as
     * engraved music.
     *
     * A staff line is the only thing on a page that darkens a whole row, so the
     * rows above the threshold are the lines themselves. Their spacing is taken
     * as the median of the small gaps between them — the large gaps are the ones
     * between staves, and the median steps over them — and it is only believed
     * when most of the small gaps agree with it. A skewed scan smears its lines
     * across many rows and produces no consensus, which is the signal to leave
     * the page whole rather than to invent systems in it.
     *
     * @param  list<array{max: float, mean: float}>  $rows
     */
    private function staffSpacing(array $rows): ?float
    {
        $height = count($rows);
        $centres = [];

        foreach ($this->runsOf($rows, fn (array $row): bool => $row['mean'] >= self::STAFF_ROW_INK) as $run) {
            $centres[] = ($run[0] + $run[1]) / 2;
        }

        if (count($centres) < self::MIN_STAFF_ROWS) {
            return null;
        }

        $deltas = [];
        for ($i = 1; $i < count($centres); $i++) {
            $delta = $centres[$i] - $centres[$i - 1];

            // Anything wider than a staff is the space between two staves, not
            // a space inside one.
            if ($delta > 0 && $delta <= $height / 20) {
                $deltas[] = $delta;
            }
        }

        if ($deltas === []) {
            return null;
        }

        sort($deltas);
        $median = $deltas[intdiv(count($deltas), 2)];

        $agreeing = array_filter(
            $deltas,
            fn (float $delta): bool => abs($delta - $median) <= $median * self::SPACING_TOLERANCE
        );

        if (count($agreeing) < count($deltas) * self::SPACING_CONSENSUS) {
            return null;
        }

        return array_sum($agreeing) / count($agreeing);
    }

    /**
     * The bands, in pixel rows.
     *
     * With no staff spacing there is nothing to merge by, so the page is left
     * whole: better one honest page than a scan chopped at arbitrary places.
     *
     * @param  list<array{max: float, mean: float}>  $rows
     * @return list<array{int, int}>
     */
    private function bandsOf(array $rows, int $height, ?float $spacing, float $threshold): array
    {
        $runs = $this->runsOf($rows, fn (array $row): bool => $row['max'] >= $threshold);

        if ($runs === []) {
            return [];
        }

        if ($spacing === null) {
            return [[$runs[0][0], end($runs)[1]]];
        }

        $merged = $this->merge($runs, $spacing * self::STAFF_HEIGHT_IN_SPACES * self::MERGE_STAFF_HEIGHTS);
        $padded = $this->pad($merged, $spacing * self::PAD_IN_SPACES, $height);

        return $this->withoutRunningFoot($padded, $height, $spacing);
    }

    /**
     * @param  list<array{int, int}>  $runs
     * @return list<array{int, int}>
     */
    private function merge(array $runs, float $distance): array
    {
        $bands = [];

        foreach ($runs as $run) {
            $last = $bands === [] ? null : count($bands) - 1;

            if ($last !== null && ($run[0] - $bands[$last][1] - 1) <= $distance) {
                $bands[$last][1] = $run[1];

                continue;
            }

            $bands[] = $run;
        }

        return $bands;
    }

    /**
     * @param  list<array{int, int}>  $bands
     * @return list<array{int, int}>
     */
    private function pad(array $bands, float $padding, int $height): array
    {
        $pad = (int) round($padding);

        return array_values(array_filter(
            array_map(fn (array $band): array => [
                max(0, $band[0] - $pad),
                min($height - 1, $band[1] + $pad),
            ], $bands),
            fn (array $band): bool => ($band[1] - $band[0] + 1) >= $height * self::MIN_BAND_HEIGHT
        ));
    }

    /**
     * Drop the page number.
     *
     * A booklet numbers its own pages, so the source's numbering is noise in it
     * — and unlike a title, which is at worst redundant, a stray "7" between two
     * hymns is actively wrong. Only the bottom of the page is examined, and only
     * for something too short to be music.
     *
     * @param  list<array{int, int}>  $bands
     * @return list<array{int, int}>
     */
    private function withoutRunningFoot(array $bands, int $height, float $spacing): array
    {
        $zone = $height * (1 - self::FOOT_ZONE);
        $tallest = $spacing * self::STAFF_HEIGHT_IN_SPACES * self::FOOT_MAX_STAFF_HEIGHTS;

        return array_values(array_filter(
            $bands,
            fn (array $band): bool => ! ($band[0] >= $zone && ($band[1] - $band[0] + 1) <= $tallest)
        ));
    }

    /**
     * Maximal runs of consecutive rows the predicate accepts.
     *
     * @param  list<array{max: float, mean: float}>  $rows
     * @param  callable(array{max: float, mean: float}): bool  $accepts
     * @return list<array{int, int}>
     */
    private function runsOf(array $rows, callable $accepts): array
    {
        $runs = [];
        $start = null;

        foreach ($rows as $y => $row) {
            if ($accepts($row)) {
                $start ??= $y;

                continue;
            }

            if ($start !== null) {
                $runs[] = [$start, $y - 1];
                $start = null;
            }
        }

        if ($start !== null) {
            $runs[] = [$start, count($rows) - 1];
        }

        return $runs;
    }

    /**
     * The page resampled to a smaller grid, area-averaged.
     */
    private function squeeze(\GdImage $page, int $width, int $height): \GdImage
    {
        $strip = imagecreatetruecolor($width, $height);
        imagefill($strip, 0, 0, imagecolorallocate($strip, 255, 255, 255));
        imagecopyresampled($strip, $page, 0, 0, 0, 0, $width, $height, imagesx($page), imagesy($page));

        return $strip;
    }

    /**
     * The ink level at which a sampling window holds WINDOW_INK_PIXELS of ink,
     * given how many pixels of the original page that window spans.
     *
     * A window narrower than a pixel is one the page was stretched into rather
     * than squeezed, and it dilutes nothing, so it is read as a whole pixel.
     */
    private function inkThreshold(float $windowPixels): float
    {
        return self::WINDOW_INK_PIXELS / max(1.0, $windowPixels);
    }

    /**
     * How dark one pixel is, from 0 (paper) to 1 (ink).
     */
    private function inkAt(\GdImage $image, int $x, int $y): float
    {
        $colour = imagecolorat($image, $x, $y);

        $luminance = 0.299 * (($colour >> 16) & 0xFF)
            + 0.587 * (($colour >> 8) & 0xFF)
            + 0.114 * ($colour & 0xFF);

        return 1 - $luminance / 255;
    }
}
