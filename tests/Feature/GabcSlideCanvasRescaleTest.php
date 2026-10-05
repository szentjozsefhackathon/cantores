<?php

use App\Enums\ProjectionRatio;
use App\Models\ProjectionSlide;
use App\Models\ProjectionStyle;
use App\Models\Score;
use App\Models\User;

/**
 * The migration that moved GABC's 4:3 and 1:1 slides off a constant 1920-unit
 * width onto the canvas every other format engraves on. Its lengths shrink by
 * the ratio of the widths, so a slide looks exactly as it did.
 */
$migration = fn () => require database_path('migrations/2026_10_05_171432_rescale_gabc_screen_settings_to_the_slide_canvas.php');

it('scales a score\'s narrower screens and leaves 16:9 and the page alone', function () use ($migration) {
    $score = Score::factory()->create([
        'settings' => [
            'gabc' => [
                '16/9' => ['lyricSize' => 12, 'staffSize' => 80],
                '4/3' => ['lyricSize' => 12, 'staffSize' => 80, 'condensingTolerance' => 0.9],
                '1/1' => ['lyricSize' => 16],
                'paper' => ['lyricSize' => 4],
            ],
            'abc' => ['4/3' => ['abcLyricSize' => 31]],
        ],
    ]);

    $migration()->up();

    $settings = $score->fresh()->settings;

    expect($settings['gabc']['16/9'])->toEqual(['lyricSize' => 12, 'staffSize' => 80])
        ->and($settings['gabc']['4/3'])->toEqual(['lyricSize' => 9, 'staffSize' => 60, 'condensingTolerance' => 0.9])
        ->and($settings['gabc']['1/1']['lyricSize'])->toEqual(9)
        ->and($settings['gabc']['paper'])->toEqual(['lyricSize' => 4])
        ->and($settings['abc']['4/3'])->toEqual(['abcLyricSize' => 31]);
});

it('scales saved defaults, a style by its own ratio, and a slide\'s own sizes', function () use ($migration) {
    $user = User::factory()->create(['score_settings' => ['gabc' => ['1/1' => ['staffSize' => 80]]]]);
    $wide = ProjectionStyle::factory()->create(['settings' => ['gabc' => ['lyricSize' => 12]]]);
    $square = ProjectionStyle::factory()->create(['ratio' => ProjectionRatio::FourThree, 'settings' => ['gabc' => ['lyricSize' => 12]]]);
    $slide = ProjectionSlide::factory()->create([
        'settings_override' => ['4/3' => ['staffSize' => 100], '16/9' => ['staffSize' => 100]],
    ]);

    $migration()->up();

    expect($user->fresh()->score_settings['gabc']['1/1']['staffSize'])->toEqual(45)
        ->and($wide->fresh()->settings['gabc']['lyricSize'])->toEqual(12)
        ->and($square->fresh()->settings['gabc']['lyricSize'])->toEqual(9)
        ->and($slide->fresh()->settings_override)->toEqual(['4/3' => ['staffSize' => 75], '16/9' => ['staffSize' => 100]]);
});

it('puts everything back when rolled back', function () use ($migration) {
    $score = Score::factory()->create(['settings' => ['gabc' => ['1/1' => ['lyricSize' => 12, 'staffSize' => 80]]]]);

    $migration()->up();
    $migration()->down();

    expect($score->fresh()->settings['gabc']['1/1'])->toEqual(['lyricSize' => 12, 'staffSize' => 80]);
});
