<?php

use App\Models\Score;
use App\Models\User;

/**
 * The migration that moved GABC and ABC off their nominal canvases onto a real
 * 170 mm page.
 *
 * Every length in a saved bucket is multiplied by the ratio of the new page to
 * the old, which leaves a score looking exactly as its author left it while the
 * numbers underneath start meaning millimetres and points. What is worth testing
 * is that: the proportions survive, the projector ratios are left alone, and
 * nothing that is a ratio rather than a length gets scaled with them.
 */
$migration = fn () => require database_path('migrations/2026_09_10_093658_rescale_score_settings_to_a_physical_page.php');

$gabcFactor = 643 / 1920;
$abcFactor = 643 / 1700;

it('restates a paper bucket in the units of a real page', function () use ($migration, $gabcFactor, $abcFactor) {
    $score = Score::factory()->create([
        'settings' => [
            'gabc' => [
                'paper' => [
                    'lyricSize' => 12,
                    'staffSize' => 80,
                    'minLyricWordSpacing' => 6,
                    'hyphenWidth' => 3,
                    'condensingTolerance' => 0.9,
                    'lyricFont' => "'EB Garamond'",
                ],
            ],
            'abc' => [
                'paper' => [
                    'abcLyricSize' => 12,
                    'abcPageScale' => 2.3,
                    'abcPageWidth' => 1700,
                    'abcStaffSep' => 36,
                    'abcNoteSpacing' => 1.4,
                ],
            ],
        ],
    ]);

    $migration()->up();

    $settings = $score->fresh()->settings;

    expect($settings['gabc']['paper']['lyricSize'])->toBe(round(12 * $gabcFactor, 4))
        ->and($settings['gabc']['paper']['staffSize'])->toBe(round(80 * $gabcFactor, 4))
        ->and($settings['gabc']['paper']['minLyricWordSpacing'])->toBe(round(6 * $gabcFactor, 4))
        ->and($settings['gabc']['paper']['hyphenWidth'])->toBe(round(3 * $gabcFactor, 4))
        ->and($settings['abc']['paper']['abcLyricSize'])->toBe(round(12 * $abcFactor, 4))
        ->and($settings['abc']['paper']['abcPageScale'])->toBe(round(2.3 * $abcFactor, 4))
        ->and($settings['abc']['paper']['abcPageWidth'])->toBe(643);

    // A ratio is not a length. Scaling these would change the engraving rather
    // than restate it.
    expect($settings['gabc']['paper']['condensingTolerance'])->toBe(0.9)
        ->and($settings['gabc']['paper']['lyricFont'])->toBe("'EB Garamond'")
        ->and($settings['abc']['paper']['abcNoteSpacing'])->toBe(1.4)
        // abc2svg multiplies the separation by the page scale, so it has already
        // shrunk with the rest of the drawing.
        ->and($settings['abc']['paper']['abcStaffSep'])->toBe(36);
});

it('leaves the projector ratios on their own screen', function () use ($migration) {
    $score = Score::factory()->create([
        'settings' => [
            'abc' => [
                '16/9' => ['abcLyricSize' => 31, 'abcPageScale' => 3.1, 'abcPageWidth' => 1920],
                'paper' => ['abcLyricSize' => 12],
            ],
            'gabc' => [
                '4/3' => ['lyricSize' => 30, 'staffSize' => 160],
            ],
        ],
    ]);

    $migration()->up();

    $settings = $score->fresh()->settings;

    expect($settings['abc']['16/9'])->toBe(['abcLyricSize' => 31, 'abcPageScale' => 3.1, 'abcPageWidth' => 1920])
        ->and($settings['gabc']['4/3'])->toBe(['lyricSize' => 30, 'staffSize' => 160]);
});

it('moves a bucket saved under the legacy auto key too', function () use ($migration, $gabcFactor) {
    $score = Score::factory()->create([
        'settings' => ['gabc' => ['auto' => ['lyricSize' => 12, 'staffSize' => 80]]],
    ]);

    $migration()->up();

    expect($score->fresh()->settings['gabc']['auto']['staffSize'])->toBe(round(80 * $gabcFactor, 4));
});

it('moves a person\'s saved defaults with their scores', function () use ($migration, $abcFactor) {
    $user = User::factory()->create([
        'score_settings' => ['abc' => ['paper' => ['abcPageScale' => 2.3, 'abcPageWidth' => 1700]]],
    ]);

    $migration()->up();

    $defaults = $user->fresh()->score_settings;

    expect($defaults['abc']['paper']['abcPageScale'])->toBe(round(2.3 * $abcFactor, 4))
        ->and($defaults['abc']['paper']['abcPageWidth'])->toBe(643);
});

it('puts a score back where it was when rolled back', function () use ($migration) {
    $original = [
        'gabc' => ['paper' => ['lyricSize' => 12, 'staffSize' => 80]],
        'abc' => ['paper' => ['abcLyricSize' => 12, 'abcPageScale' => 2.3, 'abcPageWidth' => 1700]],
    ];
    $score = Score::factory()->create(['settings' => $original]);

    $migration()->up();
    $migration()->down();

    $restored = $score->fresh()->settings;

    expect($restored['gabc']['paper']['staffSize'])->toEqualWithDelta(80, 0.01)
        ->and($restored['abc']['paper']['abcPageScale'])->toEqualWithDelta(2.3, 0.01)
        ->and($restored['abc']['paper']['abcPageWidth'])->toBe(1700);
});

it('leaves alone a score with no settings, and the formats that were physical already', function () use ($migration) {
    $bare = Score::factory()->create(['settings' => null]);
    $aretino = Score::factory()->create([
        'settings' => [
            'aretino' => ['paper' => ['aretinoStaffSize' => 7, 'aretinoLyricSize' => 10]],
            'chordpro' => ['auto' => ['chordproFontSize' => 16]],
        ],
    ]);

    $migration()->up();

    expect($bare->fresh()->settings)->toBeNull()
        ->and($aretino->fresh()->settings)->toBe([
            'aretino' => ['paper' => ['aretinoStaffSize' => 7, 'aretinoLyricSize' => 10]],
            'chordpro' => ['auto' => ['chordproFontSize' => 16]],
        ]);
});
