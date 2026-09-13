@push('page-bundles')
resources/js/projection-editor.js
@endpush

@php
    use App\Enums\ProjectionRatio;
@endphp

{{-- The deck is handed over in an attribute of its own rather than inside
     x-data, and x-data is left with nothing in it that ever changes.

     Alpine watches the DOM and re-runs any directive whose attribute it sees
     change, and Livewire rewrites this element on every round trip — so with the
     payload written into x-data, saving one knob would build the editor again
     from scratch and throw away the split, the slides and the measured render
     time. A data attribute is not a directive, so a morph may rewrite this one
     as often as it likes. Learned the hard way in the booklet editor. --}}
<div
    class="py-6 lg:flex lg:h-[calc(100vh-2rem)] lg:flex-col lg:py-0"
    data-projection-config="{{ json_encode([
        'geometry' => $this->geometry,
        'entries' => $this->renderPayload,
        'overflowText' => __('This slide is fuller than the screen — make it smaller, or split it with a %pagebreak.'),
    ]) }}"
    x-data="projectionEditor(JSON.parse($el.dataset.projectionConfig))"
    x-on:projection-updated.window="applyUpdate($event.detail)"
>
    {{-- abc2svg and exsurge draw two of the four formats, and both are globals
         rather than bundled modules. Loaded exactly as the score editor and the
         booklet editor load them, including the off-screen span abc2svg measures
         text with — it must exist before the library runs. --}}
    <script src="https://cdn.jsdelivr.net/gh/bbloomf/exsurge@v1.22.1/dist/exsurge.min.js"></script>
    <script>
        window.abc2svg = window.abc2svg || {};
        (function() {
            var el = document.createElement('span');
            el.style.cssText = 'position:absolute;top:-9999px;left:-9999px;visibility:hidden;white-space:nowrap;';
            document.body.appendChild(el);
            window.abc2svg.el = el;
        })();
    </script>
    <script src="{{ \App\Support\VendorAsset::url('js/abc2svg-1.js') }}"></script>

    <div class="mx-auto flex w-full max-w-[1600px] flex-col px-4 sm:px-6 lg:min-h-0 lg:flex-1 lg:px-8">

        {{-- The bar. Very short, and meant to be: a deck has a name and a shape,
             and everything else about how a slide looks was decided by whoever
             engraved the score, in the score editor, against this very canvas. --}}
        <div
            data-projection-toolbar
            class="mb-4 flex flex-wrap items-center gap-x-3 gap-y-2 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800/50"
            x-on:input="markBusy($event)"
        >
            {{-- The one knob here that leaves the slides exactly as they were, so
                 typing in it must not claim the deck is being drawn again. --}}
            <div class="flex items-center gap-1" data-projection-quiet>
                <flux:tooltip :content="__('Title')">
                    <flux:icon name="pencil" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                </flux:tooltip>
                <flux:input size="sm" wire:model.live.blur="title" :aria-label="__('Title')" class="w-56!" />
            </div>

            <flux:separator vertical class="h-6" />

            <div class="flex items-center gap-1">
                <flux:tooltip :content="__('Screen shape')">
                    <flux:icon name="proportions" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                </flux:tooltip>
                <flux:select size="sm" wire:model.live="ratio" :aria-label="__('Screen shape')" class="w-28 text-xs">
                    @foreach(ProjectionRatio::cases() as $case)
                        <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="ms-auto flex items-center gap-2">
                <span
                    class="flex items-center gap-1.5 rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-950 dark:text-blue-300"
                    role="status"
                    x-show="busy"
                    x-cloak
                >
                    <flux:icon name="loading" variant="micro" />
                    {{ __('Drawing…') }}
                </span>

                <flux:button
                    size="sm"
                    variant="primary"
                    icon="presentation"
                    href="{{ route('projections.present', ['projection' => $projection->id]) }}"
                    x-bind:disabled="slideCount === 0"
                >
                    {{ __('Present') }}
                </flux:button>
            </div>
        </div>

        <p class="mb-2 text-sm text-red-600 dark:text-red-400" x-show="message" x-cloak x-text="message"></p>

        <div
            class="projection-split grid gap-4 lg:min-h-0 lg:flex-1"
            wire:ignore.self
            x-bind:style="`--projection-split: ${splitPercent}%`"
            x-bind:class="splitDragging ? 'cursor-col-resize select-none' : ''"
        >

            {{-- Choosing --}}
            <div
                data-projection-pane="plan"
                class="space-y-4 lg:h-full lg:min-h-0 lg:overflow-y-auto lg:overscroll-contain lg:pe-1"
            >
                @include('livewire.pages.projection-editor.plan')
            </div>

            {{-- The handle owns a grid column of its own, so dragging it moves
                 nothing but the boundary the two panes share. --}}
            <div
                data-projection-handle
                wire:ignore.self
                role="separator"
                aria-orientation="vertical"
                aria-label="{{ __('Resize the preview') }}"
                aria-valuemin="20"
                aria-valuemax="80"
                x-bind:aria-valuenow="Math.round(splitPercent)"
                tabindex="0"
                class="group hidden touch-none select-none lg:flex lg:h-full lg:w-2 lg:cursor-col-resize lg:items-center lg:justify-center"
                x-on:pointerdown="startSplitDrag($event)"
                x-on:dblclick="resetSplit()"
                x-on:keydown.arrow-left.prevent="nudgeSplit(-2)"
                x-on:keydown.arrow-right.prevent="nudgeSplit(2)"
                x-on:keydown.home.prevent="resetSplit()"
            >
                <div class="h-16 w-1 rounded-full bg-zinc-200 transition-colors group-hover:bg-blue-500 group-focus:bg-blue-500 dark:bg-zinc-700 dark:group-hover:bg-blue-400 dark:group-focus:bg-blue-400"></div>
            </div>

            {{-- The slides --}}
            <flux:card class="relative flex flex-col p-4 lg:h-full lg:min-h-0">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <flux:subheading class="text-xs" x-show="slideCount > 0" x-cloak>
                        <span x-text="slideCount"></span> {{ __('slides') }}
                    </flux:subheading>
                </div>

                <div
                    data-projection-pane="slides"
                    class="lg:-mx-4 lg:min-h-0 lg:flex-1 lg:overflow-y-auto lg:overscroll-contain lg:px-4"
                >
                    {{-- Faded while the deck is being drawn again: a slide can be
                         re-engraved with barely a mark moving, and a preview that
                         never dims cannot say whether the change landed. --}}
                    <div
                        x-ref="slides"
                        class="projection-slides transition-opacity duration-200"
                        x-bind:class="busy ? 'opacity-40' : ''"
                        wire:ignore
                    ></div>
                </div>

                <flux:text class="text-sm text-zinc-500" x-show="!busy && slideCount === 0" x-cloak>
                    {{ __('Choose a score to see the slides.') }}
                </flux:text>
            </flux:card>
        </div>
    </div>
</div>
