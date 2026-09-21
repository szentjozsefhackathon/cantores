{{-- Where the show is on.

     Read again when the remote's show read finds the walls changed, and only this line: the remote around it is never re-rendered.
     A screen that is not offered is left out, and so is this device unless
     its own wall is up.

     One light for the show, not one per wall, and the light is the whole of what
     it says. Two walls are two copies of the same picture, and the cantor
     glancing down between verses is asking one thing — is the room looking at
     what was pressed — so there is one answer, given by the worst of the walls
     and given in colour. The words for each colour are there to be hovered and
     to be read aloud by a screen reader, but they are never a line the phone
     has to spend room on. Only when a wall is behind does anything join the
     light, and that is the way to press it again rather than a sentence about
     it. --}}
<div class="flex min-w-0 items-center gap-1 text-xs">
    @if($this->screens->isEmpty())
        <span class="size-2 shrink-0 rounded-full bg-zinc-400"></span>

        <flux:tooltip :content="__('On the computer the room will be reading from, sign in and open the projection screen.')">
            <span class="truncate text-amber-300">{{ __('No screen connected') }}</span>
        </flux:tooltip>
    @else
        <button
            type="button"
            class="shrink-0 text-amber-200"
            x-show="showNeedsRetry"
            x-on:click="retryScreenUpdate()"
            aria-label="{{ __('Retry screen update') }}"
            title="{{ __('Retry screen update') }}"
        >
            <flux:icon.arrow-path class="size-3.5" />
        </button>

        <span
            class="size-2 shrink-0 rounded-full"
            x-bind:class="showStatusClass"
            x-bind:title="showStatus?.label"
            x-bind:aria-label="showStatus?.label"
            role="status"
        ></span>

        {{-- The pencil that names a wall, one per wall, and the wall's name on it
             rather than beside it: naming is done once per device and then never
             again, so it is worth a control and not worth a word. The name is
             put here and not by the pencil's own component, so that a rename
             made anywhere reaches this line — which is what it is re-read for. --}}
        @foreach($this->screens as $screen)
            <div
                class="flex min-w-0 items-center"
                wire:key="show-status-{{ $screen->id }}"
                title="{{ $screen->label() }}"
            >
                <livewire:projection.screen-settings :screen="$screen" :on-black="true" :key="'screen-settings-'.$screen->id" />
            </div>
        @endforeach
    @endif
</div>
