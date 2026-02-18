<?php

use App\Models\User;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{

    public $users;

    public function mount() {
        $this->loadUsers();
    }

    public function openRolesModal($userId) {
        $this->dispatch('openUserRolesModal', userId: $userId);
    }

    #[On('refresh-users')]
    public function loadUsers() {
        $this->users = User::with('roles')->latest()->get();
    }
    
};
?>

<x-pages::admin.layout :heading="__('Users')">
    <div class="mt-5">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('ID') }}</flux:table.column>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Email') }}</flux:table.column>
                <flux:table.column>{{ __('Display Name') }}</flux:table.column>
                <flux:table.column>{{ __('Roles') }}</flux:table.column>
                <flux:table.column>{{ __('Email Verified') }}</flux:table.column>
                <flux:table.column>{{ __('Created At') }}</flux:table.column>
                <flux:table.column>{{ __('Updated At') }}</flux:table.column>
                <flux:table.column>{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($users as $user)
                    <flux:table.row>
                        <flux:table.cell>{{ $user->id }}</flux:table.cell>
                        <flux:table.cell>{{ $user->name }}</flux:table.cell>
                        <flux:table.cell>{{ $user->email }}</flux:table.cell>
                        <flux:table.cell>{{ $user->display_name ?? '-' }}</flux:table.cell>
                        <flux:table.cell>
                            @if($user->roles->isNotEmpty())
                                <div class="flex flex-wrap gap-1">
                                    @foreach($user->roles as $role)
                                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                            {{ $role->name }}
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-gray-500">{{ __('No roles') }}</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $user->email_verified_at ? $user->email_verified_at->format('Y-m-d H:i') : __('Not verified') }}</flux:table.cell>
                        <flux:table.cell>{{ $user->created_at->format('Y-m-d H:i') }}</flux:table.cell>
                        <flux:table.cell>{{ $user->updated_at->format('Y-m-d H:i') }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:button
                                size="sm"
                                variant="outline"
                                wire:click="openRolesModal({{ $user->id }})"
                            >
                                {{ __('Edit Roles') }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="9" class="text-center">{{ __('No users found.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
    <livewire:admin.edit-user-roles />
</x-pages::admin.layout>