<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Music>
 */
class MusicFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Models\Music>
     */
    protected $model = \App\Models\Music::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->words(3, true),
            'custom_id' => $this->faker->optional()->regexify('[A-Z]{3} \d{3}[a-z]?'),
            'user_id' => \App\Models\User::factory(),
        ];
    }
}
