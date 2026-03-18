<div>
    <div x-data="{ open: false }" x-on:keydown.escape="open = false" class="relative">
        <div class="flex gap-2 items-end">
        <div class="flex-1 relative">
            <flux:heading size="sm">{{ __('Add Slot') }}</flux:heading>
            <flux:field variant="inline" class="mt-2 mb-2">
                <flux:checkbox
                    wire:model.live="filterExcludeExisting"
                    id="filter-exclude-existing" />
                <flux:label for="filter-exclude-existing">{{ __('Only show slots not yet added') }}</flux:label>
            </flux:field>
            <flux:field>
                <flux:input
                    type="text"
                    wire:model.live="slotSearch"
                    x-on:focus="open = true"
                    x-on:click.outside="open = false"
                    :placeholder="__('Type slot name (e.g., Gloria, Entrance)...')"
                    autocomplete="off" />
                <flux:error name="slotSearch" />
            </flux:field>

            <!-- Dropdown results -->
            <div x-show="open && ($wire.searchResults.length > 0 || ($wire.slotSearch.length >= 1 && $wire.searchResults.length === 0))"
                x-transition
                class="absolute z-10 mt-1 w-full bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                <div class="py-1">
                    @foreach($searchResults as $result)
                    <button
                        type="button"
                        wire:click="addSlotDirectly({{ $result['id'] }})"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-50 cursor-not-allowed"
                        class="w-full text-left px-4 py-2 hover:bg-neutral-100 dark:hover:bg-neutral-800 flex items-center justify-between">
                        <div>
                            <div class="font-medium">{{ $result['name'] }}</div>
                            <div class="text-sm text-neutral-600 dark:text-neutral-400">{{ $result['description'] ?: __('No description') }}</div>
                        </div>
                        <div class="relative h-4 w-4">
                            <flux:icon name="plus" class="h-4 w-4 text-neutral-900 dark:text-neutral-100" wire:loading.remove wire:target="addSlotDirectly" />
                            <flux:icon.loading class="h-4 w-4 text-neutral-900 dark:text-neutral-100 absolute inset-0" wire:loading wire:target="addSlotDirectly" />
                        </div>
                    </button>
                    @endforeach

                    @if(count($searchResults) === 0 && strlen($slotSearch) >= 1)
                    <button
                        type="button"
                        wire:click="createCustomSlotFromSearch"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-50 cursor-not-allowed"
                        class="w-full text-left px-4 py-2 hover:bg-neutral-100 dark:hover:bg-neutral-800 flex items-center justify-between">
                        <div>
                            <div class="font-medium">{{ __('New custom slot') }}: {{ $slotSearch }}</div>
                            <div class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Click to create') }}</div>
                        </div>
                        <div class="relative h-4 w-4">
                            <flux:icon name="plus" class="h-4 w-4 text-neutral-900 dark:text-neutral-100" wire:loading.remove wire:target="createCustomSlotFromSearch" />
                            <flux:icon.loading class="h-4 w-4 text-neutral-900 dark:text-neutral-100 absolute inset-0" wire:loading wire:target="createCustomSlotFromSearch" />
                        </div>
                    </button>
                    @endif
                </div>
            </div>
        </div>
        <div class="flex flex-col gap-2">
            <flux:button
                wire:click="showAllSlots"
                wire:loading.attr="disabled"
                wire:loading.class="opacity-50 cursor-not-allowed"
                icon="list-bullet"
                variant="outline"
                class="whitespace-nowrap">
                {{ __('All Slots') }}
            </flux:button>
        </div>
    </div>
    </div>

    <!-- All Slots Modal -->
    <flux:modal name="all-slots-modal" wire:close="closeAllSlotsModal" size="lg">
        <flux:heading size="lg">{{ __('All Available Slots') }}</flux:heading>

        <div class="mt-4">
            <flux:input
                wire:model.live="allSlotsSearch"
                :placeholder="__('Filter slots...')"
                icon="magnifying-glass"
                clearable
                autofocus />
        </div>

        <div class="mt-3 max-h-96 overflow-y-auto border border-neutral-200 dark:border-neutral-700 rounded-lg">
            @if(count($allSlots) > 0)
            <div class="divide-y divide-neutral-200 dark:divide-neutral-700">
                @foreach($allSlots as $slot)
                <button
                    type="button"
                    wire:click="addSlotDirectly({{ $slot['id'] }})"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-50 cursor-not-allowed"
                    class="w-full text-left p-4 hover:bg-neutral-50 dark:hover:bg-neutral-800 flex items-center justify-between">
                    <div class="flex-1">
                        <div class="font-medium">{{ $slot['name'] }}</div>
                        @if($slot['description'])
                        <div class="text-sm text-neutral-600 dark:text-neutral-400 mt-1">{{ $slot['description'] }}</div>
                        @else
                        <div class="text-sm text-neutral-400 dark:text-neutral-500 mt-1">{{ __('No description') }}</div>
                        @endif
                    </div>
                    <div class="relative h-5 w-5 ml-4">
                        <flux:icon name="plus" class="h-5 w-5 text-neutral-900 dark:text-neutral-100" wire:loading.remove wire:target="addSlotDirectly" />
                        <flux:icon.loading
                            class="h-5 w-5 text-neutral-900 dark:text-neutral-100 absolute inset-0"
                            wire:loading
                            wire:target="addSlotDirectly({{ $slot['id'] }})" />
                    </div>
                </button>
                @endforeach
            </div>
            @else
            <div class="p-8 text-center">
                <flux:icon name="inbox" class="h-12 w-12 text-neutral-400 mx-auto" />
                <flux:heading size="md" class="mt-4">{{ __('No available slots') }}</flux:heading>
                <flux:text class="mt-2 text-neutral-600 dark:text-neutral-400">
                    {{ __('All slots have already been added to the music plan.') }}
                </flux:text>
            </div>
            @endif
        </div>

        <div class="mt-6 flex justify-end gap-3">
            <flux:modal.close>
                <flux:button variant="outline">
                    {{ __('Close') }}
                </flux:button>
            </flux:modal.close>
            <flux:button
                wire:click="openCreateSlotModal"
                variant="primary"
                icon="plus">
                {{ __('Create Custom Slot') }}
            </flux:button>
        </div>
    </flux:modal>

    <!-- Create Custom Slot Modal -->
    <flux:modal name="create-slot-modal" wire:close="closeCreateSlotModal" size="md">
        <flux:heading size="lg">{{ __('Create Custom Slot') }}</flux:heading>

        <div class="mt-6 space-y-4">
            <flux:field>
                <flux:label>{{ __('Slot Name') }} *</flux:label>
                <flux:input
                    wire:model.live="newSlotName"
                    :placeholder="__('e.g., Intercession, Greeting, etc.')"
                    autofocus />
                <flux:error name="newSlotName" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Description (optional)') }}</flux:label>
                <flux:textarea
                    wire:model.live="newSlotDescription"
                    :placeholder="__('Brief description of the slot...')"
                    rows="3" />
                <flux:error name="newSlotDescription" />
            </flux:field>
        </div>

        <div class="mt-8 flex justify-end gap-3">
            <flux:modal.close>
                <flux:button variant="outline">
                    {{ __('Cancel') }}
                </flux:button>
            </flux:modal.close>
            <flux:button
                wire:click="createCustomSlot"
                variant="primary"
                icon="plus">
                <span wire:loading.remove wire:target="createCustomSlot">{{ __('Create') }}</span>
                <span wire:loading wire:target="createCustomSlot">{{ __('Processing...') }}</span>
            </flux:button>
        </div>
    </flux:modal>
</div>
