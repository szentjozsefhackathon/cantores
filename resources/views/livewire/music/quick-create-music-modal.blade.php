<div>
    <flux:modal wire:model="open" title="Új ének gyors létrehozása" class="md:w-2xl">
        <div class="space-y-4">
            <!-- Title field -->
            <flux:field>
                <flux:input
                    wire:model="title"
                    placeholder="Cím"
                    required />
                <flux:error name="title"/>
            </flux:field>

            <!-- Subtitle field -->
            <flux:field>
                <flux:input
                    wire:model="subtitle"
                    placeholder="Alcím" />
            </flux:field>

            <!-- Author field (searchable custom) -->
            <flux:field>
                <div class="relative z-20">
                    <div class="flex gap-2">
                        <div class="flex-1">
                            <flux:input
                                wire:model.live.debounce.300ms="authorSearch"
                                placeholder="Keresés a szerzők között..."
                                autocomplete="off" />
                        </div>
                        @if($selectedAuthorId)
                        <flux:button
                            wire:click="$set('selectedAuthorId', null)"
                            icon="x-mark"
                            variant="outline"
                            size="sm" />
                        @endif
                    </div>
                    @if($authorSearch && $authors->isNotEmpty())
                    <div class="absolute top-full left-0 right-0 mt-1 z-50 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg shadow-2xl max-h-48 overflow-y-auto">
                        @foreach($authors as $author)
                        <button
                            type="button"
                            wire:click="$set('selectedAuthorId', {{ $author->id }}); $set('authorSearch', '')"
                            class="w-full text-left px-3 py-2 hover:bg-blue-100 dark:hover:bg-blue-900 border-b border-gray-200 dark:border-gray-700 last:border-0 focus:outline-none">
                            {{ $author->name }}
                        </button>
                        @endforeach
                    </div>
                    @endif
                </div>
                @if($selectedAuthor)
                    <div class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        Kiválasztva: <strong>{{ $selectedAuthor->name }}</strong>
                    </div>
                @endif
            </flux:field>

            <!-- Collection field (searchable custom) -->
            <flux:field>
                <div class="relative z-10">
                    <div class="flex gap-2">
                        <div class="flex-1">
                            <flux:input
                                wire:model.live.debounce.300ms="collectionSearch"
                                placeholder="Keresés a gyűjtemények között..."
                                autocomplete="off" />
                        </div>
                        @if($selectedCollectionId)
                        <flux:button
                            wire:click="$set('selectedCollectionId', null)"
                            icon="x-mark"
                            variant="outline"
                            size="sm" />
                        @endif
                    </div>
                    @if($collectionSearch && $collections->isNotEmpty())
                    <div class="absolute top-full left-0 right-0 mt-1 z-50 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg shadow-2xl max-h-48 overflow-y-auto">
                        @foreach($collections as $collection)
                        <button
                            type="button"
                            wire:click="$set('selectedCollectionId', {{ $collection->id }}); $set('collectionSearch', '')"
                            class="w-full text-left px-3 py-2 hover:bg-blue-100 dark:hover:bg-blue-900 border-b border-gray-200 dark:border-gray-700 last:border-0 focus:outline-none">
                            {{ $collection->title }}@if($collection->abbreviation) ({{ $collection->abbreviation }})@endif
                        </button>
                        @endforeach
                    </div>
                    @endif
                </div>
                @if($selectedCollection)
                    <div class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        Kiválasztva: <strong>{{ $selectedCollection->title }}@if($selectedCollection->abbreviation) ({{ $selectedCollection->abbreviation }})@endif</strong>
                    </div>
                @endif
            </flux:field>

            <!-- Collection metadata (Order and Page number) -->
            @if($selectedCollectionId)
            <div class="grid grid-cols-2 gap-3">
                <flux:field>

                    <flux:input
                        type="number"
                        wire:model="orderNumber"
                        placeholder="Sorszám"
                        min="0" />
                </flux:field>
                <flux:field>
                    <flux:input
                        type="number"
                        wire:model="pageNumber"
                        placeholder="Oldalszám"
                        min="0" />
                </flux:field>
            </div>
            @endif

            <!-- Privacy checkbox -->
            <flux:field variant="inline">
                <flux:checkbox wire:model="isPrivate" />                
                <flux:label>Privát</flux:label>
            </flux:field>

            <!-- Genres -->
            <flux:field>
                <div class="space-y-2">
                    <flux:checkbox.group variant="buttons">
                        @foreach($genres as $genre)
                        <flux:checkbox
                            wire:model="selectedGenres"
                            value="{{ $genre->id }}"
                            label=""
                            :icon="$genre->icon()" />
                        @endforeach
                    </flux:checkbox.group>
                </div>
            </flux:field>

            <!-- Action buttons -->
            <div class="flex gap-2 pt-6 justify-end border-t border-gray-200 dark:border-gray-700">
                <flux:button
                    wire:click="closeModal"
                    variant="outline">
                    Mégse
                </flux:button>
                <flux:button
                    wire:click="create"
                    variant="primary"
                    wire:loading.attr="disabled">
                    Létrehozás
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Confirmation modal -->
    <flux:modal wire:model="showConfirmation" title="Ének már létezik?">
        <div class="space-y-4">
            <p class="text-gray-700 dark:text-gray-300">
                Az "<strong>{{ $title }}</strong>" című ének már létezik az adatbázisban.
                @if($selectedCollection)
                    ({{ $selectedCollection->title }}
                @endif
            </p>
            <div class="flex gap-2 justify-end">
                <flux:button
                    wire:click="cancelConfirmation"
                    variant="outline">
                    Mégse
                </flux:button>
                <flux:button
                    wire:click="confirmCreate"
                    variant="primary"
                    wire:loading.attr="disabled">
                    Mégis létrehozás
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Trigger button -->
    <flux:button
        wire:click="openModal"
        icon="plus"
        variant="outline"
        size="sm">
        Új ének
    </flux:button>
</div>
