@props(['documents', 'type', 'heading', 'icon', 'empty'])

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
    <div class="mb-2 flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
        <flux:icon :name="$icon" variant="micro" class="shrink-0" />
        {{ $heading }}
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
                        <flux:tooltip :content="__('Present')">
                            <flux:button
                                size="xs"
                                variant="ghost"
                                icon="presentation"
                                :aria-label="__('Present')"
                                href="{{ route('projections.present', ['projection' => $document->id]) }}"
                            />
                        </flux:tooltip>
                    @endif

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
