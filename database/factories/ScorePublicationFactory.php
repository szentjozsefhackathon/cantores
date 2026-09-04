<?php

namespace Database\Factories;

use App\Enums\ScoreLicense;
use App\Enums\ScorePublicationStatus;
use App\Models\Score;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ScorePublication>
 */
class ScorePublicationFactory extends Factory
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
            'status' => ScorePublicationStatus::Submitted,
            'license' => ScoreLicense::CcBySa,
            'license_version' => '4.0',
            'source_url' => 'https://www.cpdl.org/wiki/index.php/Example',
            'source_title' => $this->faker->sentence(3),
            'submitted_at' => now(),
        ];
    }

    /**
     * Nominate the given score, attributing the nomination to its owner.
     */
    public function of(Score $score): static
    {
        return $this->state([
            'score_id' => $score->getKey(),
            'submitted_by' => $score->user_id,
        ]);
    }

    public function submitted(): static
    {
        return $this->state([
            'status' => ScorePublicationStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }

    /**
     * A live publication. The fingerprint is left null on purpose — a test that
     * cares about it should approve through ScorePublicationService, which is
     * what computes it.
     */
    public function approved(?User $reviewer = null): static
    {
        return $this->state([
            'status' => ScorePublicationStatus::Approved,
            'reviewer_id' => $reviewer?->getKey() ?? User::factory(),
            'reviewed_at' => now(),
            'published_at' => now(),
        ]);
    }

    public function rejected(?string $notes = null): static
    {
        return $this->state([
            'status' => ScorePublicationStatus::Rejected,
            'reviewer_id' => User::factory(),
            'reviewed_at' => now(),
            'review_notes' => $notes ?? 'The edition is too recent to be public domain.',
        ]);
    }

    public function withdrawn(): static
    {
        return $this->state([
            'status' => ScorePublicationStatus::Withdrawn,
            'unpublished_at' => now(),
        ]);
    }

    public function takenDown(?string $reason = null): static
    {
        return $this->state([
            'status' => ScorePublicationStatus::TakenDown,
            'reviewer_id' => User::factory(),
            'reviewed_at' => now(),
            'unpublished_at' => now(),
            'takedown_reason' => $reason ?? 'The rightholder asked us to remove it.',
        ]);
    }

    public function publicDomain(): static
    {
        return $this->state([
            'license' => ScoreLicense::PublicDomain,
            'composer_death_year' => 1750,
            'edition_is_free' => true,
        ]);
    }

    public function ownWork(): static
    {
        return $this->state([
            'license' => ScoreLicense::OwnWork,
            'outbound_license' => ScoreLicense::CcBySa,
            'source_url' => null,
        ]);
    }
}
