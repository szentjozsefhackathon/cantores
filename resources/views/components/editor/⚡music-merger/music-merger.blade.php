<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <div class="mb-8">
        <flux:heading size="2xl">{{ __('Merge Music Pieces') }}</flux:heading>
        <flux:subheading>{{ __('Select two music pieces to merge them') }}</flux:subheading>
    </div>

    <!-- Action messages -->
    <div class="mb-4">
        <x-action-message on="music-merged" />
    </div>

    @if (!$showComparison)
    <!-- Selection Phase: Two-column layout -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Left Music Selection -->
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Left Music (Source A)') }}</flux:heading>
            @if ($leftMusic)
            <!-- Selected left music -->
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-gray-50 dark:bg-gray-800">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="font-bold text-lg">{{ $leftMusic->title }}</div>
                        @if ($leftMusic->subtitle)
                        <div class="text-gray-600 dark:text-gray-400">{{ $leftMusic->subtitle }}</div>
                        @endif
                        @if ($leftMusic->custom_id)
                        <div class="text-sm font-mono text-gray-500 dark:text-gray-500">{{ $leftMusic->custom_id }}</div>
                        @endif
                    </div>
                    <flux:button
                        variant="ghost"
                        size="sm"
                        icon="x-mark"
                        wire:click="$set('leftMusicId', null)"
                        :title="__('Clear selection')" />
                </div>
                <div class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    {{ __('Collections:') }} {{ $leftMusic->collections->count() }},
                    {{ __('Genres:') }} {{ $leftMusic->genres->count() }}
                </div>
            </div>
            @else
            <livewire:music-search selectable="true" source=".mergeLeftMusic" />

            @endif
        </div>

        <!-- Right Music Selection -->
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Right Music (Source B)') }}</flux:heading>
            <flux:field>
                <flux:label>{{ __('Search Music') }}</flux:label>
                <flux:input
                    type="search"
                    wire:model.live="rightSearch"
                    :placeholder="__('Search by title, subtitle, custom ID...')" />
            </flux:field>

            @if ($rightMusic)
            <!-- Selected right music -->
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-gray-50 dark:bg-gray-800">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="font-bold text-lg">{{ $rightMusic->title }}</div>
                        @if ($rightMusic->subtitle)
                        <div class="text-gray-600 dark:text-gray-400">{{ $rightMusic->subtitle }}</div>
                        @endif
                        @if ($rightMusic->custom_id)
                        <div class="text-sm font-mono text-gray-500 dark:text-gray-500">{{ $rightMusic->custom_id }}</div>
                        @endif
                    </div>
                    <flux:button
                        variant="ghost"
                        size="sm"
                        icon="x-mark"
                        wire:click="$set('rightMusicId', null)"
                        :title="__('Clear selection')" />
                </div>
                <div class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    {{ __('Collections:') }} {{ $rightMusic->collections->count() }},
                    {{ __('Genres:') }} {{ $rightMusic->genres->count() }}
                </div>
            </div>
            @else
                 <livewire:music-search selectable="true" source=".mergeRightMusic" />
            @endif
        </div>
    </div>

    <!-- Compare button (when both selected) -->
    @if ($leftMusic && $rightMusic)
    <div class="mt-8 text-center">
        <flux:button
            variant="primary"
            icon="compare"
            wire:click="compare">
            {{ __('Compare & Merge') }}
        </flux:button>
        <div class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            {{ __('Left music will be updated with merged data. Right music will be deleted after merge.') }}
        </div>
    </div>
    @endif
    @else
    <!-- Comparison Phase: Three-column layout -->
    <div class="mb-6">
        <flux:button
            variant="ghost"
            icon="arrow-left"
            wire:click="resetSelection">
            {{ __('Back to selection') }}
        </flux:button>
    </div>

    <div class="grid grid-cols-2 gap-6">
        <!-- Left Music Column -->
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Left Music') }}</flux:heading>
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                <div class="font-bold text-lg">{{ $leftMusic->title }}</div>
                @if ($leftMusic->subtitle)
                <div class="text-gray-600 dark:text-gray-400">{{ $leftMusic->subtitle }}</div>
                @endif
                @if ($leftMusic->custom_id)
                <div class="text-sm font-mono text-gray-500 dark:text-gray-500">{{ $leftMusic->custom_id }}</div>
                @endif
                <div class="mt-2 text-sm">
                    <div class="flex items-center gap-2">
                        @if ($leftMusic->is_private)
                        <flux:icon name="eye-slash" class="h-4 w-4" />
                        <span>{{ __('Private') }}</span>
                        @else
                        <flux:icon name="eye" class="h-4 w-4" />
                        <span>{{ __('Public') }}</span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Collections -->
            <div>
                <flux:heading size="md">{{ __('Collections') }}</flux:heading>
                <div class="space-y-2">
                    @forelse ($leftMusic->collections as $collection)
                    <div class="border border-gray-200 dark:border-gray-700 rounded p-2 text-sm">
                        <div class="font-medium">{{ $collection->title }} ({{ $collection->abbreviation }})</div>
                        <div class="text-gray-600 dark:text-gray-400">
                            {{ __('Page:') }} {{ $collection->pivot->page_number ?? '-' }},
                            {{ __('Order:') }} {{ $collection->pivot->order_number ?? '-' }}
                        </div>
                    </div>
                    @empty
                    <div class="text-gray-500 dark:text-gray-400 text-sm">{{ __('No collections') }}</div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Right Music Column -->
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Right Music') }}</flux:heading>
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                <div class="font-bold text-lg">{{ $rightMusic->title }}</div>
                @if ($rightMusic->subtitle)
                <div class="text-gray-600 dark:text-gray-400">{{ $rightMusic->subtitle }}</div>
                @endif
                @if ($rightMusic->custom_id)
                <div class="text-sm font-mono text-gray-500 dark:text-gray-500">{{ $rightMusic->custom_id }}</div>
                @endif
                <div class="mt-2 text-sm">
                    <div class="flex items-center gap-2">
                        @if ($rightMusic->is_private)
                        <flux:icon name="eye-slash" class="h-4 w-4" />
                        <span>{{ __('Private') }}</span>
                        @else
                        <flux:icon name="eye" class="h-4 w-4" />
                        <span>{{ __('Public') }}</span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Collections -->
            <div>
                <flux:heading size="md">{{ __('Collections') }}</flux:heading>
                <div class="space-y-2">
                    @forelse ($rightMusic->collections as $collection)
                    <div class="border border-gray-200 dark:border-gray-700 rounded p-2 text-sm">
                        <div class="font-medium">{{ $collection->title }} ({{ $collection->abbreviation }})</div>
                        <div class="text-gray-600 dark:text-gray-400">
                            {{ __('Page:') }} {{ $collection->pivot->page_number ?? '-' }},
                            {{ __('Order:') }} {{ $collection->pivot->order_number ?? '-' }}
                        </div>
                    </div>
                    @empty
                    <div class="text-gray-500 dark:text-gray-400 text-sm">{{ __('No collections') }}</div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Merged Data Column -->
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Merged Result') }}</flux:heading>

            <!-- Conflict Summary -->
            @if (count($conflicts) > 0)
            <div class="border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 bg-yellow-50 dark:bg-yellow-900/20">
                <flux:heading size="md" class="text-yellow-800 dark:text-yellow-300">
                    {{ __('Conflicts Detected') }} ({{ count($conflicts) }})
                </flux:heading>
                <ul class="mt-2 space-y-1 text-sm">
                    @foreach ($conflicts as $field => $conflict)
                    @if (str_starts_with($field, 'collection_'))
                    <li class="text-yellow-700 dark:text-yellow-400">
                        {{ __('Collection conflict:') }} {{ $conflict['collection']->title }}
                    </li>
                    @else
                    <li class="text-yellow-700 dark:text-yellow-400">
                        {{ __('Conflict on field:') }} {{ __(ucfirst(str_replace('_', ' ', $field))) }}
                    </li>
                    @endif
                    @endforeach
                </ul>
            </div>
            @endif

            <!-- Editable Merged Fields -->
            <div class="space-y-4">
                <flux:field>
                    <flux:label>{{ __('Title') }}</flux:label>
                    <flux:input
                        wire:model="mergedTitle"
                        :placeholder="__('Title')" />
                    @if (isset($conflicts['title']))
                    <flux:error>{{ __('Conflict: using left value') }}</flux:error>
                    @endif
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Subtitle') }}</flux:label>
                    <flux:input
                        wire:model="mergedSubtitle"
                        :placeholder="__('Subtitle')" />
                    @if (isset($conflicts['subtitle']))
                    <flux:error>{{ __('Conflict: using left value') }}</flux:error>
                    @endif
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Custom ID') }}</flux:label>
                    <flux:input
                        wire:model="mergedCustomId"
                        :placeholder="__('Custom ID')" />
                    @if (isset($conflicts['custom_id']))
                    <flux:error>{{ __('Conflict: using left value') }}</flux:error>
                    @endif
                </flux:field>

                <flux:field>
                    <flux:checkbox
                        wire:model="mergedIsPrivate"
                        :label="__('Make private (only visible to you)')" />
                    @if (isset($conflicts['is_private']))
                    <flux:error>{{ __('Conflict: set to public (false)') }}</flux:error>
                    @endif
                </flux:field>
            </div>

            <!-- Merged Collections Preview -->
            <div>
                <flux:heading size="md">{{ __('Merged Collections') }}</flux:heading>
                <div class="space-y-2 max-h-60 overflow-y-auto">
                    @forelse ($mergedCollections as $item)
                    <div class="border border-gray-200 dark:border-gray-700 rounded p-2 text-sm
                                {{ isset($item['conflict']) ? 'border-yellow-300 dark:border-yellow-700 bg-yellow-50 dark:bg-yellow-900/20' : '' }}">
                        <div class="font-medium">{{ $item['collection']->title }} ({{ $item['collection']->abbreviation }})</div>
                        <div class="text-gray-600 dark:text-gray-400">
                            {{ __('Page:') }} {{ $item['pivot']->page_number ?? '-' }},
                            {{ __('Order:') }} {{ $item['pivot']->order_number ?? '-' }}
                        </div>
                        @if (isset($item['conflict']))
                        <div class="text-xs text-yellow-600 dark:text-yellow-400 mt-1">
                            {{ __('Conflict: using left page/order') }}
                        </div>
                        @endif
                    </div>
                    @empty
                    <div class="text-gray-500 dark:text-gray-400 text-sm">{{ __('No collections') }}</div>
                    @endforelse
                </div>
            </div>

            <!-- Merge Action -->
            <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                    {{ __('After merging:') }}
                    <ul class="list-disc list-inside mt-1 space-y-1">
                        <li>{{ __('Left music will be updated with merged data') }}</li>
                        <li>{{ __('All references to right music will point to left') }}</li>
                        <li>{{ __('Right music will be deleted') }}</li>
                        <li>{{ __('You will become the owner of the merged music') }}</li>
                    </ul>
                </div>

                <flux:button
                    variant="primary"
                    icon="check"
                    wire:click="saveMerge"
                    wire:confirm="{{ __('Are you sure you want to merge these music pieces? This action cannot be undone.') }}"
                    class="w-full">
                    {{ __('Save Merge') }}
                </flux:button>
            </div>
        </div>
    </div>
    @endif
</div>