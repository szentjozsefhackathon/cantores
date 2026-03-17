<?php

namespace App\Livewire\Pages;

use App\Models\Author;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class AuthorView extends Component
{
    use AuthorizesRequests, WithPagination;

    public Author $author;

    public string $search = '';

    public int $renderKey = 0;

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

    #[On('author-updated')]
    public function refreshAuthor(): void
    {
        $this->author = $this->author->fresh();
        $this->renderKey++;
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->author);

        if ($this->author->music()->count() > 0) {
            $this->dispatch('error', message: __('Cannot delete author that has music assigned to it.'));

            return;
        }

        $this->author->delete();

        $this->redirect(route('authors'), navigate: true);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $musics = $this->getMusicsQuery()->paginate(12);

        $musicCount = $this->author->music()->count();
        $description = "Szerző: {$this->author->name}. {$musicCount} zenemű érhető el tőle a Cantores.hu liturgikus Énektárában.";

        return view('pages.author-view', [
            'musics' => $musics,
        ])->layout('layouts::app.main', [
            'title' => $this->author->name,
            'description' => $description,
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
