<?php

namespace App\Livewire\Pages;

use App\Models\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::app.main')]
class CollectionView extends Component
{
    use WithPagination;

    public Collection $collection;

    public string $search = '';

    public function mount($collection): void
    {
        // Load existing collection
        if (! $collection instanceof Collection) {
            $collection = Collection::findOrFail($collection);
        }

        // Check authorization using Gate (supports guest users)
        if (! Gate::allows('view', $collection)) {
            abort(403);
        }

        $this->collection = $collection;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $musics = $this->getMusicsQuery()->paginate(12);

        return view('livewire.pages.collection-view', [
            'musics' => $musics,
        ]);
    }

    protected function getMusicsQuery()
    {
        $query = $this->collection->music()
            ->with(['collections', 'authors', 'genres'])
            ->visibleTo(Auth::user());

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('title', 'ilike', '%'.$this->search.'%')
                    ->orWhere('subtitle', 'ilike', '%'.$this->search.'%')
                    ->orWhere('custom_id', 'ilike', '%'.$this->search.'%')
                    ->orWhereHas('authors', function ($subQuery) {
                        $subQuery->where('name', 'ilike', '%'.$this->search.'%');
                    });
            });
        }

        return $query->orderBy('title');
    }
}
