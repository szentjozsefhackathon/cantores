<?php

use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    use AuthorizesRequests;

    public $users;

    public function mount(): void
    {
        $this->authorize('system.maintain');
        $this->loadUsers();
    }

    public function openRolesModal(int $userId): void
    {
        $this->dispatch('openUserRolesModal', userId: $userId);
    }

    public function blockUser(int $userId): void
    {
        $user = User::findOrFail($userId);

        if ($user->id === Auth::id()) {
            $this->dispatch('toast', message: __('You cannot block yourself.'), type: 'error');

            return;
        }

        $user->update([
            'blocked' => true,
            'blocked_at' => now(),
        ]);

        $this->dispatch('toast', message: __('User has been blocked.'), type: 'success');
        $this->loadUsers();
    }

    public function unblockUser(int $userId): void
    {
        $user = User::findOrFail($userId);

        $user->update([
            'blocked' => false,
            'blocked_at' => null,
        ]);

        $this->dispatch('toast', message: __('User has been unblocked.'), type: 'success');
        $this->loadUsers();
    }

    public function deleteUser(int $userId, AccountDeletionService $accounts): void
    {
        $user = User::findOrFail($userId);

        if ($user->id === Auth::id()) {
            $this->dispatch('toast', message: __('You cannot delete yourself.'), type: 'error');

            return;
        }

        $accounts->delete($user);

        $this->dispatch('toast', message: __('User has been anonymized and their private data deleted.'), type: 'success');
        $this->loadUsers();
    }

    #[On('refresh-users')]
    public function loadUsers(): void
    {
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
                <flux:table.column>{{ __('Last Login') }}</flux:table.column>
                <flux:table.column>{{ __('Blocked') }}</flux:table.column>
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
                        <flux:table.cell>{{ $user->last_login_at ? $user->last_login_at->format('Y-m-d H:i') : '-' }}</flux:table.cell>
                        <flux:table.cell>
                            @if($user->blocked)
                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-red-100 text-red-800">
                                    {{ __('Blocked') }}
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800">
                                    {{ __('Active') }}
                                </span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $user->created_at->format('Y-m-d H:i') }}</flux:table.cell>
                        <flux:table.cell>{{ $user->updated_at->format('Y-m-d H:i') }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex gap-2">
                                <flux:button
                                    size="sm"
                                    variant="outline"
                                    wire:click="openRolesModal({{ $user->id }})"
                                >
                                    {{ __('Edit Roles') }}
                                </flux:button>
                                @if($user->blocked)
                                    <flux:button
                                        size="sm"
                                        variant="filled"
                                        wire:click="unblockUser({{ $user->id }})"
                                        wire:confirm="{{ __('Are you sure you want to unblock this user?') }}"
                                    >
                                        {{ __('Unblock') }}
                                    </flux:button>
                                @else
                                    <flux:button
                                        size="sm"
                                        variant="danger"
                                        wire:click="blockUser({{ $user->id }})"
                                        wire:confirm="{{ __('Are you sure you want to block this user?') }}"
                                        :disabled="$user->id === auth()->id()"
                                    >
                                        {{ __('Block') }}
                                    </flux:button>
                                @endif
                                <flux:button
                                    size="sm"
                                    variant="danger"
                                    wire:click="deleteUser({{ $user->id }})"
                                    wire:confirm="{{ __('WARNING: This will permanently delete this user\'s music plans, scores and uploaded files, folders and lending links, as well as their private authors, collections and music, and anonymize their account. This cannot be undone. Are you sure?') }}"
                                    :disabled="$user->id === auth()->id()"
                                >
                                    {{ __('Delete') }}
                                </flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="11" class="text-center">{{ __('No users found.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
    <livewire:admin.edit-user-roles />
</x-pages::admin.layout>