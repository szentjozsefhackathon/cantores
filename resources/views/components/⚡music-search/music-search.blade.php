<div>
    @include('partials.music-browser-filters')

    @if($selectable)
    <div class="mt-4 flex items-center justify-end gap-4">
        <flux:field variant="inline">
            <flux:checkbox wire:model.live="filterOwnMusics" wire:loading.attr="disabled" />
            <flux:label>Saját</flux:label>
        </flux:field>
        <livewire:music.quick-create-music-modal wire:key="quick-create-music" />
    </div>
    @endif

    <div class="mt-4 relative">
        <div class="overflow-x-auto transition-opacity duration-200" wire:loading.class="opacity-50 pointer-events-none">
            @include('partials.music-browser-table', ['mode' => 'select'])
        </div>

        <!-- Loading indicator -->
        <div class="absolute inset-0 flex items-center justify-center opacity-0 pointer-events-none transition-opacity duration-200" wire:loading.class="opacity-100">
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
