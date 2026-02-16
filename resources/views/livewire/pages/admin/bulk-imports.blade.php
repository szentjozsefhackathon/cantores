<x-pages::admin.layout :heading="__('Bulk Imports')" :subheading="__('View records to be imported with BulkImports')">
    <div class="space-y-6">
        <!-- Search and Filters -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:flex-1">
                <flux:field class="w-full sm:w-auto sm:flex-1">
                    <flux:input
                        type="search"
                        wire:model.live="search"
                        :placeholder="__('Search by piece...')"
                    />
                </flux:field>

                <flux:field class="w-full sm:w-auto">
                    <flux:select
                        wire:model.live="collectionFilter"
                        :placeholder="__('All Collections')"
                    >
                        <option value="">{{ __('All Collections') }}</option>
                        @foreach ($collections as $collection)
                            <option value="{{ $collection }}">{{ $collection }}</option>
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

            <flux:button
                variant="primary"
                icon="music"
                wire:click="openCreateMusicDialog"
                :title="__('Create Music Pieces')"
            >
                {{ __('Create Music Pieces') }}
            </flux:button>
        </div>

        <!-- Create Music Dialog -->
        <flux:modal wire:model="showDialog" max-width="md">
            <flux:heading>{{ __('Create Music Pieces from Bulk Import') }}</flux:heading>
            
            <div class="mt-6 space-y-4">
                <flux:field>
                    <flux:label>{{ __('Batch Number') }}</flux:label>
                    <flux:select wire:model="selectedBatchNumber" required>
                        <option value="">{{ __('Select a batch') }}</option>
                        @foreach ($batchNumbers as $batch)
                            <option value="{{ $batch }}">{{ $batch }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="selectedBatchNumber" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Collection') }}</flux:label>
                    <flux:select wire:model="selectedCollectionId" required>
                        <option value="">{{ __('Select a collection') }}</option>
                        @foreach ($collectionList as $collection)
                            <option value="{{ $collection->id }}">{{ $collection->title }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="selectedCollectionId" />
                </flux:field>
            </div>
            
            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="closeDialog" wire:loading.attr="disabled">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="primary" wire:click="importMusic" wire:loading.attr="disabled" wire:loading.class="opacity-50 cursor-not-allowed">
                    <flux:icon name="music" class="mr-2" wire:loading.class="hidden" />
                    {{ __('Import') }}
                </flux:button>
            </div>
        </flux:modal>

        <!-- Imports Table -->
        <flux:table>
            <flux:table.columns>
                <flux:table.column
                    sortable
                    :sorted="$sortBy === 'collection'"
                    :direction="$sortDirection"
                    wire:click="sort('collection')"
                >
                    {{ __('Collection') }}
                </flux:table.column>
                <flux:table.column
                    sortable
                    :sorted="$sortBy === 'piece'"
                    :direction="$sortDirection"
                    wire:click="sort('piece')"
                >
                    {{ __('Piece') }}
                </flux:table.column>
                <flux:table.column
                    sortable
                    :sorted="$sortBy === 'reference'"
                    :direction="$sortDirection"
                    wire:click="sort('reference')"
                >
                    {{ __('Reference') }}
                </flux:table.column>
                <flux:table.column
                    sortable
                    :sorted="$sortBy === 'batch_number'"
                    :direction="$sortDirection"
                    wire:click="sort('batch_number')"
                >
                    {{ __('Batch Number') }}
                </flux:table.column>
                <flux:table.column>
                    {{ __('Created At') }}
                </flux:table.column>
            </flux:table.columns>
            
            <flux:table.rows>
                @forelse ($imports as $import)
                    <flux:table.row>
                        <flux:table.cell>
                            <div class="font-medium">{{ $import->collection }}</div>
                        </flux:table.cell>
                        
                        <flux:table.cell>
                            <div class="font-medium">{{ $import->piece }}</div>
                        </flux:table.cell>
                        
                        <flux:table.cell>
                            <div class="font-medium">{{ $import->reference }}</div>
                        </flux:table.cell>
                        
                        <flux:table.cell>
                            <div class="font-medium">{{ $import->batch_number }}</div>
                        </flux:table.cell>
                        
                        <flux:table.cell>
                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                {{ $import->created_at }}
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
                                    @if ($search || $collectionFilter)
                                        {{ __('Try adjusting your filters') }}
                                    @else
                                        {{ __('No bulk import records have been added yet.') }}
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
</x-pages::admin.layout>
