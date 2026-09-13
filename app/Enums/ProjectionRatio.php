<?php

namespace App\Enums;

/**
 * The shape of the screen a projection is thrown onto.
 *
 * The single source of truth for what a slide is, and the PHP half of the table
 * in resources/js/slide-frame.js: a score's author already tunes a layout per
 * ratio — `scores.settings[format]['16/9']` — and places page breaks that cut
 * only at one of them, so these three values are a vocabulary the application
 * already speaks. Nothing new is invented here; it is given a name.
 *
 * The pixel canvas is not stated. Each engine engraves onto a canvas of its own
 * size — and they disagree, correctly, because each author tuned their sizes
 * against the canvas their own editor drew. All that has to hold is the shape,
 * and that is what this enum is.
 */
enum ProjectionRatio: string
{
    case SixteenNine = '16/9';
    case FourThree = '4/3';
    case OneOne = '1/1';

    /**
     * How the ratio is written for a reader: with a colon, the way a projector's
     * menu writes it, rather than with the slash the settings buckets are keyed
     * by.
     */
    public function label(): string
    {
        return match ($this) {
            self::SixteenNine => '16:9',
            self::FourThree => '4:3',
            self::OneOne => '1:1',
        };
    }

    /**
     * What a screen of this shape is usually attached to, to help someone who
     * knows the room but not the number.
     */
    public function description(): string
    {
        return match ($this) {
            self::SixteenNine => __('Widescreen projector or television'),
            self::FourThree => __('Older projector'),
            self::OneOne => __('Square screen'),
        };
    }

    /** The height of a slide of this shape, for a screen of the given width. */
    public function heightFor(float $width): float
    {
        return match ($this) {
            self::SixteenNine => $width * 9 / 16,
            self::FourThree => $width * 3 / 4,
            self::OneOne => $width,
        };
    }

    /**
     * The CSS `aspect-ratio` of a slide, which is the value itself: the browser
     * reads `16/9` exactly as it is stored.
     */
    public function css(): string
    {
        return $this->value;
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
