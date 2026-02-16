<div>
    <!-- Search -->
    <flux:field class="w-full">
        <flux:input
            type="search"
            wire:model.live="search"
            :placeholder="__('Search music by title, subtitle, custom ID, collection abbreviation, order number, or page number...')"
        />
    </flux:field>

    <!-- Music Table -->
    @if($musics->count() > 0)
        <flux:table :paginate="$musics" class="mt-6">
            <flux:table.columns>
                <flux:table.column>{{ __('Title') }}</flux:table.column>
                <flux:table.column>{{ __('Collections') }}</flux:table.column>
                <flux:table.column>{{ __('Custom ID') }}</flux:table.column>
                <flux:table.column>{{ __('Genres') }}</flux:table.column>
                @if($selectable)
                    <flux:table.column class="w-20">{{ __('Select') }}</flux:table.column>
                @endif
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($musics as $music)
                    <flux:table.row>
                        <flux:table.cell>
                            <div class="max-w-80 text-wrap">
                                <div class="font-medium">{{ $music->title }}</div>
                                @if ($music->subtitle)
                                    <div class="text-sm text-gray-600 dark:text-gray-400">{{ $music->subtitle }}</div>
                                @endif
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="flex flex-wrap items-center gap-2">
                                @forelse ($music->collections as $collection)
                                    <flux:badge size="sm">
                                        {{ $collection->formatWithPivot($collection->pivot) }}
                                    </flux:badge>
                                @empty
                                    <span class="text-gray-400 dark:text-gray-500 text-sm">{{ __('None') }}</span>
                                @endforelse
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            @if ($music->custom_id)
                                <div class="font-mono text-sm text-gray-600 dark:text-gray-400">
                                    {{ $music->custom_id }}
                                </div>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                @forelse ($music->genres as $genre)
                                    <flux:icon
                                        name="{{ $genre->icon() }}"
                                        class="h-5 w-5 text-gray-600 dark:text-gray-400"
                                        :title="$genre->label()"
                                    />
                                @empty
                                    {{-- No genres --}}
                                @endforelse
                            </div>
                        </flux:table.cell>

                        @if($selectable)
                            <flux:table.cell>
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    icon="plus"
                                    wire:click="selectMusic({{ $music->id }})"
                                    wire:loading.attr="disabled"
                                    wire:loading.class="opacity-50 cursor-not-allowed"
                                    :title="__('Select')"
                                />
                            </flux:table.cell>
                        @endif
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

    @else
        <div class="mt-8 text-center">
            <flux:icon name="folder-open" class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" />
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('No music pieces found') }}</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $search ? __('Try a different search term.') : __('Get started by creating a new music piece.') }}
            </p>
        </div>
    @endif
</div>