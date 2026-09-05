<?php

namespace App\Enums;

/**
 * The paper a booklet is printed on.
 *
 * These are the only page dimensions in the application: the browser receives
 * millimetres computed from here rather than keeping its own table, so there is
 * one place to be wrong about what A5 is.
 */
enum BookletPageSize: string
{
    case A4 = 'a4';
    case A5 = 'a5';
    case A6 = 'a6';

    /**
     * The short edge, in millimetres.
     */
    public function shortEdgeMm(): float
    {
        return match ($this) {
            self::A4 => 210.0,
            self::A5 => 148.0,
            self::A6 => 105.0,
        };
    }

    /**
     * The long edge, in millimetres.
     */
    public function longEdgeMm(): float
    {
        return match ($this) {
            self::A4 => 297.0,
            self::A5 => 210.0,
            self::A6 => 148.0,
        };
    }

    public function widthMm(BookletOrientation $orientation = BookletOrientation::Portrait): float
    {
        return $orientation === BookletOrientation::Portrait
            ? $this->shortEdgeMm()
            : $this->longEdgeMm();
    }

    public function heightMm(BookletOrientation $orientation = BookletOrientation::Portrait): float
    {
        return $orientation === BookletOrientation::Portrait
            ? $this->longEdgeMm()
            : $this->shortEdgeMm();
    }

    public function label(): string
    {
        return match ($this) {
            self::A4 => __('A4'),
            self::A5 => __('A5'),
            self::A6 => __('A6'),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
