<?php

use App\Models\ProjectionSlide;
use App\Models\ProjectionStyle;
use App\Models\Score;
use App\Models\User;

/**
 * The migration that moved Aretino's slides onto the 1920 x 1080 canvas every
 * other format engraves on. Its sizes are doubled, so a slide looks exactly as
 * it did; what is worth testing is that every place a projector size is kept is
 * reached, and that a page is left alone.
 */
$migration = fn () => require database_path('migrations/2026_10_05_170322_rescale_aretino_screen_settings_to_the_slide_canvas.php');

it('doubles a score\'s projector sizes and leaves its page alone', function () use ($migration) {
    $score = Score::factory()->create([
        'settings' => [
            'aretino' => [
                '16/9' => ['aretinoLyricSize' => 45, 'aretinoStaffSize' => 13, 'aretinoStaffGap' => 1],
                '4/3' => ['aretinoLyricSize' => 40],
                'paper' => ['aretinoLyricSize' => 10, 'aretinoStaffSize' => 7],
            ],
            'abc' => ['16/9' => ['abcLyricSize' => 31]],
        ],
    ]);

    $migration()->up();

    $settings = $score->fresh()->settings;

    expect($settings['aretino']['16/9'])->toBe(['aretinoLyricSize' => 90, 'aretinoStaffSize' => 26, 'aretinoStaffGap' => 1])
        ->and($settings['aretino']['4/3']['aretinoLyricSize'])->toEqual(80)
        ->and($settings['aretino']['paper'])->toBe(['aretinoLyricSize' => 10, 'aretinoStaffSize' => 7])
        ->and($settings['abc']['16/9'])->toBe(['abcLyricSize' => 31]);
});

it('doubles a person\'s saved defaults, a style\'s sizes and a slide\'s own', function () use ($migration) {
    $user = User::factory()->create([
        'score_settings' => ['aretino' => ['16/9' => ['aretinoLyricSize' => 50]]],
    ]);
    $style = ProjectionStyle::factory()->create([
        'settings' => ['aretino' => ['aretinoLyricSize' => 45, 'aretinoStaffSize' => 13], 'abc' => ['abcLyricSize' => 31]],
    ]);
    $slide = ProjectionSlide::factory()->create([
        'settings_override' => ['16/9' => ['aretinoLyricSize' => 30, 'fromStyle' => true]],
    ]);

    $migration()->up();

    expect($user->fresh()->score_settings['aretino']['16/9']['aretinoLyricSize'])->toEqual(100)
        ->and($style->fresh()->settings['aretino'])->toEqual(['aretinoLyricSize' => 90, 'aretinoStaffSize' => 26])
        ->and($style->fresh()->settings['abc'])->toEqual(['abcLyricSize' => 31])
        ->and($slide->fresh()->settings_override['16/9'])->toEqual(['aretinoLyricSize' => 60, 'fromStyle' => true]);
});

it('puts everything back when rolled back', function () use ($migration) {
    $score = Score::factory()->create([
        'settings' => ['aretino' => ['1/1' => ['aretinoLyricSize' => 45, 'aretinoStaffSize' => 13]]],
    ]);

    $migration()->up();
    $migration()->down();

    expect($score->fresh()->settings['aretino']['1/1'])->toEqual(['aretinoLyricSize' => 45, 'aretinoStaffSize' => 13]);
});
