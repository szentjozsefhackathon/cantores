<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Restate every saved GABC and ABC setting in millimetres of real paper.
 *
 * Those two editors used to engrave on a nominal canvas — 1920 user units wide
 * for GABC, 1700 for ABC — while Aretino always laid out on a page measured in
 * millimetres. Since a score's user unit is a CSS pixel at 96 dpi wherever it
 * leaves the browser, those canvases were a 508 mm and a 450 mm sheet: the
 * exports came out on paper nobody owns, and a staff size or a lyric size in one
 * editor was no particular height in another, which is what made a booklet's
 * unification the only place the formats could be compared at all.
 *
 * Both are now the same 170 mm page the Aretino editor uses. Multiplying every
 * length in a saved bucket by the ratio of the new page to the old leaves each
 * score looking exactly as its author left it — same proportions, same line
 * breaks — while the numbers underneath finally mean something: six millimetres
 * of staff is six millimetres in all four formats, and 100 % zoom is life size.
 *
 * Only the paper bucket is touched. The projector ratios keep engraving on their
 * 1920 x 1080 screen, which is a screen and not a sheet of paper, and ChordPro
 * and Aretino were physical already.
 */
return new class extends Migration
{
    /** The page every editor now lays out on, in user units, and the two it replaces. */
    private const PAGE_WIDTH_PX = 643;

    private const GABC_CANVAS_PX = 1920;

    private const ABC_CANVAS_PX = 1700;

    /**
     * The buckets a paper layout was ever stored under. `auto` is what `paper`
     * was called before the projector ratios arrived, and `responsive` has always
     * shared the paper bucket rather than keeping one of its own.
     *
     * @var list<string>
     */
    private const PAPER_RATIOS = ['paper', 'auto'];

    /**
     * Lengths in exsurge's own units, all of which scale with the page.
     * Everything else GABC stores is a ratio and stays where it is.
     *
     * @var list<string>
     */
    private const GABC_LENGTHS = ['lyricSize', 'staffSize', 'minLyricWordSpacing', 'hyphenWidth'];

    /**
     * ABC's lengths. `abcStaffSep` is absent on purpose: abc2svg multiplies it by
     * the page scale, so it has already shrunk with the rest of the drawing.
     *
     * @var list<string>
     */
    private const ABC_LENGTHS = ['abcLyricSize', 'abcPageScale'];

    public function up(): void
    {
        $this->rescale(self::PAGE_WIDTH_PX / self::GABC_CANVAS_PX, self::PAGE_WIDTH_PX / self::ABC_CANVAS_PX);
    }

    public function down(): void
    {
        $this->rescale(self::GABC_CANVAS_PX / self::PAGE_WIDTH_PX, self::ABC_CANVAS_PX / self::PAGE_WIDTH_PX);
    }

    private function rescale(float $gabcFactor, float $abcFactor): void
    {
        $this->rescaleColumn('scores', 'settings', $gabcFactor, $abcFactor);
        $this->rescaleColumn('score_versions', 'settings', $gabcFactor, $abcFactor);
        $this->rescaleColumn('users', 'score_settings', $gabcFactor, $abcFactor);
    }

    private function rescaleColumn(string $table, string $column, float $gabcFactor, float $abcFactor): void
    {
        DB::table($table)
            ->whereNotNull($column)
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($table, $column, $gabcFactor, $abcFactor): void {
                foreach ($rows as $row) {
                    $settings = json_decode((string) $row->{$column}, true);

                    if (! is_array($settings)) {
                        continue;
                    }

                    $rescaled = $this->rescaleSettings($settings, $gabcFactor, $abcFactor);

                    if ($rescaled === $settings) {
                        continue;
                    }

                    DB::table($table)->where('id', $row->id)->update([$column => json_encode($rescaled)]);
                }
            });
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function rescaleSettings(array $settings, float $gabcFactor, float $abcFactor): array
    {
        foreach ([['gabc', self::GABC_LENGTHS, $gabcFactor], ['abc', self::ABC_LENGTHS, $abcFactor]] as [$format, $keys, $factor]) {
            if (! is_array($settings[$format] ?? null)) {
                continue;
            }

            foreach (self::PAPER_RATIOS as $ratio) {
                if (! is_array($settings[$format][$ratio] ?? null)) {
                    continue;
                }

                foreach ($keys as $key) {
                    if (is_numeric($settings[$format][$ratio][$key] ?? null)) {
                        $settings[$format][$ratio][$key] = round((float) $settings[$format][$ratio][$key] * $factor, 4);
                    }
                }

                // The width is the page itself rather than something drawn on
                // it, and is a whole number of units by convention.
                if ($format === 'abc' && is_numeric($settings[$format][$ratio]['abcPageWidth'] ?? null)) {
                    $settings[$format][$ratio]['abcPageWidth'] = (int) round((float) $settings[$format][$ratio]['abcPageWidth'] * $factor);
                }
            }
        }

        return $settings;
    }
};
