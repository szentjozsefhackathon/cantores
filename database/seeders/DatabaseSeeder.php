<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Always run production seeders (roles, permissions, admin user, genres)
        $this->call(RolePermissionSeeder::class);
        $this->call(SystemAdminSeeder::class);
        $this->call(GenreSeeder::class);

        // Run test data seeders only in the testing environment
        if (app()->environment('testing')) {
            $this->call(TestSeeder::class);
        }
    }
}
