<?php

namespace App\Livewire\Pages\Admin;

use App\Models\BulkImport;
use App\Models\Music;
use App\Models\MusicImport;
use App\Models\MusicPlanImport;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
     * Automatically merge all suggestions for the selected import without user interaction.
     * For each unique merge suggestion, the lower music ID is kept (left) and the higher is deleted (right).
     * Collections are merged with left's pivot data taking precedence on conflict.
     * Genres, URLs, and related music are unioned.
     */
    public function mergeAllSuggestions(): void
    {
        if (! $this->selectedImportId) {
            return;
        }

        $importId = $this->selectedImportId;

        $baseQuery = fn () => MusicImport::query()
            ->where(function ($q) use ($importId): void {
                $q->whereHas('musicPlanImportItem', fn ($q) => $q->where('music_plan_import_id', $importId))
                    ->orWhereHas('slotImport', fn ($q) => $q->where('music_plan_import_id', $importId));
            });

        $suggestions = $baseQuery()
            ->whereNotNull('merge_suggestion')
            ->whereNotNull('music_id')
            ->select('merge_suggestion')
            ->distinct()
            ->pluck('merge_suggestion');

        $mergedCount = 0;

        foreach ($suggestions as $suggestion) {
            $musicIds = $baseQuery()
                ->whereNotNull('music_id')
                ->where('merge_suggestion', $suggestion)
                ->pluck('music_id')
                ->unique()
                ->sort()
                ->values();

            if ($musicIds->count() < 2) {
                continue;
            }

            $leftMusic = Music::with(['collections', 'genres', 'urls', 'relatedMusic'])->find($musicIds[0]);
            $rightMusic = Music::with(['collections', 'genres', 'urls', 'relatedMusic'])->find($musicIds[1]);

            if (! $leftMusic || ! $rightMusic) {
                continue;
            }

            $this->authorize('update', $leftMusic);
            $this->authorize('update', $rightMusic);
            $this->authorize('delete', $rightMusic);

            // Resolve title and other simple fields (prefer left, fall back to right)
            $mergedTitle = $leftMusic->title ?? $rightMusic->title;
            $mergedSubtitle = $leftMusic->subtitle ?? $rightMusic->subtitle;
            $mergedCustomId = $leftMusic->custom_id ?? $rightMusic->custom_id;
            // When is_private differs, default to public
            $mergedIsPrivate = ($leftMusic->is_private === $rightMusic->is_private)
                ? (bool) $leftMusic->is_private
                : false;

            // Merge collections (union, left pivot wins on conflict)
            $collectionMap = [];
            foreach ($leftMusic->collections as $collection) {
                $collectionMap[$collection->id] = [
                    'id' => $collection->id,
                    'page_number' => $collection->pivot->page_number ?? null,
                    'order_number' => $collection->pivot->order_number ?? null,
                ];
            }
            foreach ($rightMusic->collections as $collection) {
                if (! isset($collectionMap[$collection->id])) {
                    $collectionMap[$collection->id] = [
                        'id' => $collection->id,
                        'page_number' => $collection->pivot->page_number ?? null,
                        'order_number' => $collection->pivot->order_number ?? null,
                    ];
                }
            }

            // Merge genres (union)
            $mergedGenreIds = $leftMusic->genres
                ->merge($rightMusic->genres)
                ->unique('id')
                ->pluck('id')
                ->toArray();

            // Merge URLs (union from both, recreated)
            $mergedUrls = $leftMusic->urls->map(fn ($u) => ['url' => $u->url, 'label' => $u->label])
                ->concat($rightMusic->urls->map(fn ($u) => ['url' => $u->url, 'label' => $u->label]))
                ->unique('url')
                ->values();

            // Merge related music (union)
            $mergedRelatedMusicIds = $leftMusic->relatedMusic
                ->merge($rightMusic->relatedMusic)
                ->unique('id')
                ->reject(fn ($m) => $m->id === $rightMusic->id) // exclude the one being deleted
                ->pluck('id')
                ->toArray();

            DB::transaction(function () use (
                $leftMusic, $rightMusic,
                $mergedTitle, $mergedSubtitle, $mergedCustomId, $mergedIsPrivate,
                $collectionMap, $mergedGenreIds, $mergedUrls, $mergedRelatedMusicIds,
                $importId, $suggestion,
            ): void {
                $leftMusic->update([
                    'title' => $mergedTitle,
                    'subtitle' => $mergedSubtitle,
                    'custom_id' => $mergedCustomId,
                    'is_private' => $mergedIsPrivate,
                    'user_id' => Auth::id(),
                ]);

                $leftMusic->collections()->detach();
                foreach ($collectionMap as $collectionId => $pivotData) {
                    $leftMusic->collections()->attach($collectionId, [
                        'page_number' => $pivotData['page_number'],
                        'order_number' => $pivotData['order_number'],
                    ]);
                }

                $leftMusic->genres()->sync($mergedGenreIds);

                $leftMusic->urls()->delete();
                foreach ($mergedUrls as $urlData) {
                    $leftMusic->urls()->create([
                        'url' => $urlData['url'],
                        'label' => $urlData['label'] ?? null,
                    ]);
                }

                $leftMusic->relatedMusic()->sync($mergedRelatedMusicIds);

                // Migrate music plan slot assignments
                DB::table('music_plan_slot_assignments')
                    ->where('music_id', $rightMusic->id)
                    ->update(['music_id' => $leftMusic->id]);

                // Update MusicImport records pointing to the deleted music
                MusicImport::where('music_id', $rightMusic->id)->update(['music_id' => $leftMusic->id]);

                // Clear merge_suggestion for resolved records
                MusicImport::where('merge_suggestion', $suggestion)
                    ->where(function ($q) use ($importId): void {
                        $q->whereHas('musicPlanImportItem', fn ($q) => $q->where('music_plan_import_id', $importId))
                            ->orWhereHas('slotImport', fn ($q) => $q->where('music_plan_import_id', $importId));
                    })
                    ->update(['merge_suggestion' => null]);

                $rightMusic->delete();
            });

            $mergedCount++;
        }

        $this->dispatch('merge-all-complete', count: $mergedCount);
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
