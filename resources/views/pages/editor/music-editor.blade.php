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

        $this->authorsSearchable = $query
            ->take(20)
            ->get()
            ->sortBy('name')
            ->values()
            ->merge($selectedOption);
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
        $this->authorize('update', $this->music);

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
        $existing = $this->music->relatedMusic()
            ->wherePivot('related_music_id', $validated['selectedRelatedMusicId'])
            ->exists();
        $existingReverse = Music::where('id', $validated['selectedRelatedMusicId'])
            ->first()
            ?->relatedMusic()
            ->wherePivot('related_music_id', $this->music->id)
            ->exists() ?? false;
        if ($existing || $existingReverse) {
            $this->dispatch('error', __('This music piece is already related.'));
            return;
        }

        $this->music->relatedMusic()->attach($validated['selectedRelatedMusicId'], [
            'relationship_type' => $validated['selectedRelationshipType'],
        ]);

        // Refresh the relationship
        $this->music->load('relatedMusic');

        // Reset form fields
        $this->selectedRelatedMusicId = null;
        $this->selectedRelationshipType = null;

        // Close the modal
        $this->showRelatedMusicSearchModal = false;

        $this->dispatch('related-music-added');
    }

    /**
     * Remove a related music piece.
     */
    public function removeRelatedMusic(int $relatedMusicId): void
    {
        $this->authorize('update', $this->music);

        $this->music->relatedMusic()->detach($relatedMusicId);

        // Refresh the relationship
        $this->music->load('relatedMusic');

        $this->dispatch('related-music-removed');
    }


    /**
     * Mount the component.
     */
    public function mount(Music $music): void
    {
        $this->authorize('view', $music);
        $this->music = $music->load(['collections', 'genres', 'authors', 'urls', 'relatedMusic', 'tags']);
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
        $collections = Collection::orderBy('title')->limit(100)->get();
        $authors = Author::visibleTo(Auth::user())
            ->orderBy('name')
            ->limit(100)
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
     * Add a collection to the music piece.
     */
    public function addCollection(): void
    {
        $this->authorize('update', $this->music);

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
        $this->authorize('update', $this->music);

        $this->music->collections()->detach($collectionId);
        $this->music->load('collections');

        $this->dispatch('collection-removed');
    }

    /**
     * Add an author to the music piece.
     */
    public function addAuthor(): void
    {
        $this->authorize('update', $this->music);

        $validated = $this->validate([
            'selectedAuthorId' => ['required', 'integer', 'exists:authors,id'],
        ]);

        // Check if already attached
        if ($this->music->authors()->where('author_id', $validated['selectedAuthorId'])->exists()) {
            $this->dispatch('error', __('This author is already attached to this music piece.'));

            return;
        }

        $this->music->authors()->attach($validated['selectedAuthorId']);

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
        $this->authorize('update', $this->music);

        $this->music->authors()->detach($authorId);
        $this->music->load('authors');

        $this->dispatch('author-removed');
    }

    /**
     * Add a URL to the music piece.
     */
    public function addUrl(): void
    {
        $this->authorize('update', $this->music);

        $validated = $this->validate([
            'newUrlLabel' => ['required', 'string', Rule::in(array_column(\App\MusicUrlLabel::cases(), 'value'))],
            'newUrl' => ['required', 'string', 'url', new \App\Rules\WhitelistedUrl()],
        ]);

        // Create the URL
        $this->music->urls()->create([
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
        $this->authorize('update', $this->music);

        $url = $this->music->urls()->find($urlId);

        if (!$url) {
            return;
        }

        $this->editingUrlId = $urlId;
        $this->editingUrlLabel = $url->label;
        $this->editingUrl = $url->url;
    }

    /**
     * Update the editing URL.
     */
    public function updateUrl(): void
    {
        $this->authorize('update', $this->music);

        $validated = $this->validate([
            'editingUrlLabel' => ['required', 'string', Rule::in(array_column(\App\MusicUrlLabel::cases(), 'value'))],
            'editingUrl' => ['required', 'string', 'url', new \App\Rules\WhitelistedUrl()],
        ]);

        $url = $this->music->urls()->find($this->editingUrlId);

        if (!$url) {
            $this->cancelEditUrl();
            return;
        }

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
        $this->authorize('update', $this->music);

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
        $this->authorize('update', $this->music);

        $collection = $this->music->collections()->where('collection_id', $collectionId)->first();

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
        $this->authorize('update', $this->music);

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

        $this->redirectRoute('musics');
    }
};
?>

<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Header with back button -->
        <div class="mb-6">
            <flux:button
                variant="ghost"
                icon="arrow-left"
                :href="route('musics')"
                tag="a">
                {{ __('Back to Music List') }}
            </flux:button>
        </div>

        <!-- Music summary card -->
        <div class="mb-6">
            <livewire:music-card :music="$music" />
        </div>

        <div class="mb-6">
            <livewire:music-card-extended :music="$music" />
        </div>


        <flux:card class="p-5">
            <div class="flex items-center justify-between gap-4 mb-6">
                <div>
                    <flux:heading size="xl">{{ __('Edit Music Piece') }}</flux:heading>
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

                    <flux:button
                        variant="ghost"
                        icon="trash"
                        wire:click="delete"
                        wire:confirm="{{ __('Are you sure you want to delete this music piece? This can only be done if no collections or plan slots are assigned to it.') }}"
                        :title="__('Delete')" />
                </div>
            </div>

            <!-- Edit Form -->
            <div class="space-y-6">
                <div class="grid grid-cols-3 gap-6">
                    <flux:field required>
                        <flux:label>{{ __('Title') }}</flux:label>
                        <flux:input
                            wire:model="title"
                            :placeholder="__('Enter music piece title')" />
                        <flux:error name="title" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Subtitle') }}</flux:label>
                        <flux:input
                            wire:model="subtitle"
                            :placeholder="__('Enter subtitle')" />
                        <flux:error name="subtitle" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Custom ID') }}</flux:label>
                        <flux:input
                            wire:model="customId"
                            :placeholder="__('Enter custom ID')" />
                        <flux:error name="customId" />
                    </flux:field>

                </div>
                <div class="gap-4 flex">
                    <!-- Genre Selection -->
                    <div>
                        <flux:field>
                            <flux:label>{{ __('Genres') }}</flux:label>
                            <flux:description>{{ __('Select one or more genres that apply to this music piece.') }}</flux:description>
                            <div class="space-y-2">
                                <flux:checkbox.group variant="cards">
                                    @foreach($this->genres() as $genre)
                                    <flux:checkbox
                                        variant="cards"
                                        wire:model="selectedGenres"
                                        value="{{ $genre->id }}"
                                        :label="$genre->label()"
                                        :icon="$genre->icon()" />
                                    @endforeach
                                </flux:checkbox.group>
                                <flux:error name="selectedGenres" />
                            </div>
                        </flux:field>
                    </div>
                    <!-- Privacy Toggle -->
                    <div>
                        <flux:field>
                            <flux:label>{{ __('Privacy') }}</flux:label>
                            <flux:description>{{ __('Private music pieces are only visible to you. Public music pieces are visible to all users.') }}</flux:description>
                            <flux:checkbox
                                wire:model="isPrivate"
                                :label="__('Make this music piece private')" />
                            <flux:error name="isPrivate" />
                        </flux:field>
                    </div>

                </div>
                <!-- Save Button -->
                <div class="flex justify-end items-center gap-4">
                    <x-action-message on="music-updated">
                        {{ __('Saved.') }}
                    </x-action-message>
                    <flux:button
                        variant="primary"
                        wire:click="update"
                        wire:loading.attr="disabled">
                        {{ __('Save Changes') }}
                    </flux:button>
                </div>
            </div>
        </flux:card>

        <!-- Music Tags -->
        <flux:card class="p-5 mt-6">
            <flux:heading size="lg">{{ __('Music Tags') }}</flux:heading>
            <flux:text class="text-sm text-gray-600 dark:text-gray-400 mb-6">{{ __('Assign tags to categorize this music piece by type, instrument, season, and more.') }}</flux:text>

            <livewire:music-tag-selector :music="$music" />
        </flux:card>

        <!-- Collection Connections -->
        <flux:card class="p-5 mt-6">
            <flux:heading size="lg">{{ __('Collection Connections') }}</flux:heading>
            <flux:text class="text-sm text-gray-600 dark:text-gray-400 mb-6">{{ __('Manage collections this music piece belongs to.') }}</flux:text>

            @if($music->collections->count())
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700 max-h-60 overflow-y-auto mb-6">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800 sticky top-0">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Collection') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Order Number') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Page Number') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($music->collections as $collection)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ $collection->title }}
                                @if($collection->abbreviation)
                                <span class="text-gray-500 dark:text-gray-400">({{ $collection->abbreviation }})</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $collection->pivot->order_number ?? '-' }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $collection->pivot->page_number ?? '-' }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                <div class="flex items-center gap-2">
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="pencil"
                                        wire:click="editCollection({{ $collection->id }})"
                                        :title="__('Edit')" />
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="trash"
                                        wire:click="removeCollection({{ $collection->id }})"
                                        wire:confirm="{{ __('Are you sure you want to remove this collection from the music piece?') }}"
                                        :title="__('Remove')" />
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="text-center py-4 border border-dashed border-gray-300 dark:border-gray-600 rounded-lg mb-6">
                <flux:icon name="folder-open" class="mx-auto h-8 w-8 text-gray-400 dark:text-gray-500" />
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('No collections attached') }}</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('This music piece is not attached to any collections yet.') }}</p>
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
            <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                <flux:heading size="sm">{{ __('Add Collection') }}</flux:heading>
                <flux:text class="text-sm text-gray-600 dark:text-gray-400 mb-4">{{ __('Assign this music piece to a new collection with page and order numbers.') }}</flux:text>

                <div class="space-y-4">
                    <div class="flex gap-4">
                        <flux:field required class="flex-1">
                            <flux:label>{{ __('Collection') }}</flux:label>
                            <flux:select
                                wire:model="selectedCollectionId">
                              <option value="">{{ __('Select a collection') }}</option>
                                @foreach ($collections as $collection)
                                <flux:select.option value="{{ $collection->id }}">{{ $collection->title }}@if($collection->abbreviation) ({{ $collection->abbreviation }})@endif</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="selectedCollectionId" />
                        </flux:field>
                        <flux:field class="w-32">
                            <flux:label>{{ __('Order Number') }}</flux:label>
                            <flux:input wire:model="orderNumber" :placeholder="__('Order number')" />
                            <flux:error name="orderNumber" />
                        </flux:field>

                        <flux:field class="w-32">
                            <flux:label>{{ __('Page Number') }}</flux:label>
                            <flux:input type="number" wire:model="pageNumber" :placeholder="__('Page number')" min="1" />
                            <flux:error name="pageNumber" />
                        </flux:field>
                    </div>

                    <div class="flex justify-end items-center gap-4">
                        <flux:button
                            variant="primary"
                            wire:click="addCollection"
                            wire:loading.attr="disabled">
                            {{ __('Add Collection') }}
                        </flux:button>
                        <x-action-message on="collection-added">
                            {{ __('Collection added.') }}
                        </x-action-message>
                    </div>
                </div>
            </div>
        </flux:card>

        <!-- Author Connections -->
        <flux:card class="p-5 mt-6">
            <flux:heading size="lg">{{ __('Author Connections') }}</flux:heading>
            <flux:text class="text-sm text-gray-600 dark:text-gray-400 mb-6">{{ __('Manage authors of this music piece.') }}</flux:text>

            @if($music->authors->count())
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700 max-h-60 overflow-y-auto mb-6">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800 sticky top-0">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Author') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($music->authors as $author)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ $author->name }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                <div class="flex items-center gap-2">
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="trash"
                                        wire:click="removeAuthor({{ $author->id }})"
                                        wire:confirm="{{ __('Are you sure you want to remove this author from the music piece?') }}"
                                        :title="__('Remove')" />
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="text-center py-4 border border-dashed border-gray-300 dark:border-gray-600 rounded-lg mb-6">
                <flux:icon name="user" class="mx-auto h-8 w-8 text-gray-400 dark:text-gray-500" />
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('No authors attached') }}</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('This music piece has no authors assigned yet.') }}</p>
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
            <div class="flex items-end gap-4">
                <div class="flex-1">
                    <x-mary-choices
                        label="Szerző kiválasztása"
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
                    wire:loading.attr="disabled">
                    {{ __('Add Author') }}
                </flux:button>
            </div>
        </flux:card>

        <!-- URL Connections -->
        <flux:card class="p-5 mt-6">
            <flux:heading size="lg">{{ __('URL Connections') }}</flux:heading>
            <flux:text class="text-sm text-gray-600 dark:text-gray-400 mb-6">{{ __('Manage external URLs related to this music piece (sheet music, audio, video, etc.).') }}</flux:text>

            @if($music->urls->count())
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700 max-h-60 overflow-y-auto mb-6">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800 sticky top-0">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Label') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('URL') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($music->urls as $url)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
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
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                <a href="{{ $url->url }}" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline truncate max-w-xs block">
                                    {{ $url->url }}
                                </a>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                <div class="flex items-center gap-2">
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
                            <td colspan="3" class="px-4 py-4">
                                <div class="space-y-4">
                                    <div class="grid grid-cols-2 gap-4">
                                        <flux:field required>
                                            <flux:label>{{ __('Label') }}</flux:label>
                                            <flux:select wire:model="editingUrlLabel">
                                                <option value="">{{ __('Select a label') }}</option>
                                                @foreach(\App\MusicUrlLabel::cases() as $label)
                                                <flux:select.option value="{{ $label->value }}">{{ __(ucfirst(str_replace('_', ' ', $label->value))) }}</flux:select.option>
                                                @endforeach
                                            </flux:select>
                                            <flux:error name="editingUrlLabel" />
                                        </flux:field>
                                        <flux:field required>
                                            <flux:label>{{ __('URL') }}</flux:label>
                                            <flux:input wire:model="editingUrl" :placeholder="__('https://example.com')" />
                                            <flux:error name="editingUrl" />
                                        </flux:field>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="text-center py-4 border border-dashed border-gray-300 dark:border-gray-600 rounded-lg mb-6">
                <flux:icon name="globe" class="mx-auto h-8 w-8 text-gray-400 dark:text-gray-500" />
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('No URLs attached') }}</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('This music piece has no external URLs yet.') }}</p>
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
            <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                <flux:heading size="sm">{{ __('Add URL') }}</flux:heading>
                <flux:text class="text-sm text-gray-600 dark:text-gray-400 mb-4">{{ __('Add a new external URL for this music piece. URLs must be whitelisted.') }}</flux:text>
                <flux:icon name="shield-check" class="h-5 w-5 text-green-500 inline" />                
                @foreach($whitelistRules as $rule)
                    <flux:text class="inline text-sm mb-2">{{ $rule->pattern }}</flux:text>
                @endforeach

                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <flux:field required>
                            <flux:label>{{ __('Label') }}</flux:label>
                            <flux:select wire:model="newUrlLabel">
                                <option value="">{{ __('Select a label') }}</option>
                                @foreach(\App\MusicUrlLabel::cases() as $label)
                                <flux:select.option value="{{ $label->value }}">{{ __(ucfirst(str_replace('_', ' ', $label->value))) }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="newUrlLabel" />
                        </flux:field>
                        <flux:field required>
                            <flux:label>{{ __('URL') }}</flux:label>
                            <flux:input wire:model="newUrl" :placeholder="__('https://example.com')" />
                            <flux:error name="newUrl" />
                        </flux:field>
                    </div>

                    <div class="flex justify-end items-center gap-4">
                        <flux:button
                            variant="primary"
                            wire:click="addUrl"
                            wire:loading.attr="disabled">
                            {{ __('Add URL') }}
                        </flux:button>
                    </div>
                </div>
            </div>
        </flux:card>

        <!-- Related Music Connections -->
        <flux:card class="p-5 mt-6">
            <flux:heading size="lg">{{ __('Related Music') }}</flux:heading>
            <flux:text class="text-sm text-gray-600 dark:text-gray-400 mb-6">{{ __('Manage related music pieces (variations, arrangements, etc.).') }}</flux:text>

            @if($music->relatedMusic->count())
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700 max-h-60 overflow-y-auto mb-6">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800 sticky top-0">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Music Piece') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Relationship Type') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($music->relatedMusic as $related)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                <div class="max-w-80 text-wrap">
                                    <div class="font-medium">{{ $related->title }}</div>
                                    @if ($related->subtitle)
                                        <div class="text-sm text-gray-600 dark:text-gray-400">{{ $related->subtitle }}</div>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ \App\MusicRelationshipType::from($related->pivot->relationship_type)->name }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                <div class="flex items-center gap-2">
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="trash"
                                        wire:click="removeRelatedMusic({{ $related->id }})"
                                        wire:confirm="{{ __('Are you sure you want to remove this related music?') }}"
                                        :title="__('Remove')" />
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="text-center py-4 border border-dashed border-gray-300 dark:border-gray-600 rounded-lg mb-6">
                <flux:icon name="music" class="mx-auto h-8 w-8 text-gray-400 dark:text-gray-500" />
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('No related music') }}</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('This music piece has no related music yet.') }}</p>
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
            <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                <flux:heading size="sm">{{ __('Add Related Music') }}</flux:heading>
                <flux:text class="text-sm text-gray-600 dark:text-gray-400 mb-4">{{ __('Search for a music piece and select a relationship type.') }}</flux:text>

                <div class="flex justify-end">
                    <flux:button
                        variant="primary"
                        wire:click="openRelatedMusicSearchModal"
                        icon="plus">
                        {{ __('Add Related Music') }}
                    </flux:button>
                </div>
            </div>
        </flux:card>
    </div>

    <!-- Related Music Search Modal -->
    @if($showRelatedMusicSearchModal)
    <flux:modal wire:model="showRelatedMusicSearchModal" class="max-w-4xl">
        <flux:heading size="lg">{{ __('Add Related Music') }}</flux:heading>
        <flux:text class="text-sm text-gray-600 dark:text-gray-400 mb-6">{{ __('Select a relationship type and search for a music piece. Click the Select button on a music to add it as related.') }}</flux:text>

        <!-- Relationship type selection -->
        <flux:field required class="mb-6">
            <flux:label>{{ __('Relationship Type') }}</flux:label>
            <flux:select wire:model="selectedRelationshipType">
                <option value="">{{ __('Select a relationship type') }}</option>
                @foreach(\App\MusicRelationshipType::cases() as $type)
                <flux:select.option value="{{ $type->value }}">{{ __(ucfirst($type->value)) }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="selectedRelationshipType" />
        </flux:field>

        <!-- Music search component -->
        <livewire:music-search selectable="true" source=".relatedMusic" />

        <!-- Hidden field for selected music ID -->
        <input type="hidden" wire:model="selectedRelatedMusicId" />
        <flux:error name="selectedRelatedMusicId" />

        <div class="mt-6 flex justify-end">
            <flux:button
                wire:click="closeRelatedMusicSearchModal"
                variant="outline">
                {{ __('Close') }}
            </flux:button>
        </div>
    </flux:modal>
    @endif

    <!-- Audit Log Modal -->
    @if($showAuditModal)
    <flux:modal wire:model="showAuditModal" max-width="4xl">
        <flux:heading size="lg">{{ __('Audit Log') }}</flux:heading>
        <flux:subheading>
            {{ __('Music Piece:') }} {{ $music->title }}
        </flux:subheading>

        <div class="mt-6">
            @if(count($audits))
            <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Event') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Changes') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('When') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Who') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($audits as $audit)
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                @switch($audit->event)
                                @case('created')
                                <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-300">
                                    {{ __('Created') }}
                                </span>
                                @break
                                @case('updated')
                                <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                    {{ __('Updated') }}
                                </span>
                                @break
                                @case('deleted')
                                <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800 dark:bg-red-900 dark:text-red-300">
                                    {{ __('Deleted') }}
                                </span>
                                @break
                                @default
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800 dark:bg-gray-900 dark:text-gray-300">
                                    {{ $audit->event }}
                                </span>
                                @endswitch
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
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
                                <ul class="list-disc list-inside space-y-1">
                                    @foreach($changes as $change)
                                    <li class="text-xs">{{ $change }}</li>
                                    @endforeach
                                </ul>
                                @else
                                <span class="text-gray-400 dark:text-gray-500">{{ __('No field changes recorded') }}</span>
                                @endif
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $audit->created_at->translatedFormat('Y-m-d H:i:s') }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
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
            <div class="text-center py-8">
                <flux:icon name="logs" class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" />
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('No audit logs found') }}</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('No changes have been recorded for this music piece yet.') }}</p>
            </div>
            @endif
        </div>

        <div class="mt-6 flex justify-end">
            <flux:button
                variant="ghost"
                wire:click="$set('showAuditModal', false)">
                {{ __('Close') }}
            </flux:button>
        </div>
    </flux:modal>
    @endif

    @if($showEditModal)
    <!-- Edit Collection Modal -->
    <flux:modal wire:model="showEditModal" max-width="lg">
        <flux:heading size="lg">{{ __('Edit Collection Connection') }}</flux:heading>
        <flux:subheading>
            {{ __('Update page and order numbers for this collection.') }}
        </flux:subheading>

        <div class="mt-6 space-y-4">
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

        <div class="mt-6 flex justify-end gap-3">
            <flux:button
                variant="ghost"
                wire:click="$set('showEditModal', false)">
                {{ __('Cancel') }}
            </flux:button>
            <flux:button
                variant="primary"
                wire:click="updateCollection"
                wire:loading.attr="disabled">
                {{ __('Save Changes') }}
            </flux:button>
        </div>
    </flux:modal>
    @endif

    <!-- Error Report Component -->
    <livewire:error-report />
</div>