<?php

namespace App\Enums;

enum MusicTagType: string
{
    case PartOfMass = 'part_of_mass';
    case Instrument = 'instrument';
    case Season = 'season';
    case Liturgy = 'liturgy';
    case Occasion = 'occasion';
    case Style = 'style';
    case Vocal = 'vocal';
    case Instrumental = 'instrumental';

    public function label(): string
    {
        return match ($this) {
            self::PartOfMass => __('Part of Mass'),
            self::Instrument => __('Instrument'),
            self::Season => __('Season'),
            self::Liturgy => __('Liturgy'),
            self::Occasion => __('Occasion'),
            self::Style => __('Style'),
            self::Vocal => __('Vocal'),
            self::Instrumental => __('Instrumental'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PartOfMass => 'book-open',
            self::Instrument => 'music',
            self::Season => 'calendar',
            self::Liturgy => 'cross',
            self::Occasion => 'gift',
            self::Style => 'palette',
            self::Vocal => 'mic-2',
            self::Instrumental => 'guitar',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PartOfMass => 'purple',
            self::Instrument => 'blue',
            self::Season => 'green',
            self::Liturgy => 'red',
            self::Occasion => 'yellow',
            self::Style => 'pink',
            self::Vocal => 'orange',
            self::Instrumental => 'cyan',
        };
    }
}
