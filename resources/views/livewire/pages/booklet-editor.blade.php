@php
    use App\Enums\BookletOrientation;
    use App\Enums\BookletPageSize;
    use App\Support\BookletSettingFields;

    $entries = $this->entries;
    $chosen = $this->chosenScoreIds;
    $chosenFiles = $this->chosenFileIds;
    $sources = $this->entrySources;
@endphp

<div
    class="py-6"
    x-data="bookletEditor({
        geometry: @js($this->geometry),
        entries: @js($this->renderPayload),
        exportUrl: @js(route('booklets.export-pdf', ['booklet' => $booklet->id])),
        csrfToken: @js(csrf_token()),
        exportFailedText: @js(__('Could not generate the PDF.')),
    })"
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
    <script src="{{ asset('js/abc2svg-1.js') }}"></script>

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
                <flux:input size="sm" wire:model.blur="title" :aria-label="__('Title')" :placeholder="__('Title')" class="min-w-0 flex-1" />
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
                    @foreach(BookletSettingFields::fontOptions() as $font)
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

            <div class="flex items-center gap-1">
                <flux:tooltip :content="__('Titles')">
                    <flux:icon name="eye" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                </flux:tooltip>
                <flux:switch wire:model.live="showTitles" :aria-label="__('Titles')" />
            </div>

            <div class="ml-auto flex items-center gap-2">
                <span class="text-sm text-zinc-500 dark:text-zinc-400" x-show="pageCount > 0" x-cloak>
                    <span x-text="pageCount"></span> {{ __('pages') }}
                </span>

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
                <flux:button
                    size="sm"
                    variant="primary"
                    icon="arrow-down-tray"
                    :loading="true"
                    wire:target="exportPdf"
                    wire:ignore.self
                    x-on:click="exportPdf()"
                    x-bind:data-loading="busy || exporting ? '' : false"
                    x-bind:disabled="exporting || busy || pageCount === 0"
                >
                    {{ __('Download PDF') }}
                </flux:button>
            </div>
        </div>

        <p class="mb-4 text-sm text-red-600 dark:text-red-400" x-show="message" x-cloak x-text="message"></p>

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
                <flux:card class="p-4">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <flux:heading size="lg">{{ __('In this booklet') }}</flux:heading>
                        <flux:button size="sm" variant="ghost" icon="document-plus" wire:click="addText">
                            {{ __('Add text') }}
                        </flux:button>
                    </div>

                    @if($entries->isEmpty())
                        <flux:text class="text-sm text-zinc-500">{{ __('Nothing chosen yet. Pick scores from the plan below.') }}</flux:text>
                    @else
                        {{-- Every row is a component of its own, so that opening one
                             row's toolbar — or ticking one of its boxes — costs the
                             server that row rather than the whole plan. Livewire
                             leaves a component it has already drawn alone when the
                             page around it is drawn again, which is exactly what a
                             list of thirty scores needs and what this one used to
                             spend a second of every change ignoring. --}}
                        <ul class="booklet-entries space-y-1.5">
                            @foreach($entries as $entry)
                                @php $source = $entry->isText() ? null : $sources->get($entry->score_id); @endphp
                                <livewire:booklet.entry-row
                                    :entry="$entry"
                                    :score-url="$source['url'] ?? null"
                                    :incipit-url="$source['incipit_url'] ?? null"
                                    :writing="$this->openedTextId === $entry->id"
                                    :key="'entry-'.$entry->id"
                                />
                            @endforeach
                        </ul>
                    @endif
                </flux:card>

                {{-- The plan --}}
                <flux:card class="p-4">
                    <flux:heading size="lg" class="mb-3">
                        {{ $booklet->musicPlan ? __('From the plan') : __('No music plan') }}
                    </flux:heading>

                    @forelse($this->planSlots as $slot)
                        <div class="mb-3" wire:key="slot-{{ $slot['id'] }}">
                            <div class="mb-1 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                {{ $slot['name'] }}
                            </div>

                            @foreach($slot['assignments'] as $assignment)
                                <div class="mb-1.5 pl-2" wire:key="assignment-{{ $assignment['id'] }}">
                                    <div class="flex items-center gap-1.5 text-sm font-medium">
                                        <flux:icon name="music" variant="micro" class="shrink-0 text-indigo-400" />
                                        @if($assignment['music_id'])
                                            <a href="{{ route('music-view', $assignment['music_id']) }}" target="_blank" class="min-w-0 truncate hover:underline">
                                                {{ $assignment['music_title'] }}
                                            </a>
                                        @else
                                            <span class="min-w-0 truncate">{{ $assignment['music_title'] }}</span>
                                        @endif
                                    </div>

                                    @forelse($assignment['scores'] as $score)
                                        {{-- An uploaded score holding several files is not one thing
                                             to take or leave: the projection slide and the
                                             accompaniment are different music on the page. So where
                                             there is a choice, the score only names itself and each
                                             file is added on its own line. --}}
                                        @php
                                            $files = $score['files'] ?? [];
                                            $perFile = count($files) > 1;
                                            $isChosen = ! $perFile && in_array($score['id'], $chosen, true);
                                        @endphp
                                        <div
                                            class="flex items-center gap-2 py-0.5 pl-2 text-sm"
                                            wire:key="score-{{ $assignment['id'] }}-{{ $score['id'] }}"
                                        >
                                            @unless($perFile)
                                                <flux:tooltip :content="$isChosen ? __('In the booklet — click to take it out') : __('Add to the booklet')">
                                                    <flux:button
                                                        size="sm"
                                                        variant="ghost"
                                                        :icon="$isChosen ? 'check-circle' : 'plus'"
                                                        wire:click="toggleScore({{ $score['id'] }}, {{ $assignment['id'] }})"
                                                        class="shrink-0 {{ $isChosen ? '!text-green-600 dark:!text-green-400' : '' }}"
                                                        :disabled="! $score['in_booklets']"
                                                    />
                                                </flux:tooltip>
                                            @endunless
                                            <div class="min-w-0 flex-1">
                                                <span class="flex items-center gap-1.5 {{ $isChosen ? 'text-zinc-500' : '' }}">
                                                    <flux:icon name="file-music" variant="micro" class="shrink-0 text-zinc-400" />
                                                    @if($score['url'])
                                                        <a href="{{ $score['url'] }}" target="_blank" class="min-w-0 truncate hover:underline">{{ $score['title'] }}</a>
                                                    @else
                                                        <span class="min-w-0 truncate">{{ $score['title'] }}</span>
                                                    @endif
                                                </span>
                                                @if($score['incipit_url'])
                                                    <x-incipit-image
                                                        :src="$score['incipit_url']"
                                                        :alt="__('Incipit').' — '.$score['title']"
                                                        class="mt-1 max-w-full"
                                                        imgClass="max-h-24 max-w-full rounded bg-white object-contain"
                                                    />
                                                @endif
                                            </div>
                                            <flux:badge size="sm" color="zinc">{{ $score['format'] }}</flux:badge>
                                            @if(!$score['is_own'])
                                                <flux:tooltip :content="$score['owner_name']">
                                                    <flux:icon name="user" variant="micro" class="shrink-0 text-zinc-400" />
                                                </flux:tooltip>
                                            @endif
                                        </div>

                                        @if($perFile)
                                            @foreach($files as $file)
                                                @php $isFileChosen = in_array($file['id'], $chosenFiles, true); @endphp
                                                <div
                                                    class="flex items-center gap-2 py-0.5 pl-4 text-sm"
                                                    wire:key="score-file-{{ $assignment['id'] }}-{{ $file['id'] }}"
                                                >
                                                    <flux:tooltip :content="$isFileChosen ? __('In the booklet — click to take it out') : __('Add this file to the booklet')">
                                                        <flux:button
                                                            size="sm"
                                                            variant="ghost"
                                                            :icon="$isFileChosen ? 'check-circle' : 'plus'"
                                                            wire:click="toggleScore({{ $score['id'] }}, {{ $assignment['id'] }}, {{ $file['id'] }})"
                                                            class="shrink-0 {{ $isFileChosen ? '!text-green-600 dark:!text-green-400' : '' }}"
                                                        />
                                                    </flux:tooltip>
                                                    <span class="min-w-0 flex-1 truncate text-zinc-600 dark:text-zinc-400">
                                                        {{ $file['name'] }}
                                                    </span>
                                                </div>
                                            @endforeach
                                        @endif
                                    @empty
                                        <div class="pl-2 text-xs text-zinc-400">{{ __('No scores available') }}</div>
                                    @endforelse
                                </div>
                            @endforeach
                        </div>
                    @empty
                        <flux:text class="text-sm text-zinc-500">
                            {{ __('This booklet is not linked to a music plan.') }}
                        </flux:text>
                    @endforelse
                </flux:card>
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
