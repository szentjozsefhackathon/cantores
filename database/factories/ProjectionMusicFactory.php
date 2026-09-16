<?php

namespace Database\Factories;

use App\Models\Music;
use App\Models\Projection;
use App\Models\ProjectionMusic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectionMusic>
 */
class ProjectionMusicFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'projection_id' => Projection::factory(),
            'music_id' => Music::factory(),
            'music_plan_slot_plan_id' => null,
            'sequence' => 0,
        ];
    }
}
