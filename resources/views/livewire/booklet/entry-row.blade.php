@php
    use App\Livewire\Pages\BookletEditor;
    use App\Support\BookletSettingFields;
@endphp

<li class="space-y-1">
    {{-- Where in the service this stands: the slot it fills and the music sung
         there. What is actually printed — the score — is the card beneath it. --}}
    <div data-entry-header class="flex items-start gap-1.5">
        {{-- Counted by the browser: a row moved renumbers every row after it,
             and none of them is listening. --}}
        <span data-entry-number class="w-5 shrink-0 pt-0.5 text-xs text-zinc-400"></span>

        @if($entry->isText())
            <span class="min-w-0 flex-1 truncate text-sm italic text-zinc-600 dark:text-zinc-300">
                {{ \Illuminate\Support\Str::limit(trim(strtok($entry->text ?? '', "\n")) ?: __('Empty text'), 40) }}
            </span>
            <flux:badge size="sm" color="zinc">{{ __('Text') }}</flux:badge>
        @else
            {{-- The music the plan asked for, or — for a score chosen outside the
                 plan — the one the score is of. --}}
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

        {{-- Where a row stands is the list's business, so moving it and taking it
             out are asked of the booklet rather than answered here. The ends of
             the list are greyed by the stylesheet for the same reason a row is
             numbered there: a row that has been moved does not hear about it. --}}
        <div class="ml-auto flex shrink-0 items-center gap-0.5">
            <flux:tooltip :content="__('Move up')">
                <flux:button size="sm" variant="ghost" icon="chevron-up" data-entry-move="up" :aria-label="__('Move up')" wire:click="$parent.move({{ $entry->id }}, -1)" />
            </flux:tooltip>
            <flux:tooltip :content="__('Move down')">
                <flux:button size="sm" variant="ghost" icon="chevron-down" data-entry-move="down" :aria-label="__('Move down')" wire:click="$parent.move({{ $entry->id }}, 1)" />
            </flux:tooltip>
            <flux:tooltip :content="__('Remove')">
                <flux:button size="sm" variant="ghost" icon="x-mark" :aria-label="__('Remove')" wire:click="$parent.removeEntry({{ $entry->id }})" />
            </flux:tooltip>
        </div>
    </div>

    {{-- The score itself, in a card of its own: one music may be sung from any of
         several engravings, and the card is the one that was chosen — its name,
         its opening notes, and everything that can be done to it here. --}}
    <div data-entry-card class="ms-6 rounded-md border border-zinc-200 px-2 py-1.5 dark:border-zinc-700">
        @unless($entry->isText())
            <div class="flex items-center gap-1.5 text-sm">
                <flux:icon name="file-music" variant="micro" class="shrink-0 text-zinc-400" />
                @if($scoreUrl)
                    <a href="{{ $scoreUrl }}" target="_blank" class="min-w-0 truncate hover:underline">{{ $entry->score?->title }}</a>
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

        {{-- The same incipit the plan below shows, so a row in the booklet is
             recognised by its opening notes rather than by a title that several
             arrangements share. --}}
        @if($incipitUrl)
            <x-incipit-image
                data-entry-incipit
                :src="$incipitUrl"
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
                    wire:click="toggleStartOnNewPage"
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
                        wire:click="write"
                        class="{{ $writing ? '!text-blue-600 dark:!text-blue-400' : '' }}"
                        aria-expanded="{{ $writing ? 'true' : 'false' }}"
                        :aria-label="__('Edit this text')"
                    />
                </flux:tooltip>
            @else
                <flux:tooltip :content="__('Print the music title')">
                    <flux:button
                        size="sm"
                        variant="ghost"
                        icon="musical-note"
                        wire:click="toggleShowMusicTitle"
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
                        wire:click="toggleShowVariation"
                        aria-pressed="{{ $entry->show_variation ? 'true' : 'false' }}"
                        class="{{ $entry->show_variation ? '!text-blue-600 dark:!text-blue-400' : '' }}"
                        :aria-label="__('Print the variation name')"
                    />
                </flux:tooltip>

                {{-- An uploaded score is a picture by the time it reaches a
                     booklet, so it gets a panel with the one knob a picture has;
                     an engraved one gets its format's.

                     Whether the score has been adjusted is answered from the
                     browser, as each knob in the panel is: the saving is done by
                     the booklet rather than by this row, which hears nothing of
                     it, and an override may in any case still be waiting to be
                     sent. --}}
                <flux:tooltip :content="__('Adjust this score')">
                    <flux:button
                        size="sm"
                        variant="ghost"
                        icon="adjustments-horizontal"
                        wire:click="adjust"
                        wire:ignore.self
                        x-bind:class="hasOverride({{ $entry->id }}) ? '!text-blue-600 dark:!text-blue-400' : ''"
                        aria-expanded="{{ $adjusting ? 'true' : 'false' }}"
                        :aria-label="__('Adjust this score')"
                    />
                </flux:tooltip>
            @endif

        </div>

        {{-- Both panels open inside the row they belong to: what is being adjusted
             is right above the controls adjusting it, and a list of twenty scores
             does not have to be scrolled to the end to find out which one is being
             talked about. --}}
        @if($entry->isText() && $writing)
            <div class="mt-2 border-t border-zinc-200 pt-2 dark:border-zinc-700">
                <flux:textarea
                    rows="6"
                    wire:model.live.debounce.600ms="text"
                    :placeholder="__('Stand. The cantor sings the verses, **all** repeat the antiphon.')"
                />

                <flux:text class="mt-2 text-xs text-zinc-500">
                    {{ __('Markdown: # heading, **bold**, *italic*, - list, > quote.') }}
                </flux:text>
            </div>
        @endif

        {{-- Laid out as the score editor's toolbar for the same format, control
             for control: a knob is its icon, its name is the tooltip, and it turns
             blue once this booklet has moved it away from the score's own value. --}}
        @if(! $entry->isText() && $adjusting)
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
                            {{-- Whether this knob has been moved is a question only the
                                 browser can answer — the override may still be waiting to
                                 be sent. The server does render a class here, so a morph
                                 does not strip it but overwrites it, which cost the icon
                                 its blue the moment the save landed; the morph is kept off
                                 it as it is off the divider. --}}
                            <flux:tooltip :content="$field['label']">
                                @if($field['glyph'] ?? null)
                                    <span
                                        wire:ignore.self
                                        class="shrink-0 text-xs font-bold text-zinc-500 dark:text-zinc-400"
                                        x-bind:class="isOverridden({{ $entry->id }}, '{{ $field['key'] }}') ? '!text-blue-600 dark:!text-blue-400' : ''"
                                    >{{ $field['glyph'] }}</span>
                                @else
                                    <flux:icon
                                        :name="$field['icon']"
                                        variant="micro"
                                        wire:ignore.self
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
