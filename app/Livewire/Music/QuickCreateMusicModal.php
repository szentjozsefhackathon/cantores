<?php

namespace App\Livewire\Music;

use App\Models\Author;
use App\Models\Collection;
use App\Models\Genre;
use App\Models\Music;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class QuickCreateMusicModal extends Component
{
    public bool $open = false;

    public string $title = '';

    public ?string $subtitle = null;

    public ?int $selectedAuthorId = null;

    public ?int $selectedCollectionId = null;

    public ?int $orderNumber = null;

    public ?int $pageNumber = null;

    public bool $isPrivate = false;

    public array $selectedGenres = [];

    public bool $showConfirmation = false;

    public ?array $pendingMusicData = null;

    public string $authorSearch = '';

    public string $collectionSearch = '';

    public function render()
    {
        $authors = Author::visibleTo(Auth::user())
            ->where('name', 'ilike', "%{$this->authorSearch}%")
            ->orderBy('name')
            ->take(20)
            ->get();

        $collections = Collection::visibleTo(Auth::user())
            ->where(function ($q) {
                $q->where('title', 'ilike', "%{$this->collectionSearch}%")
                    ->orWhere('abbreviation', 'ilike', "%{$this->collectionSearch}%");
            })
            ->orderBy('title')
            ->take(20)
            ->get();

        return view('livewire.music.quick-create-music-modal', [
            'authors' => $authors,
            'collections' => $collections,
            'genres' => Genre::orderBy('name')->get(),
            'selectedAuthor' => $this->selectedAuthorId ? Author::find($this->selectedAuthorId) : null,
            'selectedCollection' => $this->selectedCollectionId ? Collection::find($this->selectedCollectionId) : null,
        ]);
    }

    public function openModal(): void
    {
        $this->resetForm();
        $this->open = true;
    }

    public function closeModal(): void
    {
        $this->open = false;
        $this->showConfirmation = false;
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->title = '';
        $this->subtitle = null;
        $this->selectedAuthorId = null;
        $this->authorSearch = '';
        $this->selectedCollectionId = null;
        $this->collectionSearch = '';
        $this->orderNumber = null;
        $this->pageNumber = null;
        $this->isPrivate = false;
        $this->selectedGenres = [];
        $this->pendingMusicData = null;
    }

    public function checkAndCreate(): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'selectedAuthorId' => ['nullable', 'integer', 'exists:authors,id'],
            'selectedCollectionId' => ['nullable', 'integer', 'exists:collections,id'],
            'orderNumber' => ['nullable', 'integer', 'min:0'],
            'pageNumber' => ['nullable', 'integer', 'min:0'],
            'selectedGenres' => ['array'],
            'selectedGenres.*' => ['integer', 'exists:genres,id'],
        ]);

        // Check if music already exists
        $existingMusic = $this->findExistingMusic();

        if ($existingMusic) {
            $this->pendingMusicData = [
                'existingMusicId' => $existingMusic->id,
            ];
            $this->showConfirmation = true;

            return;
        }

        $this->createMusic();
    }

    public function confirmCreate(): void
    {
        $this->createMusic();
    }

    public function cancelConfirmation(): void
    {
        $this->showConfirmation = false;
        $this->pendingMusicData = null;
    }

    protected function findExistingMusic(): ?Music
    {
        $query = Music::where('title', 'ilike', $this->title);

        if ($this->selectedCollectionId && $this->orderNumber !== null) {
            $query = $query->whereHas('collections', function ($q) {
                $q->where('collections.id', $this->selectedCollectionId)
                    ->where('music_collection.order_number', $this->orderNumber);
            });
        }

        return $query->first();
    }

    protected function createMusic(): void
    {
        $music = Music::create([
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'user_id' => Auth::id(),
            'is_private' => $this->isPrivate,
        ]);

        if ($this->selectedAuthorId) {
            $music->authors()->attach($this->selectedAuthorId, ['user_id' => Auth::id()]);
        }

        if ($this->selectedCollectionId) {
            $music->collections()->attach($this->selectedCollectionId, [
                'user_id' => Auth::id(),
                'order_number' => $this->orderNumber,
                'page_number' => $this->pageNumber,
            ]);
        }

        if ($this->selectedGenres) {
            $music->genres()->attach($this->selectedGenres);
        }

        $this->dispatch('musicCreated', musicId: $music->id, title: $music->title);
        $this->closeModal();
    }

    public function create(): void
    {
        $this->checkAndCreate();
    }
}
