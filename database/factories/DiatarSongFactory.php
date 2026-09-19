<?php

namespace Database\Factories;

use App\Models\DiatarBook;
use App\Models\DiatarSong;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiatarSong>
 */
class DiatarSongFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'diatar_book_id' => DiatarBook::factory(),
            'title' => fake()->numerify('###').' '.fake()->words(3, true),
            'reference' => fake()->numerify('###'),
            'source_order' => fake()->unique()->numberBetween(1, 100000),
            'available' => true,
            'last_seen_sync_run_id' => fn (array $attributes) => DiatarBook::find($attributes['diatar_book_id'])?->last_seen_sync_run_id,
        ];
    }
}
