<?php

namespace App\Services;

use RuntimeException;

/**
 * A Hunspell hyphenation dictionary (libhyphen's `hyph_*.dic`), read and
 * applied the way libhyphen applies it.
 *
 * The patterns are Liang's: every substring of the dotted word that is a
 * pattern lifts the values between its letters, and an odd value is a break.
 * libhyphen adds non-standard patterns, which also rewrite the letters around
 * their break — the Hungarian `as5szon2y/sz=,2,1` turns `asszony` into
 * `asz-szony` — and those are honoured here too.
 *
 * Only the first level of a two-level dictionary is read; neither of the
 * dictionaries this application ships has a second one.
 */
class HyphenationDictionary
{
    /**
     * @param  array<string, array{0: list<int>, 1: ?string, 2: int, 3: int}>  $patterns  letters → [values, replacement, replacement start, replacement length]
     */
    public function __construct(
        private array $patterns,
        private int $longestPattern,
        private int $leftHyphenMin = 1,
        private int $rightHyphenMin = 1,
    ) {}

    public static function fromFile(string $path): self
    {
        $lines = @file($path, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            throw new RuntimeException("Cannot read the hyphenation dictionary at {$path}.");
        }

        return self::fromLines($lines);
    }

    /**
     * @param  list<string>  $lines  the dictionary, the first line naming its character set
     */
    public static function fromLines(array $lines): self
    {
        $patterns = [];
        $longestPattern = 0;
        $leftHyphenMin = 1;
        $rightHyphenMin = 1;

        foreach (array_slice($lines, 1) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '%')) {
                continue;
            }

            if (preg_match('/^([A-Z]+)\s*(\d*)$/', $line, $keyword)) {
                if ($keyword[1] === 'NEXTLEVEL') {
                    break;
                }

                match ($keyword[1]) {
                    'LEFTHYPHENMIN' => $leftHyphenMin = max(1, (int) $keyword[2]),
                    'RIGHTHYPHENMIN' => $rightHyphenMin = max(1, (int) $keyword[2]),
                    default => null,
                };

                continue;
            }

            [$pattern, $replacement, $replacementStart, $replacementLength] = self::parsePattern($line);

            $letters = preg_replace('/\d/', '', $pattern);

            if ($replacement !== null && str_starts_with($letters, '.')) {
                $replacementStart++;
            }
            $values = array_fill(0, mb_strlen($letters) + 1, 0);
            $position = 0;

            foreach (mb_str_split($pattern) as $character) {
                if (ctype_digit($character)) {
                    $values[$position] = (int) $character;
                } else {
                    $position++;
                }
            }

            $patterns[$letters] = [$values, $replacement, $replacementStart, $replacementLength];
            $longestPattern = max($longestPattern, mb_strlen($letters));
        }

        return new self($patterns, $longestPattern, $leftHyphenMin, $rightHyphenMin);
    }

    /**
     * Splits a pattern line from its non-standard replacement, if it has one.
     *
     * @return array{0: string, 1: ?string, 2: int, 3: int}
     */
    private static function parsePattern(string $line): array
    {
        if (! str_contains($line, '/')) {
            return [$line, null, 0, 0];
        }

        [$pattern, $rule] = explode('/', $line, 2);
        [$replacement, $start, $length] = array_pad(explode(',', $rule), 3, '');

        return [$pattern, $replacement, (int) $start - 1, (int) $length];
    }

    /**
     * The syllables of one lower-case word, as the patterns break it.
     *
     * Joined back together they spell the word, except where a non-standard
     * pattern rewrote the letters at a break.
     *
     * @return list<string>
     */
    public function syllables(string $word): array
    {
        $letters = mb_str_split($word);
        $length = count($letters);

        if ($length < 2) {
            return [$word];
        }

        $dotted = ['.', ...$letters, '.'];
        $dottedLength = $length + 2;
        $values = array_fill(0, $dottedLength + 1, 0);
        $replacements = [];

        for ($start = 0; $start < $dottedLength; $start++) {
            $candidate = '';
            $end = min($dottedLength, $start + $this->longestPattern);

            for ($next = $start; $next < $end; $next++) {
                $candidate .= $dotted[$next];

                if (! isset($this->patterns[$candidate])) {
                    continue;
                }

                [$patternValues, $replacement, $replacementStart, $replacementLength] = $this->patterns[$candidate];

                foreach ($patternValues as $offset => $value) {
                    if ($value <= $values[$start + $offset]) {
                        continue;
                    }

                    $values[$start + $offset] = $value;

                    if ($replacement !== null && $value % 2 === 1) {
                        $replacements[$start + $offset] = [$replacement, $start + $replacementStart - 1, $replacementLength];
                    } else {
                        unset($replacements[$start + $offset]);
                    }
                }
            }
        }

        $rewrites = [];
        $rewrittenBreaks = [];

        foreach ($replacements as $valueIndex => [$text, $replacementStart, $replacementLength]) {
            if (! isset($rewrites[$replacementStart]) && $this->allowsBreakAt($valueIndex - 1, $length)) {
                $rewrites[$replacementStart] = [$text, $replacementLength];
                $rewrittenBreaks[$valueIndex] = true;
            }
        }

        $marked = '';
        $index = 0;

        while ($index < $length) {
            if (isset($rewrites[$index])) {
                [$text, $replacementLength] = $rewrites[$index];
                $marked .= $text;
                $index += max(1, $replacementLength);

                continue;
            }

            $marked .= $letters[$index];
            $index++;

            if ($index < $length && $values[$index + 1] % 2 === 1 && ! isset($rewrittenBreaks[$index + 1]) && $this->allowsBreakAt($index, $length)) {
                $marked .= '=';
            }
        }

        return array_values(array_filter(explode('=', $marked), fn (string $syllable): bool => $syllable !== ''));
    }

    private function allowsBreakAt(int $break, int $length): bool
    {
        return $break >= $this->leftHyphenMin && $break <= $length - $this->rightHyphenMin;
    }
}
