<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MusicPlanSlotPlan>
 */
class MusicPlanSlotPlanFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Models\MusicPlanSlotPlan>
     */
    protected $model = \App\Models\MusicPlanSlotPlan::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'music_plan_id' => \App\Models\MusicPlan::factory(),
            'music_plan_slot_id' => \App\Models\MusicPlanSlot::factory(),
            'sequence' => $this->faker->numberBetween(1, 10),
        ];
    }
}