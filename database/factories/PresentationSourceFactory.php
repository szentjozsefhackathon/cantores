<?php

namespace Database\Factories;

use App\Models\Presentation;
use App\Models\PresentationSource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PresentationSource>
 */
class PresentationSourceFactory extends Factory
{
    protected $model = PresentationSource::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'presentation_id' => Presentation::factory(),
            'source_id' => (string) Str::uuid(),
            'last_sequence' => 0,
            'applied_version' => 0,
        ];
    }
}
