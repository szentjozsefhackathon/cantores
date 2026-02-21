<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\FirstName;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static $counter = 0;
        $counter++;

        // Generate unique names using counter and random string to avoid collisions
        $cityName = 'Test City '.$counter.' '.Str::random(4);
        $firstNameName = 'TestFirstName'.$counter.' '.Str::random(4);

        // Create a unique city for each user to avoid unique constraint violations
        $city = City::firstOrCreate(
            ['name' => $cityName],
            ['name' => $cityName]
        );

        // Create a unique first name for each user
        $firstName = FirstName::firstOrCreate(
            ['name' => $firstNameName],
            ['name' => $firstNameName, 'gender' => 'male']
        );

        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'city_id' => $city->id,
            'first_name_id' => $firstName->id,
        ];
    }

    /**
     * Configure the factory to assign the contributor role after creating a user.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->assignRole('contributor');
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
