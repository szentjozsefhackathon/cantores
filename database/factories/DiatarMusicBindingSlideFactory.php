<?php

namespace Database\Factories;

use App\Models\DiatarMusicBinding;
use App\Models\DiatarMusicBindingSlide;
use App\Models\DiatarSlide;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiatarMusicBindingSlide>
 */
class DiatarMusicBindingSlideFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'diatar_music_binding_id' => DiatarMusicBinding::factory(),
            'diatar_slide_id' => DiatarSlide::factory(),
            'sequence' => fake()->unique()->numberBetween(1, 100000),
        ];
    }
}
