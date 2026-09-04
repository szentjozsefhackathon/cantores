<?php

namespace Database\Factories;

use App\Models\Folder;
use App\Models\MusicPlan;
use App\Models\Score;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Loan>
 */
class LoanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'lendable_type' => Score::class,
            'lendable_id' => Score::factory(),
            'token' => Loan::generateToken(),
            'allow_download' => true,
        ];
    }

    /**
     * Loan the given model, attributing the grant to its owner.
     */
    public function of(Model $lendable): static
    {
        return $this->state([
            'lendable_type' => $lendable::class,
            'lendable_id' => $lendable->getKey(),
            'user_id' => $lendable->getAttribute('user_id'),
        ]);
    }

    public function forFolder(?Folder $folder = null): static
    {
        return $this->of($folder ?? Folder::factory()->create());
    }

    public function forMusicPlan(?MusicPlan $plan = null): static
    {
        return $this->of($plan ?? MusicPlan::factory()->create());
    }

    public function revoked(): static
    {
        return $this->state(['revoked_at' => now()]);
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subDay()]);
    }
}
