<?php

namespace App\Livewire\Pages\Editor;

use App\Models\Collection;
use App\Models\Music;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
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

    // Collection assignment
    public string $collectionSearch = '';

    public ?int $selectedCollectionId = null;

    public ?int $pageNumber = null;

    public ?string $orderNumber = null;

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
            $query->where('title', 'ilike', "%{$search}%")
                ->orWhere('custom_id', 'ilike', "%{$search}%");
        })
            ->withCount('collections')
            ->orderBy('title')
            ->paginate(10);

        $collections = Collection::orderBy('title')->limit(100)->get();

        return view('pages.editor.musics', [
            'musics' => $musics,
            'collections' => $collections,
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
     * Redirect to the music editor page.
     */
    public function edit(Music $music): void
    {
        $this->authorize('update', $music);
        $this->redirectRoute('music-editor', ['music' => $music->id]);
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

        // Create music
        $music = Music::create([
            'title' => $validated['title'],
            'custom_id' => $validated['customId'],
        ]);

        // Attach current realm if user has one selected
        $realmId = Auth::user()->current_realm_id;
        if ($realmId) {
            $music->realms()->attach($realmId);
        }

        $this->showCreateModal = false;
        $this->resetForm();
        $this->redirectRoute('music-editor', ['music' => $music->id]);
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
            $this->dispatch('error', message: __('Cannot delete music piece that has collections or plan slots assigned to it.'));

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
        $this->collectionSearch = '';
        $this->selectedCollectionId = null;
        $this->pageNumber = null;
        $this->orderNumber = null;
    }
}
