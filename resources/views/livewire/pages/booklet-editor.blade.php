@php
    use App\Enums\BookletImposition;
    use App\Enums\BookletOrientation;
    use App\Enums\BookletPageSize;
    use App\Support\BookletSettingFields;
@endphp

{{-- The booklet is handed over in an attribute of its own rather than inside
     x-data, and x-data is left with nothing in it that ever changes.

     Alpine watches the DOM and re-runs any directive whose attribute it sees
     change, and Livewire rewrites this element on every round trip — so with the
     payload written into x-data, saving one knob changed that attribute and
     built the editor again from scratch: a third layout of the booklet on top of
     the two already being run, and the split, the pages and the measured render
     time all back to where they started. A data attribute is not a directive, so
     a morph may rewrite this one as often as it likes. --}}
<div
    class="py-6"
    data-booklet-config="{{ json_encode([
        'geometry' => $this->geometry,
        'entries' => $this->renderPayload,
        'exportUrl' => route('booklets.export-pdf', ['booklet' => $booklet->id]),
        'impositions' => $this->impositions,
        'pageSize' => $this->pageSize,
        'csrfToken' => csrf_token(),
        'exportFailedText' => __('Could not generate the PDF.'),
    ]) }}"
    x-data="bookletEditor(JSON.parse($el.dataset.bookletConfig))"
    x-on:booklet-updated.window="applyUpdate($event.detail)"
>
    {{-- abc2svg and exsurge draw two of the four formats, and both are globals
         rather than bundled modules. Loaded exactly as the score editor loads
         them, including the off-screen span abc2svg measures text with — it must
         exist before the library runs. --}}
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

    {{-- Off-screen but laid out: exsurge's chant lines are measured here, and a
         display:none element has no measurable box. --}}
    <div x-ref="measure" aria-hidden="true" class="pointer-events-none absolute -left-[10000px] top-0 w-[2400px] opacity-0"></div>

    <div class="mx-auto max-w-[1600px] px-4 sm:px-6 lg:px-8">

        {{-- Geometry bar. Laid out as the score editor's setting toolbars are:
             every knob is its icon, and its name is in the tooltip, so a dozen
             of them fit on one line above a booklet rather than in a block of
             labelled fields as tall as the preview beside it. --}}
        <div
            data-booklet-toolbar
            class="mb-4 flex flex-wrap items-center gap-x-3 gap-y-2 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800/50"
            x-on:input="markBusy($event)"
        >
            {{-- The one knob on this bar that leaves the pages exactly as they
                 were, so typing in it must not claim the booklet is being laid
                 out again. --}}
            <div data-booklet-quiet class="flex min-w-48 flex-1 items-center gap-1">
                <flux:tooltip :content="__('Title')">
                    <flux:icon name="book-open-text" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                </flux:tooltip>
                <flux:input size="sm" wire:model.live.blur="title" :aria-label="__('Title')" :placeholder="__('Title')" class="min-w-0 flex-1" />
            </div>

            <div class="flex items-center gap-1">
                <flux:tooltip :content="__('Page size')">
                    <flux:icon name="proportions" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                </flux:tooltip>
                <flux:select size="sm" wire:model.live="pageSize" :aria-label="__('Page size')" class="w-24 text-xs">
                    @foreach(BookletPageSize::options() as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="flex items-center gap-1">
                <flux:tooltip :content="__('Orientation')">
                    <flux:icon name="rotate-cw-square" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                </flux:tooltip>
                <flux:select size="sm" wire:model.live="orientation" :aria-label="__('Orientation')" class="w-32 text-xs">
                    @foreach(BookletOrientation::options() as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="flex items-center gap-1">
                <flux:tooltip :content="__('Margin (mm)')">
                    <flux:icon name="scan" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                </flux:tooltip>
                <flux:input size="sm" type="number" wire:model.live.debounce.500ms="marginMm" :aria-label="__('Margin (mm)')" min="0" max="60" step="1" class="w-16!" />
            </div>

            <div class="flex items-center gap-1">
                <flux:tooltip :content="__('Lyric size (pt)')">
                    <flux:icon name="a-large-small" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                </flux:tooltip>
                <flux:input size="sm" type="number" wire:model.live.debounce.500ms="lyricSizePt" :aria-label="__('Lyric size (pt)')" min="5" max="24" step="0.5" class="w-16!" />
            </div>

            <div class="flex items-center gap-1">
                <flux:tooltip :content="__('Staff height (mm)')">
                    <flux:icon name="list-chevrons-up-down" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                </flux:tooltip>
                <flux:input size="sm" type="number" wire:model.live.debounce.500ms="staffHeightMm" :aria-label="__('Staff height (mm)')" min="2" max="20" step="0.5" class="w-16!" />
            </div>

            {{-- Format-specific, on a bar that is otherwise not. ABC reserves
                 this space above the first staff as well as between two of
                 them, so it is what stands between a heading and its music —
                 a booklet-wide decision rather than each score's. --}}
            <div class="flex items-center gap-1">
                <flux:tooltip :content="__('ABC staff separation')">
                    <flux:icon name="between-horizontal-start" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                </flux:tooltip>
                <flux:input size="sm" type="number" wire:model.live.debounce.500ms="abcStaffSep" :aria-label="__('ABC staff separation')" min="0" max="120" step="1" class="w-16!" />
            </div>

            <div class="flex items-center gap-1">
                <flux:tooltip :content="__('Text font')">
                    <flux:icon name="type-outline" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                </flux:tooltip>
                <flux:select size="sm" wire:model.live="textFont" :aria-label="__('Text font')" class="w-36 text-xs">
                    @foreach(BookletSettingFields::selectableFonts() as $font)
                        <flux:select.option value="{{ $font }}">{{ $font }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="flex items-center gap-1">
                <flux:tooltip :content="__('Heading size (×)')">
                    <flux:icon name="heading" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                </flux:tooltip>
                <flux:input size="sm" type="number" wire:model.live.debounce.500ms="headingScale" :aria-label="__('Heading size (×)')" min="0.5" max="2" step="0.05" class="w-16!" />
            </div>

            <div class="ml-auto flex items-center gap-2">
                <span class="text-sm text-zinc-500 dark:text-zinc-400" x-show="pageCount > 0" x-cloak>
                    <span x-text="pageCount"></span> {{ __('pages') }}
                </span>

                {{-- The other way out of a booklet, beside the PDF: the band
                     reads it on their own phones, live, and each of them sets
                     the size their own eyes want. It opens a dialog rather than
                     carrying its own fields, so the bar keeps its one quiet
                     field and opening it lays nothing out again. --}}
                <flux:modal.trigger name="booklet-share">
                    <flux:button size="sm" variant="ghost" icon="share">
                        {{ __('Share') }}
                    </flux:button>
                </flux:modal.trigger>

                {{-- The preview sits beside the bar on a wide screen and far
                     below it on a narrow one, so the news that the booklet is
                     being laid out again is carried here too — by the one control
                     that has to be out of use while it happens anyway. Flux draws
                     its spinner over the button's own contents rather than in
                     place of them, so nothing on the bar moves as it comes and
                     goes; the label is left alone for the same reason.

                     Whether it is spinning is decided here and nowhere else, so
                     the morph is kept off its attributes as it is off the
                     divider's: the server sends neither the spinner nor the
                     disabling, and a morph landing mid-layout would take both
                     away — leaving the button bright and clickable while the
                     pages it would export are still being laid out.

                     Here and nowhere else means the server too. A button that
                     says it can spin is wired by Flux to spin while Livewire is
                     busy, and one that names no action of its own is wired to
                     every action there is — so opening a score's toolbar, or
                     any other errand the page runs, set this spinning as though
                     a PDF were being made. Naming the export as its errand ends
                     that: the export never goes to the server, so nothing the
                     server does can claim this button again. --}}
                {{-- Three ways out, one button. What differs between them is not
                     the booklet but the paper it is printed on, which is the
                     printer's business rather than the editor's — so the sizes
                     live behind the button that starts the download instead of
                     as a fourth knob on the bar beside the page size, where they
                     would read as something the pages themselves depend on.

                     A booklet already laid out on A4 can only be printed one
                     page to the sheet, and those two items are disabled rather
                     than hidden: a menu that changes shape with the page size
                     hides the fact that the feature exists at all. --}}
                <flux:dropdown position="bottom" align="end">
                    <flux:button
                        size="sm"
                        variant="primary"
                        icon="arrow-down-tray"
                        icon-trailing="chevron-down"
                        :loading="true"
                        wire:target="exportPdf"
                        wire:ignore.self
                        x-bind:data-loading="busy || exporting ? '' : false"
                        x-bind:disabled="exporting || busy || pageCount === 0"
                    >
                        {{ __('Download PDF') }}
                    </flux:button>

                    <flux:menu>
                        <flux:menu.item
                            icon="document"
                            x-on:click="exportPdf('{{ BookletImposition::Full->value }}')"
                        >
                            {{ BookletImposition::Full->label() }}
                        </flux:menu.item>

                        <flux:menu.item
                            icon="view-columns"
                            x-on:click="exportPdf('{{ BookletImposition::TwoUp->value }}')"
                            x-bind:disabled="!imposes('{{ BookletImposition::TwoUp->value }}')"
                        >
                            {{ BookletImposition::TwoUp->label() }}
                        </flux:menu.item>

                        <flux:menu.item
                            icon="book-open"
                            x-on:click="exportPdf('{{ BookletImposition::Booklet->value }}')"
                            x-bind:disabled="!imposes('{{ BookletImposition::Booklet->value }}')"
                        >
                            {{ BookletImposition::Booklet->label() }}
                        </flux:menu.item>

                        <flux:menu.separator x-show="!imposes('{{ BookletImposition::Booklet->value }}')" x-cloak />

                        <p
                            class="px-2 py-1.5 text-xs text-zinc-500 dark:text-zinc-400"
                            x-show="!imposes('{{ BookletImposition::Booklet->value }}')"
                            x-cloak
                        >
                            {{ __('A4 pages are already the size of the paper. Choose A5 or A6 to print several to a sheet.') }}
                        </p>
                    </flux:menu>
                </flux:dropdown>
            </div>
        </div>

        <p class="mb-4 text-sm text-red-600 dark:text-red-400" x-show="message" x-cloak x-text="message"></p>

        {{-- Outside the geometry bar on purpose: a field typed into inside it
             would tell the preview it is being laid out again. --}}
        <flux:modal name="booklet-share" class="max-w-xl">
            <div x-data="{ copied: false }">
                <flux:heading size="lg">{{ __('Share with the band') }}</flux:heading>

                <flux:text class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('Anyone holding this link opens the booklet on their own phone — the booklet itself, not a copy of it, engraved to the width of their screen. They can set their own size and font without changing anything here, and they cannot download or edit it. Whatever you change is theirs on the next refresh.') }}
                </flux:text>

                <div class="mt-4" x-show="$wire.shareUrl" x-cloak>
                    <div class="flex items-center gap-2">
                        <flux:input readonly x-bind:value="$wire.shareUrl ?? ''" class="min-w-0 flex-1 font-mono text-sm" />
                        <flux:tooltip :content="__('Copy link')">
                            <flux:button
                                icon="clipboard"
                                variant="ghost"
                                :aria-label="__('Copy link')"
                                x-on:click="navigator.clipboard.writeText($wire.shareUrl).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                                x-bind:class="copied ? 'text-green-600' : ''"
                            />
                        </flux:tooltip>
                        <flux:tooltip :content="__('Recall the link')">
                            <flux:button
                                icon="trash"
                                variant="ghost"
                                :aria-label="__('Recall the link')"
                                wire:click="recallLoan"
                                wire:confirm="{{ __('Recall this link? Anyone still holding it will lose the booklet.') }}"
                            />
                        </flux:tooltip>
                    </div>
                </div>

                <div class="mt-4" x-show="!$wire.shareUrl">
                    <flux:button variant="primary" icon="link" wire:click="lendByLink">
                        {{ __('Create the link') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>

        {{-- items-start keeps the columns from stretching, which is what lets each
             one stick and scroll inside its own box instead of dragging the page.

             Where the boundary stands is the browser's business alone, so the
             server's idea of this element's attributes must not be allowed to
             land on it: a morph strips every attribute the incoming HTML does
             not carry, and the width Alpine wrote here is one of them. Without
             this, changing a page size — anything at all that goes to the server
             — put the divider back to the default while Alpine still believed it
             had been left where it was dragged. --}}
        <div
            class="booklet-split grid items-start gap-4"
            wire:ignore.self
            x-bind:style="`--booklet-split: ${splitPercent}%`"
            x-bind:class="splitDragging ? 'cursor-col-resize select-none' : ''"
        >

            {{-- Choosing --}}
            <div
                data-booklet-pane="plan"
                class="space-y-4 lg:sticky lg:top-4 lg:max-h-[calc(100vh-2rem)] lg:overflow-y-auto lg:overscroll-contain lg:pe-1"
            >
                @include('livewire.pages.booklet-editor.plan')
            </div>

            {{-- The handle owns a grid column of its own, so dragging it moves
                 nothing but the boundary the two panes share. Keyboard users move
                 it with the arrow keys; a double click puts it back. Where it
                 stands is announced from the browser, so a morph is kept off its
                 attributes for the same reason as the row's. --}}
            <div
                data-booklet-handle
                wire:ignore.self
                role="separator"
                aria-orientation="vertical"
                aria-label="{{ __('Resize the preview') }}"
                aria-valuemin="20"
                aria-valuemax="80"
                x-bind:aria-valuenow="Math.round(splitPercent)"
                tabindex="0"
                class="group hidden touch-none select-none lg:sticky lg:top-4 lg:flex lg:h-[calc(100vh-2rem)] lg:w-2 lg:cursor-col-resize lg:items-center lg:justify-center"
                x-on:pointerdown="startSplitDrag($event)"
                x-on:dblclick="resetSplit()"
                x-on:keydown.arrow-left.prevent="nudgeSplit(-2)"
                x-on:keydown.arrow-right.prevent="nudgeSplit(2)"
                x-on:keydown.home.prevent="resetSplit()"
            >
                <div class="h-16 w-1 rounded-full bg-zinc-200 transition-colors group-hover:bg-blue-500 group-focus:bg-blue-500 dark:bg-zinc-700 dark:group-hover:bg-blue-400 dark:group-focus:bg-blue-400"></div>
            </div>

            {{-- The pages --}}
            <flux:card class="relative flex flex-col p-4 lg:sticky lg:top-4 lg:max-h-[calc(100vh-2rem)]">
                {{-- The badge sits in the heading row rather than above the
                     sheets, so it stays put while the pages are scrolled. --}}
                <div class="mb-3 flex items-center justify-between gap-2">
                    <flux:heading>{{ __('Preview') }}</flux:heading>
                    <span
                        class="flex items-center gap-1.5 rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-950 dark:text-blue-300"
                        role="status"
                        x-show="busy"
                        x-cloak
                    >
                        <flux:icon name="loading" variant="micro" />
                        {{ __('Laying out…') }}
                    </span>
                </div>

                {{-- The negative margin gives the sheets' shadows room inside the
                     scroll box without narrowing them. --}}
                <div
                    data-booklet-pane="pages"
                    class="lg:-mx-4 lg:min-h-0 lg:flex-1 lg:overflow-y-auto lg:overscroll-contain lg:px-4"
                >
                    {{-- Faded while the layout is being redone: a booklet can be
                         typeset again with barely a mark moving, and a preview
                         that never dims cannot say whether the change landed or
                         nothing happened at all. --}}
                    <div
                        x-ref="pages"
                        class="booklet-pages transition-opacity duration-200"
                        x-bind:class="busy ? 'opacity-40' : ''"
                        wire:ignore
                    ></div>
                </div>

                <flux:text class="text-sm text-zinc-500" x-show="!busy && pageCount === 0" x-cloak>
                    {{ __('Choose a score to see the pages.') }}
                </flux:text>
            </flux:card>
        </div>
    </div>
</div>
