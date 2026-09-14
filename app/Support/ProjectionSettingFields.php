<?php

namespace App\Support;

use App\Enums\ProjectionRatio;

/**
 * What a projection may override on a score, within what bounds, and how to
 * label it.
 *
 * The same descriptor-doing-two-jobs as App\Support\BookletSettingFields, and
 * for the same reason: the override bucket is arbitrary JSON arriving from a
 * browser and gets replayed into a renderer, so the server has to know the
 * permitted keys and their limits, and the editor has to offer a control per key
 * with the same limits. Deriving both from one table is what stops the panel and
 * the validator drifting apart.
 *
 * It is a separate table from the booklet's rather than a shared one, because
 * the two are answering different rooms. Three differences, each deliberate:
 *
 * - **The stroke widths are here.** `abcStemWidth` and `abcStaffLineWidth` are
 *   the two knobs the score editor offers only at a projector ratio, because a
 *   hairline that prints beautifully dies on a beamer. A booklet has no use for
 *   them and correctly leaves them out; a projection is the reason they exist.
 * - **The sizes run much larger.** A booklet's lyric size tops out where a page
 *   stops making sense. A slide's starts near where a page's ends: the ABC
 *   screen defaults are 70 points at 16:9.
 * - **The layout widths are not here.** A booklet lets a score be laid out wider
 *   than the page and shrunk back, which is how a bad line break is killed. A
 *   slide has no such slack — the canvas *is* the width, and a score laid out
 *   wider would simply be engraved smaller, which is what the size knobs already
 *   do more honestly.
 * - **The face is not here either.** A booklet imposes one face on every score
 *   it gathers, so it has to offer the choice; a projection imposes nothing and
 *   leaves each score the face its author chose against this very canvas. The
 *   screen defaults are already a condensed sans, which is what a beamer wants,
 *   and the knob was only ever a way to spoil that.
 *
 * Keys and units match `scores.settings` exactly, so an override is written in
 * the same vocabulary the score's own per-ratio settings use — which is what
 * lets a slide's override sit on top of the author's 16:9 bucket without
 * translation.
 */
class ProjectionSettingFields
{
    /**
     * Per format: key => [type, min, max, step, label, icon or glyph].
     *
     * The icons are the score editor's own, key for key, so someone who set a
     * score's staff size in its editor recognises the same control here.
     *
     * As in the booklet's panel, a number the deck computes rather than asks for
     * is drawn as a pair of step buttons (`control => 'step'`): bigger and
     * smaller is the whole of what is wanted, and nobody types 31.0952.
     *
     * Those buttons move a size by `percent` of what it already reads rather
     * than by `step`, which stays what the value is snapped to. A slide's sizes
     * are held in each engine's own units — 4.6667 abc units of lyric, a staff
     * scale of 0.75 — and one step of them is a step of the last decimal: on a
     * slide that is plainly too small it took thirty presses to see anything.
     *
     * @var array<string, array<string, array{type: string, control?: string, min?: float, max?: float, step?: float, percent?: float, label: string, icon?: string, glyph?: string}>>
     */
    private const FIELDS = [
        'gabc' => [
            // exsurge's own units. The screen default is 12, which is about four
            // times what a booklet page asks for.
            'lyricSize' => ['type' => 'number', 'control' => 'step', 'min' => 2, 'max' => 80, 'step' => 0.5, 'percent' => 10, 'label' => 'Lyric size', 'icon' => 'a-large-small'],
            'staffSize' => ['type' => 'number', 'control' => 'step', 'min' => 10, 'max' => 400, 'step' => 5, 'percent' => 10, 'label' => 'Staff size', 'icon' => 'list-chevrons-up-down'],
            'dropCaps' => ['type' => 'boolean', 'label' => 'Drop caps', 'icon' => 'text-initial'],
            'spaceBetweenSystems' => ['type' => 'number', 'min' => -2, 'max' => 4, 'step' => 0.1, 'label' => 'Space between lines', 'icon' => 'between-horizontal-start'],
            'minSpaceBelowStaff' => ['type' => 'number', 'min' => -2, 'max' => 4, 'step' => 0.1, 'label' => 'Min. space below staff', 'icon' => 'align-vertical-space-around'],
            'condensingTolerance' => ['type' => 'number', 'min' => 0, 'max' => 1, 'step' => 0.05, 'label' => 'Condensing tolerance', 'icon' => 'ruler-dimension-line'],
        ],
        'abc' => [
            // 70 points of lyric at 16:9, so the ceiling has to be well past a
            // page's. The floor stays low enough to rescue a slide that has too
            // many verses on it.
            'abcLyricSize' => ['type' => 'number', 'control' => 'step', 'min' => 2, 'max' => 120, 'step' => 0.5, 'percent' => 10, 'label' => 'Lyric size', 'icon' => 'a-large-small'],
            'abcPageScale' => ['type' => 'number', 'control' => 'step', 'min' => 0.2, 'max' => 12, 'step' => 0.05, 'percent' => 10, 'label' => 'Staff scale', 'icon' => 'list-chevrons-up-down'],
            'abcLyricBold' => ['type' => 'boolean', 'label' => 'Bold lyrics', 'icon' => 'bold'],
            'abcNoteSpacing' => ['type' => 'number', 'min' => 1, 'max' => 3, 'step' => 0.1, 'label' => 'Note spacing', 'icon' => 'space'],
            'abcStaffSep' => ['type' => 'number', 'min' => 0, 'max' => 120, 'step' => 1, 'label' => 'Staff separation', 'icon' => 'between-horizontal-start'],
            'abcLyricFirstSkip' => ['type' => 'number', 'min' => 0, 'max' => 3, 'step' => 0.1, 'label' => 'Staff to lyrics', 'icon' => 'align-vertical-space-around'],
            'abcLyricSkip' => ['type' => 'number', 'min' => 0.5, 'max' => 3, 'step' => 0.1, 'label' => 'Lyric line spacing', 'icon' => 'align-vertical-space-between'],
            'abcNoClef' => ['type' => 'boolean', 'label' => 'Hide clef', 'icon' => 'clef-none'],
            // The two knobs that exist for this room and no other. abc2svg draws
            // a stem at 0.7 of a unit, which is a hairline on a page and nothing
            // at all across a nave.
            'abcStemWidth' => ['type' => 'number', 'min' => 0.5, 'max' => 4, 'step' => 0.1, 'label' => 'Stem width', 'icon' => 'minus'],
            'abcStaffLineWidth' => ['type' => 'number', 'min' => 0.5, 'max' => 4, 'step' => 0.1, 'label' => 'Staff line width', 'icon' => 'equal'],
            'abcTranspose' => ['type' => 'number', 'min' => -11, 'max' => 11, 'step' => 1, 'label' => 'Transpose', 'icon' => 'musical-note'],
        ],
        // An uploaded score reaches a screen as a picture of a page, already
        // fitted into the slide. This is the way down from there, for the scan
        // whose margins are so wide that filling the screen leaves the music
        // small anyway.
        'file' => [
            'fileZoom' => ['type' => 'number', 'min' => 0.2, 'max' => 1, 'step' => 0.05, 'label' => 'Size (×)', 'icon' => 'zoom-in'],
        ],
        // A screen of words. It has no engine and no author's layout behind it
        // — the words were typed into this row — so its whole table is the two
        // numbers the deck would otherwise decide for it, each a factor of the
        // deck's own: how large the words are set, and how far apart their lines
        // stand. Unlike a score's knobs these are not per ratio in meaning, but
        // they are stored per ratio like everything else here, because a size
        // that fills a widescreen overflows a square one.
        'text' => [
            'textSizeScale' => ['type' => 'number', 'min' => 0.3, 'max' => 4, 'step' => 0.05, 'label' => 'Text size (×)', 'icon' => 'a-large-small'],
            'textLineHeight' => ['type' => 'number', 'min' => 0.8, 'max' => 3, 'step' => 0.05, 'label' => 'Line spacing', 'icon' => 'align-vertical-space-between'],
        ],
        'chordpro' => [
            'chordproFontSize' => ['type' => 'number', 'control' => 'step', 'min' => 6, 'max' => 200, 'step' => 1, 'percent' => 10, 'label' => 'Font size', 'icon' => 'a-large-small'],
            // A second column on a projector is a second thing to find, but a
            // long hymn on a square screen can want one.
            'chordproColumns' => ['type' => 'number', 'min' => 1, 'max' => 2, 'step' => 1, 'label' => 'Columns', 'icon' => 'view-columns'],
            'chordproTranspose' => ['type' => 'number', 'min' => -11, 'max' => 11, 'step' => 1, 'label' => 'Transpose', 'icon' => 'musical-note'],
            'chordproGermanNotation' => ['type' => 'boolean', 'label' => 'German notation (H = B, B = B♭)', 'glyph' => 'H'],
        ],
        'aretino' => [
            'aretinoLyricSize' => ['type' => 'number', 'control' => 'step', 'min' => 4, 'max' => 120, 'step' => 0.5, 'percent' => 10, 'label' => 'Lyric size (pt)', 'icon' => 'a-large-small'],
            'aretinoStaffSize' => ['type' => 'number', 'control' => 'step', 'min' => 1, 'max' => 40, 'step' => 0.5, 'percent' => 10, 'label' => 'Staff height (mm)', 'icon' => 'list-chevrons-up-down'],
            'aretinoStaffGap' => ['type' => 'number', 'min' => 0, 'max' => 10, 'step' => 0.5, 'label' => 'Staff gap', 'icon' => 'between-horizontal-start'],
            'aretinoHideRepeatClef' => ['type' => 'boolean', 'label' => 'Hide repeated clef', 'icon' => 'clef-none'],
        ],
    ];

    /**
     * The faces that survive an export — what a stored value is validated
     * against. Shared with the booklet deliberately: a face the exporter cannot
     * embed is a face that will not reach a PDF from either document.
     *
     * @return list<string>
     */
    public static function fontOptions(): array
    {
        return BookletSettingFields::fontOptions();
    }

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

                if (in_array($family, self::fontOptions(), true)) {
                    // Stored quoted, the way the score editor's selects emit it.
                    $clean[$key] = "'".$family."'";
                }
            }
        }

        return $clean;
    }

    /**
     * The same, for a whole column: shape first, then the keys.
     *
     * This is the shape `projection_slides.settings_override` is stored in, and
     * the reason it is a shape rather than a bucket is the one the score's own
     * settings column gives — a number chosen against a widescreen is not an
     * answer about a square screen. Unknown shapes go the way unknown keys do,
     * and a shape left holding nothing is dropped rather than stored empty.
     *
     * @param  array<string, mixed>  $override
     * @return array<string, array<string, mixed>>
     */
    public static function sanitizeByRatio(?string $format, array $override): array
    {
        $clean = [];

        foreach ($override as $ratio => $bucket) {
            if (! is_string($ratio) || ProjectionRatio::tryFrom($ratio) === null || ! is_array($bucket)) {
                continue;
            }

            $sanitized = self::sanitize($format, $bucket);

            if ($sanitized !== []) {
                $clean[$ratio] = $sanitized;
            }
        }

        return $clean;
    }

    /**
     * The controls to render for a format, labels translated.
     *
     * @return list<array{key: string, type: string, control?: string, min?: float, max?: float, step?: float, percent?: float, label: string, icon?: string, glyph?: string}>
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
}
