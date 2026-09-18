<?php

namespace App\Support;

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
     * @return list<array{n: int, label: string|null}>
     */
    public static function list(?string $content): array
    {
        if ($content === null || $content === '') {
            return [];
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
            ];
        }

        return $sections;
    }

    /**
     * Whether a number names one of the score's own sections — what an
     * editor action checks a reference against before writing it.
     */
    public static function has(?string $content, int $number): bool
    {
        foreach (self::list($content) as $section) {
            if ($section['n'] === $number) {
                return true;
            }
        }

        return false;
    }
}
