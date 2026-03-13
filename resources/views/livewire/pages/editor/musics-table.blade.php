<div class="mx-auto w-full p-2 sm:p-6">
    <!-- Filters card -->
    <div wire:ignore class="rounded-2xl border p-3 shadow-sm sm:p-5">
        <div class="flex items-center justify-between">
            <h2 class="text-base font-semibold">Zeneművek</h2>
            <div class="flex items-center gap-4">
                <flux:field variant="inline">
                    <flux:checkbox wire:model.live="filterOwnMusics" wire:loading.attr="disabled" />
                    <flux:label>Saját</flux:label>
                </flux:field>
                @auth
                @can('create', \App\Models\Music::class)
                <div class="sm:hidden">
                    <flux:button
                        variant="primary"
                        icon="plus"
                        wire:click="$dispatch('open-create-music-modal')"
                        title="{{ __('Create Music Piece') }}" />
                </div>
                <div class="hidden sm:block">
                    <flux:button
                        variant="primary"
                        icon="plus"
                        wire:click="$dispatch('open-create-music-modal')">
                        {{ __('Create Music Piece') }}
                    </flux:button>
                </div>
                @endcan
                @endauth
            </div>
        </div>
        @if($renderFilters)
            @include('partials.music-browser-filters')
        @endif
    </div>

    <!-- Table card -->
    <div class="mt-2 rounded-2xl border shadow-sm sm:mt-4 relative">
        @can('mergeAny', \App\Models\Music::class)

        <div class="flex flex-col gap-2 border-b p-2 sm:p-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-2">
                <flux:button
                    variant="filled"
                    icon="combine"
                    wire:click="merge"
                    :disabled="!$this->canMerge"
                    :title="$this->canMerge ? __('Merge selected songs') : __('Select exactly 2 songs to merge')">
                    {{ __('Merge Songs') }}
                </flux:button>
            </div>
        </div>
        @endcan

        <div class="overflow-x-auto p-2 sm:p-4 transition-opacity duration-200" wire:loading.class="opacity-50 pointer-events-none">
            @include('partials.music-browser-table', ['mode' => 'manage'])
        </div>

        <!-- Loading indicator -->
        <div class="absolute inset-0 flex items-center justify-center rounded-2xl opacity-0 pointer-events-none transition-opacity duration-200" wire:loading.class="opacity-100">
            <div class="flex flex-col items-center gap-2 bg-white dark:bg-gray-800 rounded-lg p-4 shadow-lg">
                <div class="inline-flex items-center justify-center">
                    <svg class="animate-spin h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <span class="text-sm text-gray-600 dark:text-gray-300">{{ __('Loading') }}...</span>
            </div>
        </div>
    </div>
</div>
