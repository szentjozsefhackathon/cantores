<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Realm>
 */
class RealmFactory extends Factory
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
     * Create a realm with the organist name.
     */
    public function organist(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'organist',
        ]);
    }

    /**
     * Create a realm with the guitarist name.
     */
    public function guitarist(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'guitarist',
        ]);
    }

    /**
     * Create a realm with the other name.
     */
    public function other(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'other',
        ]);
    }
}
