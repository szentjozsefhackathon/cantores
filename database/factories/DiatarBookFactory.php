<?php

namespace Database\Factories;

use App\Models\DiatarBook;
use App\Models\DiatarSyncRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiatarBook>
 */
class DiatarBookFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $filename = fake()->unique()->slug(2).'.dtx';

        return [
            'source_path' => $filename,
            'title' => fake()->words(3, true),
            'short_name' => fake()->optional()->lexify('???'),
            'group' => fake()->optional()->word(),
            'source_revision' => fake()->sha1(),
            'checksum' => hash('sha256', $filename),
            'available' => true,
            'last_seen_sync_run_id' => DiatarSyncRun::factory(),
            'source_order' => fake()->numberBetween(1, 100),
        ];
    }

    public function unavailable(): static
    {
        return $this->state([
            'available' => false,
            'unavailable_reason' => 'Source is unavailable.',
        ]);
    }
}
