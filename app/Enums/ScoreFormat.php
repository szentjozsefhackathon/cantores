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

    /**
     * A few bars in this notation, to show someone about to write their own
     * what the format looks like.
     */
    public function example(): string
    {
        return match ($this) {
            self::Abc => "L:1/4\nK:G\nG A B c | B A G2 |]\nw: Al-le-lu-ja, al-le-lu-ja!",
            self::Aretino => "(g2) g a b g. ab a g e_d_ , g ab ag g. ||\nw: Al-le-lu-ja, al-le-lu-ja, al-le-lu-ja.",
            self::Gabc => '(c4) Al(f)le(gf)lú(h)ja.(h) (::)',
            self::ChordPro => '[G]Alleluja, [D]alle[G]luja!',
        };
    }
}
