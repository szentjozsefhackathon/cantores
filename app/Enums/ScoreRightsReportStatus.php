<?php

namespace App\Enums;

/**
 * Where a rights complaint stands.
 *
 * Nothing is ever deleted here: an upheld or dismissed report stays as the
 * record of what was claimed and what was decided about it.
 */
enum ScoreRightsReportStatus: string
{
    case Open = 'open';
    case Upheld = 'upheld';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Awaiting a decision'),
            self::Upheld => __('Upheld'),
            self::Dismissed => __('Dismissed'),
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Open;
    }
}
