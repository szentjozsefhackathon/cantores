<?php

namespace Database\Factories;

use App\Models\Season;
use App\Models\SeasonMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeasonMember>
 */
class SeasonMemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'season_id' => Season::factory(),
            'name' => fake()->unique()->firstName(),
            'user_id' => null,
        ];
    }
}
