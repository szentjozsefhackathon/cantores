<?php

namespace App\Enums;

/**
 * On which verses of a psalm the initium of the tone is sung.
 */
enum InitiumSetting: string
{
    case None = 'none';
    case FirstVerse = 'first';
    case EveryVerse = 'every';

    public function label(): string
    {
        return match ($this) {
            self::None => __('No initium'),
            self::FirstVerse => __('Initium on the first verse'),
            self::EveryVerse => __('Initium on every verse'),
        };
    }

    public function appliesTo(int $verseIndex): bool
    {
        return match ($this) {
            self::None => false,
            self::FirstVerse => $verseIndex === 0,
            self::EveryVerse => true,
        };
    }
}
