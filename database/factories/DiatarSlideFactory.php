<?php

namespace Database\Factories;

use App\Models\DiatarSlide;
use App\Models\DiatarSong;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiatarSlide>
 */
class DiatarSlideFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'diatar_song_id' => DiatarSong::factory(),
            'source_order' => fake()->unique()->numberBetween(1, 100000),
            'external_id' => mb_strtoupper(fake()->regexify('[A-F0-9]{8}')),
            'verse_name' => fake()->numerify('#. versszak'),
            'is_exportable' => true,
            'last_seen_sync_run_id' => fn (array $attributes) => DiatarSong::find($attributes['diatar_song_id'])?->last_seen_sync_run_id,
        ];
    }

    public function malformed(): static
    {
        return $this->state([
            'external_id' => null,
            'is_exportable' => false,
            'diagnostic_reason' => 'invalid_slide_id',
        ]);
    }
}
