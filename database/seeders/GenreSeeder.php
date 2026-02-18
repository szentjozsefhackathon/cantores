<?php

namespace Database\Seeders;

use App\Models\Genre;
use Illuminate\Database\Seeder;

class GenreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $genres = [
            ['name' => 'organist'],
            ['name' => 'guitarist'],
            ['name' => 'other'],
        ];

        foreach ($genres as $genre) {
            Genre::firstOrCreate(['name' => $genre['name']], $genre);
        }
    }
}
