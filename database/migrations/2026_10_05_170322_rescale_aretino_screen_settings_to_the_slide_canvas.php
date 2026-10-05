<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Restate every Aretino projector size on the slide canvas the other formats use.
 *
 * Aretino engraved a slide onto a 960 x 540 canvas while ABC, ChordPro and GABC
 * at 16:9 engrave onto 1920 x 1080, so a point of Aretino lyric came out twice
 * the height of a point of anyone else's on the same screen: 80 typed into an
 * Aretino slide was a different size from 80 typed into an ABC one. Aretino now
 * engraves onto the shared canvas, and doubling its two sizes leaves every score
 * looking exactly as its author left it — everything else Aretino draws is a
 * multiple of the staff space or the lyric size.
 *
 * Only the projector ratios are touched; a page was physical already. A slide's
 * override is keyed by ratio and carries only its own format's keys, and Aretino's
 * key names are its own, so they are found by name. A style's ratio is fixed and
 * always a projector's.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const SCREEN_RATIOS = ['16/9', '4/3', '1/1'];

    /** @var list<string> */
    private const LENGTHS = ['aretinoLyricSize', 'aretinoStaffSize'];

    public function up(): void
    {
        $this->rescale(2.0);
    }

    public function down(): void
    {
        $this->rescale(0.5);
    }

    private function rescale(float $factor): void
    {
        $scoreBuckets = function (array $settings) use ($factor): array {
            foreach (self::SCREEN_RATIOS as $ratio) {
                if (is_array($settings['aretino'][$ratio] ?? null)) {
                    $settings['aretino'][$ratio] = $this->rescaleBucket($settings['aretino'][$ratio], $factor);
                }
            }

            return $settings;
        };

        $this->rewriteColumn('scores', 'settings', $scoreBuckets);
        $this->rewriteColumn('score_versions', 'settings', $scoreBuckets);
        $this->rewriteColumn('users', 'score_settings', $scoreBuckets);

        $this->rewriteColumn('projection_styles', 'settings', function (array $settings) use ($factor): array {
            if (is_array($settings['aretino'] ?? null)) {
                $settings['aretino'] = $this->rescaleBucket($settings['aretino'], $factor);
            }

            return $settings;
        });

        $this->rewriteColumn('projection_slides', 'settings_override', function (array $override) use ($factor): array {
            foreach (self::SCREEN_RATIOS as $ratio) {
                if (is_array($override[$ratio] ?? null)) {
                    $override[$ratio] = $this->rescaleBucket($override[$ratio], $factor);
                }
            }

            return $override;
        });
    }

    /**
     * @param  Closure(array<string, mixed>): array<string, mixed>  $rewriter
     */
    private function rewriteColumn(string $table, string $column, Closure $rewriter): void
    {
        DB::table($table)
            ->whereNotNull($column)
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($table, $column, $rewriter): void {
                foreach ($rows as $row) {
                    $settings = json_decode((string) $row->{$column}, true);

                    if (! is_array($settings)) {
                        continue;
                    }

                    $rewritten = $rewriter($settings);

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
