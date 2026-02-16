<?php

namespace App\Livewire\Editor;

use App\Models\Music;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

return new class extends Component
{
    use AuthorizesRequests;

    // Music selection
    public ?int $leftMusicId = null;

    public ?int $rightMusicId = null;

    public string $leftSearch = '';

    public string $rightSearch = '';

    // Loaded music models
    public ?Music $leftMusic = null;

    public ?Music $rightMusic = null;

    // Comparison state
    public bool $showComparison = false;

    public array $mergedData = [];

    public array $conflicts = [];

    public array $mergedCollections = [];

    public array $mergedGenres = [];

    public array $mergedUrls = [];

    public array $mergedRelatedMusic = [];

    // Editable merged fields
    public string $mergedTitle = '';

    public ?string $mergedSubtitle = null;

    public ?string $mergedCustomId = null;

    public bool $mergedIsPrivate = false;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->authorize('viewAny', Music::class);
    }

    /**
     * Search for music based on search term.
     */
    public function searchMusic(string $search): array
    {
        return Music::visibleTo(Auth::user())
            ->when($search, function ($query, $search) {
                $query->search($search);
            })
            ->forCurrentGenre()
            ->orderBy('title')
            ->limit(10)
            ->get()
            ->map(fn ($music) => [
                'id' => $music->id,
                'title' => $music->title,
                'subtitle' => $music->subtitle,
                'custom_id' => $music->custom_id,
                'collections_count' => $music->collections_count ?? $music->collections()->count(),
            ])
            ->toArray();
    }

    /**
     * Select left music.
     */
    #[On('music-selected.mergeLeftMusic')]
    public function assignToLeftMusic(int $musicId): void
    {
        $music = Music::with(['collections', 'genres', 'urls', 'relatedMusic'])
            ->visibleTo(Auth::user())
            ->findOrFail($musicId);

        $this->authorize('update', $music);

        $this->leftMusicId = $musicId;
        $this->leftMusic = $music;
        $this->checkComparisonReady();
    }

    /**
     * Select right music.
     */
    #[On('music-selected.mergeRightMusic')]
    public function assignToRightMusic(int $musicId): void
    {
        $music = Music::with(['collections', 'genres', 'urls', 'relatedMusic'])
            ->visibleTo(Auth::user())
            ->findOrFail($musicId);

        $this->authorize('update', $music);

        $this->rightMusicId = $musicId;
        $this->rightMusic = $music;
        $this->checkComparisonReady();
    }

    /**
     * Check if both music are selected and ready for comparison.
     */
    private function checkComparisonReady(): void
    {
        if ($this->leftMusicId && $this->rightMusicId) {
            if ($this->leftMusicId === $this->rightMusicId) {
                $this->dispatch('error', message: __('Cannot select the same music piece for both sides.'));
                $this->rightMusicId = null;
                $this->rightMusic = null;

                return;
            }
            $this->compare();
        }
    }

    /**
     * Compare the two selected music pieces.
     */
    public function compare(): void
    {
        if (! $this->leftMusic || ! $this->rightMusic) {
            return;
        }

        $this->authorize('update', $this->leftMusic);
        $this->authorize('update', $this->rightMusic);

        $this->detectConflicts();
        $this->generateMergedData();
        $this->showComparison = true;
    }

    /**
     * Detect conflicts between left and right music.
     */
    private function detectConflicts(): void
    {
        $this->conflicts = [];

        // Direct field conflicts
        $directFields = ['title', 'subtitle', 'custom_id', 'is_private'];
        foreach ($directFields as $field) {
            $leftValue = $this->leftMusic->$field;
            $rightValue = $this->rightMusic->$field;

            if ($this->valuesDiffer($leftValue, $rightValue)) {
                $this->conflicts[$field] = [
                    'left' => $leftValue,
                    'right' => $rightValue,
                    'resolution' => $field === 'is_private' ? 'false' : 'left',
                ];
            }
        }

        // Collection conflicts (same collection, different pivot)
        foreach ($this->leftMusic->collections as $leftCollection) {
            foreach ($this->rightMusic->collections as $rightCollection) {
                if ($leftCollection->id === $rightCollection->id) {
                    if ($leftCollection->pivot->page_number != $rightCollection->pivot->page_number ||
                        $leftCollection->pivot->order_number != $rightCollection->pivot->order_number) {
                        $this->conflicts['collection_'.$leftCollection->id] = [
                            'type' => 'collection',
                            'collection' => $leftCollection,
                            'left_pivot' => $leftCollection->pivot,
                            'right_pivot' => $rightCollection->pivot,
                            'resolution' => 'left',
                        ];
                    }
                }
            }
        }
    }

    /**
     * Generate merged data from left and right music.
     */
    private function generateMergedData(): void
    {
        // Direct fields with conflict resolution
        $this->mergedTitle = $this->resolveField('title', $this->leftMusic->title, $this->rightMusic->title);
        $this->mergedSubtitle = $this->resolveField('subtitle', $this->leftMusic->subtitle, $this->rightMusic->subtitle);
        $this->mergedCustomId = $this->resolveField('custom_id', $this->leftMusic->custom_id, $this->rightMusic->custom_id);
        $this->mergedIsPrivate = $this->resolveField('is_private', $this->leftMusic->is_private, $this->rightMusic->is_private) === 'false' ? false : (bool) $this->leftMusic->is_private;

        // Collections (merge with conflict resolution)
        $this->mergedCollections = $this->mergeCollections();

        // Genres (union)
        $this->mergedGenres = $this->leftMusic->genres
            ->merge($this->rightMusic->genres)
            ->unique('id')
            ->values()
            ->toArray();

        // URLs (union)
        $this->mergedUrls = $this->leftMusic->urls
            ->merge($this->rightMusic->urls)
            ->unique('id')
            ->values()
            ->toArray();

        // Related music (union)
        $this->mergedRelatedMusic = $this->leftMusic->relatedMusic
            ->merge($this->rightMusic->relatedMusic)
            ->unique('id')
            ->values()
            ->toArray();
    }

    /**
     * Resolve a field value based on conflict rules.
     */
    private function resolveField(string $field, $leftValue, $rightValue)
    {
        if (! isset($this->conflicts[$field])) {
            // No conflict: use left if not empty, otherwise right
            return $leftValue ?? $rightValue;
        }

        // Conflict exists
        if ($field === 'is_private') {
            return 'false'; // Always public when conflict
        }

        return $leftValue; // Default to left for other fields
    }

    /**
     * Merge collections from both music pieces.
     */
    private function mergeCollections(): array
    {
        $merged = [];
        $collectionMap = [];

        // Add left collections
        foreach ($this->leftMusic->collections as $collection) {
            $key = $collection->id;
            $collectionMap[$key] = [
                'collection' => $collection,
                'pivot_data' => [
                    'page_number' => $collection->pivot->page_number ?? null,
                    'order_number' => $collection->pivot->order_number ?? null,
                ],
                'source' => 'left',
            ];
        }

        // Add or merge right collections
        foreach ($this->rightMusic->collections as $collection) {
            $key = $collection->id;
            if (isset($collectionMap[$key])) {
                // Same collection - check for conflict
                if ($collection->pivot->page_number != $collectionMap[$key]['pivot_data']['page_number'] ||
                    $collection->pivot->order_number != $collectionMap[$key]['pivot_data']['order_number']) {
                    // Conflict: use left's pivot
                    $collectionMap[$key]['conflict'] = true;
                }
            } else {
                // Different collection
                $collectionMap[$key] = [
                    'collection' => $collection,
                    'pivot_data' => [
                        'page_number' => $collection->pivot->page_number ?? null,
                        'order_number' => $collection->pivot->order_number ?? null,
                    ],
                    'source' => 'right',
                ];
            }
        }

        // Convert to array
        foreach ($collectionMap as $item) {
            $merged[] = $item;
        }

        return $merged;
    }

    /**
     * Check if two values differ (considering null/empty).
     */
    private function valuesDiffer($left, $right): bool
    {
        if ($left === null && $right === null) {
            return false;
        }
        if ($left === null || $right === null) {
            return true; // One has value, other doesn't
        }

        return $left != $right;
    }

    /**
     * Save the merged music.
     */
    public function saveMerge(): void
    {
        $this->authorize('update', $this->leftMusic);
        $this->authorize('update', $this->rightMusic);
        $this->authorize('delete', $this->rightMusic);

        // Validate merged data
        $this->validate([
            'mergedTitle' => ['required', 'string', 'max:255'],
            'mergedSubtitle' => ['nullable', 'string', 'max:255'],
            'mergedCustomId' => ['nullable', 'string', 'max:255'],
            'mergedIsPrivate' => ['boolean'],
        ]);

        \DB::transaction(function () {
            // Update left music with merged data
            $this->leftMusic->update([
                'title' => $this->mergedTitle,
                'subtitle' => $this->mergedSubtitle,
                'custom_id' => $this->mergedCustomId,
                'is_private' => $this->mergedIsPrivate,
                'user_id' => Auth::id(), // Owner becomes current user
            ]);

            // Update collections
            $this->leftMusic->collections()->detach();
            foreach ($this->mergedCollections as $item) {
                $pivotData = $item['pivot_data'] ?? [
                    'page_number' => $item['pivot']['page_number'] ?? $item['pivot']->page_number ?? null,
                    'order_number' => $item['pivot']['order_number'] ?? $item['pivot']->order_number ?? null,
                ];
                $this->leftMusic->collections()->attach($item['collection']['id'] ?? $item['collection']->id, $pivotData);
            }

            // Update genres
            $this->leftMusic->genres()->sync(
                collect($this->mergedGenres)->pluck('id')->toArray()
            );

            // Update URLs (create copies for left music)
            $this->leftMusic->urls()->delete();
            foreach ($this->mergedUrls as $url) {
                $this->leftMusic->urls()->create([
                    'url' => $url['url'],
                    'label' => $url['label'] ?? null,
                ]);
            }

            // Update related music
            $this->leftMusic->relatedMusic()->sync(
                collect($this->mergedRelatedMusic)->pluck('id')->toArray()
            );

            // Update music plan slot assignments from right to left
            \DB::table('music_plan_slot_assignments')
                ->where('music_id', $this->rightMusic->id)
                ->update(['music_id' => $this->leftMusic->id]);

            // Delete right music
            $this->rightMusic->delete();
        });

        // Dispatch event for audit logging
        $this->dispatch('music-merged', message: __('Music pieces merged successfully.'));

        // Redirect to left music editor
        $this->redirectRoute('music-editor', ['music' => $this->leftMusic->id]);
    }

    /**
     * Reset the component state.
     */
    public function resetSelection(): void
    {
        $this->leftMusicId = null;
        $this->rightMusicId = null;
        $this->leftMusic = null;
        $this->rightMusic = null;
        $this->showComparison = false;
        $this->conflicts = [];
        $this->mergedData = [];
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        $leftResults = $this->leftSearch ? $this->searchMusic($this->leftSearch) : [];
        $rightResults = $this->rightSearch ? $this->searchMusic($this->rightSearch) : [];

        return view('components.editor.⚡music-merger.music-merger', [
            'leftResults' => $leftResults,
            'rightResults' => $rightResults,
        ]);
    }
};
