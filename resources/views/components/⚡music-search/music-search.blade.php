<div>
    <!-- Search and Filters -->
    <div class="flex flex-col gap-4 mb-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <flux:field class="w-full sm:flex-1">
                <flux:input
                    type="search"
                    wire:model.live="search"
                    :placeholder="__('Search music by title, subtitle, custom ID, collection abbreviation, order number, or page number...')"
                />
            </flux:field>
            <x-mary-choices placeholder="Mind" single wire:model="filter" :options="[
                ['id' => 'all', 'name' => __('All'), 'icon' => 'o-globe-alt'],
                ['id' => 'public', 'name' => __('Public only'), 'icon' => 'o-eye'],
                ['id' => 'private', 'name' => __('Private only'), 'icon' => 'o-eye-slash'],
                ['id' => 'mine', 'name' => __('My items only'), 'icon' => 'o-user'],
            ]" class="w-full sm:w-48">
                @scope('item', $option)
                            <x-mary-list-item :item="$option">
                                <x-slot:avatar>
                                    <x-mary-icon :name="$option['icon']" />
                                </x-slot:avatar>
                            </x-mary-list-item>
                @endscope
            </x-mary-choices>
        </div>

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <flux:field class="w-full sm:flex-1">
                <flux:input
                    type="search"
                    wire:model.live="collectionFreeText"
                    :placeholder="__('Filter by collection abbreviation, title, or order number...')"
                />
            </flux:field>
            <flux:field class="w-full sm:w-64">
                <flux:select wire:model.live="collectionFilter">
                    <option value="">{{ __('All Collections') }}</option>
                    @foreach ($this->collections as $collection)
                        <option value="{{ $collection->title }}">{{ $collection->title }} ({{ $collection->abbreviation }})</option>
                    @endforeach
                </flux:select>
            </flux:field>
        </div>
    </div>

    <!-- Music Table -->
    @if($musics->count() > 0)
        <flux:table :paginate="$musics">
            <flux:table.columns>
                <flux:table.column>{{ __('Title') }}</flux:table.column>
                <flux:table.column>{{ __('Collections') }}</flux:table.column>
                <flux:table.column>{{ __('Custom ID') }}</flux:table.column>
                <flux:table.column></flux:table.column>
                @if($selectable)
                    <flux:table.column></flux:table.column>
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