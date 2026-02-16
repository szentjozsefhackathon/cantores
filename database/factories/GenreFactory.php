<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Genre>
 */
class GenreFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
        ];
    }

    /**
     * Create a genre with the organist name.
     */
    public function organist(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'organist',
        ]);
    }

    /**
     * Create a genre with the guitarist name.
     */
    public function guitarist(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'guitarist',
        ]);
    }

    /**
     * Create a genre with the other name.
     */
    public function other(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'other',
        ]);
    }
}
