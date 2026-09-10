<?php

namespace App\Support;

/**
 * The three typographies a booklet may be set in.
 *
 * A booklet already imposed one face on every score it prints, for a reason
 * worth repeating: two faces have different widths and different weights, and
 * the balance between a staff and the lyrics under it that reads well in one is
 * wrong in the other. Imposing the face while leaving each score the spacing
 * that face needs was half a decision, and this table is the other half.
 *
 * A style is therefore the booklet's whole typography — the face and every
 * number that has to agree with it — applied in one press. Nothing physical is
 * in it: the paper is chosen once because it is the paper in the printer, and a
 * style that reflowed A5 into A4 would be useless for the one thing styles are
 * for, which is seeing which of them suits this booklet.
 *
 * Named after the book each one is rather than after its face. A cantor
 * choosing between *Énekeskönyv* and *Graduále* is choosing what the booklet
 * should feel like; a cantor choosing between *Alegreya* and *EB Garamond* is
 * being asked a question about typefaces they did not come here to answer.
 *
 * Each style names a face of its own, which is what lets the booklet's own
 * `text_font` say which style it is in — see forFont(). There is no `style`
 * column to go stale, and no state in which the face says one thing and the
 * spacing another.
 */
class BookletStyles
{
    /** The style a booklet is in when nothing else has been said. */
    public const DEFAULT = 'hymnal';

    /**
     * Per style: the label, the columns it writes, and the gaps it sets in the
     * engines that keep no column of their own.
     *
     * Only two of the seven columns actually differ today, because
     * opticalLyricSizePt() already makes one lyric size read the same in every
     * face — the sizes were unified two features ago and do not need unifying
     * again. The other five are here anyway, because this is where a number
     * goes once somebody has judged it by eye, and a style that owned only the
     * two known columns would have to be widened the first time anyone decides
     * Graduále wants a taller staff.
     *
     * The two spacings that do differ were judged by eye and do not reduce to
     * arithmetic: read in ems of line box, EB Garamond wants its stanzas a
     * sixth tighter than Alegreya does, which is taste rather than unit
     * conversion.
     *
     * The engine gaps are ABC's pair under other names, and all three styles
     * keep the number their engine already draws at — nobody has judged them by
     * eye yet. They are written here rather than left to a default in a
     * dependency so that the table is where they will be judged.
     *
     * @var array<string, array{label: string, columns: array<string, mixed>, engines: array<string, float>}>
     */
    private const STYLES = [
        'hymnal' => [
            'label' => 'Hymnal',
            'columns' => [
                'text_font' => 'Alegreya',
                'lyric_size_pt' => 10.5,
                'staff_height_mm' => 5.0,
                'heading_scale' => 0.9,
                'abc_staff_sep' => 25.0,
                'abc_lyric_first_skip' => 1.4,
                'abc_lyric_skip' => 0.9,
            ],
            'engines' => [
                'minSpaceBelowStaff' => 0.0,
                'aretinoLyricDistance' => 0.2,
                'aretinoLyricMinStaffDistance' => 0.75,
            ],
        ],
        'modern' => [
            'label' => 'Modern',
            'columns' => [
                'text_font' => 'Merriweather',
                'lyric_size_pt' => 10.5,
                'staff_height_mm' => 5.0,
                'heading_scale' => 0.9,
                'abc_staff_sep' => 25.0,
                'abc_lyric_first_skip' => 1.5,
                'abc_lyric_skip' => 1.0,
            ],
            'engines' => [
                'minSpaceBelowStaff' => 0.0,
                'aretinoLyricDistance' => 0.2,
                'aretinoLyricMinStaffDistance' => 0.75,
            ],
        ],
        'graduale' => [
            'label' => 'Graduale',
            'columns' => [
                'text_font' => 'EB Garamond',
                'lyric_size_pt' => 10.5,
                'staff_height_mm' => 5.0,
                'heading_scale' => 0.9,
                'abc_staff_sep' => 25.0,
                'abc_lyric_first_skip' => 1.4,
                'abc_lyric_skip' => 0.8,
            ],
            'engines' => [
                'minSpaceBelowStaff' => 0.0,
                'aretinoLyricDistance' => 0.2,
                'aretinoLyricMinStaffDistance' => 0.75,
            ],
        ],
    ];

    /**
     * The styles to put in a select, in the order they belong in: the parish
     * songbook first because it is the default, then the face drawn for
     * screens, then the chant book.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function all(): array
    {
        return array_values(array_map(
            fn (string $style): array => ['value' => $style, 'label' => __(self::STYLES[$style]['label'])],
            self::keys(),
        ));
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::STYLES);
    }

    /**
     * The columns choosing this style writes onto the booklet.
     *
     * Everything typographic and nothing physical: the page size, the
     * orientation and the margin are left where the cantor put them, and so is
     * every per-score override — which is what makes it safe to try all three
     * styles on a service that has already been fitted onto four sides.
     *
     * @return array<string, mixed>
     */
    public static function defaults(string $style): array
    {
        return self::styleOr($style)['columns'];
    }

    /**
     * The gaps this style sets in the engines that store none of their own.
     *
     * GABC's `minSpaceBelowStaff` is a score setting today and moves to the
     * style at the zero it already has; Aretino's two are not settings at all
     * yet, so the renderer falls through to METRICS in `@aretino-chant/core`.
     * Both are handed to the browser through Booklet::geometry().
     *
     * @return array<string, float>
     */
    public static function engineSpacing(string $style): array
    {
        return self::styleOr($style)['engines'];
    }

    /**
     * The part of each style a reader's screen can swap, keyed by style.
     *
     * The reader is offered the styles for the same reason the cantor is:
     * without it, someone picking Merriweather on a phone gets Merriweather's
     * lyrics over Alegreya's gaps — the very fault the anchored staff-to-lyrics
     * gap removed from paper, reintroduced on the reader's screen. So the three
     * values that make a face read right move together or not at all.
     *
     * Sizes are not in here: how big the booklet is on a phone is the reader's
     * own zoom, and the page is the screen.
     *
     * @return array<string, array{textFont: string, abcLyricFirstSkip: float, abcLyricSkip: float}>
     */
    public static function typographies(): array
    {
        $typographies = [];

        foreach (self::STYLES as $style => $definition) {
            $typographies[$style] = [
                'textFont' => $definition['columns']['text_font'],
                'abcLyricFirstSkip' => $definition['columns']['abc_lyric_first_skip'],
                'abcLyricSkip' => $definition['columns']['abc_lyric_skip'],
            ];
        }

        return $typographies;
    }

    /**
     * Which style a booklet set in this face is in.
     *
     * The inverse of the table, and the only reason no `style` column exists. A
     * face no style claims — Inter or Barlow Condensed, from before booklets had
     * styles — is nearest to the default; the booklet keeps the face it has, and
     * picking any style moves it onto a booklet face for good.
     */
    public static function forFont(string $font): string
    {
        $bare = trim($font, " \t\n\r\0\x0B'\"");

        foreach (self::STYLES as $style => $definition) {
            if ($definition['columns']['text_font'] === $bare) {
                return $style;
            }
        }

        return self::DEFAULT;
    }

    /**
     * @return array{label: string, columns: array<string, mixed>, engines: array<string, float>}
     */
    private static function styleOr(string $style): array
    {
        return self::STYLES[$style] ?? self::STYLES[self::DEFAULT];
    }
}
