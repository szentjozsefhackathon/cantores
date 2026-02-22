<?php

namespace App\Livewire\Pages\Admin;

use App\Enums\MusicTagType;
use App\Models\BulkImport;
use App\Models\Collection;
use App\Models\Music;
use App\Models\MusicTag;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
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

    // Create music dialog
    public bool $showDialog = false;

    public ?int $selectedBatchNumber = null;

    public ?int $selectedCollectionId = null;

    public bool $isImporting = false;

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
     * Open the create music dialog.
     */
    public function openCreateMusicDialog(): void
    {
        $this->authorize('create', Music::class);
        $this->showDialog = true;
        $this->selectedBatchNumber = null;
        $this->selectedCollectionId = null;
    }

    /**
     * Close the dialog.
     */
    public function closeDialog(): void
    {
        $this->showDialog = false;
        $this->selectedBatchNumber = null;
        $this->selectedCollectionId = null;
        $this->isImporting = false;
    }

    /**
     * Import music from selected batch into selected collection.
     */
    public function importMusic(): void
    {
        $this->authorize('create', Music::class);
        $this->validate([
            'selectedBatchNumber' => 'required|integer|exists:bulk_imports,batch_number',
            'selectedCollectionId' => 'required|integer|exists:collections,id',
        ]);

        $this->isImporting = true;

        // Fetch all bulk imports for the selected batch
        $imports = BulkImport::where('batch_number', $this->selectedBatchNumber)->get();

        $createdCount = 0;
        $skippedCount = 0;

        foreach ($imports as $import) {
            // Check if a music already exists in the selected collection with the same order_number (reference)
            $existing = Music::whereHas('collections', function ($query) use ($import) {
                $query->where('collections.id', $this->selectedCollectionId)
                    ->where('music_collection.order_number', $import->reference);
            })->exists();

            if ($existing) {
                \Log::info("Skipping import: {$import->piece} (reference: {$import->reference}) - already exists in collection ID {$this->selectedCollectionId}");
                $skippedCount++;

                continue;
            }

            // Create new music
            \Log::info("Importing music: {$import->piece} (reference: {$import->reference}) into collection ID {$this->selectedCollectionId}");
            $music = Music::create([
                'title' => $import->piece,
                'subtitle' => null,
                'custom_id' => null,
                'user_id' => Auth::id(),
                'is_private' => false,
                'import_batch_number' => $import->batch_number,
            ]);

            // Attach to selected collection with order_number = reference and page_number if available
            $music->collections()->attach($this->selectedCollectionId, [
                'order_number' => $import->reference,
                'page_number' => $import->page_number ?? null,
            ]);

            // Add the collection's genres to the music
            $collection = Collection::visibleTo(Auth::user())->findOrFail($this->selectedCollectionId);
            if ($collection) {
                $genreIds = $collection->genres()->pluck('genre_id');
                $music->genres()->attach($genreIds);
            }

            // Handle tag if present
            if (! empty($import->tag)) {
                $this->attachTagToMusic($music, $import->tag);
            }

            $createdCount++;
        }

        $this->isImporting = false;
        $this->showDialog = false;

        session()->flash('message', __('Imported :created music pieces, skipped :skipped (already exist).', [
            'created' => $createdCount,
            'skipped' => $skippedCount,
        ]));
    }

    /**
     * Attach a tag to music, creating it if necessary.
     */
    private function attachTagToMusic(Music $music, string $tagName): void
    {
        // Transform tag name to Firstlettercase: ALLELUJA->Alleluja
        $transformedName = ucfirst(strtolower($tagName));

        // Find or create tag with Liturgy type (default)
        $tag = MusicTag::firstOrCreate(
            [
                'name' => $transformedName,
                'type' => MusicTagType::Liturgy,
            ],
            [
                'name' => $transformedName,
                'type' => MusicTagType::Liturgy,
            ]
        );

        // Attach tag to music
        $music->tags()->attach($tag->id);
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        // If sortBy is 'order_number', change it to 'reference' for compatibility
        $sortBy = $this->sortBy === 'order_number' ? 'reference' : $this->sortBy;

        // Ensure sortBy is a valid column
        $validColumns = ['collection', 'piece', 'reference', 'page_number', 'tag', 'batch_number', 'created_at'];
        if (! in_array($sortBy, $validColumns)) {
            $sortBy = 'collection';
        }

        $imports = BulkImport::query()
            ->when($this->search, function ($query, $search) {
                $query->where('piece', 'ilike', "%{$search}%");
            })
            ->when($this->collectionFilter, function ($query, $collection) {
                $query->where('collection', $collection);
            })
            ->orderBy($sortBy, $this->sortDirection)
            ->paginate(20);

        $collections = BulkImport::distinct('collection')->pluck('collection');
        $batchNumbers = BulkImport::distinct('batch_number')->orderBy('batch_number')->pluck('batch_number');
        $collectionList = Collection::visibleTo(Auth::user())->orderBy('title')->get();

        return view('livewire.pages.admin.bulk-imports', [
            'imports' => $imports,
            'collections' => $collections,
            'batchNumbers' => $batchNumbers,
            'collectionList' => $collectionList,
            'sortBy' => $sortBy,
            'sortDirection' => $this->sortDirection,
        ]);
    }
}
