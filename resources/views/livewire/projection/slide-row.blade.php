@php
    use App\Livewire\Pages\ProjectionEditor;
    use App\Support\ProjectionSettingFields;
    use App\Support\ScoreSections;
    use Illuminate\Support\Arr;
    use Illuminate\Support\Js;

    // Where the row stands in the plan, for a paragraph written directly under
    // it: the same slot and the same music, so words follow the thing they were
    // written about wherever it is moved to.
    $textArgs = implode(', ', [
        $entry->music_plan_slot_plan_id ?? 'null',
        $entry->music_plan_slot_assignment_id ?? 'null',
        $entry->id,
        $entry->added_music_id ?? 'null',
    ]);

    // The chip editor stands only where the score actually has parts to
    // choose from — see plans/score-sections.md.
    $scoreSections = $entry->isText() ? [] : ScoreSections::list($entry->score?->content);
    $sectionsByNumber = collect($scoreSections)->keyBy('n');

    // A row set up against an earlier version of the score, told plainly
    // rather than tracked: any change to the row clears it.
    $scoreChanged = ! $entry->isText()
        && $entry->score?->updated_at !== null
        && $entry->updated_at !== null
        && $entry->score->updated_at->gt($entry->updated_at);
@endphp

{{-- What the projection shows here.

     The slot and the music are no longer said on the row: the plan around it
     says both, one line above, and a row that repeated them said the same thing
     three times over for a slot sung from three engravings. What is left is the
     thing itself — its name, its opening notes, and everything that can be done
     to it. The green edge is what marks it as being in the deck, since in the
     plan it stands among the scores that are not. --}}
<li
    data-entry-row="{{ $entry->id }}"
    x-on:mouseenter="hoverEntry({{ $entry->id }})"
    x-on:mouseleave="hoverEntry(null)"
    x-bind:class="{ 'projection-entry-hovered': hoveredEntryId === {{ $entry->id }} }"
    data-entry="{{ $entry->isText() ? 'text' : 'score' }}" class="border-s-2 border-green-500 ps-1.5">
    <div data-entry-card class="flex gap-1 rounded-md border border-zinc-200 py-1.5 ps-1 pe-2 dark:border-zinc-700">
        {{-- Where a row stands is the list's business, so moving it and taking
             it out are asked of the deck rather than answered here. The
             ends of the list are greyed by the stylesheet for the same reason
             a row is numbered there: a row that has been moved does not hear
             about it. A move never leaves the music the row belongs to — the
             deck refuses one that would.

             They stand in a column down the left edge, where the plus of a
             score not yet taken stands, so what is done to the row's place is
             never mistaken for what is done to what it shows. --}}
        <div data-entry-nav class="flex shrink-0 flex-col items-center gap-0.5">
            <flux:tooltip :content="__('Move up')" position="left">
                <flux:button size="sm" variant="ghost" icon="chevron-up" data-entry-move="up" :aria-label="__('Move up')" wire:click="$parent.move({{ $entry->id }}, -1)" />
            </flux:tooltip>
            <flux:tooltip :content="__('Move down')" position="left">
                <flux:button size="sm" variant="ghost" icon="chevron-down" data-entry-move="down" :aria-label="__('Move down')" wire:click="$parent.move({{ $entry->id }}, 1)" />
            </flux:tooltip>
            <flux:tooltip :content="__('Remove')" position="left">
                <flux:button size="sm" variant="ghost" icon="x-mark" :aria-label="__('Remove')" wire:click="$parent.removeEntry({{ $entry->id }})" />
            </flux:tooltip>
        </div>

        <div class="min-w-0 flex-1">
            <div data-entry-header class="flex {{ $entry->isText() ? 'items-start' : 'items-center' }} gap-1.5 text-sm">
                {{-- Counted by the browser: a row moved renumbers every row after it,
                     and none of them is listening. --}}
                <span data-entry-number class="shrink-0 text-xs text-zinc-400"></span>

                @if($entry->isText())
                    {{-- As much of the words as the three buttons beside them are
                         tall, so a paragraph is recognised without opening it. --}}
                    <flux:icon name="document-text" variant="micro" class="mt-0.5 shrink-0 text-zinc-400" />
                    <span data-entry-text-preview class="line-clamp-4 min-h-20 min-w-0 flex-1 whitespace-pre-line break-words italic text-zinc-600 dark:text-zinc-300">{{ trim(strip_tags($entry->text ?? '')) ?: __('Empty text') }}</span>
                    <flux:badge size="sm" color="zinc" class="shrink-0">{{ __('Text') }}</flux:badge>
                @else
                    {{-- A score chosen outside the plan stands at the foot of the
                         pane with nothing above it to say what it is, so it names its
                         own music. One chosen from the plan, or added under a local
                         music group, has the music's name a line above it already. --}}
                    @php $music = ($entry->assignment === null && $entry->added_music_id === null) ? $entry->score?->music : null; @endphp
                    @if($music)
                        <span class="inline-flex min-w-0 shrink items-center gap-1 text-xs text-zinc-500 dark:text-zinc-400">
                            <flux:icon name="music" variant="micro" class="shrink-0 text-indigo-400" />
                            <a href="{{ route('music-view', $music) }}" target="_blank" class="min-w-0 truncate hover:underline">
                                {{ $music->title }}
                            </a>
                        </span>
                    @endif

                    <flux:icon name="file-music" variant="micro" class="shrink-0 text-zinc-400" />
                    <span class="min-w-0 truncate">
                        @if($scoreUrl)
                            <a href="{{ $scoreUrl }}" target="_blank" class="hover:underline">{{ $entry->score?->title }}</a>
                        @else
                            {{ $entry->score?->title }}
                        @endif
                        @if($entry->scoreFile)
                            <span class="text-xs text-zinc-400">· {{ $entry->scoreFile->displayName() }}</span>
                        @endif
                    </span>

                    @if($scoreChanged)
                        <flux:tooltip :content="__('The score has changed since')">
                            <span data-entry-score-changed class="shrink-0 text-amber-500" aria-hidden="true">*</span>
                        </flux:tooltip>
                    @endif

                    {{-- The variation is the one heading line said against the row
                         rather than a slot or a music, so its switch rides here,
                         right after the title. Like the collections in the plan, the
                         name itself is the switch, drawn as it will be shown. It
                         starts off: several arrangements of one music are told apart
                         by their opening notes more than by a name most decks never
                         show. --}}
                    @if(trim((string) $entry->score?->variation_name) !== '')
                        <flux:tooltip :content="__('Show the variation name')">
                            <button
                                type="button"
                                data-entry-variation
                                wire:click="toggleShowVariation"
                                aria-pressed="{{ $entry->show_variation ? 'true' : 'false' }}"
                                aria-label="{{ __('Show the variation name') }}"
                                class="max-w-[10rem] shrink-0 cursor-pointer truncate rounded-md border px-1.5 py-0.5 text-xs font-normal transition
                                    {{ $entry->show_variation
                                        ? 'border-blue-600 bg-blue-600/10 text-blue-700 dark:border-blue-400 dark:bg-blue-400/15 dark:text-blue-300'
                                        : 'border-dashed border-zinc-300 text-zinc-400 hover:border-zinc-400 hover:text-zinc-600 dark:border-zinc-600 dark:text-zinc-500 dark:hover:text-zinc-300' }}"
                            >{{ $entry->score->variation_name }}</button>
                        </flux:tooltip>
                    @endif

                    <flux:badge size="sm" color="zinc" class="shrink-0">
                        {{ $entry->score?->format?->label() ?? __('File') }}
                    </flux:badge>

                    <span class="flex-1"></span>
                @endif

                @if(! $entry->isText() && $scoreUrl)
                    <flux:tooltip :content="__('Preview the score')">
                        <flux:button size="sm" variant="ghost" icon="eye" data-entry-preview class="shrink-0" :aria-label="__('Preview the score')" wire:click="togglePreview" />
                    </flux:tooltip>
                @endif

                {{-- Words written from here belong where this row belongs, and are
                     set directly beneath it — which is the deck's business rather
                     than this row's, like the column on the left. --}}
                @php $addLabel = $entry->isText() ? __('Add text under these words') : __('Add text under this score'); @endphp
                <flux:tooltip :content="$addLabel">
                    <flux:button size="sm" variant="ghost" icon="message-square-plus" data-entry-add-text class="shrink-0" :aria-label="$addLabel" wire:click="$parent.addText({{ $textArgs }})" />
                </flux:tooltip>
            </div>

            @if($previewingScore && ! $entry->isText() && $scoreUrl)
                <flux:modal wire:model="previewingScore" class="w-full max-w-3xl" data-entry-preview-modal>
                    <x-score-preview
                        :entry-id="$entry->id"
                        :title="$entry->score?->variationLabel()"
                        :subtitle="$entry->scoreFile?->displayName()"
                    />
                </flux:modal>
            @endif

            {{-- The same incipit the scores beneath it show, so a row in the deck
                 is recognised by its opening notes rather than by a title that
                 several arrangements share. --}}
            @if($incipitUrl)
                <x-incipit-image
                    data-entry-incipit
                    :src="$incipitUrl"
                    :alt="__('Incipit').' — '.$entry->score?->variationLabel()"
                    class="mt-1 ms-5 max-w-full"
                    imgClass="max-h-16 max-w-full rounded bg-white object-contain"
                />
            @endif

            {{-- A score's sections, chosen and ordered for this row alone — see
                 plans/score-sections.md. Only offered where the score has any:
                 one with no marker prints whole, exactly as it always has. --}}
            @if(count($scoreSections) > 0)
                <div data-entry-sections class="mt-2 flex flex-wrap items-center gap-1 border-t border-zinc-100 pt-2 dark:border-zinc-800">
                    <flux:tooltip :content="__('Which parts of the score this row shows, and in what order')">
                        <flux:icon name="list-bullet" variant="micro" class="shrink-0 text-zinc-400" />
                    </flux:tooltip>

                    @php $sectionCount = count($entry->sections ?? []); @endphp
                    @forelse(($entry->sections ?? []) as $position => $reference)
                        @php $section = $sectionsByNumber->get($reference); @endphp
                        <span
                            data-entry-section
                            class="inline-flex shrink-0 items-center gap-0.5 rounded-md border px-1 py-0.5 text-xs
                                {{ $section
                                    ? 'border-blue-600 bg-blue-600/10 text-blue-700 dark:border-blue-400 dark:bg-blue-400/15 dark:text-blue-300'
                                    : 'border-dashed border-amber-400 text-amber-500' }}"
                        >
                            <button type="button" {{ $position === 0 ? 'disabled' : '' }} wire:click="$parent.moveSection({{ $entry->id }}, {{ $position }}, -1)" aria-label="{{ __('Move up') }}" class="disabled:opacity-30">
                                <flux:icon name="chevron-left" variant="micro" />
                            </button>
                            {{ $reference }}@if(($section['label'] ?? null)) &middot; {{ $section['label'] }}@endif
                            <button type="button" {{ $position === $sectionCount - 1 ? 'disabled' : '' }} wire:click="$parent.moveSection({{ $entry->id }}, {{ $position }}, 1)" aria-label="{{ __('Move down') }}" class="disabled:opacity-30">
                                <flux:icon name="chevron-right" variant="micro" />
                            </button>
                            <button type="button" wire:click="$parent.removeSection({{ $entry->id }}, {{ $position }})" aria-label="{{ __('Remove') }}">
                                <flux:icon name="x-mark" variant="micro" />
                            </button>
                        </span>
                    @empty
                        <span class="text-xs italic text-zinc-400">{{ __('Whole score') }}</span>
                    @endforelse

                    <flux:dropdown>
                        <flux:button size="sm" variant="ghost" icon="plus" :aria-label="__('Add a section')" />
                        <flux:menu>
                            @foreach($scoreSections as $section)
                                <flux:menu.item wire:click="$parent.addSection({{ $entry->id }}, {{ $section['n'] }})">
                                    {{ $section['n'] }}@if($section['label']) &middot; {{ $section['label'] }}@endif
                                </flux:menu.item>
                            @endforeach
                            <flux:menu.separator />
                            <flux:menu.item wire:click="$parent.clearSections({{ $entry->id }})">{{ __('All') }}</flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                </div>
            @endif

            <div data-entry-options class="mt-2 flex flex-wrap items-center gap-0.5 border-t border-zinc-100 pt-2 dark:border-zinc-800">
                {{-- A row does not choose where it breaks: its own source does, with
                     the `%pagebreak` written into it for this ratio — the score's, or
                     for a screen of words the Markdown's own. So what stands here is
                     not a switch but a count: how many screens this row came to when
                     the browser last cut it. --}}
                <flux:tooltip :content="$entry->isText() ? __('Slides these words come to at this shape') : __('Slides this score comes to at this shape')">
                    <span
                        class="inline-flex shrink-0 items-center gap-1 px-1.5 text-xs text-zinc-500 dark:text-zinc-400"
                        x-show="slidesOf({{ $entry->id }}) > 0"
                        x-cloak
                    >
                        <flux:icon name="rectangle-stack" variant="micro" class="shrink-0" />
                        <span x-text="slidesOf({{ $entry->id }})"></span>
                        {{-- Slides left out of the service, counted where they
                             were chosen from: a hymn showing three of its six
                             verses says so on its own row, rather than only in a
                             contact sheet somebody has to scroll. --}}
                        <span
                            class="text-amber-600 dark:text-amber-400"
                            x-show="skippedOf({{ $entry->id }}) > 0"
                            x-cloak
                        >(−<span x-text="skippedOf({{ $entry->id }})"></span>)</span>
                    </span>
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
                @endif

                {{-- What is said above this score — the slot's name, the music's, the
                     variation — is switched on and off beside those names themselves:
                     the slot's and the music's in the plan around this row, the
                     variation's on the row, next to the name it carries. --}}
                {{-- An uploaded score is a picture by the time it reaches a screen,
                     so it gets a panel with the one knob a picture has; an engraved
                     one gets its format's; a screen of words gets the two numbers
                     the deck would otherwise set them in.

                     Whether the row has been adjusted is answered from the browser,
                     as each knob in the panel is: the saving is done by the deck
                     rather than by this row, which hears nothing of it, and an
                     override may in any case still be waiting to be sent. --}}
                @php $adjustLabel = $entry->isText() ? __('Adjust these words') : __('Adjust this score'); @endphp
                <flux:tooltip :content="$adjustLabel">
                    <flux:button
                        size="sm"
                        variant="ghost"
                        icon="adjustments-horizontal"
                        wire:click="adjust"
                        wire:ignore.self
                        x-bind:class="hasOverride({{ $entry->id }}) ? '!text-blue-600 dark:!text-blue-400' : ''"
                        aria-expanded="{{ $adjusting ? 'true' : 'false' }}"
                        :aria-label="$adjustLabel"
                    />
                </flux:tooltip>
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
                        {{ __('Markdown: # heading, **bold**, *italic*, - list, > quote, <red>red</red>, <small>small</small>.') }}
                    </flux:text>

                    {{-- The break is the one thing here that behaves differently
                         from screen to screen, so it is explained rather than
                         listed: a suggestion costs nothing where the words already
                         fit, which is what makes it worth writing in advance. --}}
                    <flux:text class="mt-1 text-xs text-zinc-500">
                        {{ __('A line reading %pagebreak starts a new slide; %pagebreak? only does so when the words would not otherwise fit. Add 169, 43 or 11 — %pagebreak169? — to speak to one screen shape alone.') }}
                    </flux:text>
                </div>
            @endif

            {{-- Laid out as the score editor's toolbar for the same format, control
                 for control: a knob is its icon, its name is the tooltip, and it turns
                 blue once this deck has moved it away from the layout the score's own
                 author chose for this ratio. --}}
            @if($adjusting)
                @php $panelFormat = ProjectionEditor::overrideFormat($entry); @endphp
                <div
                    class="mt-2 border-t border-zinc-200 pt-2 dark:border-zinc-700"
                    data-projection-panel="{{ $entry->id }}"
                >
                    <flux:text class="mb-2 text-xs text-zinc-500">
                        @if($panelFormat === 'text')
                            {{ __('Changes here apply to these words at this screen shape only. They are set at the deck\'s own text size and line spacing; make them larger where a screen is nearly empty. Words that no longer fit are cut onto another screen rather than shrunk, so set a size and let the breaks follow.') }}
                        @elseif($panelFormat === 'file')
                            {{ __('Changes here apply to this projection at this screen shape only. An uploaded page is fitted to the screen; make it smaller where that is too big.') }}
                        @else
                            {{ __('Changes here apply to this projection at this screen shape only — the score itself is untouched. Make the lyrics smaller where a slide is too full, or put a %pagebreak in the score to split it instead.') }}
                        @endif
                    </flux:text>

                    <div class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800/50">
                        @foreach(ProjectionSettingFields::panelFor($panelFormat) as $field)
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

                                @if(($field['control'] ?? null) === 'step')
                                    {{-- A size resolved from the score's own layout is an arbitrary
                                         fraction — 4.6667 of nothing anybody names — so
                                         it is offered the way the reader's toolbar offers
                                         it: bigger and smaller, and no number to read. --}}
                                    @php $knob = Js::from(Arr::only($field, ['key', 'min', 'max', 'step', 'percent'])); @endphp

                                    <flux:tooltip :content="__('Smaller')">
                                        <flux:button size="sm" variant="ghost" icon="minus"
                                            aria-label="{{ $field['label'] }}: {{ __('Smaller') }}"
                                            x-on:click="nudgeOverride({{ $entry->id }}, {{ $knob }}, -1)"
                                            x-bind:disabled="atLimit({{ $entry->id }}, {{ $knob }}, -1)" />
                                    </flux:tooltip>

                                    <flux:tooltip :content="__('Bigger')">
                                        <flux:button size="sm" variant="ghost" icon="plus"
                                            aria-label="{{ $field['label'] }}: {{ __('Bigger') }}"
                                            x-on:click="nudgeOverride({{ $entry->id }}, {{ $knob }}, 1)"
                                            x-bind:disabled="atLimit({{ $entry->id }}, {{ $knob }}, 1)" />
                                    </flux:tooltip>
                                @elseif($field['type'] === 'number')
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
                                @endif
                            </div>
                        @endforeach

                        <flux:tooltip :content="__('Back to the scores own layout')">
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="arrow-path"
                                class="shrink-0"
                                :aria-label="__('Back to the scores own layout')"
                                x-on:click="resetOverride({{ $entry->id }})"
                            />
                        </flux:tooltip>
                    </div>
                </div>
            @endif
        </div>
    </div>
</li>
