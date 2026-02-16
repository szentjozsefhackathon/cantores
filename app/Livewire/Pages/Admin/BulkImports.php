<?php

namespace App\Livewire\Pages\Admin;

use App\Models\BulkImport;
use App\Models\MusicPlanSlot;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

class BulkImports extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';

    public string $collectionFilter = '';

    // Sorting
    public string $sortBy = 'collection';

    public string $sortDirection = 'asc';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->authorize('viewAny', BulkImport::class);
    }

    /**
     * Sort the table by the given column.
     */
    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    /**
     * Reset filters.
     */
    public function resetFilters(): void
    {
        $this->reset(['search', 'collectionFilter']);
        $this->resetPage();
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        $imports = BulkImport::query()
            ->when($this->search, function ($query, $search) {
                $query->where('piece', 'ilike', "%{$search}%");
            })
            ->when($this->collectionFilter, function ($query, $collection) {
                $query->where('collection', $collection);
            })
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(20);

        $collections = BulkImport::distinct('collection')->pluck('collection');

        return view('livewire.pages.admin.bulk-imports', [
            'imports' => $imports,
            'collections' => $collections,
            'sortBy' => $this->sortBy,
            'sortDirection' => $this->sortDirection,
        ]);
    }
}
