<?php

namespace App\Services;

use App\Enums\LyricLanguage;
use App\Enums\ScoreFormat;

/**
 * Splits lyrics into the syllables a score sets under its notes.
 *
 * The breaks come from the language's Hunspell hyphenation dictionary, which
 * knows where Hungarian compounds join (`meg-ál-dott`, not `me-gál-dott`) and
 * how Solesmes divides Latin. Those patterns are written for typesetting,
 * though, and the Hungarian ones never leave a single letter at either end of
 * a word; a singer needs `ö-röm` and `Má-ri-a`, so a Hungarian piece the
 * dictionary left whole is divided once more by the plain rule of the
 * language: one vowel to a syllable, and the last of the consonants between
 * two vowels starts the next one.
 *
 * ABC and Aretino lyrics take `-` between syllables; GABC gives every syllable
 * its own note group, so a word comes back as `Ky()ri()e()` for the notes to be
 * written into, keeping any group the text already had.
 */
class LyricHyphenator
{
    private const HUNGARIAN_VOWELS = 'aáeéiíoóöőuúüű';

    /** Consonants written with more than one letter, longest first. */
    private const HUNGARIAN_DIGRAPHS = ['dzs', 'cs', 'dz', 'gy', 'ly', 'ny', 'sz', 'ty', 'zs'];

    /** @var array<string, HyphenationDictionary> */
    private static array $dictionaries = [];

    public function hyphenate(string $text, LyricLanguage $language, ScoreFormat $format): string
    {
        return match ($format) {
            ScoreFormat::Abc, ScoreFormat::Aretino => $this->hyphenateLyricLines($text, $language),
            ScoreFormat::Gabc => $this->hyphenateGabc($text, $language),
            ScoreFormat::ChordPro => $text,
        };
    }

    /**
     * The syllables of one word, in the word's own capitals.
     *
     * @return list<string>
     */
    public function syllables(string $word, LyricLanguage $language): array
    {
        $lowerCase = mb_strtolower($word);
        $syllables = $this->dictionary($language)->syllables($lowerCase);

        if ($language === LyricLanguage::Hungarian) {
            $syllables = array_merge(...array_map($this->hungarianSyllables(...), $syllables));
        }

        return $this->restoreCase($word, $lowerCase, $syllables);
    }

    /**
     * Hyphenates the `w:` lines of the text, or the whole text if it has none,
     * which is a selection made inside a lyric line or lyrics about to become one.
     */
    private function hyphenateLyricLines(string $text, LyricLanguage $language): string
    {
        $lines = preg_split('/(\R)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
        $hasLyricLines = preg_grep('/^\s*w:/u', $lines) !== [];

        foreach ($lines as $index => $line) {
            if (! $hasLyricLines) {
                $lines[$index] = $this->hyphenateWords($line, $language, '-');
            } elseif (preg_match('/^(\s*w:)(.*)$/su', $line, $parts)) {
                $lines[$index] = $parts[1].$this->hyphenateWords($parts[2], $language, '-');
            }
        }

        return implode('', $lines);
    }

    /**
     * Hyphenates every word between spaces, the letters only: punctuation and
     * the lyric marks around them stay where they were. A word with an escape or
     * a `~` in it is several words sung as one syllable, and is left alone.
     */
    private function hyphenateWords(string $text, LyricLanguage $language, string $separator): string
    {
        return (string) preg_replace_callback('/\S+/u', function (array $chunk) use ($language, $separator): string {
            if (preg_match('/[\\\\~]/u', $chunk[0])) {
                return $chunk[0];
            }

            return $this->hyphenateLetters($chunk[0], $language, $separator);
        }, $text);
    }

    private function hyphenateLetters(string $text, LyricLanguage $language, string $separator): string
    {
        return (string) preg_replace_callback(
            '/[\p{L}\p{M}]+/u',
            fn (array $word): string => implode($separator, $this->syllables($word[0], $language)),
            $text,
        );
    }

    /**
     * Gives every syllable of the text a note group of its own.
     *
     * The text inside groups, tags and brackets is not lyrics and is kept as it
     * is, and a word that already has its notes keeps them on its last syllable.
     * In a whole file only the part after the `%%` header separator is touched.
     */
    private function hyphenateGabc(string $text, LyricLanguage $language): string
    {
        $header = '';

        if (preg_match('/\A(.*^%%[ \t]*\R)/msu', $text, $match)) {
            $header = $match[1];
            $text = substr($text, strlen($header));
        }

        $tokens = preg_split('/(\([^()]*\)|<[^<>]*>|\[[^\[\]]*\]|[{}]|\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $groupAfter = [];

        foreach ($tokens as $index => $token) {
            if (self::isGabcMarkup($token) || ! preg_match('/[\p{L}\p{M}]/u', $token)) {
                continue;
            }

            $tokens[$index] = $this->hyphenateLetters($token, $language, '()');

            $next = $index + 1;
            while (isset($tokens[$next]) && self::isGabcTag($tokens[$next])) {
                $next++;
            }

            if (! isset($tokens[$next]) || ! str_starts_with($tokens[$next], '(')) {
                $groupAfter[$next - 1] = true;
            }
        }

        foreach (array_keys($groupAfter) as $index) {
            $tokens[$index] .= '()';
        }

        return $header.implode('', $tokens);
    }

    private static function isGabcMarkup(string $token): bool
    {
        return self::isGabcTag($token)
            || str_starts_with($token, '(')
            || str_starts_with($token, '[')
            || trim($token) === '';
    }

    /** A style tag or a centring brace, which sits inside a syllable's text. */
    private static function isGabcTag(string $token): bool
    {
        return str_starts_with($token, '<') || $token === '{' || $token === '}';
    }

    /**
     * Divides a piece of a Hungarian word by the vowels in it.
     *
     * A doubled digraph split between two syllables is written out in full on
     * both sides, as the dictionary itself writes it: `hosz-szú`.
     *
     * @return list<string>
     */
    private function hungarianSyllables(string $piece): array
    {
        $sounds = $this->hungarianSounds($piece);
        $vowelPositions = array_keys(array_filter($sounds, fn (array $sound): bool => $sound['vowel']));

        if (count($vowelPositions) < 2) {
            return [$piece];
        }

        $starts = [];
        foreach (array_slice($vowelPositions, 1) as $order => $vowelPosition) {
            $previousVowel = $vowelPositions[$order];
            $starts[] = $vowelPosition - $previousVowel > 1 ? $vowelPosition - 1 : $vowelPosition;
        }

        $syllables = [];
        $syllable = '';

        foreach ($sounds as $position => $sound) {
            if (in_array($position, $starts, true)) {
                $syllables[] = $syllable;
                $syllable = '';
            }

            $breaksAfter = in_array($position + 1, $starts, true);
            $syllable .= $breaksAfter ? $sound['split'] : $sound['joined'];
        }

        $syllables[] = $syllable;

        return $syllables;
    }

    /**
     * The sounds of a Hungarian word, a digraph being one consonant and a
     * doubled one (`ssz`) two.
     *
     * @return list<array{joined: string, split: string, vowel: bool}>
     */
    private function hungarianSounds(string $piece): array
    {
        $letters = mb_str_split($piece);
        $sounds = [];
        $index = 0;

        while ($index < count($letters)) {
            $digraph = $this->hungarianDigraphAt($letters, $index);

            if ($digraph !== null) {
                $sounds[] = ['joined' => $digraph, 'split' => $digraph, 'vowel' => false];
                $index += mb_strlen($digraph);

                continue;
            }

            $doubled = $this->hungarianDigraphAt($letters, $index + 1);

            if ($doubled !== null && $letters[$index] === mb_substr($doubled, 0, 1)) {
                $sounds[] = ['joined' => $letters[$index], 'split' => $doubled, 'vowel' => false];
                $sounds[] = ['joined' => $doubled, 'split' => $doubled, 'vowel' => false];
                $index += 1 + mb_strlen($doubled);

                continue;
            }

            $letter = $letters[$index];
            $isVowel = str_contains(self::HUNGARIAN_VOWELS, $letter)
                || ($letter === 'y' && ! in_array($letters[$index - 1] ?? '', ['g', 'l', 'n', 't'], true));
            $sounds[] = ['joined' => $letter, 'split' => $letter, 'vowel' => $isVowel];
            $index++;
        }

        return $sounds;
    }

    /**
     * @param  list<string>  $letters
     */
    private function hungarianDigraphAt(array $letters, int $index): ?string
    {
        foreach (self::HUNGARIAN_DIGRAPHS as $digraph) {
            if (implode('', array_slice($letters, $index, mb_strlen($digraph))) === $digraph) {
                return $digraph;
            }
        }

        return null;
    }

    /**
     * Puts the capitals of the word back on syllables found in lower case.
     *
     * Where a doubled digraph was written out the letters no longer line up, and
     * only a capital initial or a word in capitals throughout is carried over.
     *
     * @param  list<string>  $syllables
     * @return list<string>
     */
    private function restoreCase(string $word, string $lowerCase, array $syllables): array
    {
        if ($word === $lowerCase) {
            return $syllables;
        }

        if (implode('', $syllables) === $lowerCase) {
            $restored = [];
            $offset = 0;

            foreach ($syllables as $syllable) {
                $restored[] = mb_substr($word, $offset, mb_strlen($syllable));
                $offset += mb_strlen($syllable);
            }

            return $restored;
        }

        if (mb_strlen($word) > 1 && $word === mb_strtoupper($word)) {
            return array_map(mb_strtoupper(...), $syllables);
        }

        $syllables[0] = mb_strtoupper(mb_substr($syllables[0], 0, 1)).mb_substr($syllables[0], 1);

        return $syllables;
    }

    private function dictionary(LyricLanguage $language): HyphenationDictionary
    {
        return self::$dictionaries[$language->value] ??= HyphenationDictionary::fromFile($language->dictionaryPath());
    }
}
