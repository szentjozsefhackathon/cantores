<div class="mx-auto p-2 sm:p-6">
    <!-- Filters card -->
    <div wire:ignore class="rounded-2xl border p-3 shadow-sm sm:p-5">
        <div class="flex items-center justify-between">
            <h2 class="text-base font-semibold">Zeneművek</h2>
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
        @if($renderFilters)
            @include('partials.music-browser-filters')
        @endif
    </div>

    <!-- Table card -->
    <div class="mt-2 rounded-2xl border shadow-sm sm:mt-4">
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

        <div class="overflow-x-auto p-2 sm:p-4">
            @include('partials.music-browser-table', ['mode' => 'manage'])
        </div>
    </div>
</div>
