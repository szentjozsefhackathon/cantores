<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

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
        // Music permissions
        Permission::firstOrCreate(['name' => 'music.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'music.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'music.update', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'music.delete', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'music.manage', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'music.unpublish', 'guard_name' => 'web']);

        // Collection permissions
        Permission::firstOrCreate(['name' => 'collection.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'collection.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'collection.update', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'collection.delete', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'collection.manage', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'collection.unpublish', 'guard_name' => 'web']);

        // Music Plan permissions
        Permission::firstOrCreate(['name' => 'music-plan.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'music-plan.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'music-plan.update', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'music-plan.delete', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'music-plan.manage', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'music-plan.unpublish', 'guard_name' => 'web']);

        // Music Plan Template permissions (admin-only)
        Permission::firstOrCreate(['name' => 'music-plan-template.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'music-plan-template.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'music-plan-template.update', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'music-plan-template.delete', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'music-plan-template.manage', 'guard_name' => 'web']);

        // Celebration permissions
        Permission::firstOrCreate(['name' => 'celebration.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'celebration.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'celebration.update', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'celebration.delete', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'celebration.manage', 'guard_name' => 'web']);

        // User permissions (admin-only)
        Permission::firstOrCreate(['name' => 'user.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'user.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'user.update', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'user.delete', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'user.manage', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'role.assign', 'guard_name' => 'web']);

        // Realm permissions (admin-only)
        Permission::firstOrCreate(['name' => 'realm.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'realm.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'realm.update', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'realm.delete', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'realm.manage', 'guard_name' => 'web']);

        // System permissions
        Permission::firstOrCreate(['name' => 'access.admin', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'manage.roles', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'system.settings', 'guard_name' => 'web']);

        $this->command->info('Permissions created successfully.');
    }

    /**
     * Create the roles for the application.
     */
    private function createRoles(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'contributor', 'guard_name' => 'web']);

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
            // View permissions
            'music.view', 'collection.view', 'music-plan.view', 'celebration.view',
            // Unpublish permissions (can unpublish any content)
            'music.unpublish', 'collection.unpublish', 'music-plan.unpublish',
            // Note: Editors can only edit published content - this will be handled in policies
            // They don't get create/update/delete permissions directly
        ];
        $editorRole->givePermissionTo($editorPermissions);

        // Contributor permissions (default role)
        $contributorPermissions = [
            // Create permissions
            'music.create', 'collection.create', 'music-plan.create',
            // View permissions
            'music.view', 'collection.view', 'music-plan.view', 'celebration.view',
            // Note: Update/delete permissions for own content will be handled in policies
            // based on ownership, not via role permissions
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
            if ($adminUser && !$adminUser->hasRole('admin')) {
                $adminUser->assignRole($adminRole);
                $this->command->info("Assigned admin role to user: {$adminUser->email}");
            }
        }

        // Assign contributor role to all other users (excluding admins)
        $nonAdminUsers = User::whereDoesntHave('roles', function ($query) {
            $query->where('name', 'admin');
        })->get();

        foreach ($nonAdminUsers as $user) {
            if (!$user->hasRole('contributor')) {
                $user->assignRole($contributorRole);
            }
        }

        $this->command->info("Assigned contributor role to {$nonAdminUsers->count()} users.");
    }
