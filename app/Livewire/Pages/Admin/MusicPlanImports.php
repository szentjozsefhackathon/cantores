<?php

namespace App\Livewire\Pages\Admin;

use App\Models\BulkImport;
use App\Models\MusicImport;
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

    // Music import filter: 'all' | 'unmatched' | 'suggestions'
    public string $musicImportFilter = 'all';

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
        if ($this->selectedImportId !== $importId) {
            $this->resetPage('musicPage');
            $this->musicImportFilter = 'all';
        }

        $this->selectedImportId = $importId;
    }

    /**
     * Deselect the current import.
     */
    public function deselectImport(): void
    {
        $this->selectedImportId = null;
        $this->musicImportFilter = 'all';
    }

    /**
     * Set the music import filter.
     */
    public function setMusicImportFilter(string $filter): void
    {
        $this->musicImportFilter = $filter;
        $this->resetPage('musicPage');
    }

    /**
     * Navigate to the music merger page for the given merge suggestion.
     * Looks up the two distinct Music IDs referenced by the slash-separated suggestion
     * within the currently selected import.
     */
    public function navigateToMerge(int $musicImportId): void
    {
        if (! $this->selectedImportId) {
            return;
        }

        $musicImport = MusicImport::find($musicImportId);

        if (! $musicImport?->merge_suggestion) {
            return;
        }

        $musicIds = MusicImport::whereNotNull('music_id')
            ->where('merge_suggestion', $musicImport->merge_suggestion)
            ->where(function ($q): void {
                $q->whereHas('musicPlanImportItem', fn ($q) => $q->where('music_plan_import_id', $this->selectedImportId))
                    ->orWhereHas('slotImport', fn ($q) => $q->where('music_plan_import_id', $this->selectedImportId));
            })
            ->pluck('music_id')
            ->unique()
            ->values();

        if ($musicIds->count() >= 2) {
            $this->redirectRoute('music-merger', ['left' => $musicIds[0], 'right' => $musicIds[1]]);
        }
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
            ->withCount(['importItems', 'slotImports'])
            ->with(['importItems' => fn ($q) => $q->withCount('musicImports')])
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(20);

        $sourceFiles = MusicPlanImport::distinct('source_file')
            ->orderBy('source_file')
            ->pluck('source_file');

        $selectedImport = null;
        $importItems = collect();
        $slotImports = collect();
        $musicImports = collect();
        $unmatchedCount = 0;
        $suggestionCount = 0;

        if ($this->selectedImportId) {
            $selectedImport = MusicPlanImport::find($this->selectedImportId);

            if ($selectedImport) {
                $importItems = $selectedImport->importItems()->withCount('musicImports')->get();
                $slotImports = $selectedImport->slotImports()->withCount('musicImports')->get();

                $baseQuery = fn () => MusicImport::query()
                    ->where(function ($q) use ($selectedImport): void {
                        $q->whereHas('musicPlanImportItem', fn ($q) => $q->where('music_plan_import_id', $selectedImport->id))
                            ->orWhereHas('slotImport', fn ($q) => $q->where('music_plan_import_id', $selectedImport->id));
                    });

                $unmatchedCount = $baseQuery()->whereNull('music_id')->count();
                $suggestionCount = $baseQuery()->whereNotNull('merge_suggestion')->count();

                $musicImportQuery = $baseQuery()->with(['slotImport', 'music', 'musicPlanImportItem']);

                if ($this->musicImportFilter === 'unmatched') {
                    $musicImportQuery->whereNull('music_id');
                } elseif ($this->musicImportFilter === 'suggestions') {
                    $musicImportQuery->whereNotNull('merge_suggestion');
                }

                $musicImports = $musicImportQuery->paginate(20, ['*'], 'musicPage');
            }
        }

        return view('pages.admin.musicplan-imports', [
            'imports' => $imports,
            'sourceFiles' => $sourceFiles,
            'sortBy' => $this->sortBy,
            'sortDirection' => $this->sortDirection,
            'selectedImport' => $selectedImport,
            'importItems' => $importItems,
            'slotImports' => $slotImports,
            'musicImports' => $musicImports,
            'unmatchedCount' => $unmatchedCount,
            'suggestionCount' => $suggestionCount,
        ]);
    }
}
