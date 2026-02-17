<?php

namespace App\Livewire\Pages\Editor;

use App\Models\Author;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use OwenIt\Auditing\Models\Audit;

class Authors extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';

    public bool $showCreateModal = false;

    public bool $showEditModal = false;

    public bool $showAuditModal = false;

    public ?Author $editingAuthor = null;

    public ?Author $auditingAuthor = null;

    public $audits = [];

    // Form fields
    public string $name = '';

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    public string $filter = 'visible'; // 'visible', 'all', 'public', 'private', 'mine'

    public bool $isPrivate = false;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->authorize('viewAny', Author::class);
    }

    /**
     * Reset pagination when search changes.
     */
    public function updatingSearch(): void
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
     * Render the component.
     */
    public function render(): View
    {
        $authors = Author::visibleTo(Auth::user())
            ->when($this->search, function ($query, $search) {
                // Use Scout full-text search if search is not empty
                if (config('scout.driver') === 'database' && ! empty($search)) {
                    $query->whereFullText('name', $search, ['language' => 'hungarian']);
                } else {
                    $query->where('name', 'ilike', "%{$search}%");
                }
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
            ->withCount('music')
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(10);

        return view('pages.editor.authors', [
            'authors' => $authors,
        ]);
    }

    /**
     * Show the create modal.
     */
    public function create(): void
    {
        $this->authorize('create', Author::class);
        $this->resetForm();
        $this->showCreateModal = true;
    }

    /**
     * Show the edit modal.
     */
    public function edit(Author $author): void
    {
        $this->authorize('update', $author);
        $this->editingAuthor = $author;
        $this->name = $author->name;
        $this->isPrivate = $author->is_private;
        $this->showEditModal = true;
    }

    /**
     * Show the audit log modal.
     */
    public function showAuditLog(Author $author): void
    {
        // Any logged-in user can view audit logs
        $this->authorize('view', $author);
        $this->auditingAuthor = $author;
        $this->audits = $author->audits()
            ->with(['user.city', 'user.firstName'])
            ->latest()
            ->get();
        $this->showAuditModal = true;
    }

    /**
     * Store a new author.
     */
    public function store(): void
    {
        $this->authorize('create', Author::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('authors', 'name')],
            'isPrivate' => ['boolean'],
        ]);

        $author = Author::create([
            ...$validated,
            'user_id' => Auth::id(),
            'is_private' => $validated['isPrivate'] ?? false,
        ]);

        $this->showCreateModal = false;
        $this->resetForm();
        $this->dispatch('author-created');
    }

    /**
     * Update the editing author.
     */
    public function update(): void
    {
        $this->authorize('update', $this->editingAuthor);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('authors', 'name')->ignore($this->editingAuthor->id)],
            'isPrivate' => ['boolean'],
        ]);

        $this->editingAuthor->update([
            ...$validated,
            'is_private' => $validated['isPrivate'] ?? false,
        ]);

        $this->showEditModal = false;
        $this->resetForm();
        $this->editingAuthor = null;
        $this->dispatch('author-updated');
    }

    /**
     * Delete an author.
     */
    public function delete(Author $author): void
    {
        $this->authorize('delete', $author);

        // Check if author has any music assigned
        if ($author->music()->count() > 0) {
            $this->dispatch('error', message: __('Cannot delete author that has music assigned to it.'));

            return;
        }

        $author->delete();
        $this->dispatch('author-deleted');
    }

    /**
     * Reset form fields.
     */
    private function resetForm(): void
    {
        $this->name = '';
        $this->isPrivate = false;
    }
}
