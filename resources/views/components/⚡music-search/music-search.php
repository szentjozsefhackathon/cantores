<?php

use App\Models\Music;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

return new class extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $source = '';

    public string $search = '';

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
        $musics = Music::when($this->search, function ($query, $search) {
            $query->search($search);
        })
            ->forCurrentGenre()
            ->with(['genres', 'collections'])
            ->withCount('collections')
            ->orderBy('title')
            ->paginate(10);

        return view('components.⚡music-search/music-search', [
            'musics' => $musics,
        ]);
    }
};
