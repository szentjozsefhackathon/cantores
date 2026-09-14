<?php

namespace Database\Factories;

use App\Models\Presentation;
use App\Models\Projection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Presentation>
 */
class PresentationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'projection_id' => Projection::factory(),
            'user_id' => User::factory(),
            'device_pairing_id' => null,
            'entry_id' => null,
            'slide_index' => 0,
            'entry_sequence' => null,
            'blanked' => false,
            'version' => 1,
            'drawn_revision' => null,
            'reveals' => null,
            'started_at' => Carbon::now(),
            'last_seen_at' => Carbon::now(),
            'ended_at' => null,
        ];
    }

    /** One nobody has heard from since well before the stale window. */
    public function stale(): static
    {
        return $this->state(fn (): array => [
            'last_seen_at' => Carbon::now()->subHour(),
        ]);
    }

    /** One whose service is over, said rather than fallen silent. */
    public function ended(): static
    {
        return $this->state(fn (): array => [
            'ended_at' => Carbon::now(),
        ]);
    }
}
