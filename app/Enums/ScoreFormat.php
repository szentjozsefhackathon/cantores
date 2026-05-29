<?php

namespace App\Enums;

enum ScoreFormat: string
{
    case Abc = 'abc';
    case Aretino = 'aretino';
    case Gabc = 'gabc';
    case ChordPro = 'chordpro';

    public function label(): string
    {
        return match ($this) {
            self::Abc => __('ABC'),
            self::Aretino => __('Aretino'),
            self::Gabc => __('Gregorio'),
            self::ChordPro => __('ChordPro')
        };
    }
}
