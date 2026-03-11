<?php

namespace App\Livewire\Pages\Editor;

use App\Models\Author;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View as IlluminateView;
use Livewire\Component;
use Livewire\WithPagination;

class Authors extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';

    public bool $showCreateModal = false;

    // Form fields for create modal
    public string $name = '';

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    public string $filter = 'visible'; // 'visible', 'all', 'public', 'private', 'mine'

    public bool $isPrivate = false;

    public function rendering(IlluminateView $view): void
    {
        $layout = Auth::check() ? 'layouts::app' : 'layouts::app.main';
        $view->layout($layout);
    }

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
                $query->where('name', 'ilike', "%{$search}%");
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
     * Store a new author.
     */
    public function store(): void
    {
        $this->authorize('create', Author::class);

        $validated = $this->validate([
            'name' => $this->getNameValidationRule(),
            'isPrivate' => ['boolean'],
        ]);

        Author::create([
            ...$validated,
            'user_id' => Auth::id(),
            'is_private' => $validated['isPrivate'] ?? false,
        ]);

        $this->showCreateModal = false;
        $this->resetForm();
        $this->dispatch('author-created');
    }

    /**
     * Delete an author.
     */
    public function delete(Author $author): void
    {
        $this->authorize('delete', $author);

        if ($author->music()->count() > 0) {
            $this->dispatch('error', message: __('Cannot delete author that has music assigned to it.'));

            return;
        }

        $author->delete();
        $this->dispatch('author-deleted');
    }

    /**
     * Get the appropriate validation rule for the name field.
     * Only enforces uniqueness for public authors.
     */
    private function getNameValidationRule(): array
    {
        $rules = ['required', 'string', 'max:255'];

        $rules[] = function ($attribute, $value, $fail) {
            if ($this->isPrivate === false) {
                $exists = Author::where('name', $value)
                    ->where('is_private', false)
                    ->exists();

                if ($exists) {
                    $fail(__('An author with this name already exists in the public library.'));
                }
            }
        };

        return $rules;
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
