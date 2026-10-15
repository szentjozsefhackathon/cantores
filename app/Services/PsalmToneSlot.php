<?php

namespace App\Services;

/**
 * One note group of a psalm-tone formula, which takes one syllable or none.
 *
 * The formula marks what the group is for with Aretino suffixes: `t` is the
 * tenor that takes every syllable left over, `o` an open note that takes one
 * to three syllables, `-` an accent, and `s` a note sung only when there is a
 * syllable to spare for it. The `s` and `-` say how the formula fits, not how
 * the result looks, so they are gone from the notes the fitted verse is
 * written with, and so is the `o`: an open note is written once for every
 * syllable it takes. Notes before the open note go with the first of those
 * syllables and notes after it with the last, so `goa` is sung `ga`, `g ga`
 * or `g g ga`.
 */
final readonly class PsalmToneSlot
{
    public function __construct(
        public string $notes,
        public bool $isTenor,
        public bool $isOpen,
        public bool $isAccent,
        public bool $isOptional,
        public int $noteCount,
        public string $beforeOpen = '',
        public string $openNote = '',
        public string $afterOpen = '',
    ) {}

    /**
     * The notes written over one syllable of the slot, given which of how
     * many syllables it is.
     */
    public function notesFor(int $index, int $count): string
    {
        if (! $this->isOpen || $count === 1) {
            return $this->notes;
        }

        $openNote = $index === 0 ? $this->openNote : (string) preg_replace('/\([^()]*\)/', '', $this->openNote);

        return match (true) {
            $index === 0 => $this->beforeOpen.$openNote,
            $index === $count - 1 => $openNote.$this->afterOpen,
            default => $openNote,
        };
    }

    public static function fromToken(string $token): self
    {
        preg_match_all('/((?:\([^()]*\))*)([a-gA-G])([^a-gA-G(]*)/u', $token, $units, PREG_SET_ORDER);
        $marks = '';
        $notes = [];
        $openIndex = null;

        foreach ($units as $index => [, $accidental, $note, $suffix]) {
            $marks .= $note.$suffix;
            $notes[] = $accidental.$note.str_replace(['s', '-', 'o'], '', $suffix);

            if ($openIndex === null && str_contains($suffix, 'o')) {
                $openIndex = $index;
            }
        }

        return new self(
            notes: implode('', $notes),
            isTenor: str_contains($marks, 't'),
            isOpen: $openIndex !== null,
            isAccent: str_contains($marks, '-'),
            isOptional: str_contains($marks, 's'),
            noteCount: count($notes),
            beforeOpen: $openIndex === null ? '' : implode('', array_slice($notes, 0, $openIndex)),
            openNote: $openIndex === null ? '' : $notes[$openIndex],
            afterOpen: $openIndex === null ? '' : implode('', array_slice($notes, $openIndex + 1)),
        );
    }
}
