<?php

namespace App\Enums;

enum ScoreFormat: string
{
    case Abc = 'abc';
    case Gabc = 'gabc';
    case ChordPro = 'chordpro';

    public function label(): string
    {
        return match ($this) {
            self::Abc => __('ABC notation'),
            self::Gabc => __('Gregorio GABC'),
            self::ChordPro => __('ChordPro'),
        };
    }
}
