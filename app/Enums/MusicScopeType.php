<?php

namespace App\Enums;

enum MusicScopeType: string
{
    case MOVEMENT = 'movement';
    case VERSE = 'verse';
    case SECTION = 'section';
    case PART = 'part';
    case STANZA = 'stanza';
    case CHORUS = 'chorus';
    case REFRAIN = 'refrain';
    case INTERLUDE = 'interlude';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::MOVEMENT => __('Movement'),
            self::VERSE => __('Verse'),
            self::SECTION => __('Section'),
            self::PART => __('Part'),
            self::STANZA => __('Stanza'),
            self::CHORUS => __('Chorus'),
            self::REFRAIN => __('Refrain'),
            self::INTERLUDE => __('Interlude'),
            self::OTHER => __('Other'),
        };
    }

    public function abbreviation(): string
    {
        return match ($this) {
            self::MOVEMENT => 'mv',
            self::VERSE => 'v',
            self::SECTION => 'sec',
            self::PART => 'pt',
            self::STANZA => 'st',
            self::CHORUS => 'ch',
            self::REFRAIN => 'rf',
            self::INTERLUDE => 'int',
            self::OTHER => '',
        };
    }
}
