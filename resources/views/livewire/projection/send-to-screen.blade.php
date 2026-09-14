{{-- Put this deck on the screen the room is reading, and stay where you are.

     Present takes over this window; this takes over the screen. On a laptop with
     the projector as a second display those are opposite gestures, and the one
     that matters is this one: the window being worked in has to go on showing
     the music plan and the slides while the room reads the deck.

     It polls, slowly, because the label is a claim about another window: a
     screen opened on the projector after this page loaded would otherwise leave
     the button saying no screen was waiting. Slowly, and only here — the
     presenter and the remote are forbidden this, and talk JSON instead so that
     no re-render can touch a picture the room is reading. --}}
<div wire:poll.15s>
    @if($this->screens->isEmpty())
        {{-- No screen yet, so the useful thing is the way to make one. A new
             window rather than this one, because this one is the one being
             worked in — it is dragged onto the projector and put full screen,
             and it is the same session, so it is already the screen this page
             will be addressing. --}}
        <flux:tooltip :content="__('Opens a window to drag onto the projector.')">
            <flux:button
                size="{{ $compact ? 'xs' : 'sm' }}"
                variant="ghost"
                icon="tv"
                href="{{ route('projection-screen') }}"
                target="{{ \App\Livewire\Projection\SendToScreen::WINDOW }}"
                :aria-label="__('Open a screen')"
            >
                {{ $compact ? '' : __('Open a screen') }}
            </flux:button>
        </flux:tooltip>
    @elseif($this->screens->count() === 1)
        @php($screen = $this->screens->first())
        @php($sent = $this->sentScreen !== null)
        {{-- This browser's own screen is opened rather than only aimed at: the
             window may have been closed, and the row outlives the window by five
             minutes. Both happen on one click, so a window already on the
             projector is simply brought forward with the deck on it. --}}
        @php($own = $this->isThisDevice($screen))

        <flux:tooltip :content="$own
            ? __('Opens the screen window on this computer, with this deck on it.')
            : ($sent ? __('The room is reading this deck.') : __(':device is waiting for a deck.', ['device' => $screen->label()]))">
            <flux:button
                size="{{ $compact ? 'xs' : 'sm' }}"
                variant="{{ $sent ? 'filled' : 'ghost' }}"
                icon="{{ $sent ? 'check' : 'tv' }}"
                wire:click="send({{ $screen->id }})"
                :href="$own ? $this->deckUrl() : null"
                :target="$own ? \App\Livewire\Projection\SendToScreen::WINDOW : null"
                :aria-label="__('Send to screen')"
            >
                {{ $compact ? '' : ($sent ? __('On the screen') : __('Send to screen')) }}
            </flux:button>
        </flux:tooltip>
    @else
        {{-- Two screens is the chapel Sunday, and then which one is a real
             question and worth asking. --}}
        <flux:dropdown position="bottom" align="end">
            <flux:button
                size="{{ $compact ? 'xs' : 'sm' }}"
                variant="{{ $this->sentScreen !== null ? 'filled' : 'ghost' }}"
                icon="tv"
                icon:trailing="chevron-down"
                :aria-label="__('Send to screen')"
            >
                {{ $compact ? '' : __('Send to screen') }}
            </flux:button>

            <flux:menu>
                @foreach($this->screens as $screen)
                    @php($own = $this->isThisDevice($screen))

                    <flux:menu.item
                        wire:key="send-screen-{{ $screen->id }}"
                        wire:click="send({{ $screen->id }})"
                        :href="$own ? $this->deckUrl() : null"
                        :target="$own ? \App\Livewire\Projection\SendToScreen::WINDOW : null"
                        icon="{{ $screen->showing()?->projection_id === $projection->id ? 'check' : 'tv' }}"
                    >
                        {{ $screen->label() }}
                    </flux:menu.item>
                @endforeach
            </flux:menu>
        </flux:dropdown>
    @endif
</div>
