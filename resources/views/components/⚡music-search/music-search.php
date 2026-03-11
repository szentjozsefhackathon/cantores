<?php

use App\Concerns\HasMusicSearchScopes;
use App\Models\Author;
use App\Models\Collection;
use App\Models\Music;
use App\Models\MusicTag;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

return new class extends Component
{
    use AuthorizesRequests, HasMusicSearchScopes, WithPagination;

    public string $source = '';

    public string $search = '';

    public string $collectionFilter = '';

    public string $collectionFreeText = '';

    public string $authorFilter = '';

    public string $authorFreeText = '';

    public array $tagFilters = [];

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
     * Reset pagination when tag filters change.
     */
    public function updatingTagFilters(): void
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
     * Get available tags for the filter.
     */
    public function getTagsProperty()
    {
        return MusicTag::orderBy('name')->get();
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
};
