<?php

namespace Database\Factories;

use App\Models\Booklet;
use App\Models\BookletMusic;
use App\Models\Music;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookletMusic>
 */
class BookletMusicFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booklet_id' => Booklet::factory(),
            'music_id' => Music::factory(),
            'music_plan_slot_plan_id' => null,
            'sequence' => 0,
        ];
    }
}
