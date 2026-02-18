<?php

use App\Livewire\Pages\Admin\RolePermissionManager;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    // Create test roles and permissions
    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $editorRole = Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);
    $viewerRole = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);

    // Create test permissions
    $manageRolesPermission = Permission::firstOrCreate(['name' => 'manage.roles', 'guard_name' => 'web']);
    $manageUsersPermission = Permission::firstOrCreate(['name' => 'manage.users', 'guard_name' => 'web']);
    $viewReportsPermission = Permission::firstOrCreate(['name' => 'view.reports', 'guard_name' => 'web']);
    $editMusicPermission = Permission::firstOrCreate(['name' => 'edit.music', 'guard_name' => 'web']);

    // Assign manage.roles permission to admin role
    $adminRole->givePermissionTo($manageRolesPermission);

    // Create admin user with manage.roles permission
    $this->adminUser = User::factory()->create();
    $this->adminUser->assignRole('admin');

    // Create non-admin user without manage.roles permission
    $this->nonAdminUser = User::factory()->create();
    $this->nonAdminUser->assignRole('editor');

    // Store IDs for later use
    $this->adminRoleId = $adminRole->id;
    $this->editorRoleId = $editorRole->id;
    $this->viewerRoleId = $viewerRole->id;
    $this->manageRolesPermissionId = $manageRolesPermission->id;
    $this->manageUsersPermissionId = $manageUsersPermission->id;
    $this->viewReportsPermissionId = $viewReportsPermission->id;
    $this->editMusicPermissionId = $editMusicPermission->id;
});

test('unauthenticated users cannot access the component', function () {
    Livewire::test(RolePermissionManager::class)
        ->assertForbidden();
});

test('non-admin users without manage.roles permission cannot access the component', function () {
    $this->actingAs($this->nonAdminUser);

    Livewire::test(RolePermissionManager::class)
        ->assertForbidden();
});

test('admin users with manage.roles permission can access the component', function () {
    $this->actingAs($this->adminUser);

    Livewire::test(RolePermissionManager::class)
        ->assertSuccessful()
        ->assertSee('Role Permission Manager')
        ->assertSee('Roles')
        ->assertSee('Permissions');
});

test('component mounts successfully with valid user', function () {
    $this->actingAs($this->adminUser);

    Livewire::test(RolePermissionManager::class)
        ->assertSet('selectedRoleId', null)
        ->assertSet('roleSearch', '')
        ->assertSet('permissionSearch', '')
        ->assertSet('selectedPermissions', []);
});

test('getRoles returns correct roles list', function () {
    $this->actingAs($this->adminUser);

    $component = Livewire::test(RolePermissionManager::class);

    $roles = $component->call('getRoles');
    expect($roles)->toHaveCount(3)
        ->and($roles->pluck('name'))->toContain('admin', 'editor', 'viewer');
});

test('getRoles filters by search term', function () {
    $this->actingAs($this->adminUser);

    $component = Livewire::test(RolePermissionManager::class)
        ->set('roleSearch', 'edit');

    $roles = $component->call('getRoles');
    expect($roles)->toHaveCount(1)
        ->and($roles->first()->name)->toBe('editor');
});

test('getPermissions returns correct permissions list', function () {
    $this->actingAs($this->adminUser);

    $component = Livewire::test(RolePermissionManager::class);

    $permissions = $component->call('getPermissions');
    expect($permissions)->toHaveCount(4)
        ->and($permissions->pluck('name'))->toContain(
            'manage.roles',
            'manage.users',
            'view.reports',
            'edit.music'
        );
});

test('getPermissions filters by search term', function () {
    $this->actingAs($this->adminUser);

    $component = Livewire::test(RolePermissionManager::class)
        ->set('permissionSearch', 'manage');

    $permissions = $component->call('getPermissions');
    expect($permissions)->toHaveCount(2)
        ->and($permissions->pluck('name'))->toContain('manage.roles', 'manage.users');
});

test('getGroupedPermissions groups permissions by prefix', function () {
    $this->actingAs($this->adminUser);

    $component = Livewire::test(RolePermissionManager::class);

    $grouped = $component->call('getGroupedPermissions');
    expect($grouped)->toBeArray()
        ->and($grouped)->toHaveKeys(['edit', 'manage', 'view'])
        ->and($grouped['manage']['permissions'])->toHaveCount(2)
        ->and($grouped['edit']['permissions'])->toHaveCount(1)
        ->and($grouped['view']['permissions'])->toHaveCount(1);
});

test('updatedSelectedRoleId updates selected permissions array when role is selected', function () {
    $this->actingAs($this->adminUser);

    // Assign some permissions to editor role
    $editorRole = Role::find($this->editorRoleId);
    $editorRole->givePermissionTo(Permission::find($this->editMusicPermissionId));
    $editorRole->givePermissionTo(Permission::find($this->viewReportsPermissionId));

    $component = Livewire::test(RolePermissionManager::class)
        ->set('selectedRoleId', $this->editorRoleId);

    $component->call('updatedSelectedRoleId');

    expect($component->get('selectedPermissions'))
        ->toBeArray()
        ->toHaveCount(2)
        ->toContain($this->editMusicPermissionId, $this->viewReportsPermissionId);
});

test('updatedSelectedRoleId clears selected permissions when no role is selected', function () {
    $this->actingAs($this->adminUser);

    $component = Livewire::test(RolePermissionManager::class)
        ->set('selectedRoleId', null);

    $component->call('updatedSelectedRoleId');

    expect($component->get('selectedPermissions'))->toBeEmpty();
});

test('togglePermission assigns permission to role', function () {
    $this->actingAs($this->adminUser);

    // Start with no permissions assigned to editor role
    $editorRole = Role::find($this->editorRoleId);
    $editorRole->permissions()->detach();

    $component = Livewire::test(RolePermissionManager::class)
        ->set('selectedRoleId', $this->editorRoleId)
        ->call('updatedSelectedRoleId');

    // Initially should not have the permission
    expect($component->get('selectedPermissions'))->not->toContain($this->editMusicPermissionId);

    // Toggle permission on
    $component->call('togglePermission', $this->editMusicPermissionId);

    // Should now have the permission
    expect($component->get('selectedPermissions'))->toContain($this->editMusicPermissionId);
    expect($editorRole->hasPermissionTo(Permission::find($this->editMusicPermissionId)))->toBeTrue();
});

test('togglePermission revokes permission from role', function () {
    $this->actingAs($this->adminUser);

    // Start with permission assigned to editor role
    $editorRole = Role::find($this->editorRoleId);
    $editorRole->givePermissionTo(Permission::find($this->editMusicPermissionId));

    $component = Livewire::test(RolePermissionManager::class)
        ->set('selectedRoleId', $this->editorRoleId)
        ->call('updatedSelectedRoleId');

    // Initially should have the permission
    expect($component->get('selectedPermissions'))->toContain($this->editMusicPermissionId);

    // Toggle permission off
    $component->call('togglePermission', $this->editMusicPermissionId);

    // Should no longer have the permission
    expect($component->get('selectedPermissions'))->not->toContain($this->editMusicPermissionId);
    expect($editorRole->hasPermissionTo(Permission::find($this->editMusicPermissionId)))->toBeFalse();
});

test('togglePermission does nothing when no role is selected', function () {
    $this->actingAs($this->adminUser);

    $component = Livewire::test(RolePermissionManager::class)
        ->set('selectedRoleId', null);

    // Count permissions before toggle
    $permissionCountBefore = Permission::count();

    $component->call('togglePermission', $this->editMusicPermissionId);

    // Permission count should not change
    expect(Permission::count())->toBe($permissionCountBefore);
});

test('component renders role list correctly', function () {
    $this->actingAs($this->adminUser);

    Livewire::test(RolePermissionManager::class)
        ->assertSee('admin')
        ->assertSee('editor')
        ->assertSee('viewer')
        ->assertSee('permissions'); // Should show "X permissions" text
});

test('component renders permission checkboxes when role is selected', function () {
    $this->actingAs($this->adminUser);

    Livewire::test(RolePermissionManager::class)
        ->set('selectedRoleId', $this->editorRoleId)
        ->assertSee('manage.roles')
        ->assertSee('manage.users')
        ->assertSee('view.reports')
        ->assertSee('edit.music');
});

test('component shows no role selected message initially', function () {
    $this->actingAs($this->adminUser);

    Livewire::test(RolePermissionManager::class)
        ->assertSee('No role selected')
        ->assertSee('Select a role from the left panel to manage its permissions.');
});

test('session flash message is set after toggling permission', function () {
    $this->actingAs($this->adminUser);

    $component = Livewire::test(RolePermissionManager::class)
        ->set('selectedRoleId', $this->editorRoleId);

    $component->call('togglePermission', $this->editMusicPermissionId);

    $component->assertDispatched('notify')
        ->assertSessionHas('message');
});