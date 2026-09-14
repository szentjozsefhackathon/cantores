{{-- Which screen this phone is about to drive.

     Exactly one facing a room is the normal Sunday, and that case never reaches
     this page — the component sends the cantor straight to it. What is left is
     the half hour before the service, when the laptop has not been started yet
     and the only useful thing to say is so; and the chapel Sunday, when there
     are two and the choice is a real one. --}}
<div class="py-6">
    <div class="mx-auto max-w-2xl px-4">
        <flux:card class="p-4">
            <flux:heading size="xl">{{ __('Remote') }}</flux:heading>
            <flux:subheading>{{ __('Drive the screen the room is reading, so nobody has to stand at the laptop.') }}</flux:subheading>

            @if($this->screens->isNotEmpty())
                <div class="mt-5 space-y-2">
                    @foreach($this->screens as $screen)
                        <a
                            wire:key="screen-{{ $screen->id }}"
                            href="{{ route('projection-remote.control', ['screen' => $screen->id]) }}"
                            wire:navigate
                            class="flex items-center gap-3 rounded-lg border border-zinc-200 px-4 py-3 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                        >
                            <flux:icon.tv class="size-5 shrink-0 text-zinc-400" />

                            <div class="min-w-0 flex-1">
                                <div class="truncate font-medium">{{ $screen->describeDevice() }}</div>
                                <div class="truncate text-xs text-zinc-500">
                                    @if($screen->showing() !== null)
                                        {{ $screen->showing()->projection->title }}
                                    @else
                                        {{ __('Showing nothing') }}
                                    @endif
                                </div>
                            </div>

                            <flux:icon.chevron-right class="size-5 shrink-0 text-zinc-400" />
                        </a>
                    @endforeach
                </div>
            @else
                {{-- The one sentence this page exists to be able to say. Before
                     a screen was a thing at all, a phone opened before the
                     laptop was ready could only show an empty list of running
                     decks and leave the cantor guessing. --}}
                <flux:callout variant="secondary" icon="tv" class="mt-5 border-dashed">
                    <flux:callout.heading>{{ __('No screen is waiting yet') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('On the computer the room will be reading from, sign in and open the projection screen. It will appear here.') }}
                    </flux:callout.text>
                </flux:callout>
            @endif
        </flux:card>
    </div>
</div>
