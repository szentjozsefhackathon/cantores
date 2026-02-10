<?php

namespace App\Livewire\Pages\Admin;

use App\Http\Requests\StoreMusicPlanSlotRequest;
use App\Http\Requests\UpdateMusicPlanSlotRequest;
use App\Models\MusicPlanSlot;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

class MusicPlanSlots extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';

    public bool $showCreateModal = false;

    public bool $showEditModal = false;

    public ?MusicPlanSlot $editingSlot = null;

    // Form fields
    public string $name = '';

    public string $description = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->authorize('viewAny', MusicPlanSlot::class);
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        $slots = MusicPlanSlot::when($this->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.pages.admin.music-plan-slots', [
            'musicPlanSlots' => $slots,
        ]);
    }

    /**
     * Show the create modal.
     */
    public function showCreate(): void
    {
        $this->authorize('create', MusicPlanSlot::class);
        $this->resetForm();
        $this->showCreateModal = true;
    }

    /**
     * Show the edit modal.
     */
    public function showEdit(MusicPlanSlot $slot): void
    {
        $this->authorize('update', $slot);
        $this->editingSlot = $slot;
        $this->name = $slot->name;
        $this->description = $slot->description ?? '';
        $this->showEditModal = true;
    }

    /**
     * Create a new slot.
     */
    public function create(): void
    {
        $this->authorize('create', MusicPlanSlot::class);

        $validated = $this->validate((new StoreMusicPlanSlotRequest)->rules());

        MusicPlanSlot::create($validated);

        $this->showCreateModal = false;
        $this->resetForm();
        $this->dispatch('slot-created');
    }

    /**
     * Update an existing slot.
     */
    public function update(): void
    {
        $this->authorize('update', $this->editingSlot);

        $validated = $this->validate((new UpdateMusicPlanSlotRequest)->rules());

        $this->editingSlot->update($validated);

        $this->showEditModal = false;
        $this->resetForm();
        $this->dispatch('slot-updated');
    }

    /**
     * Soft delete a slot.
     */
    public function delete(MusicPlanSlot $slot): void
    {
        $this->authorize('delete', $slot);

        $slot->delete();

        $this->dispatch('slot-deleted');
    }

    /**
     * Reset form fields.
     */
    private function resetForm(): void
    {
        $this->reset(['name', 'description']);
        $this->editingSlot = null;
        $this->resetErrorBag();
    }
}
