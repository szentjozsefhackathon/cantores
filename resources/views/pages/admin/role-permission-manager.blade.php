<x-pages::admin.layout :heading="__('Role Permission Manager')" :subheading="__('Assign permissions to roles in the system')">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Roles Panel -->
        <div class="lg:col-span-1">
            <flux:card class="h-full">
                <div class="space-y-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Roles</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Select a role to manage its permissions</p>
                    </div>

                    <div>
                        <flux:input 
                            wire:model.live.debounce.300ms="roleSearch"
                            placeholder="Search roles..."
                            icon="magnifying-glass"
                        />
                    </div>

                    <div class="space-y-2 max-h-[400px] overflow-y-auto">
                        @forelse($this->getRoles() as $role)
                            <button
                                wire:click="selectRole({{ $role->id }})"
                                class="w-full text-left p-3 rounded-lg transition-colors {{ $selectedRoleId === $role->id ? 'bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-700' : 'hover:bg-gray-50 dark:hover:bg-gray-800 border border-transparent' }}"
                            >
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-white">{{ $role->name }}</div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                            {{ $role->permissions_count }} permissions
                                        </div>
                                    </div>
                                    @if($selectedRoleId === $role->id)
                                        <flux:icon name="check-circle" class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                                    @endif
                                </div>
                            </button>
                        @empty
                            <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                                <flux:icon name="user-group" class="h-12 w-12 mx-auto text-gray-300 dark:text-gray-600" />
                                <p class="mt-2">No roles found</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </flux:card>
        </div>

        <!-- Permissions Panel -->
        <div class="lg:col-span-2">
            <flux:card class="h-full">
                <div class="space-y-6">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Permissions</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            @if($selectedRoleId)
                                @php($selectedRole = Spatie\Permission\Models\Role::find($selectedRoleId))
                                Assign permissions to <span class="font-medium">{{ $selectedRole?->name }}</span>
                            @else
                                Select a role to assign permissions
                            @endif
                        </p>
                    </div>

                    <div>
                <flux:field>
                        <flux:input 
                            type="search"
                            wire:model.live.debounce.300ms="permissionSearch"
                            placeholder="Search permissions..."
                            icon="magnifying-glass"
                            :disabled="!$selectedRoleId"
                        />
                        </flux:field>
                    </div>

                    @if(!$selectedRoleId)
                        <div class="text-center py-12">
                            <flux:icon name="lock-closed" class="h-16 w-16 mx-auto text-gray-300 dark:text-gray-600" />
                            <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">No role selected</h3>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Select a role from the left panel to manage its permissions.</p>
                        </div>
                    @else
                        <div class="space-y-4 max-h-[500px] overflow-y-auto">
                            @forelse($this->getGroupedPermissions() as $group)
                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden mb-4">
                                    <div class="bg-gray-50 dark:bg-gray-800 px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                                        <div class="flex items-center justify-between">
                                            <span class="font-medium text-gray-900 dark:text-white">{{ $group['label'] }}</span>
                                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ count($group['permissions']) }} permissions
                                            </span>
                                        </div>
                                    </div>
                                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach($group['permissions'] as $permission)
                                            <div wire:key="{{ $selectedRoleId }}-permission-{{ $permission->id }}" class="flex items-center justify-between p-4 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                                <div>
                                                    <div class="font-medium text-gray-900 dark:text-white">{{ $permission->name }}</div>

                                                </div>
                                                <div class="flex items-center space-x-3">
                                                    <flux:checkbox
                                                        wire:click="togglePermission({{ $permission->id }})"
                                                        wire:loading.attr="disabled"
                                                        :checked="in_array($permission->id, $selectedPermissions)"
                                                        class="data-loading:opacity-50"
                                                    />
                                                    <div wire:loading wire:target="togglePermission({{ $permission->id }})">
                                                        <flux:icon name="arrow-path" class="h-4 w-4 animate-spin text-gray-400" />
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                                    <flux:icon name="document-magnifying-glass" class="h-12 w-12 mx-auto text-gray-300 dark:text-gray-600" />
                                    <p class="mt-2">No permissions found</p>
                                </div>
                            @endforelse
                        </div>

                        <div class="pt-6 border-t border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                    <span class="font-medium">{{ count($selectedPermissions) }}</span> permissions assigned
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    Click checkboxes to toggle assignment
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </flux:card>
        </div>
    </div>

    <!-- Success Message -->
    @if(session()->has('message'))
        <div class="mt-6">
            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-green-800 dark:text-green-200">{{ session('message') }}</p>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-pages::admin.layout>