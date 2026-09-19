<div class="space-y-4">
    <div>
        <flux:heading size="md">{{ __('Exceptional Diatár binding') }}</flux:heading>
        <flux:text class="text-sm text-gray-600 dark:text-gray-400">{{ __('Use this only when collection references cannot identify the correct Diatár representation.') }}</flux:text>
    </div>

    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search book, song, or reference')" />

    <div class="max-h-48 space-y-1 overflow-y-auto rounded-lg border border-gray-200 p-2 dark:border-gray-700">
        @foreach($songs as $song)
            <button
                type="button"
                wire:key="diatar-binding-song-{{ $song->id }}"
                wire:click="selectSong({{ $song->id }})"
                class="block w-full rounded-md px-3 py-2 text-left text-sm {{ $selectedSongId === $song->id ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200' : 'hover:bg-gray-50 dark:hover:bg-gray-800' }}">
                <span class="font-medium">{{ $song->title }}</span>
                <span class="text-xs text-gray-500">— {{ $song->book->source_path }}</span>
            </button>
        @endforeach
    </div>

    @if($selectedSong)
        <div class="space-y-2">
            <flux:text class="text-sm font-medium">{{ $selectedSong->title }} — {{ $selectedSong->book->source_path }}</flux:text>
            @foreach($selectedSlideIds as $index => $slideId)
                @php($slide = $selectedSong->slides->firstWhere('id', $slideId))
                @if($slide)
                    <div wire:key="binding-slide-{{ $index }}" class="flex items-center gap-2 rounded-md bg-gray-50 px-3 py-2 dark:bg-gray-800">
                        <span class="min-w-0 flex-1 text-sm">{{ $slide->verse_name ?: __('Unnamed verse') }} <span class="font-mono text-xs text-gray-500">{{ $slide->external_id }}</span></span>
                        <flux:button size="xs" variant="ghost" wire:click="moveSlide({{ $index }}, -1)" :disabled="$index === 0">↑</flux:button>
                        <flux:button size="xs" variant="ghost" wire:click="moveSlide({{ $index }}, 1)" :disabled="$index === count($selectedSlideIds) - 1">↓</flux:button>
                        <flux:button size="xs" variant="ghost" wire:click="repeatSlide({{ $index }})">{{ __('Repeat') }}</flux:button>
                        <flux:button size="xs" variant="ghost" wire:click="removeSlide({{ $index }})">{{ __('Remove') }}</flux:button>
                    </div>
                @endif
            @endforeach

            <div class="flex flex-wrap gap-2">
                @foreach($selectedSong->slides as $slide)
                    <flux:button size="xs" variant="ghost" wire:click="addSlide({{ $slide->id }})">+ {{ $slide->verse_name ?: __('Unnamed verse') }}</flux:button>
                @endforeach
            </div>
        </div>
    @endif

    <flux:textarea wire:model="editorNote" :placeholder="__('Editor note (optional)')" rows="2" />
    <flux:error name="selectedSongId" />
    <flux:error name="selectedSlideIds" />
    <flux:error name="editorNote" />

    <div class="flex justify-end gap-2">
        @if($selectedSongId)
            <flux:button variant="ghost" wire:click="clear">{{ __('Disable override') }}</flux:button>
        @endif
        <flux:button variant="primary" wire:click="save" :disabled="$selectedSongId === null || $selectedSlideIds === []">{{ __('Save binding') }}</flux:button>
    </div>
</div>
