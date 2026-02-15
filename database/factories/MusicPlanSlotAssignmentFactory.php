<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MusicPlanSlotAssignment>
 */
class MusicPlanSlotAssignmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Models\MusicPlanSlotAssignment>
     */
    protected $model = \App\Models\MusicPlanSlotAssignment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'music_sequence' => $this->faker->numberBetween(1, 10),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
