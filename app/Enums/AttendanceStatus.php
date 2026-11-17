<?php

namespace App\Enums;

/**
 * A choir member's answer for one date of a season.
 *
 * Three answers rather than two, because "maybe" is what most people honestly
 * know about Pentecost in November, and a count of firm yeses beside a count of
 * maybes is what the leader plans the parts from.
 */
enum AttendanceStatus: string
{
    case Yes = 'yes';
    case Maybe = 'maybe';
    case No = 'no';

    public function label(): string
    {
        return match ($this) {
            self::Yes => __('Coming'),
            self::Maybe => __('Maybe'),
            self::No => __('Not coming'),
        };
    }

    /**
     * The Flux badge colour the answer is shown in.
     */
    public function color(): string
    {
        return match ($this) {
            self::Yes => 'green',
            self::Maybe => 'amber',
            self::No => 'red',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Yes => 'check',
            self::Maybe => 'question-mark-circle',
            self::No => 'x-mark',
        };
    }

    /**
     * The answer after this one, for the owner's grid where a click steps a
     * cell round: nothing, yes, maybe, no, and back to nothing.
     */
    public static function after(?self $status): ?self
    {
        return match ($status) {
            null => self::Yes,
            self::Yes => self::Maybe,
            self::Maybe => self::No,
            self::No => null,
        };
    }
}
