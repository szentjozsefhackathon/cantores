<?php

namespace Database\Factories;

use App\Models\Booklet;
use App\Models\Score;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BookletScore>
 */
class BookletScoreFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booklet_id' => Booklet::factory(),
            'score_id' => Score::factory(),
            'sequence' => 0,
            'settings_override' => null,
            'start_on_new_page' => false,
            'show_variation' => false,
            'show_music_title' => false,
        ];
    }

    /**
     * A paragraph of instructions rather than a score.
     */
    public function text(string $markdown = 'Álljunk fel.'): static
    {
        return $this->state(fn (): array => [
            'score_id' => null,
            'text' => $markdown,
        ]);
    }
}
