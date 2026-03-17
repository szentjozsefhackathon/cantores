<?php

use App\Models\Author;
use App\Models\Collection;
use App\Models\Music;
use App\Models\Genre;
use App\Models\WhitelistRule;
use App\MusicRelationshipType;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    use AuthorizesRequests;

    public Music $music;

    // Form fields
    public string $title = '';

    public ?string $subtitle = null;

    public ?string $customId = null;

    public bool $isPrivate = false;

    // Collection assignment
    public ?int $selectedCollectionId = null;

    public ?int $pageNumber = null;

    public ?string $orderNumber = null;

    // Author assignment
    public ?int $selectedAuthorId = null;

    // Genre assignment
    public array $selectedGenres = [];

    // URL management
    public ?string $newUrlLabel = null;
    public ?string $newUrl = null;
    public ?int $editingUrlId = null;
    public ?string $editingUrlLabel = null;
    public ?string $editingUrl = null;

    // Related music management
    public ?int $selectedRelatedMusicId = null;
    public ?string $selectedRelationshipType = null;

    // Related music modal
    public bool $showRelatedMusicSearchModal = false;

    // Audit log
    public bool $showAuditModal = false;

    public $audits = [];

    // Edit collection modal
    public bool $showEditModal = false;
    public ?int $editingCollectionId = null;
    public ?int $editingPageNumber = null;
    public ?string $editingOrderNumber = null;

    public \Illuminate\Support\Collection $whitelistRules;

    // Auto-save state
    public bool $isSaving = false;
    public bool $isSaved = false;

    // Author multi-searchable

    // Options list
    public \Illuminate\Support\Collection $authorsSearchable;

    public function searchAuthor(string $value = '')
    {
        // Besides the search results, you must include on demand selected option
        $selectedOption = Author::where('id', $this->selectedAuthorId)->get();

        // don't include already selected authors in the search results
        $query = Author::visibleTo(Auth::user())
            ->where('name', 'ilike', "%$value%")
            ->whereNotIn('id', $this->music->authors->pluck('id'));

        $authors = $query
            ->take(20)
            ->get()
            ->map(function ($author) {
                $author->avatar = $author->avatarThumbUrl();
                return $author;
            })
            ->sortBy('name')
            ->values();

        $selectedOption = $selectedOption->map(function ($author) {
            $author->avatar = $author->avatarThumbUrl();
            return $author;
        });

        $this->authorsSearchable = $authors->merge($selectedOption);
    }

    /**
     * Handle music selection from the music-search component.
     */
    #[On('music-selected.relatedMusic')]
    public function selectRelatedMusic(int $musicId): void
    {
        $this->selectedRelatedMusicId = $musicId;
        $this->addRelatedMusic();
    }

    public function openRelatedMusicSearchModal(): void
    {
        $this->showRelatedMusicSearchModal = true;
    }

    public function closeRelatedMusicSearchModal(): void
    {
        $this->showRelatedMusicSearchModal = false;
        $this->selectedRelatedMusicId = null;
        $this->selectedRelationshipType = null;
    }

    /**
     * Add a related music piece.
     */
    public function addRelatedMusic(): void
    {
        $this->authorize('attachRelation', [$this->music, 'related_music']);

        $validated = $this->validate([
            'selectedRelatedMusicId' => ['required', 'integer', 'exists:musics,id'],
            'selectedRelationshipType' => ['required', 'string', Rule::in(array_column(MusicRelationshipType::cases(), 'value'))],
        ]);

        // Check if same music
        if ($this->music->id === $validated['selectedRelatedMusicId']) {
            $this->dispatch('error', __('Cannot relate a music piece to itself.'));
            return;
        }

        // Check if relation already exists (in either direction)
        $existing = \App\Models\MusicRelation::between($this->music->id, $validated['selectedRelatedMusicId'])->exists();
        if ($existing) {
            $this->dispatch('error', __('This music piece is already related.'));
            return;
        }

        \App\Models\MusicRelation::create([
            'music_id' => $this->music->id,
            'related_music_id' => $validated['selectedRelatedMusicId'],
            'relationship_type' => $validated['selectedRelationshipType'],
            'user_id' => Auth::id(),
        ]);

        // Refresh the relationships
        $this->music->load(['directMusicRelations.relatedMusic', 'inverseMusicRelations.music']);

        // Reset form fields
        $this->selectedRelatedMusicId = null;
        $this->selectedRelationshipType = null;

        // Close the modal
        $this->showRelatedMusicSearchModal = false;

        $this->dispatch('related-music-added');
    }

    /**
     * Remove a related music piece.
     *
     * @param int $relationId The ID of the MusicRelation record
     */
    public function removeRelatedMusic(int $relationId): void
    {
        $relation = \App\Models\MusicRelation::findOrFail($relationId);

        // Get the related music ID for authorization
        $relatedMusicId = $relation->music_id === $this->music->id ? $relation->related_music_id : $relation->music_id;
        $relationOwnerUserId = $relation->user_id;

        $this->authorize('editOrDeleteVerifiedRelation', [$this->music, 'related_music', $relatedMusicId, $relationOwnerUserId]);

        $relation->delete();

        // Refresh the relationships
        $this->music->load(['directMusicRelations.relatedMusic', 'inverseMusicRelations.music']);

        $this->dispatch('related-music-removed');
    }


    /**
     * Mount the component.
     */
    public function mount(Music $music): void
    {
        $this->authorize('view', $music);
        $this->music = $music->load(['collections', 'genres', 'authors', 'urls', 'directMusicRelations.relatedMusic', 'inverseMusicRelations.music', 'tags']);
        $this->title = $music->title;
        $this->subtitle = $music->subtitle;
        $this->customId = $music->custom_id;
        $this->isPrivate = $music->is_private;
        $this->selectedGenres = $music->genres->pluck('id')->toArray();
        $this->searchAuthor();
        $this->whitelistRules = WhitelistRule::active()->get();
    }

    /**
     * Get all genres for selection.
     */
    public function genres(): \Illuminate\Support\Collection
    {
        return Genre::allCached();
    }

    /**
     * Get all authors for selection.
     */
    public function authors(): \Illuminate\Database\Eloquent\Collection
    {
        return Author::visibleTo(Auth::user())
            ->orderBy('name')
            ->get();
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        $collections = Collection::visibleTo(Auth::user())
            ->orderBy('title')
            ->limit(256)
            ->get();
        $authors = Author::visibleTo(Auth::user())
            ->orderBy('name')
            ->limit(1024)
            ->get();

        return view('pages.editor.music-editor', [
            'music' => $this->music,
            'collections' => $collections,
            'authors' => $authors,
        ]);
    }

    /**
     * Update the music piece.
     */
    public function update(): void
    {
        $this->authorize('update', $this->music);

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'customId' => ['nullable', 'string', 'max:255'],
            'isPrivate' => ['boolean'],
            'selectedGenres' => ['nullable', 'array'],
            'selectedGenres.*' => ['integer', Rule::exists('genres', 'id')],
        ]);

        if (isset($validated['title']) && $validated['title'] !== $this->music->title) {
            $this->authorize('updateField', [$this->music, 'title']);
        }

        if (isset($validated['subtitle']) && $validated['subtitle'] !== $this->music->subtitle) {
            $this->authorize('updateField', [$this->music, 'subtitle']);
        }

        if (isset($validated['customId']) && $validated['customId'] !== $this->music->custom_id) {
            $this->authorize('updateField', [$this->music, 'custom_id']);
        }

        // Check if changing from public to private
        if ($validated['isPrivate'] && !$this->music->is_private) {
            $this->authorize('changePublishedToPrivate', $this->music);
        }

        $this->music->update([
            'title' => $validated['title'],
            'subtitle' => $validated['subtitle'],
            'custom_id' => $validated['customId'],
            'is_private' => $validated['isPrivate'] ?? false,
        ]);

        // Sync selected genres (empty array will detach all)
        $this->music->genres()->sync($validated['selectedGenres'] ?? []);

        $this->dispatch('music-updated');
    }

    /**
     * Auto-save the music piece (called on field changes).
     */
    #[\Livewire\Attributes\Locked]
    public function autoSave(): void
    {
        $this->isSaving = true;
        $this->isSaved = false;

        try {
            $this->update();
            $this->isSaved = true;
            $this->isSaving = false;
        } catch (\Exception) {
            // Silently fail - validation errors will be shown in the form
            $this->isSaving = false;
        }
    }

    public function clearSavedIndicator(): void
    {
        $this->isSaved = false;
        $this->isSaving = false;
    }

    /**
     * Add a collection to the music piece.
     */
    public function addCollection(): void
    {
        $this->authorize('attachRelation', [$this->music, 'collection']);

        $validated = $this->validate([
            'selectedCollectionId' => ['required', 'integer', 'exists:collections,id'],
            'pageNumber' => ['nullable', 'integer', 'min:1'],
            'orderNumber' => ['nullable', 'string', 'max:255'],
        ]);

        // Check if already attached
        if ($this->music->collections()->where('collection_id', $validated['selectedCollectionId'])->exists()) {
            $this->dispatch('error', __('This collection is already attached to this music piece.'));

            return;
        }

        $this->music->collections()->attach($validated['selectedCollectionId'], [
            'user_id' => Auth::id(),
            'page_number' => $validated['pageNumber'],
            'order_number' => $validated['orderNumber'],
        ]);

        // Refresh the collections relationship
        $this->music->load('collections');

        // Reset the form fields
        $this->selectedCollectionId = null;
        $this->pageNumber = null;
        $this->orderNumber = null;

        $this->dispatch('collection-added');
    }

    /**
     * Remove a collection from the music piece.
     */
    public function removeCollection(int $collectionId): void
    {
        $collection = $this->music->collections()->where('collection_id', $collectionId)->first();
        $relationOwnerUserId = $collection?->pivot?->user_id;

        $this->authorize('editOrDeleteVerifiedRelation', [$this->music, 'collection', $collectionId, $relationOwnerUserId]);

        $this->music->collections()->detach($collectionId);
        $this->music->load('collections');

        $this->dispatch('collection-removed');
    }

    /**
     * Add an author to the music piece.
     */
    public function addAuthor(): void
    {
        $this->authorize('attachRelation', [$this->music, 'author']);

        $validated = $this->validate([
            'selectedAuthorId' => ['required', 'integer', 'exists:authors,id'],
        ]);

        // Check if already attached
        if ($this->music->authors()->where('author_id', $validated['selectedAuthorId'])->exists()) {
            $this->dispatch('error', __('This author is already attached to this music piece.'));

            return;
        }

        $this->music->authors()->attach($validated['selectedAuthorId'], [
            'user_id' => Auth::id(),
        ]);

        // Refresh the authors relationship
        $this->music->load('authors');

        // Reset the form field
        $this->selectedAuthorId = null;

        $this->dispatch('author-added');
    }

    /**
     * Remove an author from the music piece.
     */
    public function removeAuthor(int $authorId): void
    {
        $author = $this->music->authors()->where('author_id', $authorId)->first();
        $relationOwnerUserId = $author?->pivot?->user_id;

        $this->authorize('editOrDeleteVerifiedRelation', [$this->music, 'author', $authorId, $relationOwnerUserId]);

        $this->music->authors()->detach($authorId);
        $this->music->load('authors');

        $this->dispatch('author-removed');
    }

    /**
     * Add a URL to the music piece.
     */
    public function addUrl(): void
    {
        $this->authorize('attachRelation', [$this->music, 'url']);

        $validated = $this->validate([
            'newUrlLabel' => ['required', 'string', Rule::in(array_column(\App\MusicUrlLabel::cases(), 'value'))],
            'newUrl' => ['required', 'string', 'url', new \App\Rules\WhitelistedUrl()],
        ]);

        // Create the URL
        $this->music->urls()->create([
            'user_id' => Auth::id(),
            'label' => $validated['newUrlLabel'],
            'url' => $validated['newUrl'],
        ]);

        // Refresh the URLs relationship
        $this->music->load('urls');

        // Reset the form fields
        $this->newUrlLabel = null;
        $this->newUrl = null;

        $this->dispatch('url-added');
    }

    /**
     * Edit a URL.
     */
    public function editUrl(int $urlId): void
    {
        $url = $this->music->urls()->find($urlId);

        if (!$url) {
            return;
        }

        $this->authorize('editOrDeleteVerifiedRelation', [$this->music, 'url', $urlId, $url->user_id]);

        $this->editingUrlId = $urlId;
        $this->editingUrlLabel = $url->label;
        $this->editingUrl = $url->url;
    }

    /**
     * Update the editing URL.
     */
    public function updateUrl(): void
    {
        $url = $this->music->urls()->find($this->editingUrlId);

        if (!$url) {
            $this->cancelEditUrl();
            return;
        }

        $this->authorize('editOrDeleteVerifiedRelation', [$this->music, 'url', $this->editingUrlId, $url->user_id]);

        $validated = $this->validate([
            'editingUrlLabel' => ['required', 'string', Rule::in(array_column(\App\MusicUrlLabel::cases(), 'value'))],
            'editingUrl' => ['required', 'string', 'url', new \App\Rules\WhitelistedUrl()],
        ]);

        $url->update([
            'label' => $validated['editingUrlLabel'],
            'url' => $validated['editingUrl'],
        ]);

        // Refresh the URLs relationship
        $this->music->load('urls');

        // Reset the editing state
        $this->cancelEditUrl();

        $this->dispatch('url-updated');
    }

    /**
     * Delete a URL from the music piece.
     */
    public function deleteUrl(int $urlId): void
    {
        $url = $this->music->urls()->find($urlId);
        $this->authorize('editOrDeleteVerifiedRelation', [$this->music, 'url', $urlId, $url?->user_id]);

        $this->music->urls()->where('id', $urlId)->delete();
        $this->music->load('urls');

        $this->dispatch('url-deleted');
    }

    /**
     * Cancel editing a URL.
     */
    public function cancelEditUrl(): void
    {
        $this->editingUrlId = null;
        $this->editingUrlLabel = null;
        $this->editingUrl = null;
    }

    /**
     * Edit a collection's pivot data.
     */
    public function editCollection(int $collectionId): void
    {
        $collection = $this->music->collections()->where('collection_id', $collectionId)->first();
        $relationOwnerUserId = $collection?->pivot?->user_id;

        $this->authorize('editOrDeleteVerifiedRelation', [$this->music, 'collection', $collectionId, $relationOwnerUserId]);

        if (!$collection) {
            return;
        }

        $this->editingCollectionId = $collectionId;
        $this->editingPageNumber = $collection->pivot->page_number;
        $this->editingOrderNumber = $collection->pivot->order_number;
        $this->showEditModal = true;
    }

    /**
     * Update the collection's pivot data.
     */
    public function updateCollection(): void
    {
        $collection = $this->music->collections()->where('collection_id', $this->editingCollectionId)->first();
        $relationOwnerUserId = $collection?->pivot?->user_id;

        $this->authorize('editOrDeleteVerifiedRelation', [$this->music, 'collection', $this->editingCollectionId, $relationOwnerUserId]);

        $validated = $this->validate([
            'editingPageNumber' => ['nullable', 'integer', 'min:1'],
            'editingOrderNumber' => ['nullable', 'string', 'max:255'],
        ]);

        $this->music->collections()->updateExistingPivot($this->editingCollectionId, [
            'page_number' => $validated['editingPageNumber'],
            'order_number' => $validated['editingOrderNumber'],
        ]);

        $this->music->load('collections');

        $this->showEditModal = false;
        $this->editingCollectionId = null;
        $this->editingPageNumber = null;
        $this->editingOrderNumber = null;

        $this->dispatch('collection-updated');
    }

    /**
     * Show the audit log modal.
     */
    public function showAuditLog(): void
    {
        $this->authorize('view', $this->music);
        $this->audits = $this->music->audits()
            ->with(['user.city', 'user.firstName'])
            ->latest()
            ->get();
        $this->showAuditModal = true;
    }

    /**
     * Delete the music piece.
     */
    public function delete(): void
    {
        $this->authorize('delete', $this->music);

        // Check if music has any collections or plan slots assigned
        if ($this->music->collections()->count() > 0 || $this->music->musicPlanSlotAssignments()->count() > 0) {
            $this->dispatch('error', __('Cannot delete music piece that has collections or plan slots assigned to it.'));

            return;
        }

        $this->music->delete();

        $this->redirectRoute('musics', navigate: true);
    }
};
?>

<div class="py-4 md:py-8" x-data="{ showPreview: false }">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Music Card Preview -->
        <div class="mb-4" :class="{ 'hidden': !showPreview && window.innerWidth < 768, 'md:block': true }" x-transition>
            <livewire:music-card :music="$music" />
        </div>

        <flux:card class="p-4 md:p-5">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 mb-4">
                <div class="flex items-center gap-2">
                    <flux:heading size="lg" class="md:size-xl">{{ __('Edit Music Piece') }}</flux:heading>
                    <flux:button
                        variant="ghost"
                        size="sm"
                        icon="music-card-icon"
                        @click="showPreview = !showPreview"
                        class="md:hidden"
                        :title="__('Preview')" />
                    <div class="flex items-center gap-1" wire:key="save-indicator">
                        <div wire:loading wire:target="autoSave" class="inline">
                            <svg class="w-4 h-4 animate-spin text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                        @if($isSaved)
                        <flux:icon name="check-circle" class="w-4 h-4 text-green-500" />
                        @endif
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <flux:button
                        variant="ghost"
                        icon="history"
                        wire:click="showAuditLog"
                        :title="__('View Audit Log')" />

                    <flux:button
                        variant="ghost"
                        icon="flag"
                        wire:click="dispatch('openErrorReportModal', {'resourceId': {{ $music->id }}, 'resourceType' : 'music'})"
                        :title="__('Report Error')" />

                    @if(auth()->check() && auth()->user()->isEditor)
                    <flux:button
                        variant="ghost"
                        icon="check-circle"
                        :href="route('music-verifier', ['musicId' => $music->id])"
                        tag="a"
                        :title="__('Verify Music')" />
                    @endif

                    <flux:button
                        variant="ghost"
                        icon="trash"
                        wire:click="delete"
                        wire:confirm="{{ __('Are you sure you want to delete this music piece? This can only be done if no collections or plan slots are assigned to it.') }}"
                        :title="__('Delete')" />
                </div>
            </div>

            <!-- Edit Form -->
            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <flux:field required>
                        <flux:label class="flex items-center gap-2">
                            {{ __('Title') }}
                            <livewire:verification-icon :fieldName="'title'" :music="$music" />
                        </flux:label>
                        <flux:input
                            wire:model="title"
                            wire:input.debounce.1500ms="autoSave"
                            :placeholder="__('Enter music piece title')"
                            :readonly="!Auth::user()?->can('updateField', [$music, 'title'])" />
                        <flux:error name="title" />
                    </flux:field>

                    <flux:field>
                        <flux:label class="flex items-center gap-2">
                            {{ __('Subtitle') }}
                            <livewire:verification-icon :fieldName="'subtitle'" :music="$music" />
                        </flux:label>
                        <flux:input
                            wire:model="subtitle"
                            wire:input.debounce.1500ms="autoSave"
                            :placeholder="__('Enter subtitle')"
                            :readonly="!Auth::user()?->can('updateField', [$music, 'subtitle'])" />
                        <flux:error name="subtitle" />
                    </flux:field>

                    <flux:field>
                        <flux:label class="flex items-center gap-2">
                            {{ __('Custom ID') }}
                            <livewire:verification-icon :fieldName="'custom_id'" :music="$music" />
                        </flux:label>
                        <flux:input
                            wire:model="customId"
                            wire:input.debounce.1500ms="autoSave"
                            :placeholder="__('Enter custom ID')"
                            :readonly="!Auth::user()?->can('updateField', [$music, 'custom_id'])" />
                        <flux:error name="customId" />
                    </flux:field>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Genre Selection -->
                    <div>
                        <flux:field>
                            <flux:label class="flex items-center gap-2">{{ __('Genres') }}
                                <livewire:verification-icon fieldName="genre" :music="$music" />
                            </flux:label>
                            <flux:description class="text-xs md:text-sm">{{ __('Select one or more genres that apply to this music piece.') }}</flux:description>
                            <div class="space-y-2">
                                <!-- Small screens: icon buttons only -->
                                <div class="md:hidden">
                                    <flux:checkbox.group variant="buttons">
                                        @foreach($this->genres() as $genre)
                                        <flux:checkbox
                                            wire:model="selectedGenres"
                                            wire:change="autoSave"
                                            value="{{ $genre->id }}"
                                            label=""
                                            :icon="$genre->icon()"
                                            :title="$genre->label()"
                                            :disabled="!Auth::user()?->can('update', $music)" />
                                        @endforeach
                                    </flux:checkbox.group>
                                </div>
                                <!-- Medium screens and up: icon + label buttons -->
                                <div class="hidden md:block">
                                    <flux:checkbox.group variant="buttons">
                                        @foreach($this->genres() as $genre)
                                        <flux:checkbox
                                            wire:model="selectedGenres"
                                            wire:change="autoSave"
                                            value="{{ $genre->id }}"
                                            :label="$genre->label()"
                                            :icon="$genre->icon()"
                                            :disabled="!Auth::user()?->can('update', $music)" />
                                        @endforeach
                                    </flux:checkbox.group>
                                </div>
                                <flux:error name="selectedGenres" />
                            </div>
                        </flux:field>
                    </div>
                    <!-- Privacy Toggle -->
                    <div>
                        <flux:field>
                            <flux:label>{{ __('Privacy') }}</flux:label>
                            <flux:description class="text-xs md:text-sm">{{ __('Private music pieces are only visible to you. Public music pieces are visible to all users.') }}</flux:description>
                            <flux:checkbox
                                wire:model="isPrivate"
                                wire:change="autoSave"
                                :label="__('Make this music piece private')"
                                :disabled="!$music->is_private && !Auth::user()?->can('changePublishedToPrivate', $music)" />
                            <flux:error name="isPrivate" />
                        </flux:field>
                    </div>
                </div>

                <!-- Save Button -->
                <div class="flex justify-end items-center gap-4 pt-2">
                    <x-action-message on="music-updated">
                        {{ __('Saved.') }}
                    </x-action-message>
                    <flux:button
                        variant="primary"
                        wire:click="update"
                        wire:loading.attr="disabled"
                        size="sm" class="md:size-md">
                        {{ __('Save Changes') }}
                    </flux:button>
                </div>
            </div>
        </flux:card>

        <!-- Music Tags -->
        <flux:card class="p-4 md:p-5 mt-4 md:mt-6">
            <flux:heading size="md" class="md:size-lg">{{ __('Music Tags') }}</flux:heading>
            <flux:text class="text-xs md:text-sm text-gray-600 dark:text-gray-400 mb-4">{{ __('Assign tags to categorize this music piece by type, instrument, season, and more.') }}</flux:text>

            <livewire:music-tag-selector :music="$music" />
        </flux:card>

        <!-- Collection Connections -->
        <flux:card class="p-4 md:p-5 mt-4 md:mt-6">
            <flux:heading size="md" class="md:size-lg flex items-center gap-2">
                {{ __('Collection Connections') }}
            </flux:heading>
            <flux:text class="text-xs md:text-sm text-gray-600 dark:text-gray-400 mb-4">{{ __('Manage collections this music piece belongs to.') }}</flux:text>

            @if($music->collections->count())
            <div class="flex flex-wrap gap-2 mb-4">
                @foreach($music->collections as $collection)
                <div class="inline-flex items-center gap-2 px-3 py-2 rounded-full bg-gray-100 dark:bg-gray-800 text-sm">
                    <livewire:verification-icon fieldName="collection" :music="$music" :pivotReference="$collection->id" />
                    <div class="flex flex-col">
                        <span class="text-gray-900 dark:text-gray-100 font-medium">{{ $collection->title }}</span>
                        @if($collection->abbreviation || $collection->pivot->order_number || $collection->pivot->page_number)
                        <span class="text-xs text-gray-600 dark:text-gray-400">
                            @if($collection->abbreviation){{ $collection->abbreviation }}@endif
                            @if($collection->pivot->order_number) · {{ __('Order') }}: {{ $collection->pivot->order_number }}@endif
                            @if($collection->pivot->page_number) · {{ __('Page') }}: {{ $collection->pivot->page_number }}@endif
                        </span>
                        @endif
                    </div>
                    <div class="flex items-center gap-1 ml-2">
                        <flux:button
                            variant="ghost"
                            size="sm"
                            icon="pencil"
                            wire:click="editCollection({{ $collection->id }})"
                            :title="__('Edit')" />
                        <flux:button
                            variant="ghost"
                            size="sm"
                            icon="x"
                            wire:click="removeCollection({{ $collection->id }})"
                            wire:confirm="{{ __('Are you sure you want to remove this collection from the music piece?') }}"
                            :title="__('Remove')" />
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <div class="text-center py-3 border border-dashed border-gray-300 dark:border-gray-600 rounded-lg mb-4">
                <flux:icon name="folder-open" class="mx-auto h-6 w-6 text-gray-400 dark:text-gray-500" />
                <h3 class="mt-1 text-xs md:text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('No collections attached') }}</h3>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ __('This music piece is not attached to any collections yet.') }}</p>
            </div>
            @endif

            <!-- Collection removal message -->
            <div class="flex justify-end mb-2">
                <x-action-message on="collection-removed">
                    {{ __('Collection removed.') }}
                </x-action-message>
                <x-action-message on="collection-updated">
                    {{ __('Collection updated.') }}
                </x-action-message>
            </div>

            <!-- Add Collection Form -->
            <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                <div class="space-y-3">
                    <div class="flex flex-col md:flex-row gap-3">
                        <flux:field required class="flex-[2]">
                            <flux:select
                                wire:model="selectedCollectionId">
                                <option value="">{{ __('Select a collection') }}</option>
                                @foreach ($collections as $collection)
                                <flux:select.option value="{{ $collection->id }}">{{ $collection->title }}@if($collection->abbreviation) ({{ $collection->abbreviation }})@endif</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="selectedCollectionId" />
                        </flux:field>
                        <flux:field class="flex-[1]">
                            <flux:input wire:model="orderNumber" :placeholder="__('Order #')" />
                            <flux:error name="orderNumber" />
                        </flux:field>

                        <flux:field class="flex-[1]">
                            <flux:input type="number" wire:model="pageNumber" :placeholder="__('Page #')" min="1" />
                            <flux:error name="pageNumber" />
                        </flux:field>
                        <div class="flex items-end gap-2 flex-none">
                            <flux:button
                                variant="primary"
                                wire:click="addCollection"
                                wire:loading.attr="disabled"
                                icon="plus"
                                size="sm">
                                {{ __('Add Collection') }}
                            </flux:button>
                            <x-action-message on="collection-added">
                                {{ __('Collection added.') }}
                            </x-action-message>
                        </div>

                    </div>

                </div>
            </div>
        </flux:card>

        <!-- Author Connections -->
        <flux:card class="p-4 md:p-5 mt-4 md:mt-6">
            <flux:heading size="md" class="md:size-lg flex items-center gap-2">
                {{ __('Author Connections') }}
            </flux:heading>

            @if($music->authors->count())
            <div class="flex flex-wrap gap-2 mb-4">
                @foreach($music->authors as $author)
                <div class="inline-flex items-center gap-2 px-3 py-2 rounded-full bg-gray-100 dark:bg-gray-800 text-sm">
                    <span class="text-gray-900 dark:text-gray-100 font-medium">{{ $author->name }}</span>
                    <livewire:verification-icon :fieldName="'author'" :music="$music" :pivotReference="$author->id" />
                    <flux:button
                        variant="ghost"
                        size="sm"
                        icon="x"
                        wire:click="removeAuthor({{ $author->id }})"
                        wire:confirm="{{ __('Are you sure you want to remove this author from the music piece?') }}"
                        :title="__('Remove')" />
                </div>
                @endforeach
            </div>
            @endif

            <!-- Author removal message -->
            <div class="flex justify-end mb-2">
                <x-action-message on="author-removed">
                    {{ __('Author removed.') }}
                </x-action-message>
                <x-action-message on="author-added">
                    {{ __('Author added.') }}
                </x-action-message>
            </div>

            <!-- Add Author Form -->
            <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                <div class="flex flex-col sm:flex-row items-start sm:items-end gap-3">
                    <div class="flex-1 w-full">
                        <x-mary-choices
                            wire:model="selectedAuthorId"
                            :options="$authorsSearchable"
                            placeholder="Keresés..."
                            search-function="searchAuthor"
                            no-result-text="Nincs találat"
                            single
                            searchable />
                    </div>
                    <flux:button
                        variant="primary"
                        wire:click="addAuthor"
                        wire:loading.attr="disabled"
                        icon="plus"
                        size="sm"
                        class="w-full sm:w-auto">
                        {{ __('Add Author') }}
                    </flux:button>
                </div>
            </div>
        </flux:card>

        <!-- URL Connections -->
        <flux:card class="p-4 md:p-5 mt-4 md:mt-6">
            <flux:heading size="md" class="md:size-lg">{{ __('URL Connections') }}</flux:heading>

            @if($music->urls->count())
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700 max-h-56 overflow-y-auto mb-4">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800 sticky top-0">
                        <tr>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Label') }}</th>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider hidden md:table-cell">{{ __('URL') }}</th>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($music->urls as $url)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                            <td class="px-3 py-2 text-xs md:text-sm font-medium text-gray-900 dark:text-gray-100">
                                <div class="flex items-center gap-2">
                                    @switch($url->label)
                                    @case('sheet_music')
                                    {{ __('Sheet Music') }}
                                    @break
                                    @case('audio')
                                    {{ __('Audio') }}
                                    @break
                                    @case('video')
                                    {{ __('Video') }}
                                    @break
                                    @case('text')
                                    {{ __('Text') }}
                                    @break
                                    @case('information')
                                    {{ __('Information') }}
                                    @break
                                    @default
                                    {{ $url->label }}
                                    @endswitch
                                    @php
                                    $urlVerified = $music->verifications()
                                    ->where('field_name', 'url')
                                    ->where('pivot_reference', $url->id)
                                    ->where('status', 'verified')
                                    ->exists();
                                    @endphp
                                    @if($urlVerified)
                                    <flux:icon name="check" variant="solid" class="h-4 w-4 text-green-500" title="{{ __('Verified') }}" />
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-2 text-xs md:text-sm text-gray-500 dark:text-gray-400 hidden md:table-cell truncate">
                                <a href="{{ $url->url }}" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline truncate max-w-xs block">
                                    {{ $url->url }}
                                </a>
                            </td>
                            <td class="px-3 py-2 text-xs md:text-sm">
                                <div class="flex items-center gap-1">
                                    @if($editingUrlId === $url->id)
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="check"
                                        wire:click="updateUrl"
                                        wire:loading.attr="disabled">{{ __('Save') }}</flux:button>
                                    <flux:button
                                        :wire:key="'cancel-' . $url->id"
                                        variant="ghost"
                                        size="sm"
                                        icon="x"
                                        wire:click="cancelEditUrl"
                                        wire:loading.attr="disabled">{{ __('Cancel') }}</flux:button>
                                    @else
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="pencil"
                                        wire:click="editUrl({{ $url->id }})"
                                        :title="__('Edit')" />
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="trash"
                                        wire:click="deleteUrl({{ $url->id }})"
                                        wire:confirm="{{ __('Are you sure you want to delete this URL?') }}"
                                        :title="__('Delete')" />
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @if($editingUrlId === $url->id)
                        <tr class="bg-gray-50 dark:bg-gray-800">
                            <td colspan="3" class="px-3 py-3">
                                <div class="space-y-3">
                                    <div class="flex flex-col md:flex-row gap-3">
                                        <flux:field required class="flex-[1]">
                                            <flux:label>{{ __('Label') }}</flux:label>
                                            <flux:select wire:model="editingUrlLabel">
                                                <option value="">{{ __('Select a URL type') }}</option>
                                                @foreach(\App\MusicUrlLabel::cases() as $label)
                                                <flux:select.option value="{{ $label->value }}">{{ __(ucfirst(str_replace('_', ' ', $label->value))) }}</flux:select.option>
                                                @endforeach
                                            </flux:select>
                                            <flux:error name="editingUrlLabel" />
                                        </flux:field>
                                        <flux:field required class="flex-[2]">
                                            <flux:label>{{ __('URL') }}</flux:label>
                                            <flux:input wire:model="editingUrl" :placeholder="__('https://example.com')" />
                                            <flux:error name="editingUrl" />
                                        </flux:field>
                                        <div class="flex items-end gap-2 flex-none">
                                            <flux:button
                                                variant="primary"
                                                wire:click="updateUrl"
                                                wire:loading.attr="disabled"
                                                size="sm">
                                                {{ __('Update URL') }}
                                            </flux:button>
                                            <flux:button
                                                variant="ghost"
                                                wire:click="cancelEditingUrl"
                                                size="sm">
                                                {{ __('Cancel') }}
                                            </flux:button>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            <!-- URL action messages -->
            <div class="flex justify-end mb-2">
                <x-action-message on="url-added">
                    {{ __('URL added.') }}
                </x-action-message>
                <x-action-message on="url-updated">
                    {{ __('URL updated.') }}
                </x-action-message>
                <x-action-message on="url-deleted">
                    {{ __('URL deleted.') }}
                </x-action-message>
            </div>

            <!-- Add URL Form -->
            <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                <flux:text class="text-xs md:text-sm text-gray-600 dark:text-gray-400 mb-3">{{ __('Add a new external URL for this music piece. URLs must be whitelisted.') }}</flux:text>

                <div class="space-y-3">
                    <div class="flex flex-col md:flex-row gap-3">
                        <flux:field required class="flex-[1]">
                            <flux:label>{{ __('URL type') }}</flux:label>
                            <flux:select wire:model="newUrlLabel">
                                <option value="">{{ __('Select a URL type') }}</option>
                                @foreach(\App\MusicUrlLabel::cases() as $label)
                                <flux:select.option value="{{ $label->value }}">{{ __(ucfirst(str_replace('_', ' ', $label->value))) }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="newUrlLabel" />
                        </flux:field>
                        <flux:field required class="flex-[2]">
                            <flux:label>{{ __('URL') }}</flux:label>
                            <flux:tooltip toggleable>
                                <button class="inline-flex gap-1 text-xs text-green-600 dark:text-green-400 hover:underline cursor-pointer font-medium px-2 py-1 translate-y-0.5">
                                    <flux:icon name="shield-check" class="h-4 w-4" />
                                    {{ __('Show approved') }}
                                </button>
                                <x-slot name="content">
                                    <div class="text-xs space-y-1">
                                        <div class="font-semibold mb-2">{{ __('Whitelisted URL patterns:') }}</div>
                                        @foreach($whitelistRules as $rule)
                                        <div class="text-gray-200">{{ $rule->pattern }}</div>
                                        @endforeach
                                    </div>
                                </x-slot>
                            </flux:tooltip>
                            <flux:input wire:model="newUrl" :placeholder="__('https://example.com')" />
                            <flux:error name="newUrl" />
                        </flux:field>
                        <div class="flex items-end gap-2 flex-none">
                            <flux:button
                                variant="primary"
                                wire:click="addUrl"
                                wire:loading.attr="disabled"
                                icon="plus"
                                size="sm">
                                {{ __('Add URL') }}
                            </flux:button>
                        </div>
                    </div>
                </div>
            </div>
        </flux:card>

        <!-- Related Music Connections -->
        <flux:card class="p-4 md:p-5 mt-4 md:mt-6">
            <flux:heading size="md" class="md:size-lg">{{ __('Related Music') }}</flux:heading>
            <flux:text class="text-xs md:text-sm text-gray-600 dark:text-gray-400 mb-4">{{ __('Manage related music pieces (variations, arrangements, etc.).') }}</flux:text>

            @if($music->allMusicRelations()->isNotEmpty())
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700 max-h-56 overflow-y-auto mb-4">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800 sticky top-0">
                        <tr>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Music Piece') }}</th>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider hidden sm:table-cell">{{ __('Relationship Type') }}</th>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($music->allMusicRelations() as $relation)
                        @php $partner = $relation->partnerFor($music); @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                            <td class="px-3 py-2 text-xs md:text-sm font-medium text-gray-900 dark:text-gray-100">
                                <div class="max-w-sm text-wrap">
                                    <div class="font-medium line-clamp-1">{{ $partner->title }}</div>
                                    @if ($partner->subtitle)
                                    <div class="text-xs text-gray-600 dark:text-gray-400 line-clamp-1">{{ $partner->subtitle }}</div>
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-2 text-xs md:text-sm text-gray-500 dark:text-gray-400 hidden sm:table-cell">
                                {{ \App\MusicRelationshipType::from($relation->relationship_type)->label() }}
                            </td>
                            <td class="px-3 py-2 text-xs md:text-sm">
                                <div class="flex items-center gap-1">
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="trash"
                                        wire:click="removeRelatedMusic({{ $relation->id }})"
                                        wire:confirm="{{ __('Are you sure you want to remove this related music?') }}"
                                        :title="__('Remove')" />
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            <!-- Related music action messages -->
            <div class="flex justify-end mb-2">
                <x-action-message on="related-music-added">
                    {{ __('Related music added.') }}
                </x-action-message>
                <x-action-message on="related-music-removed">
                    {{ __('Related music removed.') }}
                </x-action-message>
            </div>

            <!-- Add Related Music Button -->
            <div class="border-t border-gray-200 dark:border-gray-700 pt-4 pb-4">
                <div class="flex justify-end">
                    <flux:button
                        variant="primary"
                        wire:click="openRelatedMusicSearchModal"
                        icon="plus"
                        size="sm">
                        {{ __('Add Related Music') }}
                    </flux:button>
                </div>
            </div>
        </flux:card>
    </div>

    <!-- Related Music Search Modal -->
    @if($showRelatedMusicSearchModal)
    <flux:modal wire:model="showRelatedMusicSearchModal" class="max-w-3xl">
        <flux:heading size="md">{{ __('Add Related Music') }}</flux:heading>
        <flux:text class="text-xs md:text-sm text-gray-600 dark:text-gray-400 mb-4">{{ __('Select a relationship type and search for a music piece. Click the Select button on a music to add it as related.') }}</flux:text>

        <!-- Relationship type selection -->
        <flux:field required class="mb-4">
            <flux:label>{{ __('Relationship Type') }}</flux:label>
            <flux:select wire:model="selectedRelationshipType">
                <option value="">{{ __('Select a relationship type') }}</option>
                @foreach(\App\MusicRelationshipType::cases() as $type)
                <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="selectedRelationshipType" />
        </flux:field>

        <!-- Music search component -->
        <livewire:music-search selectable="true" source=".relatedMusic" wire:key="relatedMusicSearch" />

        <!-- Hidden field for selected music ID -->
        <input type="hidden" wire:model="selectedRelatedMusicId" />
        <flux:error name="selectedRelatedMusicId" />

        <div class="mt-4 flex justify-end">
            <flux:button
                wire:click="closeRelatedMusicSearchModal"
                variant="outline"
                size="sm">
                {{ __('Close') }}
            </flux:button>
        </div>
    </flux:modal>
    @endif

    <!-- Audit Log Modal -->
    @if($showAuditModal)
    <flux:modal wire:model="showAuditModal" max-width="3xl">
        <flux:heading size="md">{{ __('Audit Log') }}</flux:heading>
        <flux:subheading class="text-xs md:text-sm">
            {{ __('Music Piece:') }} {{ $music->title }}
        </flux:subheading>

        <div class="mt-4">
            @if(count($audits))
            <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-xs md:text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Event') }}</th>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider hidden sm:table-cell">{{ __('Changes') }}</th>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('When') }}</th>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider hidden sm:table-cell">{{ __('Who') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($audits as $audit)
                        <tr>
                            <td class="px-3 py-2 whitespace-nowrap text-xs md:text-sm font-medium">
                                @switch($audit->event)
                                @case('created')
                                <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-300">
                                    {{ __('Created') }}
                                </span>
                                @break
                                @case('updated')
                                <span class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                    {{ __('Updated') }}
                                </span>
                                @break
                                @case('deleted')
                                <span class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800 dark:bg-red-900 dark:text-red-300">
                                    {{ __('Deleted') }}
                                </span>
                                @break
                                @default
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-800 dark:bg-gray-900 dark:text-gray-300">
                                    {{ $audit->event }}
                                </span>
                                @endswitch
                            </td>
                            <td class="px-3 py-2 text-xs md:text-sm text-gray-700 dark:text-gray-300 hidden sm:table-cell">
                                @if($audit->event === 'created')
                                {{ __('Music piece was created.') }}
                                @elseif($audit->event === 'deleted')
                                {{ __('Music piece was deleted.') }}
                                @else
                                @php
                                $oldValues = $audit->old_values ?? [];
                                $newValues = $audit->new_values ?? [];
                                $changes = [];
                                foreach ($newValues as $key => $value) {
                                $old = $oldValues[$key] ?? null;
                                if ($old != $value) {
                                $changes[] = __($key) . ': "' . ($old ?? __('empty')) . '" → "' . ($value ?? __('empty')) . '"';
                                }
                                }
                                @endphp
                                @if(count($changes))
                                <ul class="list-disc list-inside space-y-0.5">
                                    @foreach($changes as $change)
                                    <li class="text-xs">{{ $change }}</li>
                                    @endforeach
                                </ul>
                                @else
                                <span class="text-gray-400 dark:text-gray-500 text-xs">{{ __('No field changes recorded') }}</span>
                                @endif
                                @endif
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-xs md:text-sm text-gray-500 dark:text-gray-400">
                                {{ $audit->created_at->translatedFormat('m-d H:i') }}
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-xs md:text-sm text-gray-500 dark:text-gray-400 hidden sm:table-cell">
                                @if($audit->user)
                                {{ $audit->user->display_name }}
                                @else
                                <span class="text-gray-400 dark:text-gray-500">{{ __('System') }}</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="text-center py-6">
                <flux:icon name="logs" class="mx-auto h-10 w-10 text-gray-400 dark:text-gray-500" />
                <h3 class="mt-2 text-xs md:text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('No audit logs found') }}</h3>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('No changes have been recorded for this music piece yet.') }}</p>
            </div>
            @endif
        </div>

        <div class="mt-4 flex justify-end">
            <flux:button
                variant="ghost"
                wire:click="$set('showAuditModal', false)"
                size="sm">
                {{ __('Close') }}
            </flux:button>
        </div>
    </flux:modal>
    @endif

    @if($showEditModal)
    <!-- Edit Collection Modal -->
    <flux:modal wire:model="showEditModal" max-width="lg">
        <flux:heading size="md">{{ __('Edit Collection Connection') }}</flux:heading>
        <flux:subheading class="text-xs md:text-sm">
            {{ __('Update page and order numbers for this collection.') }}
        </flux:subheading>

        <div class="mt-4 space-y-3">
            <flux:field>
                <flux:label>{{ __('Order Number') }}</flux:label>
                <flux:input
                    wire:model="editingOrderNumber"
                    :placeholder="__('Order number')" />
                <flux:error name="editingOrderNumber" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Page Number') }}</flux:label>
                <flux:input
                    type="number"
                    wire:model="editingPageNumber"
                    :placeholder="__('Page number')"
                    min="1" />
                <flux:error name="editingPageNumber" />
            </flux:field>
        </div>

        <div class="mt-4 flex justify-end gap-2">
            <flux:button
                variant="ghost"
                wire:click="$set('showEditModal', false)"
                size="sm">
                {{ __('Cancel') }}
            </flux:button>
            <flux:button
                variant="primary"
                wire:click="updateCollection"
                wire:loading.attr="disabled"
                size="sm">
                {{ __('Save Changes') }}
            </flux:button>
        </div>
    </flux:modal>
    @endif

    <!-- Error Report Component -->
    <livewire:error-report />
</div>

@script
<script>
    Alpine.watch(() => $wire.isSaved, (value) => {
        if (value) {
            // Clear the saved indicator after 2 seconds
            setTimeout(() => {
                $wire.clearSavedIndicator();
            }, 2000);
        }
    });
</script>
@endscript