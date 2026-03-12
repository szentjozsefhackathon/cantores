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

    <div class="mt-4 overflow-x-auto">
        @include('partials.music-browser-table', ['mode' => 'select'])
    </div>
</div>
