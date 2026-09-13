<?php

namespace Database\Factories;

use App\Models\Projection;
use App\Models\Score;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ProjectionSlide>
 */
class ProjectionSlideFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'projection_id' => Projection::factory(),
            'score_id' => Score::factory(),
            'sequence' => 0,
            'settings_override' => null,
            'show_slot' => false,
            'show_music_title' => true,
            'show_variation' => false,
            'show_collections' => false,
        ];
    }

    /** A screen of words rather than a score. */
    public function text(string $markdown = 'Álljunk fel.'): static
    {
        return $this->state(fn (): array => [
            'score_id' => null,
            'text' => $markdown,
        ]);
    }
}
