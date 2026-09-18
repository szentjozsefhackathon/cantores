<?php

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
        ['n' => 1, 'label' => '1'],
        ['n' => 2, 'label' => '2'],
        ['n' => 3, 'label' => 'Refrén'],
        ['n' => 4, 'label' => 'Refrén'],
    ]);
});

it('reads a bare marker as an unlabelled section', function () {
    $abc = "X:1\nK:G\n%section\nA B|\n%section   \nc d|\n";

    expect(ScoreSections::list($abc))->toBe([
        ['n' => 1, 'label' => null],
        ['n' => 2, 'label' => null],
    ]);
});

it('checks a reference against the sections a score actually has', function () {
    $abc = "X:1\nK:G\n%section 1\nA B|\n";

    expect(ScoreSections::has($abc, 1))->toBeTrue()
        ->and(ScoreSections::has($abc, 2))->toBeFalse()
        ->and(ScoreSections::has(null, 1))->toBeFalse();
});
