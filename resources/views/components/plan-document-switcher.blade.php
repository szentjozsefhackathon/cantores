@props(['plan' => null, 'current', 'type'])

{{-- The other documents of the same service, across the top of an editor.

     A plan is routinely made into several things at once — a booklet for the
     band, a second for the cantor, a 16:9 deck for the screen — and until this
     bar existed the only way from one of them to its siblings was back out to a
     list and in again, guessing from look-alike titles. So each editor says what
     else this service has, and one press goes there. --}}
@php
    $booklets = $plan?->booklets()->mine()->latest('updated_at')->get() ?? collect();
    $projections = $plan?->projections()->mine()->latest('updated_at')->get() ?? collect();
@endphp

@if($plan && ($booklets->count() + $projections->count()) > 1)
    <div class="mb-3 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm">
        <a href="{{ route('music-plan-view', ['musicPlan' => $plan->id]) }}" wire:navigate class="flex min-w-0 items-center gap-1.5 text-zinc-500 hover:underline dark:text-zinc-400">
            <flux:icon name="calendar" variant="micro" class="shrink-0" />
            <span class="truncate">{{ $plan->celebration_name ?: __('Untitled plan') }}</span>
        </a>

        <div class="flex flex-wrap items-center gap-1">
            @foreach($booklets as $booklet)
                @if($type === 'booklet' && $booklet->id === $current->id)
                    <flux:button size="xs" icon="book-open" variant="filled" disabled>{{ $booklet->title }}</flux:button>
                @else
                    <flux:button size="xs" icon="book-open" variant="ghost" wire:navigate
                        href="{{ route('booklets.edit', ['booklet' => $booklet->id]) }}">{{ $booklet->title }}</flux:button>
                @endif
            @endforeach

            @foreach($projections as $projection)
                @if($type === 'projection' && $projection->id === $current->id)
                    <flux:button size="xs" icon="presentation" variant="filled" disabled>{{ $projection->title }}</flux:button>
                @else
                    <flux:button size="xs" icon="presentation" variant="ghost" wire:navigate
                        href="{{ route('projections.edit', ['projection' => $projection->id]) }}">{{ $projection->title }}</flux:button>
                @endif
            @endforeach
        </div>

        <a href="{{ route('plan-documents') }}" wire:navigate class="ms-auto text-xs text-zinc-500 hover:underline dark:text-zinc-400">
            {{ __('All booklets & projections') }}
        </a>
    </div>
@endif
