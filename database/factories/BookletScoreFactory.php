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
        ];
    }
}
