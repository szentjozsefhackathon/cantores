<?php

namespace App\Livewire\Pages\Editor;

use App\Facades\GenreContext;
use App\Models\Collection;
use App\Models\Genre;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
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

    public array $selectedGenres = [];

    public string $sortBy = 'title';

    public string $sortDirection = 'asc';

    public string $filter = 'visible'; // 'visible', 'all', 'public', 'private', 'mine'

    public bool $isPrivate = false;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->authorize('viewAny', Collection::class);
    }

    /**
     * Reset pagination when search changes.
     */
    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Handle genre change event.
     */
    #[On('genre-changed')]
    public function onGenreChanged(): void
    {
        $this->resetPage();
    }

    /**
     * Sort the table by a column.
     */
    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    /**
     * Get all genres for selection.
     */
    public function genres(): \Illuminate\Database\Eloquent\Collection
    {
        return Genre::all();
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        $collections = Collection::visibleTo(Auth::user())
            ->when($this->search, function ($query, $search) {
                $query->where('title', 'ilike', "%{$search}%")
                    ->orWhere('abbreviation', 'ilike', "%{$search}%")
                    ->orWhere('author', 'ilike', "%{$search}%");
            })
            ->when($this->filter === 'public', function ($query) {
                $query->public();
            })
            ->when($this->filter === 'private', function ($query) {
                $query->private();
            })
            ->when($this->filter === 'mine', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->forCurrentGenre()
            ->with(['genres'])
            ->withCount('music')
            ->orderBy($this->sortBy, $this->sortDirection)
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
        // Pre-select the user's current genre
        $genreId = GenreContext::getId();
        if ($genreId) {
            $this->selectedGenres = [$genreId];
        }
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
        $this->isPrivate = $collection->is_private;
        $this->selectedGenres = $collection->genres->pluck('id')->toArray();
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
            'isPrivate' => ['boolean'],
            'selectedGenres' => ['nullable', 'array'],
            'selectedGenres.*' => ['integer', Rule::exists('genres', 'id')],
        ]);

        $collection = Collection::create([
            ...$validated,
            'user_id' => Auth::id(),
            'is_private' => $validated['isPrivate'] ?? false,
        ]);

        // Attach selected genres (empty array will detach all)
        $collection->genres()->sync($validated['selectedGenres'] ?? []);

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
            'isPrivate' => ['boolean'],
            'selectedGenres' => ['nullable', 'array'],
            'selectedGenres.*' => ['integer', Rule::exists('genres', 'id')],
        ]);

        $this->editingCollection->update([
            ...$validated,
            'is_private' => $validated['isPrivate'] ?? false,
        ]);

        // Sync selected genres (empty array will detach all)
        $this->editingCollection->genres()->sync($validated['selectedGenres'] ?? []);

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
        $this->isPrivate = false;
        $this->selectedGenres = [];
    }
}
