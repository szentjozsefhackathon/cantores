<?php

namespace Database\Factories;

use App\Models\Folder;
use App\Models\MusicPlan;
use App\Models\Score;
use App\Models\Share;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Share>
 */
class ShareFactory extends Factory
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
            'shareable_type' => Score::class,
            'shareable_id' => Score::factory(),
            'token' => Share::generateToken(),
            'allow_download' => true,
        ];
    }

    /**
     * Share the given model, attributing the grant to its owner.
     */
    public function of(Model $shareable): static
    {
        return $this->state([
            'shareable_type' => $shareable::class,
            'shareable_id' => $shareable->getKey(),
            'user_id' => $shareable->getAttribute('user_id'),
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
