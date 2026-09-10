<?php

use App\Models\Booklet;
use App\Models\BookletScore;
use App\Models\Score;
use App\Models\ScoreVersion;
use App\Models\User;

/**
 * The migration that takes the last retired face out of the app.
 *
 * Swapping a name is the easy half. The half worth testing is the arithmetic
 * beside it: a point is a measure of the em rather than of anything visible, so
 * a score handed a new face at its old size comes out a ninth larger, which on a
 * tuned page is a line break. Every size beside a swapped face is therefore
 * restated by the ratio of the two x-heights — and nothing else in the bucket is
 * touched.
 */
$migration = fn () => require database_path('migrations/2026_09_10_115930_retire_lora.php');

/** OPTICAL_X_HEIGHT['Lora'] / OPTICAL_X_HEIGHT['Merriweather'], from booklet-geometry.js. */
$factor = 0.500 / 0.555;

it('restates a score in Merriweather at the size it was set in', function () use ($migration, $factor) {
    $score = Score::factory()->create([
        'settings' => [
            'abc' => [
                'paper' => [
                    // ABC stores the face bare: abc2svg reads it out of a
                    // %%vocalfont directive, where a quote is part of the name.
                    'abcLyricFont' => 'Lora',
                    'abcLyricSize' => 12,
                    'abcNoteSpacing' => 1.4,
                ],
            ],
            'chordpro' => [
                'paper' => ['chordproFontFamily' => "'Lora'", 'chordproFontSize' => 14.6667],
            ],
        ],
    ]);

    $migration()->up();

    $settings = $score->fresh()->settings;

    expect($settings['abc']['paper']['abcLyricFont'])->toBe('Merriweather')
        ->and($settings['abc']['paper']['abcLyricSize'])->toBe(round(12 * $factor, 4))
        ->and($settings['chordpro']['paper']['chordproFontFamily'])->toBe("'Merriweather'")
        ->and($settings['chordpro']['paper']['chordproFontSize'])->toBe(round(14.6667 * $factor, 4));

    // A spacing is not a size. Restating it would change the engraving rather
    // than hold it still.
    expect($settings['abc']['paper']['abcNoteSpacing'])->toBe(1.4);
});

it('leaves a score in a face that is staying exactly as it was', function () use ($migration) {
    $original = [
        'abc' => ['paper' => ['abcLyricFont' => 'Merriweather', 'abcLyricSize' => 12]],
        'gabc' => ['paper' => ['lyricFont' => "'EB Garamond'", 'lyricSize' => 4]],
    ];
    $score = Score::factory()->create(['settings' => $original]);

    $migration()->up();

    expect($score->fresh()->settings)->toBe($original);
});

/**
 * The rescale migration could leave the projector buckets alone because it was
 * about millimetres of paper. This one cannot: a slide names a face like any
 * other bucket, and a face that is gone is gone on a screen too.
 */
it('swaps the face in a projector bucket as well as on paper', function () use ($migration, $factor) {
    $score = Score::factory()->create([
        'settings' => [
            'abc' => [
                '16/9' => ['abcLyricFont' => 'Lora', 'abcLyricSize' => 31],
                'paper' => ['abcLyricFont' => 'Lora', 'abcLyricSize' => 12],
            ],
        ],
    ]);

    $migration()->up();

    $settings = $score->fresh()->settings;

    expect($settings['abc']['16/9']['abcLyricFont'])->toBe('Merriweather')
        ->and($settings['abc']['16/9']['abcLyricSize'])->toBe(round(31 * $factor, 4))
        ->and($settings['abc']['paper']['abcLyricFont'])->toBe('Merriweather');
});

it('moves a version and a person\'s saved defaults with the score', function () use ($migration) {
    $version = ScoreVersion::factory()->create([
        'settings' => ['aretino' => ['paper' => ['aretinoTextFont' => "'Lora'"]]],
    ]);
    $user = User::factory()->create([
        'score_settings' => ['gabc' => ['paper' => ['lyricFont' => "'Lora'"]]],
    ]);

    $migration()->up();

    expect($version->fresh()->settings['aretino']['paper']['aretinoTextFont'])->toBe("'Merriweather'")
        ->and($user->fresh()->score_settings['gabc']['paper']['lyricFont'])->toBe("'Merriweather'");
});

/**
 * A booklet's per-score nudge is one flat bucket rather than a tree of them,
 * which is why the migration walks a settings blob instead of addressing it by
 * path.
 */
it('swaps the face in a booklet\'s per-score override', function () use ($migration, $factor) {
    $entry = BookletScore::factory()->create([
        'settings_override' => [
            'aretinoTextFont' => "'Lora'",
            'aretinoLyricSize' => 11,
            'aretinoHideRepeatClef' => false,
        ],
    ]);

    $migration()->up();

    expect($entry->fresh()->settings_override)->toBe([
        'aretinoTextFont' => "'Merriweather'",
        'aretinoLyricSize' => round(11 * $factor, 4),
        'aretinoHideRepeatClef' => false,
    ]);
});

/**
 * The one place a size is not restated beside the face. A booklet quotes its
 * lyric size in the reference face and converts it per face as it draws, so the
 * type is already the same apparent height in whichever face it lands in.
 */
it('swaps a booklet\'s face without touching its lyric size', function () use ($migration) {
    $booklet = Booklet::factory()->create(['text_font' => 'Lora', 'lyric_size_pt' => 10.5]);

    $migration()->up();

    $booklet->refresh();

    expect($booklet->text_font)->toBe('Merriweather')
        ->and($booklet->lyric_size_pt)->toBe(10.5);
});
