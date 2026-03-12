<div>
    @include('partials.music-browser-filters')

    @if($selectable)
    <div class="mt-4 flex justify-end">
        <livewire:music.quick-create-music-modal wire:key="quick-create-music" />
    </div>
    @endif

    <div class="mt-4 overflow-x-auto">
        @include('partials.music-browser-table', ['mode' => 'select'])
    </div>
</div>
