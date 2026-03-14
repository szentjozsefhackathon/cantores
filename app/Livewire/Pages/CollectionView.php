<?php

namespace App\Livewire\Pages;

use App\Models\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class CollectionView extends Component
{
    use AuthorizesRequests, WithPagination;

    public Collection $collection;

    public string $search = '';

    #[On('collection-updated')]
    public function refreshCollection(): void
    {
        $this->collection = $this->collection->fresh();
    }

    public function mount($collection): void
    {
        // Load existing collection
        if (! $collection instanceof Collection) {
            $collection = Collection::visibleTo(Auth::user())->findOrFail($collection);
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

    public function delete(): void
    {
        $this->authorize('delete', $this->collection);

        if ($this->collection->music()->count() > 0) {
            $this->dispatch('error', message: __('Cannot delete collection that has music assigned to it.'));

            return;
        }

        $this->collection->delete();

        $this->redirect(route('collections'), navigate: true);
    }

    public function render()
    {
        $musics = $this->getMusicsQuery()->paginate(12);

        $musicCount = $this->collection->music()->count();
        $abbr = $this->collection->abbreviation ? " ({$this->collection->abbreviation})" : '';
        $description = "Gyűjtemény: {$this->collection->title}{$abbr}. {$musicCount} zenemű érhető el ebből a liturgikus gyűjteményből a Cantores.hu Énektárában.";

        return view('pages.collection-view', [
            'musics' => $musics,
        ])->layout('layouts::app.main', [
            'title'       => $this->collection->title,
            'description' => $description,
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
