{{-- Which deck to put up.

     A page of its own rather than a panel of the remote, so that going back from
     the control page is ordinary navigation. The remote used to be impossible to
     leave: its only way out led to a list that redirected straight back into the
     deck it had just come from. --}}
<div class="py-6">
    <div class="mx-auto max-w-2xl px-4">
        <div class="mb-3 flex items-center gap-2">
            <flux:button size="sm" variant="ghost" icon="arrow-left" href="{{ route('projection-remote') }}" wire:navigate>
                {{ __('Remote') }}
            </flux:button>
        </div>

        <flux:card class="p-4">
            <flux:heading size="xl">{{ __('Choose a deck') }}</flux:heading>
            <flux:subheading>{{ __('It goes up on every screen you have on, and this phone drives it from there.') }}</flux:subheading>

            @if($this->recents->isNotEmpty())
                <flux:heading size="sm" class="mt-5">{{ __('Recently shown') }}</flux:heading>

                <div class="mt-2 space-y-2">
                    @foreach($this->recents as $projection)
                        @include('livewire.pages.projection-remote-decks.deck', ['projection' => $projection, 'key' => 'recent'])
                    @endforeach
                </div>
            @endif

            @if($this->projections->isNotEmpty())
                @if($this->recents->isNotEmpty())
                    <flux:heading size="sm" class="mt-5">{{ __('All decks') }}</flux:heading>
                @endif

                <div @class(['space-y-2', 'mt-2' => $this->recents->isNotEmpty(), 'mt-5' => $this->recents->isEmpty()])>
                    @foreach($this->projections as $projection)
                        @include('livewire.pages.projection-remote-decks.deck', ['projection' => $projection, 'key' => 'all'])
                    @endforeach
                </div>
            @else
                <flux:callout variant="secondary" icon="presentation" class="mt-5 border-dashed">
                    <flux:callout.heading>{{ __('Nothing to project yet') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('Make a projection from a music plan first, and it will be here on Sunday.') }}
                    </flux:callout.text>
                </flux:callout>
            @endif
        </flux:card>

        <p class="mt-3 px-1 text-xs text-zinc-400">
            {{ __('The screen goes dark for a moment while it draws the new deck.') }}
        </p>
    </div>
</div>
