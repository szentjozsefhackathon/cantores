@props(['entryId' => null, 'payload' => null, 'title' => null, 'subtitle' => null])

@php
    use Illuminate\Support\Js;

    // Two ways in, one viewer. A row of the document is already in the payload
    // the browser holds, so it is fetched from there by id and costs nothing; a
    // score the document has not taken is nowhere in it, and arrives built by
    // the server. Everything after this line is the same for both.
    $source = $entryId !== null ? 'previewEntry('.(int) $entryId.')' : Js::from($payload);
@endphp

{{-- One row's score, looked at on its own.

     The engraving the people singing from the shared booklet get, asked for one
     row — see score-preview.js. It is drawn here rather than in a frame around
     the score's own page because that page draws onto a sheet of paper, and a
     sheet of paper inside a modal is a picture of A4 with the notes too small to
     read. What this shows has no paper in it: it takes the width the modal has
     and sets the music to it, at whatever size the person checking the row
     wants.

     The row is read out of the editor around it — previewEntry() and
     previewGeometry() are the booklet's and the deck's, and each answers for its
     own — so opening this costs the server nothing and can never show a score
     the editor has stopped showing. --}}
<div x-data="scorePreview({{ $source }}, previewGeometry())" class="flex min-h-0 flex-col">
    <div class="flex items-start gap-2">
        <div class="min-w-0 flex-1">
            <flux:heading size="lg" class="truncate">{{ $title }}</flux:heading>
            @if($subtitle)
                <flux:subheading class="truncate">{{ $subtitle }}</flux:subheading>
            @endif
        </div>

        {{-- The one knob worth having here, and the reader's own: how big. A
             percentage rather than a point size, because nobody opening this is
             choosing type — they are asking for it bigger. --}}
        <div class="flex shrink-0 items-center gap-0.5 rounded-lg border border-zinc-200 px-1 dark:border-zinc-700" x-show="draws">
            <flux:tooltip :content="__('Smaller')">
                <flux:button size="sm" variant="ghost" icon="minus" :aria-label="__('Smaller')"
                    x-on:click="nudgeZoom(-zoomStep)" x-bind:disabled="!canZoomOut" />
            </flux:tooltip>
            <span class="w-12 text-center text-xs tabular-nums text-zinc-500 dark:text-zinc-400" x-text="zoomPercent + '%'"></span>
            <flux:tooltip :content="__('Bigger')">
                <flux:button size="sm" variant="ghost" icon="plus" :aria-label="__('Bigger')"
                    x-on:click="nudgeZoom(zoomStep)" x-bind:disabled="!canZoomIn" />
            </flux:tooltip>
        </div>

        {{-- Kept in the row whether it is spinning or not, so a redraw does not
             shunt the heading sideways every time the size is nudged. --}}
        <span
            class="mt-2 flex w-4 shrink-0 items-center justify-center text-xs text-zinc-500 transition-opacity dark:text-zinc-400"
            role="status"
            x-bind:class="busy ? 'opacity-100' : 'opacity-0'"
            x-bind:aria-hidden="busy ? 'false' : 'true'"
        >
            <flux:icon name="loading" variant="micro" />
        </span>
    </div>

    <div data-score-preview-sheet class="relative mt-3 max-h-[70vh] overflow-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
        {{-- A deck's uploaded score travels as whole scanned pages rather than
             as the systems cut out of them, so those are shown as the pictures
             they are. --}}
        <template x-if="pages.length > 0">
            <div class="space-y-2 bg-white p-2">
                <template x-for="page in pages" :key="page.page">
                    <img x-bind:src="page.url" class="block w-full" alt="{{ $title }}" />
                </template>
            </div>
        </template>

        {{-- Filled by the browser, and kept out of Livewire's way: a round trip
             elsewhere on the page must not replace a drawing someone is in the
             middle of resizing. --}}
        <div x-ref="sheet" wire:ignore class="bg-white" x-bind:class="pages.length > 0 ? 'hidden' : ''"></div>

        <span
            class="pointer-events-none absolute left-1/2 top-4 flex -translate-x-1/2 items-center gap-1.5 rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 shadow-sm ring-1 ring-blue-200 dark:bg-blue-950 dark:text-blue-300 dark:ring-blue-900"
            role="status"
            x-show="!ready"
            x-cloak
        >
            <flux:icon name="loading" variant="micro" />
            {{ __('Laying out…') }}
        </span>
    </div>

    <flux:text x-show="failed" x-cloak class="mt-2 text-sm text-red-600 dark:text-red-400">
        {{ __('This score could not be drawn.') }}
    </flux:text>

    {{-- Off-screen but laid out: exsurge's chant lines are measured here, and a
         display:none element has no measurable box. --}}
    <div x-ref="measure" aria-hidden="true" class="pointer-events-none absolute -left-[10000px] top-0 w-[2400px] opacity-0"></div>
</div>
