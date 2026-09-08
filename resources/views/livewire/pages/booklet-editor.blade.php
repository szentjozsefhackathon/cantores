@php
    use App\Enums\BookletOrientation;
    use App\Enums\BookletPageSize;
    use App\Livewire\Pages\BookletEditor;
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
        <div data-booklet-toolbar class="mb-4 flex flex-wrap items-center gap-x-3 gap-y-2 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800/50">
            <div class="flex min-w-48 flex-1 items-center gap-1">
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
                <flux:button
                    size="sm"
                    variant="primary"
                    icon="arrow-down-tray"
                    x-on:click="exportPdf()"
                    x-bind:disabled="exporting || rendering || pageCount === 0"
                >
                    <span x-show="!exporting">{{ __('Download PDF') }}</span>
                    <span x-show="exporting" x-cloak>{{ __('Generating…') }}</span>
                </flux:button>
            </div>
        </div>

        <p class="mb-4 text-sm text-red-600 dark:text-red-400" x-show="message" x-cloak x-text="message"></p>

        {{-- items-start keeps the columns from stretching, which is what lets each
             one stick and scroll inside its own box instead of dragging the page. --}}
        <div
            class="booklet-split grid items-start gap-4"
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
                        <ul class="space-y-1.5">
                            @foreach($entries as $index => $entry)
                                <li wire:key="entry-{{ $entry->id }}" class="space-y-1">
                                    @php $source = $entry->isText() ? null : $sources->get($entry->score_id); @endphp

                                    {{-- Where in the service this stands: the slot it fills and
                                         the music sung there. What is actually printed — the
                                         score — is the card beneath it. --}}
                                    <div data-entry-header class="flex items-start gap-1.5">
                                        <span class="w-5 shrink-0 pt-0.5 text-xs text-zinc-400">{{ $index + 1 }}.</span>

                                        @if($entry->isText())
                                            <span class="min-w-0 flex-1 truncate text-sm italic text-zinc-600 dark:text-zinc-300">
                                                {{ \Illuminate\Support\Str::limit(trim(strtok($entry->text ?? '', "\n")) ?: __('Empty text'), 40) }}
                                            </span>
                                            <flux:badge size="sm" color="zinc">{{ __('Text') }}</flux:badge>
                                        @else
                                            {{-- The music the plan asked for, or — for a score chosen
                                                 outside the plan — the one the score is of. --}}
                                            @php $music = $entry->assignment?->music ?? $entry->score?->music; @endphp
                                            <div class="flex min-w-0 flex-1 flex-wrap items-baseline gap-x-1.5">
                                                @if($entry->assignment?->musicPlanSlot?->name)
                                                    <span class="truncate text-sm font-medium">{{ $entry->assignment->musicPlanSlot->name }}</span>
                                                @endif

                                                @if($music)
                                                    <span class="inline-flex min-w-0 items-center gap-1 text-xs text-zinc-500 dark:text-zinc-400">
                                                        <flux:icon name="music" variant="micro" class="shrink-0 text-indigo-400" />
                                                        <a href="{{ route('music-view', $music) }}" target="_blank" class="min-w-0 truncate hover:underline">
                                                            {{ $music->title }}
                                                        </a>
                                                    </span>
                                                @endif
                                            </div>
                                        @endif
                                        <div class="ml-auto flex shrink-0 items-center gap-0.5">
                                            <flux:tooltip :content="__('Move up')">
                                                <flux:button size="sm" variant="ghost" icon="chevron-up" :aria-label="__('Move up')" wire:click="move({{ $entry->id }}, -1)" :disabled="$index === 0" />
                                            </flux:tooltip>
                                            <flux:tooltip :content="__('Move down')">
                                                <flux:button size="sm" variant="ghost" icon="chevron-down" :aria-label="__('Move down')" wire:click="move({{ $entry->id }}, 1)" :disabled="$index === $entries->count() - 1" />
                                            </flux:tooltip>
                                            <flux:tooltip :content="__('Remove')">
                                                <flux:button size="sm" variant="ghost" icon="x-mark" :aria-label="__('Remove')" wire:click="removeEntry({{ $entry->id }})" />
                                            </flux:tooltip>
                                        </div>
                                    </div>

                                    {{-- The score itself, in a card of its own: one music may be
                                         sung from any of several engravings, and the card is the
                                         one that was chosen — its name, its opening notes, and
                                         everything that can be done to it here. --}}
                                    <div data-entry-card class="ms-6 rounded-md border border-zinc-200 px-2 py-1.5 dark:border-zinc-700">
                                        @unless($entry->isText())
                                            <div class="flex items-center gap-1.5 text-sm">
                                                <flux:icon name="file-music" variant="micro" class="shrink-0 text-zinc-400" />
                                                @if($source['url'] ?? null)
                                                    <a href="{{ $source['url'] }}" target="_blank" class="min-w-0 truncate hover:underline">{{ $entry->score?->title }}</a>
                                                @else
                                                    <span class="min-w-0 truncate">{{ $entry->score?->title }}</span>
                                                @endif
                                                @if(trim((string) $entry->score?->variation_name) !== '')
                                                    <span class="min-w-0 shrink truncate text-xs text-zinc-400">· {{ $entry->score->variation_name }}</span>
                                                @endif
                                                @if($entry->scoreFile)
                                                    <span class="shrink-0 text-xs text-zinc-400">· {{ $entry->scoreFile->displayName() }}</span>
                                                @endif
                                                <flux:badge size="sm" color="zinc" class="ml-auto shrink-0">
                                                    {{ $entry->score?->format?->label() ?? __('File') }}
                                                </flux:badge>
                                            </div>
                                        @endunless

                                        {{-- The same incipit the plan below shows, so a row in the
                                             booklet is recognised by its opening notes rather than by
                                             a title that several arrangements share. --}}
                                        @if($source['incipit_url'] ?? null)
                                            <x-incipit-image
                                                data-entry-incipit
                                                :src="$source['incipit_url']"
                                                :alt="__('Incipit').' — '.$entry->score?->variationLabel()"
                                                class="mt-1 ms-5 max-w-full"
                                                imgClass="max-h-24 max-w-full rounded bg-white object-contain"
                                            />
                                        @endif

                                        <div data-entry-options class="mt-2 flex flex-wrap items-center gap-0.5 border-t border-zinc-100 pt-2 dark:border-zinc-800">
                                            <flux:tooltip :content="__('Start on a new page')">
                                                <flux:button
                                                    size="sm"
                                                    variant="ghost"
                                                    icon="scissors"
                                                    wire:click="toggleStartOnNewPage({{ $entry->id }})"
                                                    aria-pressed="{{ $entry->start_on_new_page ? 'true' : 'false' }}"
                                                    class="{{ $entry->start_on_new_page ? '!text-blue-600 dark:!text-blue-400' : '' }}"
                                                    :aria-label="__('Start on a new page')"
                                                />
                                            </flux:tooltip>

                                            @if($entry->isText())
                                                <flux:tooltip :content="__('Edit this text')">
                                                    <flux:button
                                                        size="sm"
                                                        variant="ghost"
                                                        icon="pencil-square"
                                                        wire:click="editText({{ $this->editingTextId === $entry->id ? 'null' : $entry->id }})"
                                                        class="{{ $this->editingTextId === $entry->id ? '!text-blue-600 dark:!text-blue-400' : '' }}"
                                                        aria-expanded="{{ $this->editingTextId === $entry->id ? 'true' : 'false' }}"
                                                        :aria-label="__('Edit this text')"
                                                    />
                                                </flux:tooltip>
                                            @else
                                                <flux:tooltip :content="__('Print the music title')">
                                                    <flux:button
                                                        size="sm"
                                                        variant="ghost"
                                                        icon="musical-note"
                                                        wire:click="toggleShowMusicTitle({{ $entry->id }})"
                                                        aria-pressed="{{ $entry->show_music_title ? 'true' : 'false' }}"
                                                        class="{{ $entry->show_music_title ? '!text-blue-600 dark:!text-blue-400' : '' }}"
                                                        :aria-label="__('Print the music title')"
                                                    />
                                                </flux:tooltip>

                                                <flux:tooltip :content="__('Print the variation name')">
                                                    <flux:button
                                                        size="sm"
                                                        variant="ghost"
                                                        icon="tag"
                                                        wire:click="toggleShowVariation({{ $entry->id }})"
                                                        aria-pressed="{{ $entry->show_variation ? 'true' : 'false' }}"
                                                        class="{{ $entry->show_variation ? '!text-blue-600 dark:!text-blue-400' : '' }}"
                                                        :aria-label="__('Print the variation name')"
                                                    />
                                                </flux:tooltip>

                                                {{-- An uploaded score is a picture by the time it reaches a
                                                     booklet, so it gets a panel with the one knob a picture
                                                     has; an engraved one gets its format's. --}}
                                                <flux:tooltip :content="__('Adjust this score')">
                                                    <flux:button
                                                        size="sm"
                                                        variant="ghost"
                                                        icon="adjustments-horizontal"
                                                        wire:click="editSettings({{ $this->editingEntryId === $entry->id ? 'null' : $entry->id }})"
                                                        class="{{ $entry->settings_override ? '!text-blue-600 dark:!text-blue-400' : '' }}"
                                                        aria-expanded="{{ $this->editingEntryId === $entry->id ? 'true' : 'false' }}"
                                                        :aria-label="__('Adjust this score')"
                                                    />
                                                </flux:tooltip>
                                            @endif

                                        </div>

                                        {{-- Both panels open inside the row they belong to: what is
                                             being adjusted is right above the controls adjusting it,
                                             and a list of twenty scores does not have to be scrolled
                                             to the end to find out which one is being talked about. --}}
                                        @if($entry->isText() && $this->editingTextId === $entry->id)
                                            <div class="mt-2 border-t border-zinc-200 pt-2 dark:border-zinc-700">
                                                <flux:textarea
                                                    rows="6"
                                                    wire:model.live.debounce.600ms="editingText"
                                                    :placeholder="__('Stand. The cantor sings the verses, **all** repeat the antiphon.')"
                                                />

                                                <flux:text class="mt-2 text-xs text-zinc-500">
                                                    {{ __('Markdown: # heading, **bold**, *italic*, - list, > quote.') }}
                                                </flux:text>
                                            </div>
                                        @endif

                                        {{-- Laid out as the score editor's toolbar for the same
                                             format, control for control: a knob is its icon, its
                                             name is the tooltip, and it turns blue once this
                                             booklet has moved it away from the score's own value. --}}
                                        @if(! $entry->isText() && $this->editingEntryId === $entry->id)
                                            @php $panelFormat = BookletEditor::overrideFormat($entry); @endphp
                                            <div
                                                class="mt-2 border-t border-zinc-200 pt-2 dark:border-zinc-700"
                                                data-booklet-panel="{{ $entry->id }}"
                                            >
                                                <flux:text class="mb-2 text-xs text-zinc-500">
                                                    @if($panelFormat === 'file')
                                                        {{ __('Changes here apply to this booklet only. An uploaded score is printed at the full width of the page; make it smaller where that is too big.') }}
                                                    @else
                                                        {{ __('Changes here apply to this booklet only — the score itself is untouched. Widen a score to stop a line breaking; lower its staff height to stop a page breaking.') }}
                                                    @endif
                                                </flux:text>

                                                <div class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800/50">
                                                    @foreach(BookletSettingFields::panelFor($panelFormat) as $field)
                                                        <div class="flex items-center gap-1" wire:key="field-{{ $entry->id }}-{{ $field['key'] }}">
                                                            <flux:tooltip :content="$field['label']">
                                                                @if($field['glyph'] ?? null)
                                                                    <span
                                                                        class="shrink-0 text-xs font-bold text-zinc-500 dark:text-zinc-400"
                                                                        x-bind:class="isOverridden({{ $entry->id }}, '{{ $field['key'] }}') ? '!text-blue-600 dark:!text-blue-400' : ''"
                                                                    >{{ $field['glyph'] }}</span>
                                                                @else
                                                                    <flux:icon
                                                                        :name="$field['icon']"
                                                                        variant="micro"
                                                                        class="shrink-0 text-zinc-500 dark:text-zinc-400"
                                                                        x-bind:class="isOverridden({{ $entry->id }}, '{{ $field['key'] }}') ? '!text-blue-600 dark:!text-blue-400' : ''"
                                                                    />
                                                                @endif
                                                            </flux:tooltip>

                                                            @if($field['type'] === 'number')
                                                                <flux:input
                                                                    size="sm"
                                                                    type="number"
                                                                    :aria-label="$field['label']"
                                                                    min="{{ $field['min'] }}"
                                                                    max="{{ $field['max'] }}"
                                                                    step="{{ $field['step'] }}"
                                                                    class="w-16!"
                                                                    x-bind:value="settingsOf({{ $entry->id }})['{{ $field['key'] }}']"
                                                                    x-on:change="setOverride({{ $entry->id }}, '{{ $field['key'] }}', Number($event.target.value))"
                                                                />
                                                            @elseif($field['type'] === 'boolean')
                                                                <flux:switch
                                                                    :aria-label="$field['label']"
                                                                    x-bind:checked="!!settingsOf({{ $entry->id }})['{{ $field['key'] }}']"
                                                                    x-on:change="setOverride({{ $entry->id }}, '{{ $field['key'] }}', $event.target.checked)"
                                                                />
                                                            @else
                                                                <flux:select
                                                                    size="sm"
                                                                    class="w-36 text-xs"
                                                                    :aria-label="$field['label']"
                                                                    x-bind:value="settingsOf({{ $entry->id }})['{{ $field['key'] }}']"
                                                                    x-on:change="setOverride({{ $entry->id }}, '{{ $field['key'] }}', $event.target.value)"
                                                                >
                                                                    @foreach(BookletSettingFields::fontOptions() as $font)
                                                                        <flux:select.option value="'{{ $font }}'">{{ $font }}</flux:select.option>
                                                                    @endforeach
                                                                </flux:select>
                                                            @endif
                                                        </div>
                                                    @endforeach

                                                    <flux:tooltip :content="__('Back to the booklet defaults')">
                                                        <flux:button
                                                            size="sm"
                                                            variant="ghost"
                                                            icon="arrow-path"
                                                            class="shrink-0"
                                                            :aria-label="__('Back to the booklet defaults')"
                                                            x-on:click="resetOverride({{ $entry->id }})"
                                                        />
                                                    </flux:tooltip>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </li>
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
                 it with the arrow keys; a double click puts it back. --}}
            <div
                data-booklet-handle
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
                <flux:heading class="mb-3">{{ __('Preview') }}</flux:heading>
                <div
                    class="mb-2 flex items-center gap-1.5 text-sm text-zinc-500"
                    x-show="rendering"
                    x-cloak
                >
                    <flux:icon name="loading" variant="micro" />
                    {{ __('Laying out…') }}
                </div>

                {{-- The negative margin gives the sheets' shadows room inside the
                     scroll box without narrowing them. --}}
                <div
                    data-booklet-pane="pages"
                    class="lg:-mx-4 lg:min-h-0 lg:flex-1 lg:overflow-y-auto lg:overscroll-contain lg:px-4"
                >
                    <div x-ref="pages" class="booklet-pages" wire:ignore></div>
                </div>

                <flux:text class="text-sm text-zinc-500" x-show="!rendering && pageCount === 0" x-cloak>
                    {{ __('Choose a score to see the pages.') }}
                </flux:text>
            </flux:card>
        </div>
    </div>
</div>
