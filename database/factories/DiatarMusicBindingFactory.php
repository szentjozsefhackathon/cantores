<?php

namespace Database\Factories;

use App\Models\DiatarMusicBinding;
use App\Models\DiatarSong;
use App\Models\Music;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiatarMusicBinding>
 */
class DiatarMusicBindingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'music_id' => Music::factory(),
            'diatar_song_id' => DiatarSong::factory(),
            'is_active' => true,
            'editor_note' => fake()->optional()->sentence(),
        ];
    }
}
