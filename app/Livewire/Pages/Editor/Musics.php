<?php

namespace App\Livewire\Pages\Editor;

use App\Models\Music;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use OwenIt\Auditing\Models\Audit;

class Musics extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';

    public bool $showCreateModal = false;

    public bool $showEditModal = false;

    public bool $showAuditModal = false;

    public ?Music $editingMusic = null;

    public ?Music $auditingMusic = null;

    public $audits = [];

    // Form fields
    public string $title = '';

    public ?string $customId = null;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->authorize('viewAny', Music::class);
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        $musics = Music::when($this->search, function ($query, $search) {
            $query->where('title', 'like', "%{$search}%")
                ->orWhere('custom_id', 'like', "%{$search}%");
        })
            ->withCount('collections')
            ->orderBy('title')
            ->paginate(10);

        return view('pages.editor.musics', [
            'musics' => $musics,
        ]);
    }

    /**
     * Show the create modal.
     */
    public function create(): void
    {
        $this->authorize('create', Music::class);
        $this->resetForm();
        $this->showCreateModal = true;
    }

    /**
     * Show the edit modal.
     */
    public function edit(Music $music): void
    {
        $this->authorize('update', $music);
        $this->editingMusic = $music;
        $this->title = $music->title;
        $this->customId = $music->custom_id;
        $this->showEditModal = true;
    }

    /**
     * Show the audit log modal.
     */
    public function showAuditLog(Music $music): void
    {
        // Any logged-in user can view audit logs
        $this->authorize('view', $music);
        $this->auditingMusic = $music;
        $this->audits = $music->audits()
            ->with(['user.city', 'user.firstName'])
            ->latest()
            ->get();
        $this->showAuditModal = true;
    }

    /**
     * Store a new music piece.
     */
    public function store(): void
    {
        $this->authorize('create', Music::class);

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'customId' => ['nullable', 'string', 'max:255'],
        ]);

        // Map customId to custom_id
        Music::create([
            'title' => $validated['title'],
            'custom_id' => $validated['customId'],
        ]);

        $this->showCreateModal = false;
        $this->resetForm();
        $this->dispatch('music-created');
    }

    /**
     * Update the editing music piece.
     */
    public function update(): void
    {
        $this->authorize('update', $this->editingMusic);

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'customId' => ['nullable', 'string', 'max:255'],
        ]);

        $this->editingMusic->update([
            'title' => $validated['title'],
            'custom_id' => $validated['customId'],
        ]);

        $this->showEditModal = false;
        $this->resetForm();
        $this->editingMusic = null;
        $this->dispatch('music-updated');
    }

    /**
     * Delete a music piece.
     */
    public function delete(Music $music): void
    {
        $this->authorize('delete', $music);

        // Check if music has any collections or plan slots assigned
        if ($music->collections()->count() > 0 || $music->musicPlanSlotAssignments()->count() > 0) {
            $this->dispatch('error', __('Cannot delete music piece that has collections or plan slots assigned to it.'));

            return;
        }

        $music->delete();
        $this->dispatch('music-deleted');
    }

    /**
     * Reset form fields.
     */
    private function resetForm(): void
    {
        $this->title = '';
        $this->customId = null;
    }
}
