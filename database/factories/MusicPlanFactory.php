<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MusicPlan>
 */
class MusicPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'celebration_name' => fake()->words(3, true),
            'actual_date' => fake()->date(),
            'setting' => fake()->randomElement(['organist', 'guitarist', 'other']),
            'season' => fake()->numberBetween(1, 10),
            'week' => fake()->numberBetween(1, 52),
            'day' => fake()->numberBetween(1, 7),
            'readings_code' => fake()->optional()->regexify('[A-Z]{3}[0-9]{3}'),
            'year_letter' => fake()->optional()->randomElement(['A', 'B', 'C']),
            'year_parity' => fake()->optional()->randomElement(['I', 'II']),
            'is_published' => fake()->boolean(),
        ];
    }
}
