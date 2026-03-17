<?php

namespace App\Livewire\Editor;

use App\Models\Music;
use App\MusicRelationshipType;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

return new class extends Component
{
    use AuthorizesRequests, WithPagination;

    #[Url]
    public string $search = '';

    public string $sortBy = 'title';

    public string $sortDirection = 'asc';

    public function mount(): void
    {
        $this->authorize('viewAny', Music::class);
    }

    public function getMusicsWithDuplicatesProperty()
    {
        $query = Music::query()
            ->visibleTo(Auth::user())
            ->whereHas('directMusicRelations', function ($q) {
                $q->where('relationship_type', MusicRelationshipType::Duplicate->value);
            })
            ->withCount(['directMusicRelations as duplicate_count' => function ($q) {
                $q->where('relationship_type', MusicRelationshipType::Duplicate->value);
            }])
            ->with(['directMusicRelations' => function ($q) {
                $q->where('relationship_type', MusicRelationshipType::Duplicate->value)
                    ->with(['relatedMusic.collections']);
            }, 'collections']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('title', 'ilike', "%{$this->search}%")
                    ->orWhere('custom_id', 'ilike', "%{$this->search}%")
                    ->orWhere('subtitle', 'ilike', "%{$this->search}%");
            });
        }

        $query->orderBy($this->sortBy, $this->sortDirection);

        return $query->paginate(20);
    }

    public function getFirstDuplicateId(Music $music): ?int
    {
        $duplicate = $music->directMusicRelations
            ->where('relationship_type', MusicRelationshipType::Duplicate->value)
            ->first();

        if ($duplicate) {
            return $duplicate->related_music_id;
        }

        $inverseDuplicate = $music->inverseMusicRelations
            ->where('relationship_type', MusicRelationshipType::Duplicate->value)
            ->first();

        return $inverseDuplicate?->music_id;
    }

    public function merge(Music $music): void
    {
        $duplicateId = $this->getFirstDuplicateId($music);

        if (! $duplicateId) {
            return;
        }

        $ids = [$music->id, $duplicateId];
        sort($ids);
        [$left, $right] = $ids;

        $this->redirectRoute('music-merger', ['left' => $left, 'right' => $right]);
    }

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
    }

    public function render(): View
    {
        return view('components.editor.⚡duplicate-merger.duplicate-merger', [
            'musics' => $this->musicsWithDuplicates,
        ]);
    }
};
