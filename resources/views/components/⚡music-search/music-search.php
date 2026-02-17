<?php

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
     * Apply the same scopes as the musics page.
     */
    protected function applyScopes($query)
    {
        return $query
            ->visibleTo(Auth::user())
            ->when($this->filter === 'public', function ($query) {
                $query->public();
            })
            ->when($this->filter === 'private', function ($query) {
                $query->private();
            })
            ->when($this->filter === 'mine', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->when($this->collectionFilter !== '', function ($query) {
                $query->whereHas('collections', function ($subQuery) {
                    $subQuery->search($this->collectionFilter);
                });
            })
            ->when($this->collectionFreeText !== '', function ($query) {
                $words = preg_split('/\s+/', trim($this->collectionFreeText));
                $query->whereHas('collections', function ($subQuery) use ($words) {
                    foreach ($words as $word) {
                        $subQuery->where(function ($q) use ($word) {
                            $q->where('collections.title', 'ilike', "%{$word}%")
                                ->orWhere('collections.abbreviation', 'ilike', "%{$word}%")
                                ->orWhere('music_collection.order_number', 'ilike', "%{$word}%");
                        });
                    }
                });
            })
            ->forCurrentGenre()
            ->with(['genres', 'collections'])
            ->withCount('collections')
            ->orderBy('title');
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
     * Render the component.
     */
    public function render(): View
    {
        if ($this->search) {
            $musics = Music::search($this->search)
                ->query(
                    fn ($q) => $this->applyScopes($q)
                )
                ->paginate(10);
        } else {
            $musics = $this->applyScopes(Music::query())
                ->paginate(10);
        }

        return view('components.⚡music-search/music-search', [
            'musics' => $musics,
        ]);
    }
};
