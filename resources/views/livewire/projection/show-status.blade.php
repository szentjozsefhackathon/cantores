{{-- Where the show is on.

     Read again when the remote's show read finds the walls changed, and only this line: the remote around it is never re-rendered.
     A screen that is not offered is left out, and so is this device unless
     its own wall is up. --}}
<div class="flex min-w-0 items-center gap-1 text-xs">
    @if($this->screens->isEmpty())
        <span class="size-2 shrink-0 rounded-full bg-zinc-400"></span>

        <flux:tooltip :content="__('On the computer the room will be reading from, sign in and open the projection screen.')">
            <span class="truncate text-amber-300">{{ __('No screen connected') }}</span>
        </flux:tooltip>
    @else
        @foreach($this->screens as $screen)
            <div class="flex min-w-0 items-center gap-1" wire:key="show-status-{{ $screen->id }}">
                <span
                    class="size-2 shrink-0 rounded-full"
                    x-bind:class="statusClass((screens || []).find((screen) => screen.id === {{ $screen->id }}) || {})"
                ></span>
                <span
                    class="truncate text-white/80"
                    x-text="`${@js($screen->label())}: ${screenStatus((screens || []).find((screen) => screen.id === {{ $screen->id }}) || {}).label}`"
                ></span>

                <button
                    type="button"
                    class="shrink-0 text-amber-200 underline"
                    x-show="['waiting', 'stale'].includes(screenStatus((screens || []).find((screen) => screen.id === {{ $screen->id }}) || {}).kind)"
                    x-on:click="retryScreenUpdate()"
                >{{ __('Retry screen update') }}</button>

                <livewire:projection.screen-settings :screen="$screen" :on-black="true" :key="'screen-settings-'.$screen->id" />
            </div>
        @endforeach
    @endif
</div>
