<?php

namespace Database\Factories;

use App\Models\Music;
use App\ScriptureReferenceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MusicScriptureReference>
 */
class MusicScriptureReferenceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Models\MusicScriptureReference>
     */
    protected $model = \App\Models\MusicScriptureReference::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'music_id' => Music::factory(),
            'reference_type' => $this->faker->randomElement(ScriptureReferenceType::cases())->value,
            'reference' => 'Jn '.$this->faker->numberBetween(1, 21).','.$this->faker->numberBetween(1, 30),
            'text' => $this->faker->sentence(),
        ];
    }
}
