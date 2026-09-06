@php
    use App\Enums\BookletOrientation;
    use App\Enums\BookletPageSize;
    use App\Support\BookletSettingFields;

    $entries = $this->entries;
    $chosen = $this->chosenScoreIds;
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

        {{-- Geometry bar --}}
        <flux:card class="mb-4 p-4">
            <div class="flex flex-wrap items-end gap-x-4 gap-y-3">
                <flux:field class="min-w-56 flex-1">
                    <flux:label>{{ __('Title') }}</flux:label>
                    <flux:input wire:model.blur="title" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Page size') }}</flux:label>
                    <flux:select wire:model.live="pageSize" class="w-24">
                        @foreach(BookletPageSize::options() as $value => $label)
                            <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Orientation') }}</flux:label>
                    <flux:select wire:model.live="orientation" class="w-32">
                        @foreach(BookletOrientation::options() as $value => $label)
                            <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Margin (mm)') }}</flux:label>
                    <flux:input type="number" wire:model.live.debounce.500ms="marginMm" min="0" max="60" step="1" class="w-20!" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Lyric size (pt)') }}</flux:label>
                    <flux:input type="number" wire:model.live.debounce.500ms="lyricSizePt" min="5" max="24" step="0.5" class="w-20!" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Staff height (mm)') }}</flux:label>
                    <flux:input type="number" wire:model.live.debounce.500ms="staffHeightMm" min="2" max="20" step="0.5" class="w-20!" />
                </flux:field>

                <flux:field variant="inline">
                    <flux:switch wire:model.live="showTitles" />
                    <flux:label>{{ __('Titles') }}</flux:label>
                </flux:field>

                <div class="ml-auto flex items-center gap-2">
                    <span class="text-sm text-zinc-500 dark:text-zinc-400" x-show="pageCount > 0" x-cloak>
                        <span x-text="pageCount"></span> {{ __('pages') }}
                    </span>
                    <flux:button
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

            <p class="mt-2 text-sm text-red-600 dark:text-red-400" x-show="message" x-cloak x-text="message"></p>
        </flux:card>

        {{-- items-start keeps the columns from stretching, which is what lets each
             one stick and scroll inside its own box instead of dragging the page. --}}
        <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,26rem)_minmax(0,1fr)]">

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
                                <li
                                    wire:key="entry-{{ $entry->id }}"
                                    class="rounded-md border border-zinc-200 px-2 py-1.5 dark:border-zinc-700"
                                >
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-5 shrink-0 text-xs text-zinc-400">{{ $index + 1 }}.</span>

                                        @if($entry->isText())
                                            <span class="min-w-0 flex-1 truncate text-sm italic text-zinc-600 dark:text-zinc-300">
                                                {{ \Illuminate\Support\Str::limit(trim(strtok($entry->text ?? '', "\n")) ?: __('Empty text'), 40) }}
                                            </span>
                                            <flux:badge size="sm" color="zinc">{{ __('Text') }}</flux:badge>
                                        @else
                                            <span class="min-w-0 flex-1 truncate text-sm">
                                                {{ $entry->score?->variationLabel() }}
                                                @if($entry->assignment?->musicPlanSlot?->name)
                                                    <span class="text-xs text-zinc-400">· {{ $entry->assignment->musicPlanSlot->name }}</span>
                                                @endif
                                            </span>
                                            @if($entry->score?->format)
                                                <flux:badge size="sm" color="zinc">{{ $entry->score->format->label() }}</flux:badge>
                                            @endif
                                        @endif
                                    </div>

                                    <div class="mt-0.5 flex items-center justify-end gap-0.5">
                                        <flux:tooltip :content="__('Start on a new page')">
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                icon="scissors"
                                                wire:click="toggleStartOnNewPage({{ $entry->id }})"
                                                class="{{ $entry->start_on_new_page ? '!text-blue-600 dark:!text-blue-400' : '' }}"
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
                                                />
                                            </flux:tooltip>
                                        @else
                                            <flux:tooltip :content="__('Print the music title')">
                                                <flux:button
                                                    size="sm"
                                                    variant="ghost"
                                                    icon="musical-note"
                                                    wire:click="toggleShowMusicTitle({{ $entry->id }})"
                                                    class="{{ $entry->show_music_title ? '!text-blue-600 dark:!text-blue-400' : '' }}"
                                                />
                                            </flux:tooltip>

                                            <flux:tooltip :content="__('Print the variation name')">
                                                <flux:button
                                                    size="sm"
                                                    variant="ghost"
                                                    icon="tag"
                                                    wire:click="toggleShowVariation({{ $entry->id }})"
                                                    class="{{ $entry->show_variation ? '!text-blue-600 dark:!text-blue-400' : '' }}"
                                                />
                                            </flux:tooltip>

                                            <flux:tooltip :content="__('Adjust this score')">
                                                <flux:button
                                                    size="sm"
                                                    variant="ghost"
                                                    icon="adjustments-horizontal"
                                                    wire:click="editSettings({{ $this->editingEntryId === $entry->id ? 'null' : $entry->id }})"
                                                    class="{{ $entry->settings_override ? '!text-blue-600 dark:!text-blue-400' : '' }}"
                                                />
                                            </flux:tooltip>
                                        @endif

                                        {{-- :disabled, never @disabled: a directive inside a component
                                             tag stops Blade compiling the tag at all. --}}
                                        <flux:button size="sm" variant="ghost" icon="chevron-up" wire:click="move({{ $entry->id }}, -1)" :disabled="$index === 0" />
                                        <flux:button size="sm" variant="ghost" icon="chevron-down" wire:click="move({{ $entry->id }}, 1)" :disabled="$index === $entries->count() - 1" />
                                        <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="removeEntry({{ $entry->id }})" />
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

                                    @if(! $entry->isText() && $this->editingEntryId === $entry->id && $entry->score?->format)
                                        <div
                                            class="mt-2 border-t border-zinc-200 pt-2 dark:border-zinc-700"
                                            data-booklet-panel="{{ $entry->id }}"
                                        >
                                            <div class="mb-2 flex items-start justify-between gap-2">
                                                <flux:text class="text-xs text-zinc-500">
                                                    {{ __('Changes here apply to this booklet only — the score itself is untouched. Widen a score to stop a line breaking; lower its staff height to stop a page breaking.') }}
                                                </flux:text>

                                                <flux:tooltip :content="__('Back to the booklet defaults')">
                                                    <flux:button
                                                        size="sm"
                                                        variant="ghost"
                                                        icon="arrow-path"
                                                        class="shrink-0"
                                                        x-on:click="resetOverride({{ $entry->id }})"
                                                    />
                                                </flux:tooltip>
                                            </div>

                                            <div class="grid grid-cols-2 gap-x-3 gap-y-2">
                                                @foreach(BookletSettingFields::panelFor($entry->score->format->value) as $field)
                                                    <div class="flex flex-col gap-0.5" wire:key="field-{{ $entry->id }}-{{ $field['key'] }}">
                                                        <label class="flex items-center gap-1 text-xs text-zinc-500 dark:text-zinc-400">
                                                            {{ $field['label'] }}
                                                            <span
                                                                class="text-blue-600 dark:text-blue-400"
                                                                x-show="isOverridden({{ $entry->id }}, '{{ $field['key'] }}')"
                                                                x-cloak
                                                                title="{{ __('Changed for this booklet') }}"
                                                            >●</span>
                                                        </label>

                                                        @if($field['type'] === 'number')
                                                            <flux:input
                                                                size="sm"
                                                                type="number"
                                                                min="{{ $field['min'] }}"
                                                                max="{{ $field['max'] }}"
                                                                step="{{ $field['step'] }}"
                                                                x-bind:value="settingsOf({{ $entry->id }})['{{ $field['key'] }}']"
                                                                x-on:change="setOverride({{ $entry->id }}, '{{ $field['key'] }}', Number($event.target.value))"
                                                            />
                                                        @elseif($field['type'] === 'boolean')
                                                            <flux:switch
                                                                x-bind:checked="!!settingsOf({{ $entry->id }})['{{ $field['key'] }}']"
                                                                x-on:change="setOverride({{ $entry->id }}, '{{ $field['key'] }}', $event.target.checked)"
                                                            />
                                                        @else
                                                            <flux:select
                                                                size="sm"
                                                                class="text-xs"
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
                                            </div>
                                        </div>
                                    @endif
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
                                    <div class="text-sm font-medium">{{ $assignment['music_title'] }}</div>

                                    @forelse($assignment['scores'] as $score)
                                        @php $isChosen = in_array($score['id'], $chosen, true); @endphp
                                        <div
                                            class="flex items-center gap-2 py-0.5 pl-2 text-sm"
                                            wire:key="score-{{ $assignment['id'] }}-{{ $score['id'] }}"
                                        >
                                            <flux:tooltip :content="$isChosen ? __('In the booklet — click to take it out') : __('Add to the booklet')">
                                                <flux:button
                                                    size="sm"
                                                    variant="ghost"
                                                    :icon="$isChosen ? 'check-circle' : 'plus'"
                                                    wire:click="toggleScore({{ $score['id'] }}, {{ $assignment['id'] }})"
                                                    class="shrink-0 {{ $isChosen ? '!text-green-600 dark:!text-green-400' : '' }}"
                                                    :disabled="$score['format_value'] === null"
                                                />
                                            </flux:tooltip>
                                            <span class="min-w-0 flex-1 truncate {{ $isChosen ? 'text-zinc-500' : '' }}">{{ $score['title'] }}</span>
                                            <flux:badge size="sm" color="zinc">{{ $score['format'] }}</flux:badge>
                                            @if(!$score['is_own'])
                                                <flux:tooltip :content="$score['owner_name']">
                                                    <flux:icon name="user" variant="micro" class="shrink-0 text-zinc-400" />
                                                </flux:tooltip>
                                            @endif
                                        </div>
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

            {{-- The pages --}}
            <flux:card class="relative flex flex-col p-4 lg:sticky lg:top-4 lg:max-h-[calc(100vh-2rem)]">
                <div
                    class="absolute right-4 top-4 flex items-center gap-1.5 text-sm text-zinc-500"
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
