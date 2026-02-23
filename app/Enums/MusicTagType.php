<?php

namespace App\Enums;

enum MusicTagType: string
{
    case Season = 'season';
    case Liturgy = 'liturgy';
    case PartOfMass = 'part_of_mass';
    case Occasion = 'occasion';
    case Daily = 'daily';
    case Theme = 'theme';
    case Instrument = 'instrument';
    case Style = 'style';

    public function label(): string
    {
        return match ($this) {
            self::PartOfMass => __('Part of Mass'),
            self::Instrument => __('Instrument'),
            self::Season => __('Season'),
            self::Liturgy => __('Liturgy'),
            self::Occasion => __('Occasion'),
            self::Daily => __('Daily'),
            self::Theme => __('Theme'),
            self::Style => __('Style'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PartOfMass => 'church',
            self::Instrument => 'piano',
            self::Season => 'leaf',
            self::Liturgy => 'cross',
            self::Occasion => 'gift',
            self::Daily => 'sun',
            self::Theme => 'light-bulb',
            self::Style => 'palette',
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
            self::Daily => 'orange',
            self::Theme => 'indigo',
            self::Style => 'pink',
        };
    }
}
