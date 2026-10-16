<?php

namespace App\Services;

use App\Enums\LyricLanguage;
use App\Enums\SyllableStress;

/**
 * Divides Hungarian text into syllables and says which of them are stressed.
 *
 * Hungarian stresses the first syllable of a word, and a long word again on
 * its third, fifth … syllable, though never on its last. Articles and a few
 * short particles lean on the next word and have no stress of their own.
 *
 * The rules miss where a compound joins. The compounds of the liturgy are
 * known, and their second part gets a secondary stress; for the rest the text
 * may say it: a `'` before a syllable stresses it (`szent'lélek`), a `·` gives
 * it a secondary stress (`szent·lélek`), and a word divided by hand
 * (`Is-te-nünk`) keeps the division it was given.
 */
class HungarianStress
{
    private const LONG_VOWELS = 'áéíóőúű';

    private const VOWELS = 'aáeéiíoóöőuúüű';

    /** Words sung without a stress of their own. */
    private const UNSTRESSED_WORDS = ['a', 'az', 's', 'és', 'is', 'hogy', 'mint', 'ha', 'de', 'se', 'sem'];

    /** Compounds of the liturgy, matched at the start of a word, with a `·` where the second part begins. */
    private const COMPOUNDS = [
        '/^(szent)(l[eé]l)/iu' => '$1·$2',
    ];

    public function __construct(private LyricHyphenator $hyphenator) {}

    /**
     * @return list<PsalmSyllable>
     */
    public function syllables(string $text): array
    {
        $syllables = [];
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $pendingPrefix = '';

        foreach ($words as $index => $word) {
            if (! preg_match('/^(\P{L}*)(\p{L}.*\p{L}|\p{L})(\P{L}*)$/u', $word, $parts)) {
                if ($syllables === []) {
                    $pendingPrefix .= $word.'~';
                } else {
                    $last = array_pop($syllables);
                    $syllables[] = new PsalmSyllable($last->text.'~'.$word, $last->word, $last->stress, $last->isLong, $last->isMarked);
                }

                continue;
            }

            [, $prefix, $core, $suffix] = $parts;

            if (! preg_match("/['·-]/u", $core)) {
                $core = (string) preg_replace(array_keys(self::COMPOUNDS), array_values(self::COMPOUNDS), $core);
            }

            $wordSyllables = $this->wordSyllables(str_contains($prefix, "'") ? "'".$core : $core, $index);
            $prefix = str_replace(["'", '·'], '', $prefix);
            $suffix = str_replace(["'", '·'], '', $suffix);
            $first = $wordSyllables[0];
            $wordSyllables[0] = new PsalmSyllable($pendingPrefix.$prefix.$first->text, $first->word, $first->stress, $first->isLong, $first->isMarked);
            $last = array_pop($wordSyllables);
            $wordSyllables[] = new PsalmSyllable($last->text.$suffix, $last->word, $last->stress, $last->isLong, $last->isMarked);
            $pendingPrefix = '';

            array_push($syllables, ...$wordSyllables);
        }

        return $syllables;
    }

    /**
     * @return non-empty-list<PsalmSyllable>
     */
    private function wordSyllables(string $word, int $index): array
    {
        $pieces = preg_split("/(['·])/u", $word, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$word];
        $isUnstressedWord = count($pieces) === 1 && in_array(mb_strtolower($word), self::UNSTRESSED_WORDS, true);
        $syllables = [];
        $mark = '';

        foreach ($pieces as $pieceIndex => $piece) {
            if ($piece === "'" || $piece === '·') {
                $mark = $piece;

                continue;
            }

            if ($piece === '') {
                continue;
            }

            $texts = str_contains($piece, '-')
                ? array_values(array_filter(explode('-', $piece), fn (string $text): bool => $text !== ''))
                : $this->hyphenator->syllables($piece, LyricLanguage::Hungarian);

            foreach ($texts as $position => $text) {
                $stress = match (true) {
                    $position === 0 && $mark === "'" => SyllableStress::Primary,
                    $position === 0 && $mark === '·' => SyllableStress::Secondary,
                    $position === 0 && $pieceIndex === 0 => $isUnstressedWord ? SyllableStress::None : SyllableStress::Primary,
                    $position % 2 === 0 && $position < count($texts) - 1 => SyllableStress::Secondary,
                    default => SyllableStress::None,
                };

                $syllables[] = new PsalmSyllable($text, $index, $stress, $this->isLong($text), $position === 0 && $mark === "'");
            }

            $mark = '';
        }

        return $syllables;
    }

    /**
     * A syllable is long when its vowel is long or a consonant closes it.
     */
    private function isLong(string $syllable): bool
    {
        $letters = mb_str_split(mb_strtolower($syllable));

        foreach ($letters as $letter) {
            if (str_contains(self::LONG_VOWELS, $letter)) {
                return true;
            }
        }

        return ! str_contains(self::VOWELS, (string) end($letters));
    }
}
