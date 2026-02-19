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

    // Filtering
    public string $filterType = 'all'; // 'all', 'global', 'custom'

    // Sorting
    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    // Form fields
    public string $name = '';

    public string $description = '';

    public int $priority = 0;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->authorize('viewAny', MusicPlanSlot::class);
    }

    /**
     * Sort the table by the given column.
     */
    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        $slots = MusicPlanSlot::query()
            ->when($this->filterType === 'global', fn ($q) => $q->global())
            ->when($this->filterType === 'custom', fn ($q) => $q->custom())
            ->when($this->search, function ($query, $search) {
                $query->where('name', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%");
            })
            ->with(['musicPlan', 'owner'])
            ->withCount('templates')
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(20);

        return view('pages.admin.music-plan-slots', [
            'musicPlanSlots' => $slots,
            'sortBy' => $this->sortBy,
            'sortDirection' => $this->sortDirection,
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
        $this->priority = (int) $slot->priority;
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

        $validated = $this->validate((new UpdateMusicPlanSlotRequest)->rules($this->editingSlot->id));

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
        $this->reset(['name', 'description', 'priority']);
        $this->editingSlot = null;
        $this->resetErrorBag();
    }
}
