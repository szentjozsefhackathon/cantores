<?php

namespace App\Services;

use App\Enums\SyllableStress;

/**
 * One syllable of a psalm verse, as the tone fitter weighs it.
 *
 * The text carries the punctuation around the word on its first and last
 * syllables, and `word` numbers the words of the half-verse, so the syllables
 * of one word can be joined again when they are written out. A syllable the
 * text marked with a `'` is `isMarked`: the setter is to put an accent on it.
 */
final readonly class PsalmSyllable
{
    public function __construct(
        public string $text,
        public int $word,
        public SyllableStress $stress,
        public bool $isLong,
        public bool $isMarked = false,
    ) {}
}
