<?php

namespace App\Support;

/**
 * What a booklet may override on a score, within what bounds, and how to label it.
 *
 * One descriptor doing two jobs on purpose. The override bucket is arbitrary
 * JSON arriving from a browser and gets replayed into a renderer, so the server
 * has to know the permitted keys and their limits; the editor has to offer a
 * control per key with the same limits. Deriving both from this table is what
 * stops the panel and the validator drifting apart.
 *
 * Keys and units match `scores.settings` exactly, so an override is written in
 * the same vocabulary the score's own settings use. The one addition is
 * `gabcLayoutWidth`: GABC stores no width, because its editor always lays out on
 * a fixed nominal canvas, and a booklet has to be able to say how wide the page
 * is.
 *
 * Deliberately not shared with the score editor's toolbars, which carry things a
 * booklet has no use for — projector aspect ratios, "save as my default".
 */
class BookletSettingFields
{
    /**
     * Per format: key => [type, min, max, step, label, icon or glyph].
     *
     * The icons are the score editor's own, key for key: someone who has set a
     * score's staff size in its editor should recognise the same control here
     * rather than read a label to find it again. Where that toolbar names a knob
     * with a letter instead of a picture — German notation's H — the letter is
     * carried across as a glyph.
     *
     * @var array<string, array<string, array{type: string, min?: float, max?: float, step?: float, label: string, icon?: string, glyph?: string}>>
     */
    private const FIELDS = [
        'gabc' => [
            'gabcLayoutWidth' => ['type' => 'number', 'min' => 200, 'max' => 8000, 'step' => 5, 'label' => 'Layout width (px)', 'icon' => 'ruler'],
            'lyricSize' => ['type' => 'number', 'min' => 4, 'max' => 60, 'step' => 0.5, 'label' => 'Lyric size', 'icon' => 'a-large-small'],
            // Wider at the bottom than the score editor's own control: a chant
            // staff sized for a real A5 page lands near 21, below the 30 the
            // editor allows on its nominal 508 mm canvas.
            'staffSize' => ['type' => 'number', 'min' => 10, 'max' => 300, 'step' => 1, 'label' => 'Staff size', 'icon' => 'list-chevrons-up-down'],
            'lyricFont' => ['type' => 'font', 'label' => 'Font', 'icon' => 'type-outline'],
            'dropCaps' => ['type' => 'boolean', 'label' => 'Drop caps', 'icon' => 'text-initial'],
            'spaceBetweenSystems' => ['type' => 'number', 'min' => -2, 'max' => 2, 'step' => 0.1, 'label' => 'Space between lines', 'icon' => 'between-horizontal-start'],
            'minSpaceBelowStaff' => ['type' => 'number', 'min' => -2, 'max' => 2, 'step' => 0.1, 'label' => 'Min. space below staff', 'icon' => 'align-vertical-space-around'],
            'minLyricWordSpacing' => ['type' => 'number', 'min' => 0, 'max' => 40, 'step' => 1, 'label' => 'Word spacing (px)', 'icon' => 'space'],
            'hyphenWidth' => ['type' => 'number', 'min' => 0, 'max' => 40, 'step' => 1, 'label' => 'Hyphen width (px)', 'icon' => 'minus'],
            'condensingTolerance' => ['type' => 'number', 'min' => 0, 'max' => 1, 'step' => 0.05, 'label' => 'Condensing tolerance', 'icon' => 'ruler-dimension-line'],
            'zoom' => ['type' => 'number', 'min' => 50, 'max' => 300, 'step' => 5, 'label' => 'Zoom (%)', 'icon' => 'zoom-in'],
        ],
        'abc' => [
            'abcPageWidth' => ['type' => 'number', 'min' => 200, 'max' => 8000, 'step' => 5, 'label' => 'Layout width (px)', 'icon' => 'ruler'],
            'abcLyricSize' => ['type' => 'number', 'min' => 2, 'max' => 60, 'step' => 0.1, 'label' => 'Lyric size', 'icon' => 'a-large-small'],
            'abcPageScale' => ['type' => 'number', 'min' => 0.2, 'max' => 5, 'step' => 0.05, 'label' => 'Staff scale', 'icon' => 'list-chevrons-up-down'],
            'abcLyricFont' => ['type' => 'font', 'label' => 'Font', 'icon' => 'type-outline'],
            'abcLyricBold' => ['type' => 'boolean', 'label' => 'Bold lyrics', 'icon' => 'bold'],
            'abcNoteSpacing' => ['type' => 'number', 'min' => 1, 'max' => 3, 'step' => 0.1, 'label' => 'Note spacing', 'icon' => 'space'],
            'abcStaffSep' => ['type' => 'number', 'min' => 0, 'max' => 120, 'step' => 1, 'label' => 'Staff separation', 'icon' => 'between-horizontal-start'],
            'abcVocalSpace' => ['type' => 'number', 'min' => 0, 'max' => 40, 'step' => 1, 'label' => 'Vocal space', 'icon' => 'align-vertical-space-around'],
            'abcStemWidth' => ['type' => 'number', 'min' => 0.1, 'max' => 3, 'step' => 0.1, 'label' => 'Stem width', 'icon' => 'pencil-line'],
            'abcStaffLineWidth' => ['type' => 'number', 'min' => 0.1, 'max' => 3, 'step' => 0.1, 'label' => 'Staff line width', 'icon' => 'bars-3'],
            'abcNoClef' => ['type' => 'boolean', 'label' => 'Hide clef', 'icon' => 'clef-none'],
            'abcTranspose' => ['type' => 'number', 'min' => -11, 'max' => 11, 'step' => 1, 'label' => 'Transpose', 'icon' => 'musical-note'],
            'abcZoom' => ['type' => 'number', 'min' => 50, 'max' => 300, 'step' => 5, 'label' => 'Zoom (%)', 'icon' => 'zoom-in'],
        ],
        // An uploaded score is a picture by the time it reaches a booklet, so
        // the only thing that can be done to it is scale it. It already arrives
        // scaled to the width of the page, which is right for nearly every file;
        // this is the way down from there, for the scan engraved so large that
        // filling the page makes it shout.
        'file' => [
            'fileZoom' => ['type' => 'number', 'min' => 0.2, 'max' => 1, 'step' => 0.05, 'label' => 'Size (×)', 'icon' => 'zoom-in'],
        ],
        'chordpro' => [
            'chordproFontSize' => ['type' => 'number', 'min' => 6, 'max' => 32, 'step' => 0.5, 'label' => 'Font size', 'icon' => 'a-large-small'],
            'chordproFontFamily' => ['type' => 'font', 'label' => 'Font', 'icon' => 'type-outline'],
            'chordproColumns' => ['type' => 'number', 'min' => 1, 'max' => 4, 'step' => 1, 'label' => 'Columns', 'icon' => 'view-columns'],
            'chordproTranspose' => ['type' => 'number', 'min' => -11, 'max' => 11, 'step' => 1, 'label' => 'Transpose', 'icon' => 'musical-note'],
            'chordproGermanNotation' => ['type' => 'boolean', 'label' => 'German notation (H = B, B = B♭)', 'glyph' => 'H'],
        ],
        'aretino' => [
            'aretinoStaffWidth' => ['type' => 'number', 'min' => 30, 'max' => 800, 'step' => 1, 'label' => 'Layout width (mm)', 'icon' => 'ruler'],
            'aretinoLyricSize' => ['type' => 'number', 'min' => 4, 'max' => 80, 'step' => 0.5, 'label' => 'Lyric size (pt)', 'icon' => 'a-large-small'],
            'aretinoStaffSize' => ['type' => 'number', 'min' => 1, 'max' => 20, 'step' => 0.1, 'label' => 'Staff height (mm)', 'icon' => 'list-chevrons-up-down'],
            'aretinoTextFont' => ['type' => 'font', 'label' => 'Font', 'icon' => 'type-outline'],
            'aretinoStaffGap' => ['type' => 'number', 'min' => 0, 'max' => 10, 'step' => 0.5, 'label' => 'Staff gap', 'icon' => 'between-horizontal-start'],
            'aretinoHideRepeatClef' => ['type' => 'boolean', 'label' => 'Hide repeated clef', 'icon' => 'clef-none'],
            'aretinoZoom' => ['type' => 'number', 'min' => 50, 'max' => 300, 'step' => 5, 'label' => 'Zoom (%)', 'icon' => 'zoom-in'],
        ],
    ];

    /**
     * The faces the exporter can embed. A font that is not in
     * resources/js/svg-fonts.js is a font that will not survive the trip to PDF.
     *
     * @var list<string>
     */
    private const EMBEDDABLE_FONTS = ['EB Garamond', 'Lora', 'Inter', 'Barlow Condensed'];

    /**
     * Keep only the keys this format allows, clamped into range.
     *
     * Anything unrecognised is dropped rather than rejected: an override bucket
     * is a set of nudges, and a stale key from an older client should not cost
     * someone the rest of their adjustments.
     *
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    public static function sanitize(?string $format, array $override): array
    {
        $fields = self::FIELDS[$format] ?? [];
        $clean = [];

        foreach ($override as $key => $value) {
            $field = $fields[$key] ?? null;

            if ($field === null) {
                continue;
            }

            if ($field['type'] === 'number') {
                if (! is_numeric($value)) {
                    continue;
                }

                $clean[$key] = min($field['max'], max($field['min'], (float) $value));

                continue;
            }

            if ($field['type'] === 'boolean') {
                $clean[$key] = filter_var($value, FILTER_VALIDATE_BOOL);

                continue;
            }

            if ($field['type'] === 'font' && is_string($value)) {
                $family = trim($value, " \t\n\r\0\x0B'\"");

                if (in_array($family, self::EMBEDDABLE_FONTS, true)) {
                    // Stored quoted, the way the score editor's selects emit it.
                    $clean[$key] = "'".$family."'";
                }
            }
        }

        return $clean;
    }

    /**
     * The controls to render for a format, labels translated.
     *
     * A control is named by its icon, or by its glyph where the score editor
     * names it with one; the label becomes the tooltip.
     *
     * @return list<array{key: string, type: string, min?: float, max?: float, step?: float, label: string, icon?: string, glyph?: string}>
     */
    public static function panelFor(?string $format): array
    {
        $panel = [];

        foreach (self::FIELDS[$format] ?? [] as $key => $field) {
            $panel[] = array_merge(['key' => $key], $field, ['label' => __($field['label'])]);
        }

        return $panel;
    }

    /**
     * @return list<string>
     */
    public static function keysFor(?string $format): array
    {
        return array_keys(self::FIELDS[$format] ?? []);
    }

    /**
     * @return list<string>
     */
    public static function fontOptions(): array
    {
        return self::EMBEDDABLE_FONTS;
    }
}
