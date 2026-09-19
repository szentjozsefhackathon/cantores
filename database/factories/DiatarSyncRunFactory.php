<?php

namespace Database\Factories;

use App\Enums\DiatarSyncStatus;
use App\Models\DiatarSyncRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiatarSyncRun>
 */
class DiatarSyncRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_revision' => fake()->sha1(),
            'status' => DiatarSyncStatus::Completed,
            'fetched_count' => 1,
            'indexed_count' => 1,
            'skipped_count' => 0,
            'unavailable_count' => 0,
            'warning_count' => 0,
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ];
    }
}
