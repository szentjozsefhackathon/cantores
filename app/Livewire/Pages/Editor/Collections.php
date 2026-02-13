<?php

namespace App\Livewire\Pages\Editor;

use App\Models\Collection;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use OwenIt\Auditing\Models\Audit;

class Collections extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';

    public bool $showCreateModal = false;

    public bool $showEditModal = false;

    public bool $showAuditModal = false;

    public ?Collection $editingCollection = null;

    public ?Collection $auditingCollection = null;

    public $audits = [];

    // Form fields
    public string $title = '';

    public ?string $abbreviation = null;

    public ?string $author = null;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->authorize('viewAny', Collection::class);
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        $collections = Collection::when($this->search, function ($query, $search) {
            $query->where('title', 'ilike', "%{$search}%")
                ->orWhere('abbreviation', 'ilike', "%{$search}%")
                ->orWhere('author', 'ilike', "%{$search}%");
        })
            ->withCount('music')
            ->orderBy('title')
            ->paginate(10);

        return view('pages.editor.collections', [
            'collections' => $collections,
        ]);
    }

    /**
     * Show the create modal.
     */
    public function create(): void
    {
        $this->authorize('create', Collection::class);
        $this->resetForm();
        $this->showCreateModal = true;
    }

    /**
     * Show the edit modal.
     */
    public function edit(Collection $collection): void
    {
        $this->authorize('update', $collection);
        $this->editingCollection = $collection;
        $this->title = $collection->title;
        $this->abbreviation = $collection->abbreviation;
        $this->author = $collection->author;
        $this->showEditModal = true;
    }

    /**
     * Show the audit log modal.
     */
    public function showAuditLog(Collection $collection): void
    {
        // Any logged-in user can view audit logs
        $this->authorize('view', $collection);
        $this->auditingCollection = $collection;
        $this->audits = $collection->audits()
            ->with(['user.city', 'user.firstName'])
            ->latest()
            ->get();
        $this->showAuditModal = true;
    }

    /**
     * Store a new collection.
     */
    public function store(): void
    {
        $this->authorize('create', Collection::class);

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255', Rule::unique('collections', 'title')],
            'abbreviation' => ['nullable', 'string', 'max:20', Rule::unique('collections', 'abbreviation')],
            'author' => ['nullable', 'string', 'max:255'],
        ]);

        $collection = Collection::create($validated);

        // Attach the current realm if set
        $realmId = Auth::user()->current_realm_id;
        if ($realmId) {
            $collection->realms()->attach($realmId);
        }

        $this->showCreateModal = false;
        $this->resetForm();
        $this->dispatch('collection-created');
    }

    /**
     * Update the editing collection.
     */
    public function update(): void
    {
        $this->authorize('update', $this->editingCollection);

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255', Rule::unique('collections', 'title')->ignore($this->editingCollection->id)],
            'abbreviation' => ['nullable', 'string', 'max:20', Rule::unique('collections', 'abbreviation')->ignore($this->editingCollection->id)],
            'author' => ['nullable', 'string', 'max:255'],
        ]);

        $this->editingCollection->update($validated);

        $this->showEditModal = false;
        $this->resetForm();
        $this->editingCollection = null;
        $this->dispatch('collection-updated');
    }

    /**
     * Delete a collection.
     */
    public function delete(Collection $collection): void
    {
        $this->authorize('delete', $collection);

        // Check if collection has any music assigned
        if ($collection->music()->count() > 0) {
            $this->dispatch('error', message: __('Cannot delete collection that has music assigned to it.'));

            return;
        }

        $collection->delete();
        $this->dispatch('collection-deleted');
    }

    /**
     * Reset form fields.
     */
    private function resetForm(): void
    {
        $this->title = '';
        $this->abbreviation = null;
        $this->author = null;
    }
}
