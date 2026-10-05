<?php

namespace Database\Factories;

use App\Enums\ProjectionRatio;
use App\Enums\ProjectionTextTheme;
use App\Models\ProjectionStyle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectionStyle>
 */
class ProjectionStyleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(2, true),
            'ratio' => ProjectionRatio::SixteenNine,
            'settings' => null,
            'text_theme' => ProjectionTextTheme::Dark,
            'text_size_scale' => 1.0,
            'text_line_height' => 1.45,
            'min_scale' => 0.9,
        ];
    }

    public function fourThree(): static
    {
        return $this->state(['ratio' => ProjectionRatio::FourThree]);
    }
}
