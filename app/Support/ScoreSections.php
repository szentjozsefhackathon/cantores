<?php

namespace App\Support;

use App\Enums\ScoreFormat;

/**
 * The `%section` markers a score's source carries, read for the row editors
 * and for validating a row's chosen references.
 *
 * This is a list only. It never cuts a score into the pieces a row prints —
 * that stays the browser's job, done from the score's own source at render
 * time, the same way `ProjectionRenderPayload` already assumes for
 * `%pagebreak`. See resources/js/score-sections.js for the renderer's own
 * copy of the same marker pattern, and plans/score-sections.md for why the
 * two are kept in step by a shared fixture rather than by sharing code.
 *
 * A chord sheet is the exception: every sung paragraph of it is a section,
 * since a blank line is where a lead sheet's author already ends a verse, and a
 * marker only names the paragraph below it. See parseChordproSections in the
 * JavaScript.
 */
class ScoreSections
{
    /**
     * A line matching this starts a section; everything up to the next one, or
     * to the end of the source, belongs to it. The label is optional and is
     * only a name for people to read — it need not be unique.
     */
    private const MARKER = '/^\s*%section(?:\s+(.+?))?\s*$/';

    /**
     * The sections a score's source carries, in the order they are written.
     *
     * Each carries an `excerpt` too — the opening of its words — so a chord
     * sheet's paragraph nobody named can still be told apart from the others in
     * a menu. An engraved score's sections are named by their markers, and their
     * words cannot be had without the engine, so theirs is empty.
     *
     * @return list<array{n: int, label: string|null, excerpt: string}>
     */
    public static function list(?string $content, ScoreFormat|string|null $format = null): array
    {
        if ($content === null || $content === '') {
            return [];
        }

        $format = $format instanceof ScoreFormat ? $format->value : $format;

        if ($format === ScoreFormat::ChordPro->value) {
            return self::chordproSections($content);
        }

        $sections = [];

        foreach (explode("\n", $content) as $line) {
            if (preg_match(self::MARKER, $line, $matches) !== 1) {
                continue;
            }

            $label = trim($matches[1] ?? '');

            $sections[] = [
                'n' => count($sections) + 1,
                'label' => $label === '' ? null : $label,
                'excerpt' => '',
            ];
        }

        return $sections;
    }

    /**
     * A chord sheet's paragraphs that carry something sung, each named by the
     * marker standing directly above it, if any.
     *
     * @return list<array{n: int, label: string|null, excerpt: string}>
     */
    private static function chordproSections(string $content): array
    {
        $sections = [];
        $paragraph = [];
        $label = null;

        $close = function () use (&$sections, &$paragraph, &$label): void {
            if ($paragraph === []) {
                return;
            }

            $sung = array_values(array_filter($paragraph, self::isSungChordproLine(...)));

            if ($sung !== []) {
                $sections[] = [
                    'n' => count($sections) + 1,
                    'label' => $label,
                    'excerpt' => self::excerptOf($sung[0]),
                ];
                $label = null;
            }

            $paragraph = [];
        };

        foreach (explode("\n", $content) as $line) {
            if (preg_match(self::MARKER, $line, $matches) === 1) {
                $close();
                $marked = trim($matches[1] ?? '');
                $label = $marked === '' ? null : $marked;

                continue;
            }

            if (trim($line) === '') {
                $close();

                continue;
            }

            $paragraph[] = $line;
        }

        $close();

        return $sections;
    }

    /**
     * Whether a chord-sheet line is sung, rather than a directive, a comment or
     * a page break between the verses.
     */
    private static function isSungChordproLine(string $line): bool
    {
        $trimmed = trim($line);

        return $trimmed !== ''
            && preg_match('/^\{.*\}$/', $trimmed) !== 1
            && ! str_starts_with($trimmed, '#')
            && ! str_starts_with($trimmed, '%');
    }

    /**
     * The opening words of a line of a chord sheet, its chords taken out.
     */
    private static function excerptOf(string $line): string
    {
        $words = trim(preg_replace('/\s+/', ' ', preg_replace('/\[[^\]]*\]/', '', $line)) ?? '');

        return mb_strlen($words) > 24 ? rtrim(mb_substr($words, 0, 24)).'…' : $words;
    }

    /**
     * Whether a number names one of the score's own sections — what an
     * editor action checks a reference against before writing it.
     */
    public static function has(?string $content, int $number, ScoreFormat|string|null $format = null): bool
    {
        foreach (self::list($content, $format) as $section) {
            if ($section['n'] === $number) {
                return true;
            }
        }

        return false;
    }
}
