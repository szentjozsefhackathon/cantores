<?php

use App\Models\Author;
use App\Models\Collection;
use App\Models\Music;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

return new class extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $source = '';

    public string $search = '';

    public string $filter = 'all';

    public string $collectionFilter = '';

    public string $collectionFreeText = '';

    public string $authorFilter = '';

    public string $authorFreeText = '';

    public bool $selectable = false;

    /**
     * Mount the component.
     */
    public function mount(bool $selectable = false): void
    {
        $this->authorize('viewAny', Music::class);
        $this->selectable = $selectable;
    }

    /**
     * Reset pagination when search changes.
     */
    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Reset pagination when filter changes.
     */
    public function updatingFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Reset pagination when collection filter changes.
     */
    public function updatingCollectionFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Reset pagination when collection free text filter changes.
     */
    public function updatingCollectionFreeText(): void
    {
        $this->resetPage();
    }

    /**
     * Reset pagination when author filter changes.
     */
    public function updatingAuthorFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Reset pagination when author free text filter changes.
     */
    public function updatingAuthorFreeText(): void
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
     * Emit event when a music is selected.
     */
    public function selectMusic($musicId): void
    {
        $this->dispatch("music-selected{$this->source}", musicId: (int) $musicId);
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        if ($this->search) {
            $musics = Music::search($this->search)
                ->query(fn ($q) => $this->applyScopes($q, searching: true))
                ->paginate(10);
        } else {
            $musics = $this->applyScopes(Music::query(), searching: false)
                ->paginate(10);
        }

        return view('components.⚡music-search/music-search', [
            'musics' => $musics,
        ]);
    }

    protected function applyScopes($query, bool $searching)
    {
        $query = $query
            ->visibleTo(Auth::user())
            ->when($this->filter === 'public', fn ($q) => $q->public())
            ->when($this->filter === 'private', fn ($q) => $q->private())
            ->when($this->filter === 'mine', fn ($q) => $q->where('user_id', Auth::id()));

        // Collections: keep your existing ilike logic; no full-text index required
        $query = $query
            ->when($this->collectionFilter !== '', function ($q) {
                $q->whereHas('collections', function ($subQuery) {
                    // keep whatever your existing scopeSearch does on Collection (Eloquent scope)
                    $subQuery->search($this->collectionFilter);
                });
            })
            ->when($this->collectionFreeText !== '', function ($q) {
                $words = preg_split('/\s+/', trim($this->collectionFreeText));
                $q->whereHas('collections', function ($subQuery) use ($words) {
                    foreach ($words as $word) {
                        $subQuery->where(function ($qq) use ($word) {
                            $qq->where('collections.title', 'ilike', "%{$word}%")
                                ->orWhere('collections.abbreviation', 'ilike', "%{$word}%")
                                ->orWhere('music_collection.order_number', 'ilike', "%{$word}%");
                        });
                    }
                });
            });

        $query = $query
            ->when($this->authorFreeText !== '', function ($q) {
                $authorIds = Author::search($this->authorFreeText)
                    ->take(500)
                    ->keys();

                if ($authorIds->isEmpty()) {
                    $q->whereRaw('1=0'); // AND semantics: no matching author => no musics

                    return;
                }

                $q->whereHas('authors', fn ($aq) => $aq->whereIn('authors.id', $authorIds));
            });

        $query = $query
            ->forCurrentGenre()
            ->with(['genres', 'collections', 'authors'])
            ->withCount('collections');

        // Only order by title when NOT using Scout search (keep relevance rank when searching)
        if (! $searching) {
            $query->orderBy('title');
        }

        return $query;
    }

    /**
     * Get collections for the dropdown filter.
     */
    public function getCollectionsProperty()
    {
        return Collection::visibleTo(Auth::user())
            ->forCurrentGenre()
            ->orderBy('title')
            ->get();
    }

    /**
     * Get authors for the dropdown filter.
     */
    public function getAuthorsProperty()
    {
        return \App\Models\Author::visibleTo(Auth::user())
            ->orderBy('name')
            ->get();
    }
};
