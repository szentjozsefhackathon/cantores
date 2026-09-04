<?php

namespace Database\Factories;

use App\Enums\ScoreRightsClaimantCapacity;
use App\Enums\ScoreRightsReportStatus;
use App\Models\Score;
use App\Models\ScorePublication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ScoreRightsReport>
 */
class ScoreRightsReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'score_id' => Score::factory(),
            'status' => ScoreRightsReportStatus::Open,
            'capacity' => ScoreRightsClaimantCapacity::RightsHolder,
            'claim' => $this->faker->sentence(12),
            'reporter_name' => $this->faker->name(),
            'reporter_email' => $this->faker->safeEmail(),
        ];
    }

    /**
     * File the report against the given score's live publication.
     */
    public function against(Score $score): static
    {
        return $this->state([
            'score_id' => $score->getKey(),
            'score_publication_id' => ScorePublication::query()
                ->where('score_id', $score->getKey())
                ->value('id'),
        ]);
    }

    public function dismissed(): static
    {
        return $this->state([
            'status' => ScoreRightsReportStatus::Dismissed,
            'handled_at' => now(),
            'resolution_notes' => $this->faker->sentence(8),
        ]);
    }
}
