{{-- Where the show is on.

     Read again when the remote's show read finds the walls changed, and only this line: the remote around it is never re-rendered.
     A screen that is not offered is left out, and so is this device unless
     its own wall is up. --}}
<div class="flex min-w-0 items-center gap-1 text-xs">
    @if($this->screens->isEmpty())
        <flux:icon.cast class="size-4 shrink-0 text-amber-300" />

        <flux:tooltip :content="__('On the computer the room will be reading from, sign in and open the projection screen.')">
            <span class="truncate text-amber-300">{{ __('No screen connected') }}</span>
        </flux:tooltip>
    @else
        <flux:icon.cast class="size-4 shrink-0 text-white/60" />

        <span class="shrink-0 text-white/60">{{ __('On:') }}</span>

        @foreach($this->screens as $screen)
            <div class="flex min-w-0 items-center" wire:key="show-status-{{ $screen->id }}">
                <span class="truncate text-white/80">{{ $screen->label() }}</span>

                <livewire:projection.screen-settings :screen="$screen" :on-black="true" :key="'screen-settings-'.$screen->id" />
            </div>
        @endforeach
    @endif
</div>
