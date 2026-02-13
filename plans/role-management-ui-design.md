# Role Management UI Design (Optional)

## Overview
This document outlines an optional role management UI that can be implemented to allow administrators to manage user roles and permissions through a web interface.

## Features

### 1. User Role Management
- View list of users with their current roles
- Assign/remove roles for individual users
- Filter users by role
- Search users by name or email

### 2. Role Management
- View list of roles with their permissions
- Create new custom roles
- Edit existing roles (add/remove permissions)
- Delete custom roles (with confirmation)

### 3. Permission Management
- View all available permissions
- Group permissions by resource type
- Search/filter permissions

## UI Components

### 1. User Management Page
```
/admin/users/roles
```

**Layout:**
- Search bar at top
- Table of users with columns: Name, Email, Current Roles, Actions
- Role assignment modal
- Pagination

### 2. Role Management Page  
```
/admin/roles
```

**Layout:**
- List of roles (Admin, Editor, Viewer, Custom roles)
- Each role shows: Name, Description, Permission count, User count
- Create/Edit role modal
- Delete role button (with confirmation)

### 3. Permission Management Page
```
/admin/permissions
```

**Layout:**
- Grouped permissions by resource (Music, Collection, etc.)
- Each permission shows: Name, Description, Assigned roles
- Search and filter options

## Implementation Details

### Livewire Components

#### 1. UserRoleManager Component
```php
// app/Livewire/Pages/Admin/UserRoleManager.php
namespace App\Livewire\Pages\Admin;

use App\Models\User;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class UserRoleManager extends Component
{
    public $users;
    public $roles;
    public $selectedUserId;
    public $selectedRoleIds = [];
    
    public function mount()
    {
        $this->users = User::with('roles')->paginate(20);
        $this->roles = Role::all();
    }
    
    public function updateUserRoles($userId)
    {
        $user = User::findOrFail($userId);
        $user->syncRoles($this->selectedRoleIds);
        
        session()->flash('message', 'Roles updated successfully.');
    }
    
    public function render()
    {
        return view('livewire.pages.admin.user-role-manager');
    }
}
```

#### 2. RoleManager Component
```php
// app/Livewire/Pages/Admin/RoleManager.php
namespace App\Livewire\Pages\Admin;

use Livewire\Component;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleManager extends Component
{
    public $roles;
    public $permissions;
    public $selectedRoleId;
    public $selectedPermissionIds = [];
    public $roleName = '';
    public $roleDescription = '';
    
    public function mount()
    {
        $this->roles = Role::with('permissions')->get();
        $this->permissions = Permission::all()->groupBy(function ($permission) {
            return explode('.', $permission->name)[0];
        });
    }
    
    public function createRole()
    {
        $validated = $this->validate([
            'roleName' => 'required|unique:roles,name',
            'roleDescription' => 'nullable|string',
        ]);
        
        $role = Role::create([
            'name' => $validated['roleName'],
            'guard_name' => 'web',
        ]);
        
        $role->syncPermissions($this->selectedPermissionIds);
        
        session()->flash('message', 'Role created successfully.');
        $this->reset(['roleName', 'roleDescription', 'selectedPermissionIds']);
    }
    
    public function render()
    {
        return view('livewire.pages.admin.role-manager');
    }
}
```

### Blade Views

#### User Role Manager View
```blade
{{-- resources/views/livewire/pages/admin/user-role-manager.blade.php --}}
<div>
    <div class="mb-6">
        <h2 class="text-2xl font-bold">User Role Management</h2>
        <p class="text-gray-600">Assign roles to users</p>
    </div>
    
    <div class="mb-4">
        <input type="text" wire:model.live="search" placeholder="Search users..." class="w-full px-4 py-2 border rounded-lg">
    </div>
    
    <div class="overflow-x-auto bg-white rounded-lg shadow">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Roles</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($users as $user)
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap">{{ $user->name }}</td>
                    <td class="px-6 py-4 whitespace-nowrap">{{ $user->email }}</td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex flex-wrap gap-1">
                            @foreach($user->roles as $role)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                {{ $role->name }}
                            </span>
                            @endforeach
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <button wire:click="editUser({{ $user->id }})" class="text-indigo-600 hover:text-indigo-900">
                            Edit Roles
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        
        <div class="px-6 py-3 border-t">
            {{ $users->links() }}
        </div>
    </div>
    
    <!-- Edit Role Modal -->
    @if($editingUserId)
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 max-w-md w-full">
            <h3 class="text-lg font-medium mb-4">Edit Roles for {{ $editingUser->name }}</h3>
            
            <div class="space-y-3 mb-6">
                @foreach($roles as $role)
                <label class="flex items-center">
                    <input type="checkbox" wire:model="selectedRoleIds" value="{{ $role->id }}" class="rounded border-gray-300">
                    <span class="ml-2">{{ $role->name }}</span>
                </label>
                @endforeach
            </div>
            
            <div class="flex justify-end space-x-3">
                <button wire:click="cancelEdit" class="px-4 py-2 border rounded-lg">
                    Cancel
                </button>
                <button wire:click="updateUserRoles" class="px-4 py-2 bg-indigo-600 text-white rounded-lg">
                    Save Changes
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
```

### Routes
```php
// routes/admin.php
Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->group(function () {
    // Existing routes...
    
    // Role management routes
    Route::livewire('users/roles', \App\Livewire\Pages\Admin\UserRoleManager::class)->name('admin.user-roles');
    Route::livewire('roles', \App\Livewire\Pages\Admin\RoleManager::class)->name('admin.roles');
    Route::livewire('permissions', \App\Livewire\Pages\Admin\PermissionManager::class)->name('admin.permissions');
});
```

### Navigation Updates
Add to admin sidebar:
```blade
{{-- resources/views/layouts/app/sidebar.blade.php --}}
@if(auth()->user()->hasRole('admin'))
<flux:navlist.group label="Role Management">
    <flux:navlist.item href="{{ route('admin.user-roles') }}" :active="request()->routeIs('admin.user-roles')">
        User Roles
    </flux:navlist.item>
    <flux:navlist.item href="{{ route('admin.roles') }}" :active="request()->routeIs('admin.roles')">
        Role Management
    </flux:navlist.item>
    <flux:navlist.item href="{{ route('admin.permissions') }}" :active="request()->routeIs('admin.permissions')">
        Permissions
    </flux:navlist.item>
</flux:navlist.group>
@endif
```

## Security Considerations

1. **Authorization**: Only users with `admin` role can access these pages
2. **Validation**: Validate all role/permission assignments
3. **Audit Logging**: Log role changes for security auditing
4. **Default Roles Protection**: Prevent modification/deletion of default roles (admin, editor, viewer)

## Implementation Priority

### Phase 1: Basic User Role Management
- User list with current roles
- Simple role assignment (checkboxes)
- Basic search/filter

### Phase 2: Advanced Role Management
- Create/edit custom roles
- Permission management
- Role descriptions and metadata

### Phase 3: Enhanced Features
- Bulk role assignment
- Role inheritance
- Permission testing interface
- Audit logs

## Testing

### Test Cases
1. Admin can access role management pages
2. Non-admin cannot access role management pages
3. Admin can assign roles to users
4. Admin can create custom roles
5. Admin can edit role permissions
6. Default roles cannot be deleted
7. Validation prevents invalid role assignments

## Migration Path

If implementing this UI later:
1. Start with Phase 1 features
2. Use Artisan commands for role management initially
3. Gradually add UI features based on user needs
4. Collect feedback from administrators

## Alternatives

If full UI is not needed:
1. Use Artisan commands for role management
2. Create simple admin forms for common tasks
3. Use database seeders for role/permission setup
4. Provide documentation for manual role management