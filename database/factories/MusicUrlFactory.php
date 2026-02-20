<?php

namespace Database\Factories;

use App\Models\Music;
use App\MusicUrlLabel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MusicUrl>
 */
class MusicUrlFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Models\MusicUrl>
     */
    protected $model = \App\Models\MusicUrl::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'music_id' => Music::factory(),
            'url' => $this->faker->url(),
            'label' => $this->faker->randomElement([
                MusicUrlLabel::SheetMusic->value,
                MusicUrlLabel::Audio->value,
                MusicUrlLabel::Video->value,
                MusicUrlLabel::Text->value,
                MusicUrlLabel::Information->value,
            ]),
        ];
    }
}
