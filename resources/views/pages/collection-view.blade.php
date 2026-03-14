<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <flux:card class="p-5">
            <div class="flex items-start gap-4 mb-6">
                @if($collection->coverUrl())
                    <img src="{{ $collection->coverUrl() }}" alt="{{ $collection->title }}"
                         class="w-20 h-20 shrink-0 rounded-xl object-cover shadow-sm" />
                @else
                    <div class="w-20 h-20 rounded-xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center shrink-0">
                        <flux:icon name="book-open" class="w-10 h-10 text-gray-400 dark:text-gray-500" />
                    </div>
                @endif
                <div>
                    <flux:heading size="xl">{{ $collection->title }}</flux:heading>
                    <flux:subheading>
                        @if($collection->abbreviation && $collection->publisher)
                            {{ $collection->abbreviation }} &middot; {{ $collection->publisher }}
                        @elseif($collection->abbreviation)
                            {{ $collection->abbreviation }}
                        @elseif($collection->publisher)
                            {{ $collection->publisher }}
                        @endif
                    </flux:subheading>
                    @auth
                    <flux:button variant="ghost" icon="flag" wire:click="dispatch('openErrorReportModal', { resourceId: {{ $collection->id }}, resourceType: 'collection' })">
                        {{ __('Report an Issue') }}
                    </flux:button>
                    @endauth
                </div>
            </div>

            <div class="space-y-6">
                <!-- Description -->
                @if($collection->description)
                <div>
                    <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-2">{{ __('Description') }}</flux:heading>
                    <flux:text class="text-gray-700 dark:text-gray-300">{{ $collection->description }}</flux:text>
                </div>
                @endif

                <!-- Music pieces in this collection -->
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <flux:heading size="lg">{{ __('Music Pieces in this Collection') }}</flux:heading>
                        <flux:badge color="blue" size="lg">{{ $collection->music()->count() }}</flux:badge>
                    </div>

                    <!-- Search input -->
                    <div class="mb-6">
                        <flux:field>
                            <flux:input
                                type="search"
                                wire:model.live="search"
                                :placeholder="__('Search by title, subtitle, custom ID, or author...')"
                            />
                        </flux:field>
                    </div>
                    
                    @if($musics->isNotEmpty())
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-5">
                            @foreach($musics as $music)
                                <livewire:music-card :music="$music" :key="'music-card-'.$music->id" />
                            @endforeach
                        </div>

                        <!-- Pagination -->
                        <div class="mt-8">
                            {{ $musics->links() }}
                        </div>
                    @else
                        <flux:callout variant="secondary" icon="musical-note">
                            @if($search)
                                {{ __('No music pieces found matching your search.') }}
                            @else
                                {{ __('No music pieces are assigned to this collection yet.') }}
                            @endif
                        </flux:callout>
                    @endif
                </div>

            </div>

            <!-- Status bar -->
            <div class="mt-6 pt-3 border-t border-neutral-200 dark:border-neutral-700 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-neutral-500 dark:text-neutral-400">
                <flux:badge color="{{ $collection->is_private ? 'zinc' : 'green' }}" size="sm">
                    {{ $collection->is_private ? __('Private') : __('Public') }}
                </flux:badge>
                <span class="font-mono">#{{ $collection->id }}</span>
                <span>{{ __('Created by') }}: <span class="text-neutral-700 dark:text-neutral-300">{{ $collection->user?->display_name ?? '–' }}</span></span>
                <span>{{ __('Created') }}: <span class="text-neutral-700 dark:text-neutral-300">{{ $collection->created_at->translatedFormat('Y-m-d') }}</span></span>
                <span>{{ __('Updated') }}: <span class="text-neutral-700 dark:text-neutral-300">{{ $collection->updated_at->translatedFormat('Y-m-d') }}</span></span>
                @if($collection->photo_license)
                    <span>{{ __('Cover license') }}: <span class="text-neutral-700 dark:text-neutral-300">{{ $collection->photo_license }}</span></span>
                @endif
            </div>
        </flux:card>

        <!-- Actions (only for authenticated users) -->
        @auth
        <div class="mt-6 flex flex-col sm:flex-row gap-3">
            @can('update', $collection)
                <flux:button variant="primary" icon="pencil" wire:click="$dispatch('edit-collection', { collectionId: {{ $collection->id }} })">
                    {{ __('Edit Collection') }}
                </flux:button>
            @endcan

            @can('delete', $collection)
                <flux:button variant="danger" icon="trash"
                    wire:click="delete"
                    wire:confirm="{{ __('Are you sure you want to delete this collection? This can only be done if no music pieces are assigned to it.') }}">
                    {{ __('Delete Collection') }}
                </flux:button>
            @endcan
        </div>
        @endauth
    </div>

<livewire:pages.editor.collection-edit-modal />

    <x-action-message on="collection-updated">
        {{ __('Collection updated.') }}
    </x-action-message>

<livewire:error-report />
</div>