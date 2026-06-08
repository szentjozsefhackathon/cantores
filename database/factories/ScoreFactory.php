<?php

namespace Database\Factories;

use App\Enums\ScoreFormat;
use App\Models\Music;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Score>
 */
class ScoreFactory extends Factory
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
            'music_id' => Music::factory(),
            'title' => $this->faker->sentence(3),
            'format' => $this->faker->randomElement(ScoreFormat::cases()),
            'content' => "X:1\nT:".$this->faker->sentence(3)."\nK:C\nC D E F|G A B c|",
        ];
    }

    public function abc(): static
    {
        return $this->state([
            'format' => ScoreFormat::Abc,
            'content' => "X:1\nT:Sample ABC\nK:C\nC D E F|G A B c|",
        ]);
    }

    public function gabc(): static
    {
        return $this->state([
            'format' => ScoreFormat::Gabc,
            'content' => "name: Sample GABC;\n%%\n(c4) Do(e) mi(f) nus(g) (::)",
        ]);
    }

    public function chordpro(): static
    {
        return $this->state([
            'format' => ScoreFormat::ChordPro,
            'content' => "{title: Sample ChordPro}\n\n[G]Amazing [C]grace how [G]sweet the sound",
        ]);
    }

    public function unattached(): static
    {
        return $this->state(['music_id' => null]);
    }

    public function linksOnly(): static
    {
        return $this->state(['format' => null, 'content' => null]);
    }
}
