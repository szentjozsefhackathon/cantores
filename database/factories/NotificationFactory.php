<?php

namespace Database\Factories;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Notification>
 */
class NotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => NotificationType::ERROR_REPORT,
            'message' => $this->faker->sentence(10),
            'reporter_id' => User::factory(),
            'notifiable_id' => 1,
            'notifiable_type' => \App\Models\Music::class,
        ];
    }

    /**
     * Indicate that the notification is for a music resource.
     */
    public function forMusic(\App\Models\Music $music): static
    {
        return $this->state(fn (array $attributes) => [
            'notifiable_id' => $music->id,
            'notifiable_type' => \App\Models\Music::class,
        ]);
    }

    /**
     * Indicate that the notification is for a collection resource.
     */
    public function forCollection(\App\Models\Collection $collection): static
    {
        return $this->state(fn (array $attributes) => [
            'notifiable_id' => $collection->id,
            'notifiable_type' => \App\Models\Collection::class,
        ]);
    }

    /**
     * Indicate that the notification is for an author resource.
     */
    public function forAuthor(\App\Models\Author $author): static
    {
        return $this->state(fn (array $attributes) => [
            'notifiable_id' => $author->id,
            'notifiable_type' => \App\Models\Author::class,
        ]);
    }

    /**
     * Indicate the reporter of the notification.
     */
    public function reportedBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'reporter_id' => $user->id,
        ]);
    }
}
