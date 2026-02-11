<?php

namespace App\Livewire\Pages\Admin;

use App\Http\Requests\StoreMusicPlanTemplateSlotRequest;
use App\Models\MusicPlanSlot;
use App\Models\MusicPlanTemplate;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class MusicPlanTemplateSlots extends Component
{
    use AuthorizesRequests;

    public MusicPlanTemplate $template;

    public bool $showAddSlotModal = false;

    public bool $showEditSlotModal = false;

    public ?array $editingSlotPivot = null;

    // Add slot form fields
    public ?int $slot_id = null;

    public int $sequence = 1;

    public bool $is_included_by_default = true;

    // Edit slot form fields
    public int $edit_sequence = 1;

    public bool $edit_is_included_by_default = true;

    /**
     * Mount the component.
     */
    public function mount(MusicPlanTemplate $template): void
    {
        $this->template = $template->load('slots');
        $this->authorize('update', $template);
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        $availableSlots = MusicPlanSlot::active()
            ->whereNotIn('id', $this->template->slots->pluck('id'))
            ->orderBy('name')
            ->get();

        return view('livewire.pages.admin.music-plan-template-slots', [
            'template' => $this->template,
            'availableSlots' => $availableSlots,
        ]);
    }

    /**
     * Show the add slot modal.
     */
    public function showAddSlot(?int $slotId = null): void
    {
        $this->authorize('update', $this->template);
        $this->resetAddSlotForm();
        if ($slotId !== null) {
            $this->slot_id = $slotId;
        }
        $this->showAddSlotModal = true;
    }

    /**
     * Show the edit slot modal.
     */
    public function showEditSlot(array $slotPivot): void
    {
        $this->authorize('update', $this->template);
        $this->editingSlotPivot = $slotPivot;
        $this->edit_sequence = $slotPivot['pivot']['sequence'];
        $this->edit_is_included_by_default = $slotPivot['pivot']['is_included_by_default'];
        $this->showEditSlotModal = true;
    }

    /**
     * Add a slot to the template.
     */
    public function addSlot(): void
    {
        $this->authorize('update', $this->template);

        $validated = $this->validate((new StoreMusicPlanTemplateSlotRequest)->rules());

        $this->template->attachSlot(
            MusicPlanSlot::findOrFail($validated['slot_id']),
            $validated['sequence'],
            $validated['is_included_by_default']
        );

        $this->showAddSlotModal = false;
        $this->resetAddSlotForm();
        $this->dispatch('slot-added');
    }

    /**
     * Update a slot in the template.
     */
    public function updateSlot(): void
    {
        $this->authorize('update', $this->template);

        $validated = $this->validate([
            'edit_sequence' => ['required', 'integer', 'min:1'],
            'edit_is_included_by_default' => ['boolean'],
        ]);

        $slot = MusicPlanSlot::findOrFail($this->editingSlotPivot['id']);

        $this->template->updateSlot(
            $slot,
            $validated['edit_sequence'],
            $validated['edit_is_included_by_default']
        );

        $this->showEditSlotModal = false;
        $this->resetEditSlotForm();
        $this->dispatch('slot-updated');
    }

    /**
     * Remove a slot from the template.
     */
    public function removeSlot(MusicPlanSlot $slot): void
    {
        $this->authorize('update', $this->template);

        $this->template->detachSlot($slot);

        $this->dispatch('slot-removed');
    }

    /**
     * Reset add slot form fields.
     */
    private function resetAddSlotForm(): void
    {
        $this->reset(['slot_id', 'sequence', 'is_included_by_default']);
        $this->resetErrorBag();
    }

    /**
     * Reset edit slot form fields.
     */
    private function resetEditSlotForm(): void
    {
        $this->reset(['editingSlotPivot', 'edit_sequence', 'edit_is_included_by_default']);
        $this->resetErrorBag();
    }
}
