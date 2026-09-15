<?php

namespace Database\Factories;

use App\Models\Collection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Collection>
 */
class CollectionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Collection>
     */
    protected $model = Collection::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->words(4, true),
            'abbreviation' => $this->faker->optional()->regexify('[A-Z]{2,4}'),
            'author' => $this->faker->optional()->name(),
            'user_id' => User::factory(),
            'is_private' => false,
            'priority' => 100,
        ];
    }

    public function private(): static
    {
        return $this->state(['is_private' => true]);
    }
}
