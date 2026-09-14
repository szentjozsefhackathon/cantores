<?php

namespace Database\Factories;

use App\Models\DevicePairing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\DevicePairing>
 */
class DevicePairingFactory extends Factory
{
    /**
     * A pending invitation: minted, unscanned, and still worth showing.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'token' => Str::random(DevicePairing::TOKEN_LENGTH),
            'confirmation_code' => DevicePairing::generateConfirmationCode(),
            'requesting_session_id' => Str::random(40),
            'session_id' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36',
            'expires_at' => Carbon::now()->addMinutes(DevicePairing::TOKEN_MINUTES),
            'scanned_at' => null,
            'approved_at' => null,
            'claimed_at' => null,
            'revoked_at' => null,
            'last_seen_at' => null,
        ];
    }

    /**
     * A phone has the code open and the token has stopped rotating.
     */
    public function scanned(): static
    {
        return $this->state([
            'scanned_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addMinutes(DevicePairing::SCANNED_MINUTES),
        ]);
    }

    /**
     * Approved on the phone, waiting for the laptop to collect it.
     */
    public function approved(?User $user = null): static
    {
        return $this->state(fn (): array => [
            'user_id' => $user?->getKey() ?? User::factory(),
            'scanned_at' => Carbon::now(),
            'approved_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addMinutes(DevicePairing::SCANNED_MINUTES),
        ]);
    }

    /**
     * A device that is signed in right now.
     */
    public function claimed(?User $user = null): static
    {
        return $this->approved($user)->state(fn (): array => [
            'session_id' => Str::random(40),
            'claimed_at' => Carbon::now(),
            'last_seen_at' => Carbon::now(),
        ]);
    }

    /**
     * An invitation nobody took up.
     */
    public function expired(): static
    {
        return $this->state(['expires_at' => Carbon::now()->subMinute()]);
    }

    public function revoked(): static
    {
        return $this->state(['revoked_at' => Carbon::now()]);
    }
}
