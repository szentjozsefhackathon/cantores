@push('page-bundles')
resources/js/projection-editor.js
@endpush

@php
    use App\Enums\ProjectionRatio;
    use App\Enums\ProjectionTextTheme;
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
        'excluded' => $this->excluded,
        // A deck has no paper, and the preview that shows one of its scores on
        // its own is a page of music. See Booklet::previewGeometry().
        'previewGeometry' => \App\Models\Booklet::previewGeometry(),
        'overflowText' => __('This slide is fuller than the screen — make it smaller, or split it with a %pagebreak.'),
        'skipText' => __('Leave this slide out of the projection'),
        'unskipText' => __('Show this slide again'),
        'skippedText' => __('Skipped'),
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

        <x-plan-document-switcher :plan="$projection->musicPlan" :current="$projection" type="projection"
            :delete-confirm="__('Delete this projection? This cannot be undone.')" />

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

            {{-- How a screen of words is set — a text row, and a chord sheet,
                 which is words too. Engraved music is not offered: three engines
                 draw it in ink, and a staff reversed out of black is harder to
                 read across a nave rather than easier. --}}
            <div class="flex items-center gap-1">
                <flux:tooltip :content="__('Text slides')">
                    <flux:icon name="swatch" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                </flux:tooltip>
                <flux:select size="sm" wire:model.live="textTheme" :aria-label="__('Text slides')" class="w-36 text-xs">
                    @foreach(ProjectionTextTheme::cases() as $case)
                        <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            {{-- And how large those words are set, with the leading they are
                 stacked at. Deck wide, like the theme beside them; a row that
                 wants something else says so on its own panel. --}}
            <div class="flex items-center gap-1">
                <flux:tooltip :content="__('Text size (×)')">
                    <flux:icon name="document-text" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                </flux:tooltip>
                <flux:input size="sm" type="number" wire:model.live.debounce.500ms="textSizeScale" :aria-label="__('Text size (×)')" min="0.3" max="4" step="0.05" class="w-16!" />
            </div>

            <div class="flex items-center gap-1">
                <flux:tooltip :content="__('Text line spacing')">
                    <flux:icon name="align-vertical-space-between" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                </flux:tooltip>
                <flux:input size="sm" type="number" wire:model.live.debounce.500ms="textLineHeight" :aria-label="__('Text line spacing')" min="0.8" max="3" step="0.05" class="w-16!" />
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

                {{-- The way to a variation without laying every slide out
                     again by hand: a 4:3 version of a 16:9 deck, starting from
                     exactly where this one stands. --}}
                <flux:tooltip :content="__('Copy this deck')">
                    <flux:button size="sm" variant="ghost" icon="document-duplicate" wire:click="duplicate">
                        {{ __('Copy') }}
                    </flux:button>
                </flux:tooltip>

                {{-- Put this deck on a screen, without leaving the editor.
                     Opens one if none is up yet, points an existing one at
                     this deck otherwise — the only gesture the toolbar needs,
                     now that "Present" and "Open screen" said the same thing
                     two different confusing ways. --}}
                <livewire:projection.send-to-screen :projection="$projection" />
            </div>
        </div>

        <p class="mb-2 text-sm text-red-600 dark:text-red-400" x-show="message" x-cloak x-text="message"></p>

        {{-- The look at a score the deck has not taken.

             One modal for the whole pane rather than one per offered line: the
             pane lists every engraving of every music in the service, and a
             modal per line would be a hundred of them standing in the markup
             for the one that is ever opened. A row of the deck carries its
             own, because a row can answer for itself out of the payload the
             browser already holds — this one has to be built.

             It stands outside the split on purpose. <ui-modal> is an element
             the browser knows nothing about, so it is laid out like any other
             inline box — which inside the split's grid means a cell of its own,
             taken from the three the columns are cut into. Written between the
             panes, opening it pushed the handle into the preview's column and
             the preview onto a second row below the plan: the right column
             empty, the pages at the foot of the left one. Out here it has no
             column to take. --}}
        @if($showingScorePreview && $this->scorePreview !== null)
            <flux:modal wire:model="showingScorePreview" class="w-full max-w-3xl" data-offer-preview-modal>
                <x-score-preview :payload="$this->scorePreview" :title="$this->previewTitle" />
            </flux:modal>
        @endif

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
                        <span x-text="shownCount"></span> {{ __('slides') }}
                        <span x-show="slideCount > shownCount" x-cloak>
                            (<span x-text="slideCount - shownCount"></span> {{ __('skipped') }})
                        </span>
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
