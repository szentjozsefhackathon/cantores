<?php

namespace Database\Seeders;

use App\Models\Collection;
use App\Models\Music;
use App\Models\MusicPlan;
use App\Models\MusicPlanSlot;
use App\Models\MusicPlanSlotAssignment;
use App\Models\MusicUrl;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create or get a test user
        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        // Create or get music pieces
        $music1 = Music::firstOrCreate(
            ['custom_id' => 'BWV 846'],
            [
                'title' => 'Ave Maria',
                'user_id' => $user->id,
            ]
        );

        $music2 = Music::firstOrCreate(
            ['custom_id' => 'ÉE 435'],
            [
                'title' => 'Jézus neve szent',
                'user_id' => $user->id,
            ]
        );

        $music3 = Music::firstOrCreate(
            ['title' => 'Kyrie eleison'],
            ['user_id' => $user->id]
        );

        // Create or get collections
        $collection1 = Collection::firstOrCreate(
            ['abbreviation' => 'ÉE'],
            [
                'title' => 'Éneklő Egyház',
                'author' => 'Magyar Katolikus Püspöki Konferencia',
                'user_id' => $user->id,
            ]
        );

        $collection2 = Collection::firstOrCreate(
            ['abbreviation' => 'BWV'],
            [
                'title' => 'Bach Werke Verzeichnis',
                'user_id' => $user->id,
            ]
        );

        // Attach music to collections with page/order numbers (if not already attached)
        if (! $collection1->music()->where('music_id', $music2->id)->exists()) {
            $collection1->music()->attach($music2, [
                'page_number' => 435,
                'order_number' => '435',
            ]);
        }

        if (! $collection2->music()->where('music_id', $music1->id)->exists()) {
            $collection2->music()->attach($music1, [
                'page_number' => 1,
                'order_number' => 'BWV 846',
            ]);
        }

        // Create related music (variations) if not already related
        if (! $music1->relatedMusic()->where('related_music_id', $music3->id)->exists()) {
            $music1->relatedMusic()->attach($music3, [
                'relationship_type' => 'variation',
            ]);
        }

        // Create URLs for music if they don't exist
        MusicUrl::firstOrCreate(
            [
                'music_id' => $music1->id,
                'url' => 'https://example.com/bach-ave-maria.pdf',
            ],
            ['label' => 'sheet_music']
        );

        MusicUrl::firstOrCreate(
            [
                'music_id' => $music1->id,
                'url' => 'https://youtube.com/watch?v=example',
            ],
            ['label' => 'video']
        );

        // Create music assignment flags
        $importantFlag = \App\Models\MusicAssignmentFlag::firstOrCreate(
            ['name' => 'important'],
            ['name' => 'important']
        );
        $alternativeFlag = \App\Models\MusicAssignmentFlag::firstOrCreate(
            ['name' => 'alternative'],
            ['name' => 'alternative']
        );
        $lowPriorityFlag = \App\Models\MusicAssignmentFlag::firstOrCreate(
            ['name' => 'low_priority'],
            ['name' => 'low_priority']
        );

        // Create a music plan and slot if they exist
        if (class_exists(MusicPlan::class) && class_exists(MusicPlanSlot::class)) {
            $musicPlan = MusicPlan::factory()->create([
                'user_id' => $user->id,
            ]);

            $slot = MusicPlanSlot::factory()->create([
                'name' => 'Entrance Hymn',
                'description' => 'Opening hymn for the celebration',
            ]);

            // Assign music to the slot in the plan
            MusicPlanSlotAssignment::factory()->create([
                'music_plan_id' => $musicPlan->id,
                'music_plan_slot_id' => $slot->id,
                'music_id' => $music1->id,
                'sequence' => 1,
                'notes' => 'Traditional setting',
            ]);

            MusicPlanSlotAssignment::factory()->create([
                'music_plan_id' => $musicPlan->id,
                'music_plan_slot_id' => $slot->id,
                'music_id' => $music2->id,
                'sequence' => 2,
                'notes' => 'Congregational hymn',
            ]);
        }

        $this->command->info('Sample data seeded successfully!');
        $this->command->info('Music pieces: '.Music::count());
        $this->command->info('Collections: '.Collection::count());
        $this->command->info('Music-Collection relationships: '.\DB::table('music_collection')->count());
    }
}
