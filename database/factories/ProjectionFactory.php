<?php

namespace Database\Factories;

use App\Enums\ProjectionRatio;
use App\Enums\ProjectionTextTheme;
use App\Models\MusicPlan;
use App\Models\Projection;
use App\Models\ProjectionStyle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Projection>
 */
class ProjectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'music_plan_id' => null,
            'title' => fake()->words(3, true),
            'ratio' => ProjectionRatio::SixteenNine,
            'text_theme' => ProjectionTextTheme::Dark,
            'text_size_scale' => 1.0,
            'text_line_height' => 1.45,
        ];
    }

    public function fourThree(): static
    {
        return $this->state(['ratio' => ProjectionRatio::FourThree]);
    }

    /** Shown in a style, at the style's own ratio. */
    public function withStyle(ProjectionStyle $style): static
    {
        return $this->state([
            'user_id' => $style->user_id,
            'projection_style_id' => $style->id,
            'ratio' => $style->ratio,
        ]);
    }

    public function square(): static
    {
        return $this->state(['ratio' => ProjectionRatio::OneOne]);
    }

    public function forPlan(MusicPlan $plan): static
    {
        return $this->state([
            'music_plan_id' => $plan->getKey(),
            'user_id' => $plan->user_id,
        ]);
    }
}
