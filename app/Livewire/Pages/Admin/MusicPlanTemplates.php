<?php

namespace App\Livewire\Pages\Admin;

use App\Http\Requests\StoreMusicPlanTemplateRequest;
use App\Http\Requests\UpdateMusicPlanTemplateRequest;
use App\Models\MusicPlanTemplate;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

class MusicPlanTemplates extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';

    public bool $showCreateModal = false;

    public bool $showEditModal = false;

    public ?MusicPlanTemplate $editingTemplate = null;

    // Form fields
    public string $name = '';

    public string $description = '';

    public bool $is_active = true;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->authorize('viewAny', MusicPlanTemplate::class);
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        $templates = MusicPlanTemplate::when($this->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.pages.admin.music-plan-templates', [
            'templates' => $templates,
        ]);
    }

    /**
     * Show the create modal.
     */
    public function showCreate(): void
    {
        $this->authorize('create', MusicPlanTemplate::class);
        $this->resetForm();
        $this->showCreateModal = true;
    }

    /**
     * Show the edit modal.
     */
    public function showEdit(MusicPlanTemplate $template): void
    {
        $this->authorize('update', $template);
        $this->editingTemplate = $template;
        $this->name = $template->name;
        $this->description = $template->description ?? '';
        $this->is_active = $template->is_active;
        $this->showEditModal = true;
    }

    /**
     * Create a new template.
     */
    public function create(): void
    {
        $this->authorize('create', MusicPlanTemplate::class);

        $validated = $this->validate((new StoreMusicPlanTemplateRequest)->rules());

        MusicPlanTemplate::create($validated);

        $this->showCreateModal = false;
        $this->resetForm();
        $this->dispatch('template-created');
    }

    /**
     * Update an existing template.
     */
    public function update(): void
    {
        $this->authorize('update', $this->editingTemplate);

        $validated = $this->validate((new UpdateMusicPlanTemplateRequest)->rules());

        $this->editingTemplate->update($validated);

        $this->showEditModal = false;
        $this->resetForm();
        $this->dispatch('template-updated');
    }

    /**
     * Soft delete a template.
     */
    public function delete(MusicPlanTemplate $template): void
    {
        $this->authorize('delete', $template);

        $template->delete();

        $this->dispatch('template-deleted');
    }

    /**
     * Toggle template active status.
     */
    public function toggleActive(MusicPlanTemplate $template): void
    {
        $this->authorize('update', $template);

        $template->update(['is_active' => ! $template->is_active]);

        $this->dispatch('template-status-updated');
    }

    /**
     * Reset form fields.
     */
    private function resetForm(): void
    {
        $this->reset(['name', 'description', 'is_active']);
        $this->editingTemplate = null;
        $this->resetErrorBag();
    }
}
