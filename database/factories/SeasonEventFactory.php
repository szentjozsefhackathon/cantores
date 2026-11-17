<?php

namespace Database\Factories;

use App\Models\Season;
use App\Models\SeasonEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeasonEvent>
 */
class SeasonEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'season_id' => Season::factory(),
            'name' => fake()->words(2, true),
            'date' => fake()->dateTimeBetween('+1 week', '+6 months')->format('Y-m-d'),
            'time' => null,
            'note' => null,
        ];
    }

    public function past(): static
    {
        return $this->state(fn (): array => [
            'date' => fake()->dateTimeBetween('-6 months', '-1 week')->format('Y-m-d'),
        ]);
    }
}
