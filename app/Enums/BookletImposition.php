<?php

namespace App\Enums;

/**
 * How a booklet's pages are arranged on the paper that comes out of the printer.
 *
 * Deliberately three choices rather than a printer's dialogue full of them. A
 * cantor has A4 paper and wants either one page per sheet, or the little pages
 * side by side on it so that a sheet cut in half is two of them, or the pages
 * shuffled so that a stack folded down the middle reads as a book.
 */
enum BookletImposition: string
{
    case Full = 'full';
    case TwoUp = 'two_up';
    case Booklet = 'booklet';

    public function label(): string
    {
        return match ($this) {
            self::Full => __('Export PDF'),
            self::TwoUp => __('Export 2-up on A4'),
            self::Booklet => __('Export booklet on A4'),
        };
    }

    /**
     * Whether this arrangement rearranges anything, i.e. needs a sheet the
     * booklet's own pages are placed onto.
     */
    public function imposes(): bool
    {
        return $this !== self::Full;
    }
}
