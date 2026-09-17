{{-- Put this deck on the screen, and stay where you are.

     Present takes over this window; this takes over the show. On a laptop with
     the projector as a second display those are opposite gestures, and the one
     that matters is this one: the window being worked in has to go on showing
     the music plan and the slides while the room reads the deck.

     It polls, slowly, because the label is a claim about other windows: a screen
     opened after this page loaded, or a deck put up from the phone, would
     otherwise leave the button saying something that stopped being true.
     Slowly, and only here — the presenter and the remote are forbidden this, and
     talk JSON instead so that no re-render can touch a picture the room is
     reading. --}}
<div wire:poll.15s>
    @if($this->screens->isEmpty())
        {{-- No screen on, so the useful thing is the way to make one. A new
             window rather than this one, because this one is the one being
             worked in — it is dragged onto the projector and put full screen.

             Opened on this deck rather than bare, because opening the presenter
             on a deck puts that deck up: one press, not two. --}}
        <flux:tooltip :content="__('Opens a window with this deck on it, to drag onto the projector.')">
            <flux:button
                size="{{ $compact ? 'xs' : 'sm' }}"
                variant="ghost"
                icon="cast"
                href="{{ $this->deckUrl() }}"
                target="{{ \App\Livewire\Projection\SendToScreen::WINDOW }}"
                :aria-label="__('Open a screen')"
            >
                {{ $compact ? '' : __('Open a screen') }}
            </flux:button>
        </flux:tooltip>
    @else
        @php($on = $this->isOnScreen)

        <flux:tooltip :content="$this->opensWindow
            ? __('Opens the screen window on this computer, with this deck on it.')
            : ($on ? __('The room is reading this deck.') : __('Puts this deck on every screen you have on.'))">
            <flux:button
                size="{{ $compact ? 'xs' : 'sm' }}"
                variant="{{ $on ? 'filled' : 'ghost' }}"
                icon="{{ $on ? 'check' : 'cast' }}"
                wire:click="putUp"
                :href="$this->opensWindow ? $this->deckUrl() : null"
                :target="$this->opensWindow ? \App\Livewire\Projection\SendToScreen::WINDOW : null"
                :aria-label="__('Put on screen')"
            >
                {{ $compact ? '' : ($on ? __('On the screen') : __('Put on screen')) }}
            </flux:button>
        </flux:tooltip>
    @endif
</div>
