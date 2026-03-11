<div class="mx-auto p-4 sm:p-6">
    <!-- Filters card -->
    <div wire:ignore class="rounded-2xl border p-4 shadow-sm sm:p-5">
        <h2 class="text-base font-semibold">Zeneművek</h2>
        @if($renderFilters)
            @include('partials.music-browser-filters')
        @endif
    </div>

    <!-- Table card -->
    <div class="mt-4 rounded-2xl border shadow-sm">
        <div class="flex flex-col gap-2 border-b p-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-2">
                @can('mergeAny', \App\Models\Music::class)
                <flux:button
                    variant="filled"
                    icon="combine"
                    wire:click="merge"
                    :disabled="!$this->canMerge"
                    :title="$this->canMerge ? __('Merge selected songs') : __('Select exactly 2 songs to merge')">
                    {{ __('Merge Songs') }}
                </flux:button>
                @endcan
            </div>
        </div>

        <div class="overflow-x-auto p-4">
            @include('partials.music-browser-table', ['mode' => 'manage'])
        </div>
    </div>
</div>
