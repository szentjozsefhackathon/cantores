<?php

namespace App\Support;

/**
 * Normalises song titles and measures how similar two titles are.
 *
 * Titles for the same song vary wildly between songbooks (a verse's first line,
 * the chorus' first line, the official title), so a similarity score below the
 * import threshold is treated as "probably different songs" and surfaced for a
 * manual merge/separate decision rather than merged automatically.
 */
class TitleSimilarity
{
    /**
     * Lowercase accented Hungarian characters mapped to their ASCII base.
     *
     * @var array<string, string>
     */
    private const ACCENTS = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ö' => 'o',
        'ő' => 'o', 'ú' => 'u', 'ü' => 'u', 'ű' => 'u',
    ];

    /**
     * Reduce a title to a comparable form: lowercase, parenthetical sections
     * removed, accents folded to ASCII, punctuation dropped, whitespace collapsed.
     */
    public static function normalize(string $title): string
    {
        $value = mb_strtolower($title, 'UTF-8');
        $value = preg_replace(['/\([^)]*\)/u', '/\[[^\]]*\]/u'], ' ', $value);
        $value = strtr($value, self::ACCENTS);
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value);

        return trim(preg_replace('/\s+/', ' ', $value));
    }

    /**
     * Similarity ratio between two titles, from 0.0 (nothing in common) to 1.0
     * (identical after normalisation).
     */
    public static function ratio(string $a, string $b): float
    {
        $a = self::normalize($a);
        $b = self::normalize($b);

        if ($a === '' || $b === '') {
            return 0.0;
        }
        if ($a === $b) {
            return 1.0;
        }

        similar_text($a, $b, $percent);

        return round($percent / 100, 4);
    }
}
