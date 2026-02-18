<?php

namespace App\Livewire\Pages\Admin;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionManager extends Component
{
    use AuthorizesRequests;

    public ?int $selectedRoleId = null;

    public string $roleSearch = '';

    public string $permissionSearch = '';

    public array $selectedPermissions = [];

    public function mount(): void
    {
        $this->authorize('system.maintain');
    }

    public function selectRole(int $roleId): void
    {
        $this->selectedRoleId = $roleId;
        $this->updatedSelectedRoleId();
    }

    public function togglePermission(int $permissionId): void
    {
        $this->authorize('system.maintain');

        if (! $this->selectedRoleId) {
            return;
        }

        $role = Role::findOrFail($this->selectedRoleId);
        $permission = Permission::findOrFail($permissionId);

        if (in_array($permissionId, $this->selectedPermissions)) {
            $role->revokePermissionTo($permission);
        } else {
            $role->givePermissionTo($permission);
        }

        $this->updatedSelectedRoleId();

        session()->flash('message', "Permission '{$permission->name}' ".
            (in_array($permissionId, $this->selectedPermissions) ? 'removed from' : 'assigned to').
            " role '{$role->name}'");
    }

    public function getRoles()
    {
        return Role::query()
            ->when($this->roleSearch, function ($query, $search) {
                $query->where('name', 'ilike', "%{$search}%");
            })
            ->orderBy('name')
            ->get()
            ->map(function ($role) {
                $role->permissions_count = $role->permissions()->count();

                return $role;
            });
    }

    public function getPermissions()
    {
        return Permission::query()
            ->when($this->permissionSearch, function ($query, $search) {
                $query->where('name', 'ilike', "%{$search}%");
            })
            ->orderBy('name')
            ->get();
    }

    public function getGroupedPermissions(): array
    {
        $permissions = $this->getPermissions();
        $grouped = [];

        foreach ($permissions as $permission) {
            $parts = explode('.', $permission->name);
            $prefix = $parts[0] ?? 'other';

            if (! isset($grouped[$prefix])) {
                $grouped[$prefix] = [
                    'label' => ucfirst($prefix),
                    'permissions' => [],
                ];
            }

            $grouped[$prefix]['permissions'][] = $permission;
        }

        ksort($grouped);

        return $grouped;
    }

    public function updatedSelectedRoleId(): void
    {
        if (! $this->selectedRoleId) {
            $this->selectedPermissions = [];

            return;
        }

        $role = Role::find($this->selectedRoleId);
        if ($role) {
            $this->selectedPermissions = $role->permissions->pluck('id')->toArray();
        } else {
            $this->selectedPermissions = [];
        }
    }

    /**
     * Render the component.
     */
    public function render(): \Illuminate\Contracts\View\View
    {
        return view('pages.admin.role-permission-manager');
    }
}
