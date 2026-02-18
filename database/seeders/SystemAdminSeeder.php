<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\FirstName;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SystemAdminSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $email = config('admin.email');
        $knownPassword = config('admin.password');

        if (! $email) {
            $this->command?->warn('ADMIN_EMAIL not set. Skipping SystemAdminSeeder.');

            return;
        }

        // Ensure a city exists for the admin user
        $city = City::firstOrCreate(
            ['name' => 'System City'],
            ['name' => 'System City']
        );

        // Ensure a first name exists for the admin user
        $firstName = FirstName::firstOrCreate(
            ['name' => 'Admin'],
            ['name' => 'Admin', 'gender' => 'unknown']
        );

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $isLocalOrTesting = app()->environment(['local', 'testing']);
        $shouldUseKnownPassword = $isLocalOrTesting && ! empty($knownPassword);

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'System Admin',
                'password' => $shouldUseKnownPassword
                    ? Hash::make($knownPassword)
                    : Hash::make(str()->random(32)), // random/unusable for prod bootstrap
                'city_id' => $city->id,
                'first_name_id' => $firstName->id,
            ]
        );

        if (! $user->hasRole('admin')) {
            $user->assignRole($adminRole);
        }

        // ✅ In prod-like env: send reset link only on first creation (avoid spam on deploys)
        if (! $shouldUseKnownPassword && $user->wasRecentlyCreated) {
            Password::sendResetLink(['email' => $user->email]);
            $this->command?->info('Admin created and password reset link sent.');
        }

        // ✅ In local/testing: print credentials once (nice DX)
        if ($shouldUseKnownPassword) {
            $this->command?->info("Admin ensured: {$email} / {$knownPassword}");
        }
    }
}
