<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MusicAssignmentFlag>
 */
class MusicAssignmentFlagFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Models\MusicAssignmentFlag>
     */
    protected $model = \App\Models\MusicAssignmentFlag::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['important', 'alternative', 'low_priority']),
        ];
    }
}
