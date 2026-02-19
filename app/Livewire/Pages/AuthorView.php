<?php

namespace App\Livewire\Pages;

use App\Models\Author;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::app.main')]
class AuthorView extends Component
{
    use WithPagination;

    public Author $author;

    public string $search = '';

    public function mount($author): void
    {
        // Load existing author
        if (! $author instanceof Author) {
            $author = Author::visibleTo(Auth::user())->findOrFail($author);
        }

        // Check authorization using Gate (supports guest users)
        if (! Gate::allows('view', $author)) {
            abort(403);
        }

        $this->author = $author;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $musics = $this->getMusicsQuery()->paginate(12);

        return view('livewire.pages.author-view', [
            'musics' => $musics,
        ]);
    }

    protected function getMusicsQuery()
    {
        $query = $this->author->music()
            ->with(['collections', 'genres'])
            ->visibleTo(Auth::user());

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('title', 'ilike', '%'.$this->search.'%')
                    ->orWhere('subtitle', 'ilike', '%'.$this->search.'%')
                    ->orWhere('custom_id', 'ilike', '%'.$this->search.'%');
            });
        }

        return $query->orderBy('title');
    }
}
