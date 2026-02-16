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
        </div>

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
                    :sorted="$sortBy === 'order_number'"
                    :direction="$sortDirection"
                    wire:click="sort('order_number')"
                >
                    {{ __('Order Number') }}
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
                            <div class="font-medium">{{ $import->order_number }}</div>
                        </flux:table.cell>
                        
                        <flux:table.cell>
                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                {{ $import->created_at->format('Y-m-d H:i') }}
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center py-8">
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
