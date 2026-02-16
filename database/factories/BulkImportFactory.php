<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BulkImport>
 */
class BulkImportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'collection' => $this->faker->word(),
            'piece' => $this->faker->sentence(),
            'reference' => (string) $this->faker->unique()->numberBetween(1, 1000),
            'batch_number' => $this->faker->numberBetween(1, 10),
        ];
    }
}
