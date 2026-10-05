<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Restate every GABC 4:3 and 1:1 size on the slide canvas the other formats use.
 *
 * GABC engraved every projector ratio 1920 units wide, and a narrower screen
 * came out taller instead — 1920 x 1440 for 4:3, 1920 x 1920 for 1:1 — while
 * every other format draws 4:3 on 1440 x 1080 and 1:1 on 1080 x 1080. Shown on
 * the same screen, a point of GABC lyric was three quarters of a point of ABC's
 * at 4:3 and not much more than half at 1:1. GABC now engraves onto the shared
 * canvas, and its lengths are scaled by the ratio of the two widths, which
 * leaves every score looking exactly as its author left it. 16:9 was the same
 * canvas already.
 *
 * The lengths are the ones the paper rescale named; everything else GABC stores
 * is a ratio. A slide's override carries only its own format's keys, and GABC's
 * `lyricSize` and `staffSize` are its own names. A style's ratio is fixed, so
 * its whole GABC bucket is scaled by that ratio's factor.
 */
return new class extends Migration
{
    /** @var array<string, float> */
    private const FACTORS = ['4/3' => 1440 / 1920, '1/1' => 1080 / 1920];

    /** @var list<string> */
    private const LENGTHS = ['lyricSize', 'staffSize', 'minLyricWordSpacing', 'hyphenWidth'];

    public function up(): void
    {
        $this->rescale(false);
    }

    public function down(): void
    {
        $this->rescale(true);
    }

    private function rescale(bool $inverse): void
    {
        $factor = fn (string $ratio): float => $inverse ? 1 / self::FACTORS[$ratio] : self::FACTORS[$ratio];

        $scoreBuckets = function (array $settings) use ($factor): array {
            foreach (array_keys(self::FACTORS) as $ratio) {
                if (is_array($settings['gabc'][$ratio] ?? null)) {
                    $settings['gabc'][$ratio] = $this->rescaleBucket($settings['gabc'][$ratio], $factor($ratio));
                }
            }

            return $settings;
        };

        $this->rewriteColumn('scores', 'settings', $scoreBuckets);
        $this->rewriteColumn('score_versions', 'settings', $scoreBuckets);
        $this->rewriteColumn('users', 'score_settings', $scoreBuckets);

        $this->rewriteColumn('projection_styles', 'settings', function (array $settings, object $row) use ($factor): array {
            if (is_array($settings['gabc'] ?? null) && isset(self::FACTORS[$row->ratio])) {
                $settings['gabc'] = $this->rescaleBucket($settings['gabc'], $factor($row->ratio));
            }

            return $settings;
        }, ['ratio']);

        $this->rewriteColumn('projection_slides', 'settings_override', function (array $override) use ($factor): array {
            foreach (array_keys(self::FACTORS) as $ratio) {
                if (is_array($override[$ratio] ?? null)) {
                    $override[$ratio] = $this->rescaleBucket($override[$ratio], $factor($ratio));
                }
            }

            return $override;
        });
    }

    /**
     * @param  Closure(array<string, mixed>, object): array<string, mixed>  $rewriter
     * @param  list<string>  $extraColumns
     */
    private function rewriteColumn(string $table, string $column, Closure $rewriter, array $extraColumns = []): void
    {
        DB::table($table)
            ->whereNotNull($column)
            ->select(['id', $column, ...$extraColumns])
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($table, $column, $rewriter): void {
                foreach ($rows as $row) {
                    $settings = json_decode((string) $row->{$column}, true);

                    if (! is_array($settings)) {
                        continue;
                    }

                    $rewritten = $rewriter($settings, $row);

                    if ($rewritten === $settings) {
                        continue;
                    }

                    DB::table($table)->where('id', $row->id)->update([$column => json_encode($rewritten)]);
                }
            });
    }

    /**
     * @param  array<string, mixed>  $bucket
     * @return array<string, mixed>
     */
    private function rescaleBucket(array $bucket, float $factor): array
    {
        foreach (self::LENGTHS as $key) {
            if (is_numeric($bucket[$key] ?? null)) {
                $bucket[$key] = round((float) $bucket[$key] * $factor, 4);
            }
        }

        return $bucket;
    }
};
