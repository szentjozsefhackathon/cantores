<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A chord sheet's sections are now its paragraphs, so the rows that chose some
 * of them are renumbered to say the same thing in the new numbering.
 *
 * Until now a ChordPro section ran from one `%section` marker to the next,
 * whatever blank lines stood between; it is now every sung paragraph, and a
 * marker only names the one below it. A row that chose marker 2 chose every
 * paragraph standing between the second marker and the third, so it is given
 * exactly those, in order — the row prints what it printed before. A reference
 * no marker answers to printed nothing before and is dropped; a row left with
 * nothing chosen prints the whole score.
 *
 * Self-contained on purpose: the parser it mirrors (App\Support\ScoreSections)
 * is free to change after today, and this has to keep meaning today.
 */
return new class extends Migration
{
    private const MARKER = '/^\s*%section(?:\s+(.+?))?\s*$/';

    public function up(): void
    {
        foreach (['booklet_scores', 'projection_slides'] as $table) {
            DB::table($table)
                ->join('scores', 'scores.id', '=', "{$table}.score_id")
                ->where('scores.format', 'chordpro')
                ->whereNotNull("{$table}.sections")
                ->select(["{$table}.id", "{$table}.sections", 'scores.content'])
                ->orderBy("{$table}.id")
                ->each(function (object $row) use ($table): void {
                    $references = json_decode((string) $row->sections, true);

                    if (! is_array($references) || $references === []) {
                        return;
                    }

                    $renumbered = $this->renumber((string) $row->content, $references);

                    DB::table($table)->where('id', $row->id)->update([
                        'sections' => $renumbered === [] ? null : json_encode($renumbered),
                    ]);
                });
        }
    }

    /**
     * Paragraph numbering is not marker numbering run backwards — a marker over
     * two paragraphs cannot be told from two markers — so there is no way back.
     */
    public function down(): void {}

    /**
     * @param  list<int>  $references  marker numbers, as a row chose them
     * @return list<int> paragraph numbers, the same parts in the same order
     */
    private function renumber(string $content, array $references): array
    {
        $byMarker = $this->paragraphsByMarker($content);
        $renumbered = [];

        foreach ($references as $reference) {
            foreach ($byMarker[(int) $reference] ?? [] as $paragraph) {
                $renumbered[] = $paragraph;
            }
        }

        return $renumbered;
    }

    /**
     * Which paragraph numbers stand under each marker number.
     *
     * @return array<int, list<int>>
     */
    private function paragraphsByMarker(string $content): array
    {
        $byMarker = [];
        $marker = 0;
        $paragraphs = 0;
        $paragraph = [];

        $close = function () use (&$byMarker, &$marker, &$paragraphs, &$paragraph): void {
            if ($paragraph !== [] && array_filter($paragraph, $this->isSung(...)) !== []) {
                $paragraphs++;

                if ($marker > 0) {
                    $byMarker[$marker][] = $paragraphs;
                }
            }

            $paragraph = [];
        };

        foreach (explode("\n", $content) as $line) {
            if (preg_match(self::MARKER, $line) === 1) {
                $close();
                $marker++;

                continue;
            }

            if (trim($line) === '') {
                $close();

                continue;
            }

            $paragraph[] = $line;
        }

        $close();

        return $byMarker;
    }

    private function isSung(string $line): bool
    {
        $trimmed = trim($line);

        return $trimmed !== ''
            && preg_match('/^\{.*\}$/', $trimmed) !== 1
            && ! str_starts_with($trimmed, '#')
            && ! str_starts_with($trimmed, '%');
    }
};
