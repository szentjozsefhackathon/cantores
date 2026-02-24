<div>
    <!-- Search and Filters -->
    <div class="flex flex-col gap-4 mb-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:gap-2">
            <flux:field class="flex-1 min-w-0">
                <flux:input
                    type="search"
                    wire:model.live="search"
                    :placeholder="__('Title, subtitle, etc.')"
                />
            </flux:field>
            <x-mary-choices placeholder="Mind" single wire:model="filter" :options="[
                ['id' => 'all', 'name' => __('All'), 'icon' => 'lucide.layers'],
                ['id' => 'public', 'name' => __('Public'), 'icon' => 'lucide.globe'],
                ['id' => 'private', 'name' => __('Private'), 'icon' => 'lucide.globe-lock'],
                ['id' => 'mine', 'name' => __('My items'), 'icon' => 'o-user'],
            ]" class="w-full lg:w-48 flex-shrink-0">
                @scope('item', $option)
                            <x-mary-list-item :item="$option">
                                <x-slot:avatar>
                                    <x-mary-icon :name="$option['icon']" />
                                </x-slot:avatar>
                            </x-mary-list-item>
                @endscope
            </x-mary-choices>
        </div>

        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:gap-2">
            <flux:field class="flex-1 min-w-0">
                <flux:input
                    type="search"
                    wire:model.live.debounce.500ms="collectionFreeText"
                    autocomplete="off"
                    :placeholder="__('Filter by collection abbreviation, title, or order number...')"
                />
            </flux:field>
            <flux:field class="w-full lg:w-64 flex-shrink-0">
                <flux:select wire:model.live="collectionFilter">
                    <option value="">{{ __('All Collections') }}</option>
                    @foreach ($this->collections as $collection)
                        <option value="{{ $collection->title }}">{{ $collection->title }} ({{ $collection->abbreviation }})</option>
                    @endforeach
                </flux:select>
            </flux:field>
        </div>

        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:gap-2">
            <flux:field class="flex-1 min-w-0">
                <flux:input
                    type="search"
                    wire:model.live.debounce.500ms="authorFreeText"
                    autocomplete="off"
                    :placeholder="__('Filter by author name...')"
                />
            </flux:field>
            <flux:field class="w-full lg:w-64 flex-shrink-0">
                <flux:select wire:model.live="authorFilter">
                    <option value="">{{ __('All Authors') }}</option>
                    @foreach ($this->authors as $author)
                        <option value="{{ $author->name }}">{{ $author->name }}</option>
                    @endforeach
                </flux:select>
            </flux:field>
        </div>
    </div>

    <!-- Music Table -->
    @if($musics->count() > 0)
        <div class="overflow-x-auto">
            <flux:table :paginate="$musics">
                <flux:table.columns>
                    <flux:table.column>{{ __('Title') }}</flux:table.column>
                    <flux:table.column class="hidden md:table-cell">{{ __('Collections') }}</flux:table.column>
                    <flux:table.column class="hidden lg:table-cell">{{ __('Authors') }}</flux:table.column>
                    <flux:table.column class="hidden lg:table-cell">{{ __('Custom ID') }}</flux:table.column>
                    <flux:table.column class="hidden sm:table-cell"></flux:table.column>
                    @if($selectable)
                        <flux:table.column></flux:table.column>
                    @endif
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($musics as $music)
                        <flux:table.row>
                            <flux:table.cell>
                                <div class="max-w-xs sm:max-w-sm md:max-w-md text-wrap">
                                    <div class="font-medium text-sm sm:text-base">{{ $music->title }}</div>
                                    @if ($music->subtitle)
                                        <div class="text-xs sm:text-sm text-gray-600 dark:text-gray-400">{{ $music->subtitle }}</div>
                                    @endif
                                    
                                    <!-- Mobile: Collections (hidden on md+) -->
                                    <div class="md:hidden">
                                        <div class="flex flex-wrap items-center gap-1">
                                            @foreach ($music->collections as $collection)
                                                <flux:badge size="sm">
                                                    {{ $collection->formatWithPivot($collection->pivot) }}
                                                </flux:badge>
                                            @endforeach
                                        </div>
                                    </div>
                                    
                                    <!-- Mobile: Authors (hidden on lg+) -->
                                    <div class="lg:hidden">
                                        <div class="flex flex-wrap items-center gap-1">
                                            @foreach ($music->authors as $author)
                                                <flux:badge size="sm">
                                                    {{ $author->name }}
                                                </flux:badge>
                                            @endforeach
                                        </div>
                                    </div>
                                    
                                    <!-- Mobile: Custom ID (hidden on lg+) -->
                                    @if ($music->custom_id)
                                        <div class="lg:hidden">
                                            <div class="font-mono text-xs text-gray-600 dark:text-gray-400">
                                                {{ $music->custom_id }}
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </flux:table.cell>

                            <flux:table.cell class="hidden md:table-cell">
                                <div class="flex flex-wrap items-center gap-1 sm:gap-2">
                                    @forelse ($music->collections as $collection)
                                        <flux:badge size="sm">
                                            {{ $collection->formatWithPivot($collection->pivot) }}
                                        </flux:badge>
                                    @empty
                                        <span class="text-gray-400 dark:text-gray-500 text-xs sm:text-sm">{{ __('None') }}</span>
                                    @endforelse
                                </div>
                            </flux:table.cell>

                            <flux:table.cell class="hidden lg:table-cell">
                                <div class="flex flex-wrap items-center gap-1 sm:gap-2">
                                    @forelse ($music->authors as $author)
                                        <flux:badge size="sm">
                                            {{ $author->name }}
                                        </flux:badge>
                                    @empty
                                        <span class="text-gray-400 dark:text-gray-500 text-xs sm:text-sm">{{ __('None') }}</span>
                                    @endforelse
                                </div>
                            </flux:table.cell>

                            <flux:table.cell class="hidden lg:table-cell">
                                @if ($music->custom_id)
                                    <div class="font-mono text-xs sm:text-sm text-gray-600 dark:text-gray-400">
                                        {{ $music->custom_id }}
                                    </div>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell class="hidden sm:table-cell">
                                <div class="flex items-center gap-1 sm:gap-2">
                                    @forelse ($music->genres as $genre)
                                        <flux:icon
                                            name="{{ $genre->icon() }}"
                                            class="h-4 w-4 sm:h-5 sm:w-5 text-gray-600 dark:text-gray-400"
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
        </div>

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