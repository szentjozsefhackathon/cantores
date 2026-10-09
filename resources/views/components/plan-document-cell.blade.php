@props(['documents', 'type', 'plan'])

{{-- One celebration's booklets, or its decks, as a cell of that celebration's
     single row on the Booklets & Projections screen.

     A service nearly always has exactly one of each, so the cell is sized for
     one: a chip that opens it, and the button that makes another. The title
     is only spelled out when someone changed it from the default, because the
     default is the celebration and date the row already starts with. --}}
@php
    $isProjection = $type === 'projection';
    $icon = $isProjection ? 'presentation' : 'book-open';
    $defaultTitle = $isProjection ? \App\Models\Projection::titleFor($plan) : \App\Models\Booklet::titleFor($plan);
@endphp

<div class="relative flex min-w-0 flex-col gap-1 {{ $documents->isEmpty() ? '' : 'pe-8' }}">
    @foreach($documents as $document)
        <div wire:key="{{ $type }}-{{ $document->id }}" class="flex min-w-0 items-center gap-1">
            <a
                href="{{ $isProjection ? route('projections.edit', ['projection' => $document->id]) : route('booklets.edit', ['booklet' => $document->id]) }}"
                wire:navigate
                title="{{ $document->title }}"
                class="inline-flex min-w-0 items-center gap-1.5 rounded-md border border-zinc-200 px-2 py-1 text-sm hover:border-zinc-400 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:border-zinc-500 dark:hover:bg-zinc-800"
            >
                <flux:icon :name="$icon" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                @if($document->title !== $defaultTitle)
                    <span class="truncate font-medium">{{ $document->title }}</span>
                @endif
                <span class="shrink-0 text-xs text-zinc-500 dark:text-zinc-400">
                    {{ $isProjection ? $document->ratio->label() : $document->page_size->label() }} · {{ $document->entries_count }}
                </span>
            </a>

            @if($isProjection)
                <livewire:projection.send-to-screen :projection="$document" :compact="true" :key="'send-to-screen-'.$document->id" />
            @endif
        </div>
    @endforeach

    {{-- Labelled while the cell is empty, since then it is the cell's whole
         content; once there is one, a second is rare, so it shrinks to an icon
         beside the chips rather than another line under them. --}}
    <form method="POST" action="{{ route($isProjection ? 'projections.store' : 'booklets.store') }}" class="{{ $documents->isEmpty() ? '' : 'absolute end-0 top-1/2 -translate-y-1/2' }}">
        @csrf
        <input type="hidden" name="music_plan_id" value="{{ $plan->id }}">
        @if($documents->isEmpty())
            <flux:button
                type="submit"
                size="sm"
                variant="ghost"
                :icon="$isProjection ? 'presentation-plus' : 'book-plus'"
                class="text-zinc-500!"
            >
                {{ $isProjection ? __('Projection') : __('Booklet') }}
            </flux:button>
        @else
            <flux:tooltip :content="$isProjection ? __('New Projection') : __('New Booklet')">
                <flux:button
                    type="submit"
                    size="xs"
                    variant="ghost"
                    :icon="$isProjection ? 'presentation-plus' : 'book-plus'"
                    :aria-label="$isProjection ? __('New Projection') : __('New Booklet')"
                    class="text-zinc-400!"
                />
            </flux:tooltip>
        @endif
    </form>
</div>
