@props(['plan' => null, 'current', 'type', 'deleteConfirm' => null])

{{-- The toolbar across the top of an editor: where this document's plan is,
     what its siblings are, and how to leave or delete it. All of it fits on
     one line — a plan may have many booklets and projections, so each kind
     collapses to a single button once there's more than one, opening a
     dropdown instead of spilling pills across several rows. --}}
@php
    $booklets = $plan?->booklets()->mine()->latest('updated_at')->get() ?? collect();
    $projections = $plan?->projections()->mine()->latest('updated_at')->get() ?? collect();
    $otherBooklets = $type === 'booklet' ? $booklets->reject(fn ($booklet) => $booklet->id === $current->id) : $booklets;
    $otherProjections = $type === 'projection' ? $projections->reject(fn ($projection) => $projection->id === $current->id) : $projections;
@endphp

@if($plan)
    <div class="mb-3 flex flex-wrap items-center gap-2 text-sm">
        <a href="{{ route('music-plan-view', ['musicPlan' => $plan->id]) }}" wire:navigate class="flex min-w-0 items-center gap-1.5 text-zinc-500 hover:underline dark:text-zinc-400">
            <flux:icon name="calendar" variant="micro" class="shrink-0" />
            <span class="truncate">{{ $plan->celebration_name ?: __('Untitled plan') }}</span>
        </a>

        @if($otherBooklets->count() === 1)
            <flux:button size="xs" icon="book-open" variant="ghost" wire:navigate
                href="{{ route('booklets.edit', ['booklet' => $otherBooklets->first()->id]) }}">{{ $otherBooklets->first()->title }}</flux:button>
        @elseif($otherBooklets->count() > 1)
            <flux:dropdown>
                <flux:button size="xs" icon="book-open" icon-trailing="chevron-down" variant="ghost">{{ __('Booklets') }}</flux:button>
                <flux:menu>
                    @foreach($otherBooklets as $booklet)
                        <flux:menu.item :href="route('booklets.edit', ['booklet' => $booklet->id])" wire:navigate>{{ $booklet->title }}</flux:menu.item>
                    @endforeach
                </flux:menu>
            </flux:dropdown>
        @endif

        @if($otherProjections->count() === 1)
            <flux:button size="xs" icon="presentation" variant="ghost" wire:navigate
                href="{{ route('projections.edit', ['projection' => $otherProjections->first()->id]) }}">{{ $otherProjections->first()->title }}</flux:button>
        @elseif($otherProjections->count() > 1)
            <flux:dropdown>
                <flux:button size="xs" icon="presentation" icon-trailing="chevron-down" variant="ghost">{{ __('Projections') }}</flux:button>
                <flux:menu>
                    @foreach($otherProjections as $projection)
                        <flux:menu.item :href="route('projections.edit', ['projection' => $projection->id])" wire:navigate>{{ $projection->title }}</flux:menu.item>
                    @endforeach
                </flux:menu>
            </flux:dropdown>
        @endif

        <div class="ms-auto flex items-center gap-3">
            <a href="{{ route('plan-documents') }}" wire:navigate class="text-xs text-zinc-500 hover:underline dark:text-zinc-400">
                {{ __('All booklets & projections') }}
            </a>

            @if($deleteConfirm)
                <flux:button size="xs" variant="danger" icon="trash" square :title="__('Delete')" wire:click="delete" wire:confirm="{{ $deleteConfirm }}" />
            @endif
        </div>
    </div>
@endif
