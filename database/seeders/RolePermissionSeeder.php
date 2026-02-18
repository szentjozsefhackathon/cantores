<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->createPermissions();
        $this->createRoles();
        $this->assignPermissionsToRoles();
        $this->assignRolesToExistingUsers();
    }

    /**
     * Create all permissions for the application.
     */
    private function createPermissions(): void
    {

        // Content permissions. These apply to music, collections and authors.
        // Guest users can view published content.
        // Every logged in user can create, edit, publish, unpublish and delete their own content. There are some rules around that (like if the music is verified, it became "common" treasure, the owner cannot edit it any more freely).

        Permission::firstOrCreate(['name' => 'content.create']);
        Permission::firstOrCreate(['name' => 'content.edit.own']); // includes delete
        Permission::firstOrCreate(['name' => 'content.edit.verified']); // includes delete
        Permission::firstOrCreate(['name' => 'content.edit.published']); // includes delete

        Permission::firstOrCreate(['name' => 'masterdata.maintain']);
        Permission::firstOrCreate(['name' => 'system.maintain']);

        $this->command->info('Permissions created successfully.');
    }

    /**
     * Create the roles for the application.
     */
    private function createRoles(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'editor']);
        Role::firstOrCreate(['name' => 'contributor']);

        $this->command->info('Roles created successfully.');
    }

    /**
     * Assign permissions to roles.
     */
    private function assignPermissionsToRoles(): void
    {
        $adminRole = Role::findByName('admin', 'web');
        $editorRole = Role::findByName('editor', 'web');
        $contributorRole = Role::findByName('contributor', 'web');

        // Admin gets all permissions
        $adminRole->givePermissionTo(Permission::all());

        // Editor permissions
        $editorPermissions = [
            // Create permissions
            'content.create',
            // Edit own content
            'content.edit.own',
            // Edit published content (including unpublish)
            'content.edit.published',
            'content.edit.verified',
            // Master data maintenance
            'masterdata.maintain',
        ];
        $editorRole->givePermissionTo($editorPermissions);

        // Contributor permissions (default role)
        $contributorPermissions = [
            'content.create',
            'content.edit.own',
        ];
        $contributorRole->givePermissionTo($contributorPermissions);

        $this->command->info('Permissions assigned to roles successfully.');
    }

    /**
     * Assign roles to existing users based on current admin status.
     */
    private function assignRolesToExistingUsers(): void
    {
        $adminRole = Role::findByName('admin', 'web');
        $contributorRole = Role::findByName('contributor', 'web');

        // Get admin email from config
        $adminEmail = config('admin.email', env('ADMIN_EMAIL'));

        if ($adminEmail) {
            $adminUser = User::where('email', $adminEmail)->first();
            if ($adminUser && ! $adminUser->hasRole('admin')) {
                $adminUser->assignRole($adminRole);
                $this->command->info("Assigned admin role to user: {$adminUser->email}");
            }
        }

        // Assign contributor role to all other users (excluding admins)
        $nonAdminUsers = User::whereDoesntHave('roles', function ($query) {
            $query->where('name', 'admin');
        })->get();

        foreach ($nonAdminUsers as $user) {
            if (! $user->hasRole('contributor')) {
                $user->assignRole($contributorRole);
            }
        }

        $this->command->info("Assigned contributor role to {$nonAdminUsers->count()} users.");
    }
}
