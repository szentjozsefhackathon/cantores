<?php

namespace Database\Factories;

use App\Models\SeasonEvent;
use App\Models\SeasonEventAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeasonEventAttachment>
 */
class SeasonEventAttachmentFactory extends Factory
{
    /**
     * A plain link; booklets and plans are attached through
     * SeasonSharingService, which also lends them.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'season_event_id' => SeasonEvent::factory(),
            'url' => fake()->url(),
            'label' => fake()->words(2, true),
            'position' => 1,
        ];
    }
}
