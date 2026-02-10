<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MusicPlanSlot>
 */
class MusicPlanSlotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slotNames = [
            'Entrance Procession',
            'Kyrie',
            'Gloria',
            'Psalm',
            'Alleluia',
            'Offertory',
            'Sanctus',
            'Agnus Dei',
            'Communion',
            'Recessional',
        ];

        return [
            'name' => fake()->unique()->randomElement($slotNames),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
