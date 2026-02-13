# Test Updates for Role-Based Permission System

## Overview
This document outlines the necessary updates to existing tests to work with the new role-based permission system. Tests need to be updated to use roles instead of the email-based `is_admin` check.

## Current Test Patterns

### 1. Admin Tests (Email-based)
```php
// Current pattern
$admin = User::factory()->create([
    'email' => 'admin@example.com', // Matches ADMIN_EMAIL config
]);

// Or using is_admin attribute
$admin = User::factory()->create();
// is_admin is computed from email matching ADMIN_EMAIL
```

### 2. Non-Admin User Tests
```php
$user = User::factory()->create([
    'email' => 'user@example.com', // Not ADMIN_EMAIL
]);
```

## New Test Patterns

### 1. Admin Tests (Role-based)
```php
use Spatie\Permission\Models\Role;

// Create admin user with role
$admin = User::factory()->create();
$adminRole = Role::findByName('admin', 'web');
$admin->assignRole($adminRole);

// Or use a helper method
$admin = User::factory()->create();
$admin->assignRole('admin');
```

### 2. Editor Tests
```php
$editor = User::factory()->create();
$editor->assignRole('editor');
```

### 3. Viewer Tests  
```php
$viewer = User::factory()->create();
$viewer->assignRole('viewer');
```

## Test Helper Functions

Create a test helper trait or functions to simplify role assignment:

### Option 1: Test Helper Trait
```php
// tests/Helpers/AssignsRoles.php
namespace Tests\Helpers;

use Spatie\Permission\Models\Role;

trait AssignsRoles
{
    protected function createAdminUser(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole('admin');
        return $user;
    }
    
    protected function createEditorUser(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole('editor');
        return $user;
    }
    
    protected function createViewerUser(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole('viewer');
        return $user;
    }
    
    protected function assignRole(User $user, string $role): void
    {
        $user->assignRole($role);
    }
}
```

### Option 2: Factory States
```php
// database/factories/UserFactory.php
public function admin()
{
    return $this->afterCreating(function (User $user) {
        $user->assignRole('admin');
    });
}

public function editor()
{
    return $this->afterCreating(function (User $user) {
        $user->assignRole('editor');
    });
}

public function viewer()
{
    return $this->afterCreating(function (User $user) {
        $user->assignRole('viewer');
    });
}

// Usage in tests
$admin = User::factory()->admin()->create();
$editor = User::factory()->editor()->create();
$viewer = User::factory()->viewer()->create();
```

## Specific Test Updates

### 1. MusicPlanSlotsTest Updates

**Current:**
```php
// tests/Feature/MusicPlanSlotsTest.php
$this->admin = User::factory()->create([
    'city_id' => $this->city1->id,
    'first_name_id' => $this->firstName1->id,
    'email' => 'admin@example.com', // Relies on ADMIN_EMAIL config
]);
```

**Updated:**
```php
// tests/Feature/MusicPlanSlotsTest.php
use Tests\Helpers\AssignsRoles;

beforeEach(function () {
    // ... existing setup ...
    
    $this->admin = User::factory()->create([
        'city_id' => $this->city1->id,
        'first_name_id' => $this->firstName1->id,
    ]);
    $this->admin->assignRole('admin');
    
    $this->user = User::factory()->create([
        'city_id' => $this->city2->id,
        'first_name_id' => $this->firstName2->id,
    ]);
    $this->user->assignRole('viewer'); // Or no role for regular user
});
```

### 2. MusicPlanAuthorizationTest Updates

**Current tests check ownership only:**
```php
test('owner can view their own music plan', function () {
    $user = User::factory()->create([...]);
    $musicPlan = MusicPlan::factory()->create(['user_id' => $user->id]);
    // ...
});

test('non-owner cannot view another user\'s music plan', function () {
    $owner = User::factory()->create([...]);
    $intruder = User::factory()->create([...]);
    $musicPlan = MusicPlan::factory()->create(['user_id' => $owner->id]);
    // ...
});
```

**These tests should still pass** because policies check both ownership AND roles. However, we should add new tests for role-based access:

```php
test('admin can view any music plan regardless of ownership', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    
    $otherUser = User::factory()->create();
    $musicPlan = MusicPlan::factory()->create(['user_id' => $otherUser->id]);
    
    $response = $this->actingAs($admin)->get(route('music-plan-editor', $musicPlan));
    $response->assertOk();
});

test('editor cannot view another user\'s music plan in different realm', function () {
    $editor = User::factory()->create();
    $editor->assignRole('editor');
    
    $otherUser = User::factory()->create();
    $musicPlan = MusicPlan::factory()->create([
        'user_id' => $otherUser->id,
        'realm_id' => 2, // Different realm
    ]);
    
    $response = $this->actingAs($editor)->get(route('music-plan-editor', $musicPlan));
    $response->assertForbidden();
});
```

### 3. New Role-Based Authorization Tests

Create new test files for role-based authorization:

```php
// tests/Feature/RoleAuthorizationTest.php
namespace Tests\Feature;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    public function test_admin_can_access_admin_panel(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        
        $this->actingAs($admin)
            ->get(route('admin.music-plan-slots'))
            ->assertSuccessful();
    }
    
    public function test_editor_cannot_access_admin_panel(): void
    {
        $editor = User::factory()->create();
        $editor->assignRole('editor');
        
        $this->actingAs($editor)
            ->get(route('admin.music-plan-slots'))
            ->assertForbidden();
    }
    
    public function test_viewer_cannot_access_admin_panel(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');
        
        $this->actingAs($viewer)
            ->get(route('admin.music-plan-slots'))
            ->assertForbidden();
    }
    
    public function test_user_without_role_cannot_access_admin_panel(): void
    {
        $user = User::factory()->create();
        // No role assigned
        
        $this->actingAs($user)
            ->get(route('admin.music-plan-slots'))
            ->assertForbidden();
    }
}
```

### 4. Database Seeder Test

```php
// tests/Feature/RolePermissionSeederTest.php
namespace Tests\Feature;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RolePermissionSeederTest extends TestCase
{
    public function test_seeder_creates_roles(): void
    {
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
        
        $this->assertDatabaseHas('roles', ['name' => 'admin']);
        $this->assertDatabaseHas('roles', ['name' => 'editor']);
        $this->assertDatabaseHas('roles', ['name' => 'viewer']);
    }
    
    public function test_seeder_assigns_admin_role_to_admin_email(): void
    {
        // Set admin email in config
        config(['admin.email' => 'admin@test.com']);
        
        $adminUser = User::factory()->create(['email' => 'admin@test.com']);
        
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
        
        $this->assertTrue($adminUser->hasRole('admin'));
    }
    
    public function test_seeder_assigns_viewer_role_to_other_users(): void
    {
        $user = User::factory()->create(['email' => 'user@test.com']);
        
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
        
        $this->assertTrue($user->hasRole('viewer'));
    }
}
```

## Test Setup and Teardown

### Global Test Setup
Consider adding a global setup to ensure roles exist for all tests:

```php
// tests/TestCase.php
namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure roles exist for tests
        $this->ensureRolesExist();
    }
    
    protected function ensureRolesExist(): void
    {
        if (Role::where('name', 'admin')->doesntExist()) {
            Role::create(['name' => 'admin', 'guard_name' => 'web']);
            Role::create(['name' => 'editor', 'guard_name' => 'web']);
            Role::create(['name' => 'viewer', 'guard_name' => 'web']);
        }
    }
}
```

### Test Database Refresh
When refreshing database during tests, roles and permissions will be cleared. Options:

1. **Run seeder in setUp**: Slow but ensures fresh data
2. **Use DatabaseTransactions**: Preserve roles between tests
3. **Create test-specific setup**: Only create roles when needed

## Migration Testing Strategy

### Phase 1: Update Existing Tests
1. Update tests that rely on `is_admin` attribute
2. Add role assignment to admin users in tests
3. Verify tests still pass with new role system

### Phase 2: Add New Role Tests
1. Create tests for each role (admin, editor, viewer)
2. Test role-based permissions for each resource
3. Test realm-based restrictions

### Phase 3: Integration Tests
1. Test end-to-end role assignment flow
2. Test permission inheritance
3. Test role management UI (if implemented)

## Common Test Issues and Solutions

### Issue 1: Role Not Found
**Error**: `Role "admin" does not exist.`
**Solution**: Ensure roles are created before tests run
```php
// In test setup
Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
```

### Issue 2: Permission Cache
**Error**: Permissions not recognized after role assignment
**Solution**: Clear permission cache
```php
app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
```

### Issue 3: Database State
**Error**: Roles persist between tests causing conflicts
**Solution**: Use database transactions or refresh database
```php
use Illuminate\Foundation\Testing\DatabaseTransactions;

class RoleTest extends TestCase
{
    use DatabaseTransactions;
    // ...
}
```

## Running Updated Tests

```bash
# Run all tests
php artisan test

# Run specific test files
php artisan test --filter=MusicPlanAuthorizationTest
php artisan test --filter=MusicPlanSlotsTest
php artisan test --filter=RoleAuthorizationTest

# Run with coverage
php artisan test --coverage
```

## Test Data Factories

Update factories to include role assignment:

```php
// database/factories/UserFactory.php
public function configure()
{
    return $this->afterCreating(function (User $user) {
        // Default role assignment if needed
        if (! $user->hasAnyRole(['admin', 'editor', 'viewer'])) {
            $user->assignRole('viewer');
        }
    });
}
```

## Continuous Integration

Update CI configuration to run role/permission seeder:

```yaml
# .github/workflows/tests.yml
jobs:
  tests:
    steps:
      - name: Run migrations
        run: php artisan migrate --force
      
      - name: Seed roles and permissions
        run: php artisan db:seed --class=RolePermissionSeeder
      
      - name: Run tests
        run: php artisan test
```

## Summary of Test Updates Required

1. **All admin user creation** → Add `assignRole('admin')`
2. **All `is_admin` checks** → Update to `hasRole('admin')` or `hasPermissionTo()`
3. **Add new tests** for role-based authorization
4. **Update test helpers** to simplify role assignment
5. **Ensure test database** includes roles and permissions
6. **Update CI/CD pipeline** to seed roles before tests