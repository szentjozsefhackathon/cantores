<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MusicPlanTemplate>
 */
class MusicPlanTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
            'priority' => null,
        ];
    }

    public function withPriority(int $priority): static
    {
        return $this->state(['priority' => $priority]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
