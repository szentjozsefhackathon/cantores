<?php

namespace Database\Factories;

use App\Enums\ProjectionRatio;
use App\Models\MusicPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Projection>
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
        ];
    }

    public function fourThree(): static
    {
        return $this->state(['ratio' => ProjectionRatio::FourThree]);
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
