<?php

namespace App\Enums;

/**
 * The colours a projection sets its words in.
 *
 * Only its words. Music is engraved black on white by three engines that draw
 * ink on paper, and reversing a staff out of black does not help anybody read
 * it — so this decides the look of the text-only slides and nothing else.
 *
 * The palette is stated here rather than in the stylesheet because the slide is
 * an SVG document: the background is a rectangle drawn into it and the type is a
 * `fill` on each run, so the colours have to travel with the deck all the way to
 * the browser that draws it. Their JavaScript half is the table in
 * resources/js/projection-deck.js, which reads exactly these keys.
 */
enum ProjectionTextTheme: string
{
    /**
     * White on black: what a projector in a darkened church is expected to do,
     * and the reason this is the default. A full white screen carrying eight
     * words is the brightest thing in the room.
     */
    case Dark = 'dark';

    /** Black on white, for a bright room or a screen that is really a television. */
    case Light = 'light';

    public function label(): string
    {
        return match ($this) {
            self::Dark => __('White on black'),
            self::Light => __('Black on white'),
        };
    }

    /**
     * Every colour a screen of words is drawn with.
     *
     * `accent` is what `<red>` comes out as. Red on white is the rubric colour a
     * missal has used for centuries; the same red on black is nearly unreadable,
     * so the dark theme answers with the complement that keeps the warning
     * without the mud.
     *
     * @return array{background: string, text: string, quote: string, rule: string, accent: string}
     */
    public function palette(): array
    {
        return match ($this) {
            self::Dark => [
                'background' => '#000000',
                'text' => '#ffffff',
                'quote' => '#b4b4b4',
                'rule' => '#666666',
                'accent' => '#ff6b6b',
            ],
            self::Light => [
                'background' => '#ffffff',
                'text' => '#000000',
                'quote' => '#555555',
                'rule' => '#999999',
                'accent' => '#cc0000',
            ],
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
