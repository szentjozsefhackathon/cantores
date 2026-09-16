@props(['documents', 'type', 'heading', 'icon', 'empty', 'plan' => null])

{{-- One half of a service's row on the Booklets & Projections screen: either the
     booklets made from that plan or the decks, whichever this column was handed.

     The two are listed the same way on purpose — a booklet and a projection are
     the same service seen from opposite sides, and the only thing that differs
     here is the badge the shape is written in and the projector button a deck
     gets. --}}
@php
    $isProjection = $type === 'projection';
@endphp

<div class="bg-white p-4 dark:bg-zinc-900">
    <div class="mb-2 flex items-center justify-between gap-1.5">
        <div class="flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
            <flux:icon :name="$icon" variant="micro" class="shrink-0" />
            {{ $heading }}
        </div>

        {{-- The plan is already the row; this is the one thing an empty
             column needs, put where the empty column is rather than in a
             shared toolbar a search away from it. --}}
        @if($plan)
            <form method="POST" action="{{ route($isProjection ? 'projections.store' : 'booklets.store') }}">
                @csrf
                <input type="hidden" name="music_plan_id" value="{{ $plan->id }}">
                <flux:tooltip :content="$isProjection ? __('New Projection') : __('New Booklet')">
                    <flux:button
                        type="submit"
                        size="xs"
                        variant="ghost"
                        :icon="$isProjection ? 'presentation-plus' : 'book-plus'"
                        :aria-label="$isProjection ? __('New Projection') : __('New Booklet')"
                    />
                </flux:tooltip>
            </form>
        @endif
    </div>

    @if($documents->isEmpty())
        <flux:text class="text-sm text-zinc-400 dark:text-zinc-500">{{ $empty }}</flux:text>
    @else
        <ul class="space-y-1">
            @foreach($documents as $document)
                <li wire:key="{{ $type }}-{{ $document->id }}" class="flex items-center gap-2">
                    <a
                        href="{{ $isProjection ? route('projections.edit', ['projection' => $document->id]) : route('booklets.edit', ['booklet' => $document->id]) }}"
                        wire:navigate
                        class="min-w-0 flex-1 truncate text-sm font-medium hover:underline"
                    >
                        {{ $document->title }}
                    </a>

                    <flux:badge color="zinc" size="sm">
                        {{ $isProjection ? $document->ratio->label() : $document->page_size->label() }}
                    </flux:badge>
                    <flux:badge color="zinc" size="sm">{{ $document->entries_count }}</flux:badge>

                    @if($isProjection)
                        <livewire:projection.send-to-screen :projection="$document" :compact="true" :key="'send-to-screen-'.$document->id" />
                    @endif

                    <flux:tooltip :content="__('Copy')">
                        <flux:button
                            size="xs"
                            variant="ghost"
                            icon="document-duplicate"
                            :aria-label="__('Copy')"
                            wire:click="{{ $isProjection ? 'duplicateProjection' : 'duplicateBooklet' }}({{ $document->id }})"
                        />
                    </flux:tooltip>

                    <flux:button
                        size="xs"
                        variant="ghost"
                        icon="trash"
                        :aria-label="__('Delete')"
                        wire:click="{{ $isProjection ? 'deleteProjection' : 'deleteBooklet' }}({{ $document->id }})"
                        wire:confirm="{{ $isProjection ? __('Delete this projection?') : __('Delete this booklet?') }}"
                    />
                </li>
            @endforeach
        </ul>
    @endif
</div>
