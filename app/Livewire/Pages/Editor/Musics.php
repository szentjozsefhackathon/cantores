<?php

namespace App\Livewire\Pages\Editor;

use App\Facades\GenreContext;
use App\Models\Author;
use App\Models\Collection;
use App\Models\Genre;
use App\Models\Music;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use OwenIt\Auditing\Models\Audit;

class Musics extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';

    public string $collectionFilter = '';

    public string $collectionFreeText = '';

    public string $authorFilter = '';

    public string $authorFreeText = '';

    public bool $showCreateModal = false;

    public bool $showEditModal = false;

    public bool $showAuditModal = false;

    public ?Music $editingMusic = null;

    public ?Music $auditingMusic = null;

    public $audits = [];

    // Form fields
    public string $title = '';

    public ?string $subtitle = null;

    public ?string $customId = null;

    // Collection assignment
    public string $collectionSearch = '';

    public ?int $selectedCollectionId = null;

    public ?int $pageNumber = null;

    public ?string $orderNumber = null;

    public string $filter = 'all'; // 'visible', 'all', 'public', 'private', 'mine'

    public bool $isPrivate = false;

    /**
     * Selected music IDs for merging.
     */
    public array $selectedMusicIds = [];

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->authorize('viewAny', Music::class);
    }

    /**
     * Reset pagination when search changes.
     */
    public function updatingSearch(): void
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
     * Toggle selection of a music piece.
     */
    public function toggleSelection(int $musicId): void
    {
        if (in_array($musicId, $this->selectedMusicIds)) {
            $this->selectedMusicIds = array_diff($this->selectedMusicIds, [$musicId]);
        } else {
            $this->selectedMusicIds[] = $musicId;
        }
    }

    /**
     * Clear all selections.
     */
    public function clearSelections(): void
    {
        $this->selectedMusicIds = [];
    }

    /**
     * Determine if merge button should be enabled.
     */
    public function getCanMergeProperty(): bool
    {
        return count($this->selectedMusicIds) === 2;
    }

    /**
     * Navigate to music merger with selected IDs.
     */
    public function merge(): void
    {
        if (! $this->canMerge) {
            return;
        }

        $ids = $this->selectedMusicIds;
        sort($ids); // ensure consistent order
        $left = $ids[0];
        $right = $ids[1];

        $this->redirectRoute('music-merger', ['left' => $left, 'right' => $right]);
    }

    protected function applyScopes($query, bool $searching)
    {
        $query = $query
            ->visibleTo(Auth::user())
            ->when($this->filter === 'public', fn ($q) => $q->public())
            ->when($this->filter === 'private', fn ($q) => $q->private())
            ->when($this->filter === 'mine', fn ($q) => $q->where('user_id', Auth::id()));

        if ($searching) {
            $words = preg_split('/\s+/', trim($this->search), -1, PREG_SPLIT_NO_EMPTY);
            $query = $query->orWhere(function ($q) use ($words) {
                foreach ($words as $word) {
                    $q->where('musics.titles', 'ilike', '%'.$word.'%');
                }
            });
            $query->orderByRaw(
                'GREATEST('.implode(', ', array_fill(0, count($words), 'similarity(musics.titles, ?)')).') DESC',
                $words
            );
        }

        // Collections: keep your existing ilike logic; no full-text index required
        $query = $query
            ->when($this->collectionFilter !== '', function ($q) {
                $q->whereHas('collections', function ($subQuery) {
                    // keep whatever your existing scopeSearch does on Collection (Eloquent scope)
                    $subQuery->search($this->collectionFilter);
                });
            })
            ->when($this->authorFilter !== '', function ($q) {
                $q->whereHas('authors', function ($subQuery) {
                    // keep whatever your existing scopeSearch does on Author (Eloquent scope)
                    $subQuery->search($this->authorFilter);
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

        return view('pages.editor.musics', [
            'musics' => $musics,
        ]);
    }

    /**
     * Show the create modal.
     */
    public function create(): void
    {
        $this->authorize('create', Music::class);
        $this->resetForm();
        $this->showCreateModal = true;
    }

    /**
     * Redirect to the music editor page.
     */
    public function edit(Music $music): void
    {
        $this->authorize('update', $music);
        $this->redirectRoute('music-editor', ['music' => $music->id]);
    }

    /**
     * Show the audit log modal.
     */
    public function showAuditLog(Music $music): void
    {
        // Any logged-in user can view audit logs
        $this->authorize('view', $music);
        $this->auditingMusic = $music;
        $this->audits = $music->audits()
            ->with(['user.city', 'user.firstName'])
            ->latest()
            ->get();
        $this->showAuditModal = true;
    }

    /**
     * Store a new music piece.
     */
    public function store(): void
    {
        $this->authorize('create', Music::class);

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'customId' => ['nullable', 'string', 'max:255'],
            'isPrivate' => ['boolean'],
        ]);

        // Create music with owner
        $music = Music::create([
            'title' => $validated['title'],
            'subtitle' => $validated['subtitle'],
            'custom_id' => $validated['customId'],
            'user_id' => Auth::id(),
            'is_private' => $validated['isPrivate'] ?? false,
        ]);

        // Attach current genre if user has one selected
        $genreId = GenreContext::getId();
        if ($genreId) {
            $music->genres()->attach($genreId);
        }

        $this->showCreateModal = false;
        $this->resetForm();
        $this->redirectRoute('music-editor', ['music' => $music->id]);
    }

    /**
     * Update the editing music piece.
     */
    public function update(): void
    {
        $this->authorize('update', $this->editingMusic);

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'customId' => ['nullable', 'string', 'max:255'],
        ]);

        $this->editingMusic->update([
            'title' => $validated['title'],
            'subtitle' => $validated['subtitle'],
            'custom_id' => $validated['customId'],
        ]);

        $this->showEditModal = false;
        $this->resetForm();
        $this->editingMusic = null;
        $this->dispatch('music-updated');
    }

    /**
     * Delete a music piece.
     */
    public function delete(Music $music): void
    {
        $this->authorize('delete', $music);

        $music->delete();
        $this->dispatch('music-deleted');
    }

    /**
     * Reset form fields.
     */
    private function resetForm(): void
    {
        $this->title = '';
        $this->subtitle = null;
        $this->customId = null;
        $this->isPrivate = false;
        $this->collectionSearch = '';
        $this->selectedCollectionId = null;
        $this->pageNumber = null;
        $this->orderNumber = null;
    }
}
