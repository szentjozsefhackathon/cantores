<?php

namespace App\Livewire\Pages\Admin;

use App\Enums\MusicTagType;
use App\Models\BulkImport;
use App\Models\Collection;
use App\Models\Music;
use App\Models\MusicRelation;
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
     * Reset pagination when search changes.
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Reset pagination when collection filter changes.
     */
    public function updatedCollectionFilter(): void
    {
        $this->resetPage();
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
        $tagAddedCount = 0;
        $relationAddedCount = 0;

        foreach ($imports as $import) {
            // Check if a music already exists in the selected collection with the same order_number (reference)
            $existingMusic = Music::whereHas('collections', function ($query) use ($import) {
                $query->where('collections.id', $this->selectedCollectionId)
                    ->where('music_collection.order_number', $import->reference);
            })->first();

            if ($existingMusic) {
                // If tag is present and different from existing tags, add it
                if (! empty($import->tag)) {
                    $tagAdded = $this->attachTagToMusic($existingMusic, $import->tag);
                    if ($tagAdded) {
                        $tagAddedCount++;
                        \Log::info("Added tag '{$import->tag}' to existing music: {$import->piece} (reference: {$import->reference}) in collection ID {$this->selectedCollectionId}");
                    }
                }

                // Handle related field if present (even for existing music)
                if (! empty($import->related)) {
                    $relationAdded = $this->handleRelatedField($existingMusic, $import->related);
                    if ($relationAdded) {
                        $relationAddedCount++;
                        \Log::info("Added relation for existing music: {$import->piece} (reference: {$import->reference}) in collection ID {$this->selectedCollectionId}");
                    }
                }

                \Log::info("Skipping import: {$import->piece} (reference: {$import->reference}) - already exists in collection ID {$this->selectedCollectionId}");
                $skippedCount++;

                continue;
            }

            // Create new music
            \Log::info("Importing music: {$import->piece} (reference: {$import->reference}) into collection ID {$this->selectedCollectionId}");
            $music = Music::create([
                'title' => $import->piece,
                'subtitle' => $import->subtitle ?? null,
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

            // Handle related field if present
            if (! empty($import->related)) {
                $relationAdded = $this->handleRelatedField($music, $import->related);
                if ($relationAdded) {
                    $relationAddedCount++;
                }
            }

            $createdCount++;
        }

        $this->isImporting = false;
        $this->showDialog = false;

        $message = __('Imported :created music pieces, skipped :skipped (already exist).', [
            'created' => $createdCount,
            'skipped' => $skippedCount,
        ]);

        if ($tagAddedCount > 0) {
            $message .= ' '.__('Added :tag_added tags to existing music.', ['tag_added' => $tagAddedCount]);
        }

        if ($relationAddedCount > 0) {
            $message .= ' '.__('Added :relation_added relations.', ['relation_added' => $relationAddedCount]);
        }

        session()->flash('message', $message);
    }

    /**
     * Attach a tag to music, creating it if necessary.
     * Returns true if the tag was attached (i.e., it wasn't already attached), false otherwise.
     */
    private function attachTagToMusic(Music $music, string $tagName): bool
    {
        // Transform tag name to Firstlettercase: ALLELUJA->Alleluja
        $transformedName = mb_convert_case($tagName, MB_CASE_TITLE);

        // Find existing tag by name (any type) or create with Liturgy type
        $tag = MusicTag::firstOrCreate(
            ['name' => $transformedName],
            [
                'name' => $transformedName,
                'type' => MusicTagType::Liturgy,
            ]
        );

        // Check if the music already has this tag
        if ($music->tags()->where('music_tag_id', $tag->id)->exists()) {
            return false;
        }

        // Attach tag to music
        $music->tags()->attach($tag->id);

        return true;
    }

    /**
     * Handle the "related" field by looking up a music in a collection by abbreviation and order number.
     * Returns true if a relation was created, false otherwise.
     */
    private function handleRelatedField(Music $music, string $related): bool
    {
        // Parse the related field: format "ÉE 553" -> abbreviation "ÉE", orderNumber "553"
        $parts = explode(' ', trim($related), 2);
        if (count($parts) !== 2) {
            \Log::info("Could not parse related field '{$related}' for music ID {$music->id}");

            return false;
        }

        $abbreviation = trim($parts[0]);
        $orderNumber = trim($parts[1]);

        // Find collection with matching abbreviation
        $collection = Collection::where('abbreviation', $abbreviation)->first();
        if (! $collection) {
            \Log::info("No collection found with abbreviation '{$abbreviation}' for related field '{$related}'");

            return false;
        }

        // Find music in that collection with matching order_number
        $relatedMusic = Music::whereHas('collections', function ($query) use ($collection, $orderNumber) {
            $query->where('collections.id', $collection->id)
                ->where('music_collection.order_number', $orderNumber);
        })->first();

        if (! $relatedMusic) {
            \Log::info("No music found in collection '{$abbreviation}' with order number '{$orderNumber}' for related field '{$related}'");

            return false;
        }

        // Check if relation already exists (either direction)
        $existingRelation = MusicRelation::between($music->id, $relatedMusic->id)->first();
        if ($existingRelation) {
            \Log::info("Relation already exists between music ID {$music->id} and {$relatedMusic->id} for related field '{$related}' - removing it");
            $existingRelation->delete();
        }

        // Create relation with type "Duplicate"
        MusicRelation::create([
            'music_id' => $music->id,
            'related_music_id' => $relatedMusic->id,
            'relationship_type' => \App\MusicRelationshipType::Duplicate->value,
            'user_id' => Auth::id(),
        ]);

        \Log::info("Created relation between music ID {$music->id} and {$relatedMusic->id} for related field '{$related}'");

        return true;
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

        return view('pages.admin.bulk-imports', [
            'imports' => $imports,
            'collections' => $collections,
            'batchNumbers' => $batchNumbers,
            'collectionList' => $collectionList,
            'sortBy' => $sortBy,
            'sortDirection' => $this->sortDirection,
        ]);
    }
}
