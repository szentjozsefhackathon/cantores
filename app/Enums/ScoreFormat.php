<?php

namespace App\Enums;

enum ScoreFormat: string
{
    case Abc = 'abc';
    case Gabc = 'gabc';

    public function label(): string
    {
        return match ($this) {
            self::Abc => __('ABC notation'),
            self::Gabc => __('Gregorio GABC'),
        };
    }
}
