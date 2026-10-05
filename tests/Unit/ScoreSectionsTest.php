<?php

use App\Enums\ScoreFormat;
use App\Support\ScoreSections;

/**
 * The same fixture sources tests/Unit/score-sections.test.mjs reads, so the
 * list the row editor offers agrees with the parts the browser actually cuts.
 */
it('finds no sections in a score with no markers', function () {
    $abc = "X:1\nT:Teszt\nK:G\nA B c d|\n";

    expect(ScoreSections::list($abc))->toBe([])
        ->and(ScoreSections::list(null))->toBe([])
        ->and(ScoreSections::list(''))->toBe([]);
});

it('numbers sections by position, labelled or not, and allows repeated labels', function () {
    $abc = "X:1\nT:Ki Jézus Szívét\nK:G\n%section 1\nA B|\n%section 2\nc d|\n%section Refrén\ne f|\n%section Refrén\ng a|\n";

    expect(ScoreSections::list($abc))->toBe([
        ['n' => 1, 'label' => '1', 'excerpt' => ''],
        ['n' => 2, 'label' => '2', 'excerpt' => ''],
        ['n' => 3, 'label' => 'Refrén', 'excerpt' => ''],
        ['n' => 4, 'label' => 'Refrén', 'excerpt' => ''],
    ]);
});

it('reads a bare marker as an unlabelled section', function () {
    $abc = "X:1\nK:G\n%section\nA B|\n%section   \nc d|\n";

    expect(ScoreSections::list($abc))->toBe([
        ['n' => 1, 'label' => null, 'excerpt' => ''],
        ['n' => 2, 'label' => null, 'excerpt' => ''],
    ]);
});

it('checks a reference against the sections a score actually has', function () {
    $abc = "X:1\nK:G\n%section 1\nA B|\n";

    expect(ScoreSections::has($abc, 1))->toBeTrue()
        ->and(ScoreSections::has($abc, 2))->toBeFalse()
        ->and(ScoreSections::has(null, 1))->toBeFalse();
});

/*
 * The same lead sheet tests/Unit/score-sections.test.mjs cuts: every sung
 * paragraph is a section, a marker names the one below it, and a label typed
 * as lyrics names nothing.
 */
it('lists every sung paragraph of a chord sheet, named by the marker above it', function () {
    $sheet = "{title: Teszt}\n{key: G}\n\n%section Verse 1\n1. [G]Hi this is an ordered\nverse with [D]multiple lines\n\n%section Chorus\nR. This is the [C]refrain\nvery long and [G]repeating\n\nVerse 2:\n2. [G]A label typed as lyrics\nnames nothing\n";

    expect(ScoreSections::list($sheet, ScoreFormat::ChordPro))->toBe([
        ['n' => 1, 'label' => 'Verse 1', 'excerpt' => '1. Hi this is an ordered'],
        ['n' => 2, 'label' => 'Chorus', 'excerpt' => 'R. This is the refrain'],
        ['n' => 3, 'label' => null, 'excerpt' => 'Verse 2:'],
    ]);
});

it('counts a paragraph of directives alone as no section of a chord sheet', function () {
    $sheet = "[C]Első\n\n{comment: Refrén}\n\n[G]Második\n";

    expect(ScoreSections::list($sheet, 'chordpro'))->toHaveCount(2)
        ->and(ScoreSections::has($sheet, 2, 'chordpro'))->toBeTrue()
        ->and(ScoreSections::has($sheet, 3, 'chordpro'))->toBeFalse();
});

it('names only the first paragraph under a chord-sheet marker', function () {
    $sheet = "%section Egy\n[C]Első\n\n[G]Második\n";

    expect(array_column(ScoreSections::list($sheet, 'chordpro'), 'label'))->toBe(['Egy', null]);
});
