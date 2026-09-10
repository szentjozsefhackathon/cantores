<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Move every score and booklet still set in Lora onto Merriweather.
 *
 * Lora was the modern serif of the set until Merriweather replaced it, and has
 * been out of the picker since — but a retired face is not a gone face. It was
 * still in the validator, still in the stylesheet, still eight woff2 files and a
 * TTF, and still what seventeen scores were engraved in. Carrying a whole face
 * for scores nobody can choose it for again is the cost this pays off.
 *
 * The swap is not a rename. A point is a measure of the em square rather than of
 * anything the eye can see, and Lora's letters stand at half an em where
 * Merriweather's stand at 0.555 — so the same stored size reads a ninth larger
 * in the new face, which on a tuned ABC page is a line break. Every size beside
 * a swapped face is therefore restated in it, by the ratio of the two x-heights,
 * exactly as opticalSizeFactor() in booklet-geometry.js does at render time.
 * A score comes out looking as its author left it.
 *
 * A booklet's own `text_font` needs no such restatement: its `lyric_size_pt` is
 * quoted in the reference face and converted per face as it is drawn, which is
 * the whole point of pageGeometry() doing it there.
 */
return new class extends Migration
{
    /**
     * Where each engine keeps the face it sets lyrics in, and the size beside it
     * that has to be restated when that face changes.
     *
     * @var array<string, string>
     */
    private const SIZE_BESIDE_FONT = [
        'lyricFont' => 'lyricSize',
        'abcLyricFont' => 'abcLyricSize',
        'chordproFontFamily' => 'chordproFontSize',
        'aretinoTextFont' => 'aretinoLyricSize',
    ];

    /**
     * x-height per em, from OPTICAL_X_HEIGHT in resources/js/booklet-geometry.js.
     * Held constant across the swap, which is what keeps the type the same
     * apparent size in the new face.
     */
    private const LORA_X_HEIGHT = 0.500;

    private const MERRIWEATHER_X_HEIGHT = 0.555;

    /**
     * Every column a face can be stored in. The first three are score settings
     * — a score, its versions, and the defaults a user saved for new ones — and
     * the fourth is a booklet's per-score nudge, which is one flat bucket rather
     * than a tree of them. The walk below handles either shape.
     *
     * @var array<string, string>
     */
    private const SETTINGS_COLUMNS = [
        'scores' => 'settings',
        'score_versions' => 'settings',
        'users' => 'score_settings',
        'booklet_scores' => 'settings_override',
    ];

    public function up(): void
    {
        foreach (self::SETTINGS_COLUMNS as $table => $column) {
            $this->swapColumn($table, $column);
        }

        DB::table('booklets')->where('text_font', 'Lora')->update(['text_font' => 'Merriweather']);
    }

    /**
     * Deliberately empty. Which of the Merriweather scores were Lora yesterday
     * is not written down anywhere after this runs, and guessing would put the
     * retired face back on scores that never wore it.
     */
    public function down(): void {}

    private function swapColumn(string $table, string $column): void
    {
        DB::table($table)
            ->whereNotNull($column)
            ->where($column, 'like', '%Lora%')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($table, $column): void {
                foreach ($rows as $row) {
                    $settings = json_decode((string) $row->{$column}, true);

                    if (! is_array($settings)) {
                        continue;
                    }

                    $swapped = $this->swap($settings);

                    if ($swapped === $settings) {
                        continue;
                    }

                    DB::table($table)->where('id', $row->id)->update([$column => json_encode($swapped)]);
                }
            });
    }

    /**
     * Swap the face wherever it appears, at whatever depth.
     *
     * Walked rather than addressed by path because the same four keys live at
     * three different depths: a score keeps them under a format and a ratio, a
     * booklet's override keeps them loose, and a projector bucket is a sibling
     * of the paper one and just as able to name a face. A walk finds all three
     * without a list of shapes to keep current.
     *
     * @param  array<mixed>  $settings
     * @return array<mixed>
     */
    private function swap(array $settings): array
    {
        foreach ($settings as $key => $value) {
            if (is_array($value)) {
                $settings[$key] = $this->swap($value);

                continue;
            }

            if (! is_string($value) || ! isset(self::SIZE_BESIDE_FONT[$key])) {
                continue;
            }

            $quote = $value !== '' && ($value[0] === "'" || $value[0] === '"') ? $value[0] : '';

            if (trim($value, " \t\n\r\0\x0B'\"") !== 'Lora') {
                continue;
            }

            // Written back in the quoting it arrived in: abc2svg reads the face
            // out of a %%vocalfont directive, where a quote is part of the name,
            // and the other three editors' selects emit it quoted.
            $settings[$key] = $quote.'Merriweather'.$quote;

            $sizeKey = self::SIZE_BESIDE_FONT[$key];

            if (is_numeric($settings[$sizeKey] ?? null)) {
                $settings[$sizeKey] = round(
                    (float) $settings[$sizeKey] * self::LORA_X_HEIGHT / self::MERRIWEATHER_X_HEIGHT,
                    4,
                );
            }
        }

        return $settings;
    }
};
