<x-pages::admin.layout :heading="__('MusicPlan Imports')" :subheading="__('View records created with MusicPlanImport command')">
    <div class="space-y-6">
        <!-- Main Content -->
        <div class="space-y-6">
            <!-- Search and Filters -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:flex-1">
                    <flux:field class="w-full sm:w-auto sm:flex-1">
                        <flux:input
                            type="search"
                            wire:model.live="search"
                            :placeholder="__('Search by celebration info...')"
                        />
                    </flux:field>

                    <flux:field class="w-full sm:w-auto">
                        <flux:select
                            wire:model.live="sourceFileFilter"
                            :placeholder="__('All Source Files')"
                        >
                            <option value="">{{ __('All Source Files') }}</option>
                            @foreach ($sourceFiles as $sourceFile)
                                <option value="{{ $sourceFile }}">{{ $sourceFile }}</option>
                            @endforeach
                        </flux:select>
                    </flux:field>

                    <flux:button
                        variant="ghost"
                        icon="x"
                        wire:click="resetFilters"
                        :title="__('Reset filters')"
                    />
                </div>
            </div>

            <!-- Imports Table -->
            <flux:table>
                <flux:table.columns>
                    <flux:table.column
                        sortable
                        :sorted="$sortBy === 'source_file'"
                        :direction="$sortDirection"
                        wire:click="sort('source_file')"
                    >
                        {{ __('Source File') }}
                    </flux:table.column>
                    <flux:table.column>
                        {{ __('Items') }}
                    </flux:table.column>
                    <flux:table.column>
                        {{ __('Slots') }}
                    </flux:table.column>
                    <flux:table.column>
                        {{ __('Music Imports') }}
                    </flux:table.column>
                    <flux:table.column
                        sortable
                        :sorted="$sortBy === 'created_at'"
                        :direction="$sortDirection"
                        wire:click="sort('created_at')"
                    >
                        {{ __('Created At') }}
                    </flux:table.column>
                </flux:table.columns>
                
                <flux:table.rows>
                    @forelse ($imports as $import)
                        <flux:table.row
                            wire:click="selectImport({{ $import->id }})"
                            @class([
                                'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors',
                                'bg-blue-50 dark:bg-blue-900/20' => $selectedImportId === $import->id,
                            ])
                        >
                            <flux:table.cell>
                                <div class="font-medium">{{ $import->source_file }}</div>
                            </flux:table.cell>
                            
                            <flux:table.cell>
                                <div class="font-medium">{{ $import->import_items_count }}</div>
                            </flux:table.cell>
                            
                            <flux:table.cell>
                                <div class="font-medium">{{ $import->slot_imports_count }}</div>
                            </flux:table.cell>
                            
                            <flux:table.cell>
                                <div class="font-medium">
                                    {{ $import->importItems->sum('music_imports_count') }}
                                </div>
                            </flux:table.cell>
                            
                            <flux:table.cell>
                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ $import->created_at->format('Y-m-d H:i') }}
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5" class="text-center py-8">
                                <div class="flex flex-col items-center justify-center text-gray-500 dark:text-gray-400">
                                    <flux:icon name="document-text" class="h-12 w-12 mb-2 opacity-50" />
                                    <p class="text-lg font-medium">{{ __('No import records found') }}</p>
                                    <p class="text-sm mt-1">
                                        @if ($search || $sourceFileFilter)
                                            {{ __('Try adjusting your filters') }}
                                        @else
                                            {{ __('No musicplan import records have been added yet.') }}
                                        @endif
                                    </p>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>

            <!-- Pagination -->
            @if ($imports->hasPages())
                <div class="mt-4">
                    {{ $imports->links() }}
                </div>
            @endif
        </div>

        <!-- Detail Panel -->
        @if ($selectedImport)
            <div class="space-y-6">
                    <!-- Header -->
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold">{{ __('Details') }}</h3>
                        <flux:button
                            variant="ghost"
                            icon="x"
                            wire:click="deselectImport"
                            size="sm"
                        />
                    </div>

                    <!-- Import Info -->
                    <flux:card class="space-y-4">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Source File') }}</p>
                            <p class="font-medium break-all">{{ $selectedImport->source_file }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Created At') }}</p>
                            <p class="font-medium">{{ $selectedImport->created_at->format('Y-m-d H:i:s') }}</p>
                        </div>
                    </flux:card>

                    <!-- Import Items -->
                    <div>
                        <h4 class="font-semibold mb-3 flex items-center gap-2">
                            <flux:icon name="document" class="h-4 w-4" />
                            {{ __('Import Items') }} ({{ $importItems->count() }})
                        </h4>
                        <div class="space-y-2 max-h-64 overflow-y-auto">
                            @forelse ($importItems as $item)
                                <flux:card class="p-3 text-sm">
                                    <p class="font-medium">{{ $item->celebration_info }}</p>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">
                                        {{ $item->celebration_date?->format('Y-m-d') ?? __('No date') }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">
                                        {{ $item->music_imports_count }} {{ __('music imports') }}
                                    </p>
                                </flux:card>
                            @empty
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No import items') }}</p>
                            @endforelse
                        </div>
                    </div>

                    <!-- Slot Imports -->
                    <div>
                        <h4 class="font-semibold mb-3 flex items-center gap-2">
                            <flux:icon name="squares-2x2" class="h-4 w-4" />
                            {{ __('Slot Imports') }} ({{ $slotImports->count() }})
                        </h4>
                        <div class="space-y-2 max-h-64 overflow-y-auto">
                            @forelse ($slotImports as $slot)
                                <flux:card class="p-3 text-sm">
                                    <p class="font-medium">{{ $slot->name }}</p>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">
                                        {{ __('Column') }}: {{ $slot->column_number }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">
                                        {{ $slot->music_imports_count }} {{ __('music imports') }}
                                    </p>
                                </flux:card>
                            @empty
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No slot imports') }}</p>
                            @endforelse
                        </div>
                    </div>

                    <!-- Music Imports -->
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="font-semibold flex items-center gap-2">
                                <flux:icon name="musical-note" class="h-4 w-4" />
                                {{ __('Music Imports') }} ({{ $musicImports->total() }})
                            </h4>
                        <div class="flex items-center gap-1">
                            @if ($unmatchedCount > 0)
                                <flux:badge color="orange" size="sm">{{ $unmatchedCount }} {{ __('no match') }}</flux:badge>
                            @endif
                            @if ($suggestionCount > 0)
                                <flux:badge color="blue" size="sm">{{ $suggestionCount }} {{ __('suggestions') }}</flux:badge>
                                <flux:button
                                    size="xs"
                                    variant="filled"
                                    icon="arrow-path"
                                    wire:click="mergeAllSuggestions"
                                    wire:confirm="{{ __('This will automatically merge all suggestions. Are you sure?') }}"
                                    wire:loading.attr="disabled"
                                    wire:target="mergeAllSuggestions"
                                >
                                    <span wire:loading.remove wire:target="mergeAllSuggestions">{{ __('Merge all') }}</span>
                                    <span wire:loading wire:target="mergeAllSuggestions">{{ __('Merging…') }}</span>
                                </flux:button>
                            @endif
                        </div>
                        </div>

                        <!-- Filter Tabs -->
                        <div class="flex gap-2 mb-3">
                            <flux:button
                                size="sm"
                                :variant="$musicImportFilter === 'all' ? 'primary' : 'ghost'"
                                wire:click="setMusicImportFilter('all')"
                            >
                                {{ __('All') }}
                            </flux:button>
                            <flux:button
                                size="sm"
                                :variant="$musicImportFilter === 'unmatched' ? 'primary' : 'ghost'"
                                wire:click="setMusicImportFilter('unmatched')"
                            >
                                {{ __('No Match') }} ({{ $unmatchedCount }})
                            </flux:button>
                            <flux:button
                                size="sm"
                                :variant="$musicImportFilter === 'suggestions' ? 'primary' : 'ghost'"
                                wire:click="setMusicImportFilter('suggestions')"
                            >
                                {{ __('Merge Suggestions') }} ({{ $suggestionCount }})
                            </flux:button>
                        </div>

                        <div class="space-y-2">
                            @forelse ($musicImports as $music)
                                <flux:card
                                    class="p-3 text-sm"
                                    :class="$music->music_id ? '' : 'border-orange-300 dark:border-orange-700'"
                                >
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0 flex-1">
                                            <p class="font-medium">{{ $music->abbreviation ?? $music->label ?? __('N/A') }}</p>
                                            @if ($music->label && $music->abbreviation && $music->label !== $music->abbreviation)
                                                <p class="text-xs text-gray-600 dark:text-gray-400">
                                                    {{ __('Label') }}: {{ $music->label }}
                                                </p>
                                            @endif
                                            @if ($music->slotImport)
                                                <p class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                                                    📍 {{ __('Slot') }}: {{ $music->slotImport->name }}
                                                </p>
                                            @elseif ($music->musicPlanImportItem)
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                    📅 {{ $music->musicPlanImportItem->celebration_info }}
                                                </p>
                                            @endif
                                            @if ($music->merge_suggestion)
                                                <div class="mt-2 rounded bg-blue-50 dark:bg-blue-900/30 px-2 py-1">
                                                    <div class="flex items-center justify-between gap-2">
                                                        <div>
                                                            <p class="text-xs font-medium text-blue-700 dark:text-blue-300">
                                                                {{ __('Merge suggestion') }}:
                                                            </p>
                                                            <p class="text-xs text-blue-600 dark:text-blue-400">{{ $music->merge_suggestion }}</p>
                                                        </div>
                                                        <flux:button
                                                            size="xs"
                                                            variant="ghost"
                                                            icon="arrow-path"
                                                            wire:click="navigateToMerge({{ $music->id }})"
                                                            :title="__('Open in Music Merger')"
                                                        >
                                                            {{ __('Merge') }}
                                                        </flux:button>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="shrink-0">
                                            @if ($music->music_id)
                                                <flux:badge color="green" size="sm">✓ {{ __('Matched') }}</flux:badge>
                                            @else
                                                <flux:badge color="orange" size="sm">⚠ {{ __('No match') }}</flux:badge>
                                            @endif
                                        </div>
                                    </div>
                                </flux:card>
                            @empty
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No music imports') }}</p>
                            @endforelse
                        </div>

                        @if ($musicImports->hasPages())
                            <div class="mt-4">
                                {{ $musicImports->links() }}
                            </div>
                        @endif
                    </div>
            </div>
        @endif
    </div>
</x-pages::admin.layout>
