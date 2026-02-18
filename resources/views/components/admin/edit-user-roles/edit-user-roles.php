<?php

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Spatie\Permission\Models\Role;

new class extends Component
{
    public ?User $user = null;

    public bool $showModal = false;

    public array $selectedRoles = [];

    public array $availableRoles = [];

    public function mount($userId = null): void
    {
        $this->availableRoles = Role::pluck('name')->toArray();
        if ($userId) {
            $this->user = User::find($userId);
            if ($this->user) {
                $this->selectedRoles = $this->user->roles->pluck('name')->toArray();
            }
        }
    }

    #[On('openUserRolesModal')]
    public function openModal($userId): void
    {
        if ($userId) {
            $this->user = User::find($userId);
            if ($this->user) {
                $this->selectedRoles = $this->user->roles->pluck('name')->toArray();
            }
        }

        if (! $this->user) {
            $this->dispatch('error', message: __('Unable to load user.'));

            return;
        }

        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset('user', 'selectedRoles');
    }

    public function save(): void
    {
        $this->validate([
            'selectedRoles' => 'array',
            'selectedRoles.*' => 'string|in:'.implode(',', $this->availableRoles),
        ]);

        if (! $this->user) {
            $this->dispatch('error', message: __('No user selected.'));

            return;
        }

        // Check if current user is admin
        if (! Auth::user()->hasRole('admin')) {
            $this->dispatch('error', message: __('You do not have permission to edit roles.'));

            return;
        }

        // Sync roles
        $this->user->syncRoles($this->selectedRoles);

        $this->dispatch('success', message: __('User roles updated successfully.'));
        $this->closeModal();
        $this->dispatch('refresh-users');
    }

    public function render(): View
    {
        return view('components.admin.edit-user-roles.edit-user-roles');
    }
};
