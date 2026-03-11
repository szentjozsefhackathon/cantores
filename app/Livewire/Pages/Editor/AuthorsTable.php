<?php

namespace App\Livewire\Pages\Editor;

use App\Models\Author;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class AuthorsTable extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    public string $filter = 'visible';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

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

    #[On('author-created')]
    #[On('author-updated')]
    public function refresh(): void {}

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

        return view('livewire.pages.editor.authors-table', [
            'authors' => $authors,
        ]);
    }
}
