<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Give a slide's hand-made adjustments a ratio to belong to.
 *
 * `projection_slides.settings_override` was one flat bucket of nudges, in the
 * same vocabulary as `scores.settings` — but a layer below the very thing that
 * column is keyed by. A score's author keeps a layout per projector shape,
 * because 70 points of lyric on a widescreen is a different decision from 70 on
 * a square screen; an override sitting on top of all three at once had to be
 * redone every time a deck changed shape, and silently wrecked the shape it was
 * changed away from.
 *
 * So the bucket gains the level the score's own settings already have: ratio
 * first, then the keys. What was stored is filed under the shape the deck is
 * currently thrown at, which is the shape it was adjusted against — nobody could
 * have tuned a slide at a ratio the deck was not showing.
 */
return new class extends Migration
{
    /** The shapes a bucket may be filed under; anything else is not a slide. */
    private const RATIOS = ['16/9', '4/3', '1/1'];

    public function up(): void
    {
        $this->rewrite(function (array $override, string $ratio): array {
            // Already nested — a deck saved by a client that ran ahead of this.
            if ($this->isNested($override)) {
                return $override;
            }

            return $override === [] ? [] : [$ratio => $override];
        });
    }

    /**
     * Back to one flat bucket, keeping the deck's current shape and dropping the
     * rest. Lossy, and unavoidably so: the column has one place to put them.
     */
    public function down(): void
    {
        $this->rewrite(function (array $override, string $ratio): array {
            if (! $this->isNested($override)) {
                return $override;
            }

            $bucket = $override[$ratio] ?? [];

            return is_array($bucket) ? $bucket : [];
        });
    }

    /**
     * @param  \Closure(array<string, mixed>, string): array<string, mixed>  $rewriter
     */
    private function rewrite(Closure $rewriter): void
    {
        DB::table('projection_slides')
            ->join('projections', 'projections.id', '=', 'projection_slides.projection_id')
            ->whereNotNull('projection_slides.settings_override')
            ->orderBy('projection_slides.id')
            ->select(['projection_slides.id', 'projection_slides.settings_override', 'projections.ratio'])
            ->chunkById(200, function ($rows) use ($rewriter): void {
                foreach ($rows as $row) {
                    $override = json_decode((string) $row->settings_override, true);

                    if (! is_array($override)) {
                        continue;
                    }

                    $ratio = in_array($row->ratio, self::RATIOS, true) ? $row->ratio : self::RATIOS[0];
                    $rewritten = $rewriter($override, $ratio);

                    if ($rewritten === $override) {
                        continue;
                    }

                    DB::table('projection_slides')
                        ->where('id', $row->id)
                        ->update(['settings_override' => $rewritten === [] ? null : json_encode($rewritten)]);
                }
            }, 'projection_slides.id', 'id');
    }

    /**
     * Whether a bucket is already keyed by shape.
     *
     * A setting key is never a ratio and a ratio is never a setting key, so the
     * two are told apart by looking rather than by remembering when the column
     * changed.
     *
     * @param  array<string, mixed>  $override
     */
    private function isNested(array $override): bool
    {
        foreach (array_keys($override) as $key) {
            if (! in_array($key, self::RATIOS, true)) {
                return false;
            }
        }

        return $override !== [];
    }
};
