<?php

namespace Database\Factories;

use App\Models\Music;
use App\Models\MusicVerification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MusicVerification>
 */
class MusicVerificationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Models\MusicVerification>
     */
    protected $model = MusicVerification::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'music_id' => Music::factory(),
            'verifier_id' => User::factory(),
            'field_name' => $this->faker->randomElement(['title', 'subtitle', 'custom_id', 'collection', 'genre', 'author', 'url']),
            'pivot_reference' => $this->faker->optional()->randomNumber(),
            'status' => $this->faker->randomElement(['pending', 'verified', 'rejected']),
            'notes' => $this->faker->optional()->sentence(),
            'verified_at' => $this->faker->optional()->dateTime(),
        ];
    }

    /**
     * Indicate that the verification is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'verified_at' => null,
        ]);
    }

    /**
     * Indicate that the verification is verified.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'verified',
            'verified_at' => now(),
        ]);
    }

    /**
     * Indicate that the verification is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'verified_at' => now(),
        ]);
    }

    /**
     * Indicate that the verification is for a specific field.
     */
    public function forField(string $fieldName): static
    {
        return $this->state(fn (array $attributes) => [
            'field_name' => $fieldName,
        ]);
    }

    /**
     * Indicate that the verification has a pivot reference.
     */
    public function withPivot(int $pivotReference): static
    {
        return $this->state(fn (array $attributes) => [
            'pivot_reference' => $pivotReference,
        ]);
    }
}
