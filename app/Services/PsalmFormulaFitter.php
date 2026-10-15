<?php

namespace App\Services;

use App\Enums\SyllableStress;

/**
 * Sets the syllables of a half-verse under the slots of one formula.
 *
 * Every way of doing it is weighed, and the cheapest wins: an accent slot
 * wants a stressed syllable, a plain slot would rather not take a primary
 * stress, two notes sit better on a long syllable than on a short one, and an
 * optional note is left out unless it helps. When the text runs out, a slot
 * may join the syllable before it, which is how a verse ending on a stressed
 * monosyllable still gets the notes after its accent. Among equally cheap
 * settings the one with more syllables on the tenor wins.
 */
class PsalmFormulaFitter
{
    private const ACCENT_ON_UNSTRESSED = 10.0;

    private const ACCENT_ON_SECONDARY = 1.0;

    private const PLAIN_ON_PRIMARY = 3.0;

    private const JOIN_LONG = 5.0;

    private const JOIN_SHORT = 8.0;

    private const NEUME_ON_SHORT = 1.0;

    /** A tenor is always sung; it goes without a syllable only when the text is too short for anything else. */
    private const EMPTY_TENOR = 100.0;

    private const OPTIONAL_USED = 0.1;

    /** Each syllable an open note takes beyond its first. */
    private const OPEN_EXTRA_SYLLABLE = 0.5;

    /** The third syllable of an open note, so that two open notes share the syllables rather than one taking them all. */
    private const OPEN_THIRD_SYLLABLE = 0.25;

    /** A word stress inside an open note rather than where it begins. */
    private const OPEN_INNER_PRIMARY = 0.5;

    /** The most syllables an open note takes. */
    private const OPEN_MAX_SYLLABLES = 3;

    private const EPSILON = 1e-9;

    /**
     * What the setting before a slot ended with, which decides whether the slot
     * may join it: a note sung on several syllables takes no more notes.
     */
    private const AFTER_NOTHING = 0;

    private const AFTER_TENOR = 1;

    private const AFTER_SLOT = 2;

    /** @var list<PsalmToneSlot> */
    private array $slots = [];

    /** @var list<PsalmSyllable> */
    private array $syllables = [];

    /** @var array<string, array{cost: float, tenor: int, move: array{kind: string, length: int, next: string}}|null> */
    private array $memo = [];

    /**
     * @param  list<PsalmToneSlot>  $slots
     * @param  list<PsalmSyllable>  $syllables
     * @return array{cost: float, placements: list<array{notes: string, syllables: list<PsalmSyllable>, isTenor: bool, continuesOpen: bool, isAccent: bool, missesAccent: bool}>}
     */
    public function fit(array $slots, array $syllables): array
    {
        $this->slots = $slots;
        $this->syllables = $syllables;
        $this->memo = [];

        $best = $this->best(0, 0, false, self::AFTER_NOTHING);

        if ($best === null) {
            return ['cost' => INF, 'placements' => []];
        }

        return ['cost' => $best['cost'], 'placements' => $this->placements()];
    }

    /**
     * @return array{cost: float, tenor: int, move: array{kind: string, length: int, next: string}}|null
     */
    private function best(int $slotIndex, int $syllableIndex, bool $carriesAccent, int $after): ?array
    {
        $key = self::key($slotIndex, $syllableIndex, $carriesAccent, $after);

        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        if ($slotIndex === count($this->slots)) {
            return $this->memo[$key] = $syllableIndex === count($this->syllables)
                ? ['cost' => 0.0, 'tenor' => 0, 'move' => ['kind' => 'end', 'length' => 0, 'next' => '']]
                : null;
        }

        $slot = $this->slots[$slotIndex];
        $accented = $slot->isAccent || $carriesAccent;
        $candidates = [];

        if ($slot->isTenor) {
            $candidates[] = ['empty', 0, self::EMPTY_TENOR, $slotIndex + 1, $syllableIndex, false, $after];

            for ($length = 1; $syllableIndex + $length <= count($this->syllables); $length++) {
                $candidates[] = ['tenor', $length, 0.0, $slotIndex + 1, $syllableIndex + $length, false, self::AFTER_TENOR];
            }
        } else {
            if ($syllableIndex < count($this->syllables)) {
                $syllable = $this->syllables[$syllableIndex];
                $cost = $this->stressCost($syllableIndex, $accented)
                    + ($slot->noteCount > 1 && ! $syllable->isLong ? self::NEUME_ON_SHORT : 0.0)
                    + ($slot->isOptional ? self::OPTIONAL_USED : 0.0);
                $candidates[] = ['take', 1, $cost, $slotIndex + 1, $syllableIndex + 1, false, self::AFTER_SLOT];

                $maxLength = $slot->isOpen ? min(self::OPEN_MAX_SYLLABLES, count($this->syllables) - $syllableIndex) : 1;

                for ($length = 2; $length <= $maxLength; $length++) {
                    $inner = $this->syllables[$syllableIndex + $length - 1];
                    $cost += $this->stressCost($syllableIndex + $length - 1, false) + self::OPEN_EXTRA_SYLLABLE
                        + ($length >= 3 ? self::OPEN_THIRD_SYLLABLE : 0.0)
                        + ($inner->stress === SyllableStress::Primary ? self::OPEN_INNER_PRIMARY : 0.0);
                    $candidates[] = ['take', $length, $cost, $slotIndex + 1, $syllableIndex + $length, false, self::AFTER_TENOR];
                }
            }

            if ($slot->isOptional) {
                $candidates[] = ['skip', 0, 0.0, $slotIndex + 1, $syllableIndex, $accented, $after];
            }

            if ($after === self::AFTER_SLOT) {
                $previous = $this->syllables[$syllableIndex - 1];
                $cost = $this->stressCost($syllableIndex - 1, $accented) + ($previous->isLong ? self::JOIN_LONG : self::JOIN_SHORT);
                $candidates[] = ['join', 0, $cost, $slotIndex + 1, $syllableIndex, false, self::AFTER_SLOT];
            }
        }

        $best = null;

        foreach ($candidates as [$kind, $length, $cost, $nextSlot, $nextSyllable, $nextCarries, $nextAfter]) {
            $rest = $this->best($nextSlot, $nextSyllable, $nextCarries, $nextAfter);

            if ($rest === null) {
                continue;
            }

            $candidate = [
                'cost' => $cost + $rest['cost'],
                'tenor' => ($kind === 'tenor' ? $length : 0) + $rest['tenor'],
                'move' => ['kind' => $kind, 'length' => $length, 'next' => self::key($nextSlot, $nextSyllable, $nextCarries, $nextAfter)],
            ];

            if ($best === null
                || $candidate['cost'] < $best['cost'] - self::EPSILON
                || (abs($candidate['cost'] - $best['cost']) <= self::EPSILON && $candidate['tenor'] > $best['tenor'])) {
                $best = $candidate;
            }
        }

        return $this->memo[$key] = $best;
    }

    private function stressCost(int $syllableIndex, bool $accented): float
    {
        $syllable = $this->syllables[$syllableIndex];

        return match (true) {
            $accented && $syllable->stress === SyllableStress::None => self::ACCENT_ON_UNSTRESSED,
            $accented && $syllable->stress === SyllableStress::Secondary => self::ACCENT_ON_SECONDARY,
            ! $accented && $syllable->stress === SyllableStress::Primary => self::PLAIN_ON_PRIMARY,
            default => 0.0,
        };
    }

    /**
     * Walks the cheapest setting from the start and writes down where every
     * slot went.
     *
     * @return list<array{notes: string, syllables: list<PsalmSyllable>, isTenor: bool, continuesOpen: bool, isAccent: bool, missesAccent: bool}>
     */
    private function placements(): array
    {
        $placements = [];
        $key = self::key(0, 0, false, self::AFTER_NOTHING);
        $slotIndex = 0;
        $syllableIndex = 0;
        $carriesAccent = false;

        while (($state = $this->memo[$key] ?? null) !== null && $state['move']['kind'] !== 'end') {
            $move = $state['move'];
            $slot = $this->slots[$slotIndex];
            $accented = $slot->isAccent || $carriesAccent;

            if ($move['kind'] === 'tenor' || $move['kind'] === 'take') {
                $taken = array_slice($this->syllables, $syllableIndex, $move['length']);
                $groups = $slot->isOpen ? array_map(fn (PsalmSyllable $syllable): array => [$syllable], $taken) : [$taken];

                foreach ($groups as $groupIndex => $group) {
                    $placements[] = [
                        'notes' => $slot->notesFor($groupIndex, count($groups)),
                        'syllables' => $group,
                        'isTenor' => $slot->isTenor,
                        'continuesOpen' => $groupIndex > 0,
                        'isAccent' => $groupIndex === 0 && $accented,
                        'missesAccent' => $groupIndex === 0 && $accented && $group[0]->stress === SyllableStress::None,
                    ];
                }
                $syllableIndex += $move['length'];
            } elseif ($move['kind'] === 'join') {
                $last = array_pop($placements);
                $last['notes'] .= $slot->notes;
                $last['isAccent'] = $last['isAccent'] || $accented;
                $last['missesAccent'] = $last['missesAccent'] || ($accented && $last['syllables'][0]->stress === SyllableStress::None);
                $placements[] = $last;
            }

            $carriesAccent = $move['kind'] === 'skip' && $accented;
            $slotIndex++;
            $key = $move['next'];
        }

        return $placements;
    }

    private static function key(int $slotIndex, int $syllableIndex, bool $carriesAccent, int $after): string
    {
        return $slotIndex.':'.$syllableIndex.':'.(int) $carriesAccent.':'.$after;
    }
}
