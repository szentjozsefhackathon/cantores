# Roles and Permissions Implementation Plan

## Database Seeder Implementation

### File: `database/seeders/RolePermissionSeeder.php`

```php
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

        // Collection permissions
        Permission::firstOrCreate(['name' => 'collection.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'collection.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'collection.update', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'collection.delete', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'collection.manage', 'guard_name' => 'web']);

        // Music Plan permissions
        Permission::firstOrCreate(['name' => 'music-plan.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'music-plan.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'music-plan.update', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'music-plan.delete', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'music-plan.manage', 'guard_name' => 'web']);

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
        Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);

        $this->command->info('Roles created successfully.');
    }

    /**
     * Assign permissions to roles.
     */
    private function assignPermissionsToRoles(): void
    {
        $adminRole = Role::findByName('admin', 'web');
        $editorRole = Role::findByName('editor', 'web');
        $viewerRole = Role::findByName('viewer', 'web');

        // Admin gets all permissions
        $adminRole->givePermissionTo(Permission::all());

        // Editor permissions
        $editorPermissions = [
            'music.view', 'music.create', 'music.update', 'music.delete',
            'collection.view', 'collection.create', 'collection.update', 'collection.delete',
            'music-plan.view', 'music-plan.create', 'music-plan.update', 'music-plan.delete',
            'celebration.view', 'celebration.create', 'celebration.update', 'celebration.delete',
        ];
        $editorRole->givePermissionTo($editorPermissions);

        // Viewer permissions (read-only)
        $viewerPermissions = [
            'music.view',
            'collection.view',
            'music-plan.view',
            'celebration.view',
        ];
        $viewerRole->givePermissionTo($viewerPermissions);

        $this->command->info('Permissions assigned to roles successfully.');
    }

    /**
     * Assign roles to existing users based on current admin status.
     */
    private function assignRolesToExistingUsers(): void
    {
        $adminRole = Role::findByName('admin', 'web');
        $viewerRole = Role::findByName('viewer', 'web');

        // Get admin email from config
        $adminEmail = config('admin.email', env('ADMIN_EMAIL'));

        if ($adminEmail) {
            $adminUser = User::where('email', $adminEmail)->first();
            if ($adminUser && !$adminUser->hasRole('admin')) {
                $adminUser->assignRole($adminRole);
                $this->command->info("Assigned admin role to user: {$adminUser->email}");
            }
        }

        // Assign viewer role to all other users (excluding admins)
        $nonAdminUsers = User::whereDoesntHave('roles', function ($query) {
            $query->where('name', 'admin');
        })->get();

        foreach ($nonAdminUsers as $user) {
            if (!$user->hasRole('viewer')) {
                $user->assignRole($viewerRole);
            }
        }

        $this->command->info("Assigned viewer role to {$nonAdminUsers->count()} users.");
    }
}
```

### Update DatabaseSeeder to include RolePermissionSeeder

```php
// In database/seeders/DatabaseSeeder.php
public function run(): void
{
    // Run role and permission seeder first
    $this->call(RolePermissionSeeder::class);
    
    // Rest of existing seeding logic...
}
```

## User Model Updates

### File: `app/Models/User.php`

The User model already has the `HasRoles` trait, but we should update the `getIsAdminAttribute()` method to check for the admin role instead of just email:

```php
// In app/Models/User.php
/**
 * Determine if the user is the admin.
 */
public function getIsAdminAttribute(): bool
{
    // Backward compatibility: check both email and role
    $adminEmail = config('admin.email', env('ADMIN_EMAIL'));
    
    if ($this->email === $adminEmail) {
        // Ensure they have the admin role
        if (!$this->hasRole('admin')) {
            $this->assignRole('admin');
        }
        return true;
    }
    
    return $this->hasRole('admin');
}

/**
 * Check if user has any of the given roles.
 */
public function hasAnyRole(array $roles): bool
{
    return $this->hasRole($roles);
}

/**
 * Get the user's role names.
 */
public function getRoleNamesAttribute(): array
{
    return $this->getRoleNames()->toArray();
}
```

## AdminMiddleware Updates

### File: `app/Http/Middleware/AdminMiddleware.php`

Update to check for admin role instead of email:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (! Auth::check() || ! Auth::user()->hasRole('admin')) {
            abort(403);
        }

        return $next($request);
    }
}
```

## Policy Updates

### Example: MusicPolicy Update

```php
// app/Policies/MusicPolicy.php
public function view(User $user, Music $music): bool
{
    // Admin can view anything
    if ($user->hasRole('admin')) {
        return true;
    }
    
    // Check if user has permission to view music
    if (!$user->hasPermissionTo('music.view')) {
        return false;
    }
    
    // For non-admin users, check realm access
    // Assuming Music has a realm_id field or relationship
    if (isset($music->realm_id) && $user->current_realm_id !== $music->realm_id) {
        return false;
    }
    
    return true;
}

public function update(User $user, Music $music): bool
{
    // Admin can update anything
    if ($user->hasRole('admin')) {
        return true;
    }
    
    // Check if user has permission to update music
    if (!$user->hasPermissionTo('music.update')) {
        return false;
    }
    
    // For non-admin users, check ownership and realm
    if ($user->id !== $music->user_id) {
        return false;
    }
    
    if (isset($music->realm_id) && $user->current_realm_id !== $music->realm_id) {
        return false;
    }
    
    return true;
}
```

## Running the Implementation

### 1. Create and Run Migration
```bash
php artisan migrate
```

### 2. Run the Seeder
```bash
php artisan db:seed --class=RolePermissionSeeder
```

Or include in DatabaseSeeder and run:
```bash
php artisan db:seed
```

### 3. Test the Implementation
```bash
php artisan test --filter=MusicPlanAuthorizationTest
php artisan test --filter=MusicPlanSlotsTest
```

## Testing the New System

### Create Test Users with Different Roles
```php
// In tests
$admin = User::factory()->create();
$admin->assignRole('admin');

$editor = User::factory()->create();
$editor->assignRole('editor');

$viewer = User::factory()->create();
$viewer->assignRole('viewer');
```

### Test Role-Based Access
```php
test('admin can access admin panel', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    
    $this->actingAs($admin)
        ->get(route('admin.music-plan-slots'))
        ->assertSuccessful();
});

test('editor cannot access admin panel', function () {
    $editor = User::factory()->create();
    $editor->assignRole('editor');
    
    $this->actingAs($editor)
        ->get(route('admin.music-plan-slots'))
        ->assertForbidden();
});
```

## Next Steps After Implementation

1. **Update all existing policies** to incorporate role checks
2. **Update tests** to use role-based authorization
3. **Optional**: Create admin UI for role management
4. **Optional**: Add role assignment during user registration
5. **Document** the new permission system for developers

## Migration Considerations

- The system maintains backward compatibility with the email-based admin check
- Existing admin users will automatically get the admin role
- All other users will get the viewer role
- Policies should check both roles and ownership for granular control