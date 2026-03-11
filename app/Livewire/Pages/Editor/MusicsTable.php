<?php

namespace App\Livewire\Pages\Editor;

use App\Concerns\HasMusicSearchScopes;
use App\Models\Music;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class MusicsTable extends Component
{
    use AuthorizesRequests, HasMusicSearchScopes, WithPagination;

    public string $search = '';

    public string $collectionFilter = '';

    public string $collectionFreeText = '';

    public string $authorFilter = '';

    public string $authorFreeText = '';

    public string $filter = 'all';

    public array $tagFilters = [];

    public array $selectedMusicIds = [];

    public bool $filtersRendered = false;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCollectionFilter(): void
    {
        $this->resetPage();
    }

    public function updatingCollectionFreeText(): void
    {
        $this->resetPage();
    }

    public function updatingAuthorFilter(): void
    {
        $this->resetPage();
    }

    public function updatingAuthorFreeText(): void
    {
        $this->resetPage();
    }

    public function updatingTagFilters(): void
    {
        $this->resetPage();
    }

    #[On('genre-changed')]
    public function onGenreChanged(): void
    {
        $this->resetPage();
    }

    public function toggleSelection(int $musicId): void
    {
        if (in_array($musicId, $this->selectedMusicIds)) {
            $this->selectedMusicIds = array_diff($this->selectedMusicIds, [$musicId]);
        } else {
            $this->selectedMusicIds[] = $musicId;
        }
    }

    public function clearSelections(): void
    {
        $this->selectedMusicIds = [];
    }

    public function getCanMergeProperty(): bool
    {
        return count($this->selectedMusicIds) === 2;
    }

    public function merge(): void
    {
        if (! $this->canMerge) {
            return;
        }

        $ids = $this->selectedMusicIds;
        sort($ids);
        [$left, $right] = $ids;

        $this->redirectRoute('music-merger', ['left' => $left, 'right' => $right]);
    }

    public function delete(Music $music): void
    {
        $this->authorize('delete', $music);

        $music->delete();
        $this->dispatch('music-deleted');
    }

    public function getTagsProperty()
    {
        return \App\Models\MusicTag::orderBy('name')->get();
    }

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

        $renderFilters = ! $this->filtersRendered;
        $this->filtersRendered = true;

        return view('livewire.pages.editor.musics-table', [
            'musics' => $musics,
            'renderFilters' => $renderFilters,
        ]);
    }
}
