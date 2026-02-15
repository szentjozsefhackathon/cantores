<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Celebration>
 */
class CelebrationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'celebration_key' => $this->faker->unique()->randomNumber(),
            'actual_date' => $this->faker->date(),
            'name' => $this->faker->words(3, true),
            'season' => $this->faker->numberBetween(0, 10),
            'season_text' => $this->faker->word(),
            'week' => $this->faker->numberBetween(0, 52),
            'day' => $this->faker->numberBetween(0, 6),
            'readings_code' => $this->faker->lexify('???###'),
            'year_letter' => $this->faker->randomElement(['A', 'B', 'C']),
            'year_parity' => $this->faker->randomElement(['I', 'II']),
        ];
    }
}
