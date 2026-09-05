<?php

namespace Database\Factories;

use App\Enums\BookletOrientation;
use App\Enums\BookletPageSize;
use App\Models\MusicPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Booklet>
 */
class BookletFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'music_plan_id' => null,
            'title' => fake()->words(3, true),
            'page_size' => BookletPageSize::A5,
            'orientation' => BookletOrientation::Portrait,
            'margin_mm' => 12,
            'lyric_size_pt' => 11,
            'staff_height_mm' => 7,
            'show_titles' => true,
        ];
    }

    public function a4(): static
    {
        return $this->state(['page_size' => BookletPageSize::A4]);
    }

    public function forPlan(MusicPlan $plan): static
    {
        return $this->state([
            'music_plan_id' => $plan->getKey(),
            'user_id' => $plan->user_id,
        ]);
    }
}
