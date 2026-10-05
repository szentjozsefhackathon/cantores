<?php

use App\Enums\ScoreFormat;
use App\Models\BookletScore;
use App\Models\ProjectionSlide;
use App\Models\Score;

/**
 * A chord sheet's sections became its paragraphs; a row that chose sections by
 * marker number still prints the same parts afterwards.
 */
$migration = fn () => require database_path('migrations/2026_10_05_094345_renumber_chordpro_sections_by_paragraph.php');

$sheet = "{title: Teszt}\n\n%section Egy\n[C]Első\n\n[C]Első folytatás\n\n%section Refrén\n[G]Refrén\n\n%section Kettő\n[D]Második\n";

it('gives a row every paragraph standing under the markers it chose', function () use ($migration, $sheet) {
    $score = Score::factory()->create(['format' => ScoreFormat::ChordPro, 'content' => $sheet]);
    $slide = ProjectionSlide::factory()->create(['score_id' => $score->id, 'sections' => [1, 2, 3, 2]]);
    $entry = BookletScore::factory()->create(['score_id' => $score->id, 'sections' => [3]]);

    $migration()->up();

    expect($slide->fresh()->sections)->toBe([1, 2, 3, 4, 3])
        ->and($entry->fresh()->sections)->toBe([4]);
});

it('drops references no marker answers to, and leaves nothing chosen as the whole score', function () use ($migration) {
    $score = Score::factory()->create(['format' => ScoreFormat::ChordPro, 'content' => "[C]Első\n\n[G]Második\n"]);
    $slide = ProjectionSlide::factory()->create(['score_id' => $score->id, 'sections' => [1]]);

    $migration()->up();

    expect($slide->fresh()->sections)->toBeNull();
});

it('leaves the sections of an engraved score alone', function () use ($migration) {
    $score = Score::factory()->create(['format' => ScoreFormat::Abc, 'content' => "X:1\nK:G\n%section 1\nA B|\n%section 2\nc d|\n"]);
    $slide = ProjectionSlide::factory()->create(['score_id' => $score->id, 'sections' => [2, 1]]);

    $migration()->up();

    expect($slide->fresh()->sections)->toBe([2, 1]);
});
