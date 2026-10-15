<?php

namespace App\Enums;

/**
 * A language lyrics can be split into syllables in, each with the Hunspell
 * (libhyphen) dictionary that knows its patterns.
 */
enum LyricLanguage: string
{
    case Hungarian = 'hu';
    case Latin = 'la';

    public function label(): string
    {
        return match ($this) {
            self::Hungarian => __('Hungarian'),
            self::Latin => __('Latin'),
        };
    }

    public function dictionaryPath(): string
    {
        return resource_path(match ($this) {
            self::Hungarian => 'hyphenation/hyph_hu_HU.dic',
            self::Latin => 'hyphenation/hyph_la_liturgical.dic',
        });
    }
}
