{{-- Which deck to put on the screen.

     A page of its own rather than a panel of the remote, so that going back from
     the control page is ordinary navigation. The remote used to be impossible to
     leave: its only way out led to a list that redirected straight back into the
     deck it had just come from. --}}
<div class="py-6">
    <div class="mx-auto max-w-2xl px-4">
        <div class="mb-3 flex items-center gap-2">
            <flux:button size="sm" variant="ghost" icon="arrow-left" href="{{ route('projection-remote.control', ['screen' => $screen->id]) }}" wire:navigate>
                {{ __('Remote') }}
            </flux:button>

            <span class="min-w-0 flex-1 truncate text-sm text-zinc-500">{{ $screen->describeDevice() }}</span>
        </div>

        <flux:card class="p-4">
            <flux:heading size="xl">{{ __('Choose a deck') }}</flux:heading>
            <flux:subheading>{{ __('It goes up on the screen the room is reading, and this phone drives it from there.') }}</flux:subheading>

            @if($this->projections->isNotEmpty())
                <div class="mt-5 space-y-2">
                    @foreach($this->projections as $projection)
                        @php($current = $this->showing?->projection_id === $projection->id)

                        <button
                            type="button"
                            wire:key="projection-{{ $projection->id }}"
                            wire:click="present({{ $projection->id }})"
                            @class([
                                'flex w-full items-center gap-3 rounded-lg border px-4 py-3 text-start',
                                'border-zinc-900 bg-zinc-50 dark:border-white dark:bg-zinc-800' => $current,
                                'border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800' => ! $current,
                            ])
                        >
                            <flux:icon.presentation class="size-5 shrink-0 text-zinc-400" />

                            <div class="min-w-0 flex-1">
                                <div class="truncate font-medium">{{ $projection->title }}</div>
                                <div class="text-xs text-zinc-500">{{ $projection->updated_at->diffForHumans() }}</div>
                            </div>

                            @if($current)
                                <span class="shrink-0 text-xs font-medium text-zinc-500">{{ __('On the screen') }}</span>
                            @endif

                            {{-- The deck has to be engraved before the room can
                                 see it, which is seconds rather than frames, so
                                 the screen goes black while it happens. Said here
                                 because the person tapping is the one person who
                                 cannot see the screen. --}}
                            <flux:icon.chevron-right class="size-5 shrink-0 text-zinc-400" wire:loading.remove wire:target="present({{ $projection->id }})" />
                            <flux:icon.loading class="size-5 shrink-0 text-zinc-400" wire:loading wire:target="present({{ $projection->id }})" />
                        </button>
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
