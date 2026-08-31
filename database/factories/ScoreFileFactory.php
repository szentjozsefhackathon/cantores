<?php

namespace Database\Factories;

use App\Enums\ScoreFileRenderStatus;
use App\Enums\ScoreFileRights;
use App\Models\Score;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ScoreFile>
 */
class ScoreFileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->slug(2).'.mscz';

        return [
            'score_id' => Score::factory()->linksOnly(),
            'path' => '',
            'original_name' => $name,
            'label' => null,
            'mime' => 'application/x-musescore',
            'size_bytes' => $this->faker->numberBetween(2000, 500000),
            'checksum' => hash('sha256', $name.$this->faker->uuid()),
            'rights' => ScoreFileRights::OwnWork,
            'render_status' => ScoreFileRenderStatus::Pending,
            'has_thumbnail' => false,
        ];
    }

    /**
     * The path is derived from the id, so it can only be filled in once the row
     * exists — the uploader does the same thing.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (\App\Models\ScoreFile $scoreFile): void {
            if ($scoreFile->path === '') {
                $scoreFile->update(['path' => $scoreFile->directory().'/source.'.$scoreFile->extension()]);
            }
        });
    }

    public function ready(int $pageCount = 1): static
    {
        return $this->state([
            'render_status' => ScoreFileRenderStatus::Ready,
            'render_error' => null,
            'has_thumbnail' => true,
            'page_count' => $pageCount,
            'rendered_at' => now(),
        ]);
    }

    /**
     * A PDF upload: already engraved, so the renderer only cuts it into pages.
     */
    public function pdf(?string $label = null): static
    {
        $name = $this->faker->slug(2).'.pdf';

        return $this->state([
            'original_name' => $name,
            'label' => $label,
            'mime' => 'application/pdf',
            'checksum' => hash('sha256', $name),
            'path' => '',
        ]);
    }

    public function failed(string $error = 'Score rendering failed.'): static
    {
        return $this->state([
            'render_status' => ScoreFileRenderStatus::Failed,
            'render_error' => $error,
        ]);
    }
}
