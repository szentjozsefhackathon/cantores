<?php

namespace App\Livewire\Pages\Admin;

use App\Models\BulkImport;
use App\Models\MusicPlanImport;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

class MusicPlanImports extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';

    public string $sourceFileFilter = '';

    // Sorting
    public string $sortBy = 'created_at';

    public string $sortDirection = 'desc';

    // Selection
    public ?int $selectedImportId = null;

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
        $this->reset(['search', 'sourceFileFilter']);
        $this->resetPage();
    }

    /**
     * Select an import to view details.
     */
    public function selectImport(int $importId): void
    {
        $this->selectedImportId = $importId;
    }

    /**
     * Deselect the current import.
     */
    public function deselectImport(): void
    {
        $this->selectedImportId = null;
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        $imports = MusicPlanImport::query()
            ->when($this->search, function ($query, $search) {
                $query->whereHas('importItems', function ($q) use ($search) {
                    $q->where('celebration_info', 'ilike', "%{$search}%");
                });
            })
            ->when($this->sourceFileFilter, function ($query, $sourceFile) {
                $query->where('source_file', $sourceFile);
            })
            ->with(['importItems', 'slotImports'])
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(20);

        $sourceFiles = MusicPlanImport::distinct('source_file')
            ->orderBy('source_file')
            ->pluck('source_file');

        $selectedImport = null;
        $importItems = collect();
        $slotImports = collect();
        $musicImports = collect();

        if ($this->selectedImportId) {
            $selectedImport = MusicPlanImport::with([
                'importItems.musicImports',
                'slotImports.musicImports',
            ])->find($this->selectedImportId);

            if ($selectedImport) {
                $importItems = $selectedImport->importItems;
                $slotImports = $selectedImport->slotImports;
                $musicImports = $selectedImport->importItems
                    ->flatMap(fn ($item) => $item->musicImports)
                    ->merge(
                        $selectedImport->slotImports
                            ->flatMap(fn ($slot) => $slot->musicImports)
                    )
                    ->unique('id');
            }
        }

        return view('livewire.pages.admin.musicplan-imports', [
            'imports' => $imports,
            'sourceFiles' => $sourceFiles,
            'sortBy' => $this->sortBy,
            'sortDirection' => $this->sortDirection,
            'selectedImport' => $selectedImport,
            'importItems' => $importItems,
            'slotImports' => $slotImports,
            'musicImports' => $musicImports,
        ]);
    }
}
