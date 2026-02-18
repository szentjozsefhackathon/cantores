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
        // Always run production seeders (roles, permissions, admin user)
        $this->call(RolePermissionSeeder::class);

        // Run test data seeders only in the testing environment
        if (app()->environment('testing')) {
            $this->call(TestSeeder::class);
        }
    }
}
