<?php

namespace Database\Factories;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<Screen>
 */
class ScreenFactory extends Factory
{
    protected $model = Screen::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'device_id' => (string) Str::uuid(),
            'device_pairing_id' => null,
            'session_id' => Str::random(40),
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0',
            'last_seen_at' => Carbon::now(),
        ];
    }

    /**
     * A screen nobody has heard from since before the service — a laptop closed
     * and carried home, or a tab shut without warning.
     */
    public function stale(): static
    {
        return $this->state(fn (array $attributes): array => [
            'last_seen_at' => Carbon::now()->subMinutes(Screen::STALE_MINUTES + 1),
        ]);
    }
}
