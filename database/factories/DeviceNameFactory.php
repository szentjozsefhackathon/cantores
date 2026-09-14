<?php

namespace Database\Factories;

use App\Models\DeviceName;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DeviceName>
 */
class DeviceNameFactory extends Factory
{
    protected $model = DeviceName::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'device_id' => (string) Str::uuid(),
            'name' => null,
            'offered' => true,
        ];
    }

    /**
     * A device its owner has said is not a screen — the laptop at home, signed
     * in as the same person and describing itself with the same string.
     */
    public function notAScreen(): static
    {
        return $this->state(fn (array $attributes): array => ['offered' => false]);
    }
}
