<?php

namespace App\Enums;

enum MusicScopeType: string
{
    case MOVEMENT = 'movement';
    case PART = 'part';
    case STANZA = 'stanza';
    case CHORUS = 'chorus';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::MOVEMENT => __('Movement'),
            self::PART => __('Part'),
            self::STANZA => __('Stanza'),
            self::CHORUS => __('Chorus'),
            self::OTHER => __('Other'),
        };
    }

    public function abbreviation(): string
    {
        return match ($this) {
            self::MOVEMENT => 'mv',
            self::PART => 'pt',
            self::STANZA => 'st',
            self::CHORUS => 'ch',
            self::OTHER => '',
        };
    }
}
