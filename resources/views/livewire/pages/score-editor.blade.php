<div class="py-8" x-data="scoreEditor({
        scoreSettings: @js($settings ?: (object) []),
        userDefaults: @js($userDefaults ?: (object) []),
        clippedWarningText: @js(__('Content does not fit on page')),
        clipboardNotSupported: @js(__('Clipboard not supported in this browser')),
        imageCopied: @js(__('Image copied to clipboard')),
        failedToCopy: @js(__('Failed to copy image')),
        shareLinkCopied: @js(__('Lending link copied!')),
        linkCopyFailed: @js(__('Failed to copy link')),
        htmlCopied: @js(__('HTML copied to clipboard!')),
        plainTextCopied: @js(__('Plain text copied to clipboard!')),
        copyAsImageText: @js(__('Copy as Image')),
        shareText: @js(__('Lend')),
        exportText: @js(__('Export')),
        exportPngText: @js(__('Export PNG')),
        exportSvgText: @js(__('Export SVG')),
        exportPdfText: @js(__('Export PDF')),
        fullscreenText: @js(__('Fullscreen')),
        autosave: @js((bool) $score),
        autosaveLabels: @js([
            'pending' => __('Unsaved changes'),
            'saving' => __('Saving…'),
            'saved' => __('All changes saved'),
            'failed' => __('Could not save — press Save Score'),
        ]),
    })">
    <div class="mx-auto max-w-5xl xl:max-w-none">
        <flux:card class="p-4 lg:p-4">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    @if(!$isGuest)
                    <flux:button variant="ghost" icon="arrow-left" :href="route('scores')" wire:navigate class="mb-2">
                        {{ __('Back to Scores') }}
                    </flux:button>
                    @endif
                    <flux:heading size="2xl">
                        @if($score)
                        {{ __('Edit Score') }}
                        @elseif($isSharedLink && $isGuest)
                        {{ __('Score Preview') }}
                        @elseif($isGuest)
                        {{ __('Score Editor') }}
                        @else
                        {{ __('Create Score') }}
                        @endif
                    </flux:heading>
                    <flux:subheading>
                        @if($isGuest && !$isSharedLink)
                        Regisztráció nélkül szerkeszthetsz kottát. A munkád mentéséhez használd a <strong>Megosztás</strong> gombot, és küldd el magadnak a linket – vagy mentsd el a böngészőben könyvjelzőként.
                        @elseif($isSharedLink && $isGuest)
                        {{ __('Sign in to save this score to your library.') }}
                        @elseif($isSharedLink)
                        {{ __('Saving will create a new score in your library.') }}
                        @else
                        {{ __('A score is private until you share it — with a secret link, or by offering it to the public library.') }}
                        @endif
                    </flux:subheading>

                    @if(! $isGuest && ! $isSharedLink && $score)
                    @php($headerPublication = $this->publication)
                    @php($headerPublicationStatus = $headerPublication?->status)
                    <div class="mt-2 flex flex-wrap items-center gap-1.5">
                        @if($loanLinkUrl)
                        <flux:badge size="sm" color="amber" icon="link">{{ __('Shared with a secret link') }}</flux:badge>
                        @endif

                        @if($this->indirectLoans->isNotEmpty())
                        <flux:badge size="sm" color="amber" icon="folder">{{ __('Shared through a folder or music plan') }}</flux:badge>
                        @endif

                        @if($headerPublicationStatus === \App\Enums\ScorePublicationStatus::Approved)
                        <flux:badge size="sm" color="green" icon="globe-alt">{{ __('In the public library') }}</flux:badge>
                        @elseif($headerPublicationStatus === \App\Enums\ScorePublicationStatus::Submitted)
                        <flux:badge size="sm" color="blue" icon="clock">{{ __('Waiting for review by an editor') }}</flux:badge>
                        @endif

                        @if(! $loanLinkUrl
                            && $this->indirectLoans->isEmpty()
                            && $headerPublicationStatus !== \App\Enums\ScorePublicationStatus::Approved
                            && $headerPublicationStatus !== \App\Enums\ScorePublicationStatus::Submitted)
                        <flux:badge size="sm" color="zinc" icon="lock-closed">{{ __('Private — only you can see it') }}</flux:badge>
                        @endif
                    </div>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @if(!$isGuest)
                        <span
                            x-show="autosaveState"
                            x-cloak
                            x-text="autosaveLabel()"
                            class="text-sm text-zinc-500 dark:text-zinc-400"
                            :class="autosaveState === 'failed' ? 'text-red-600 dark:text-red-400' : ''"></span>
                        <flux:tooltip :content="$score ? __('Lend this score') : __('Save the score first')">
                            <flux:button variant="filled" icon="link" x-on:click="openShareModal()" :disabled="! $score">
                                {{ __('Lend') }}
                            </flux:button>
                        </flux:tooltip>
                        @if($linksOnly)
                        <flux:button variant="filled" variant="primary" icon="check" wire:click="save">
                            {{ __('Save Score') }}
                        </flux:button>
                        @else
                        <flux:button variant="filled" variant="primary" icon="check" x-on:click="saveScore()" x-bind:disabled="savingScore">
                            {{ __('Save Score') }}
                        </flux:button>
                        @endif
                    @endif
                </div>
            </div>


            <div class="space-y-4">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="flex flex-col gap-4">
                        <flux:field required>
                            <flux:label class="inline">{{ __('Score title') }}</flux:label>
                            <flux:input wire:model="title" x-on:input="markDirty()" :placeholder="__('Score title')" autofocus />
                            <flux:error name="title" />
                        </flux:field>

                        @if(!$isGuest)
                        <flux:field>
                            <div class="flex items-center gap-2">
                                <flux:input
                                    readonly
                                    :value="$this->selectedMusic ? $this->selectedMusic->title.($this->selectedMusic->subtitle ? ' — '.$this->selectedMusic->subtitle : '') : ''"
                                    :placeholder="__('No music attached')"
                                    class="flex-1" />
                                <flux:button icon="magnifying-glass" x-on:click="$flux.modal('score-music-search').show()">
                                    {{ __('Browse') }}
                                </flux:button>
                                @if($musicId)
                                <flux:button icon="x-mark" variant="ghost" wire:click="clearMusic" :title="__('Remove')" />
                                @endif
                            </div>
                            <flux:error name="musicId" />
                        </flux:field>

                        @if($musicId)
                        <flux:field>
                            <flux:label class="inline">{{ __('Variation name') }}</flux:label>
                            <flux:input
                                wire:model="variationName"
                                x-on:input="markDirty()"
                                maxlength="120"
                                :placeholder="__('e.g. Flute, Choir, Lyrics only')" />
                            <flux:description>{{ __('Tells this version apart from the other scores of the same music.') }}</flux:description>
                            <flux:error name="variationName" />
                        </flux:field>
                        @endif

                        <flux:modal name="score-music-search" class="max-w-4xl">
                            <livewire:music-search lazy selectable="true" source=".score" wire:key="score-music-search" />
                            <div class="mt-6 flex justify-end">
                                <flux:button x-on:click="$flux.modal('score-music-search').close()" variant="outline">
                                    {{ __('Cancel') }}
                                </flux:button>
                            </div>
                        </flux:modal>
                        @endif
                    </div>

                    <flux:field>
                        <flux:label class="inline">{{ __('Format') }}</flux:label>
                        {{-- Five buttons share one row down to the narrowest phone, so both the label and the picture shrink with the viewport. --}}
                        <div class="flex flex-nowrap gap-1 sm:gap-2">
                            @foreach($formats as $formatOption)
                            <button
                                type="button"
                                wire:click="selectFormat('{{ $formatOption->value }}')"
                                @class([
                                    'flex flex-1 min-w-0 basis-0 flex-col items-center gap-0.5 rounded-lg border px-1 py-1.5 text-zinc-800 transition sm:gap-1 sm:px-2 sm:py-2 dark:text-zinc-100',
                                    'border-zinc-900 bg-zinc-100 dark:border-white dark:bg-zinc-700' => ! $linksOnly && $format === $formatOption->value,
                                    'border-zinc-200 bg-white hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-800 dark:hover:bg-zinc-700' => $linksOnly || $format !== $formatOption->value,
                                ])>
                                <span class="hyphens-auto text-center text-[10px] leading-tight font-medium break-words sm:text-sm">{{ $formatOption->label() }}</span>
                                <img src="{{ asset($formatOption->value.'-button.png') }}" alt="{{ $formatOption->label() }}" class="h-7 w-auto object-contain sm:h-10" />
                            </button>
                            @endforeach

                            @if(!$isGuest)
                            <button
                                type="button"
                                wire:click="selectLinksOnly"
                                @class([
                                    'flex flex-1 min-w-0 basis-0 flex-col items-center gap-0.5 rounded-lg border px-1 py-1.5 text-zinc-800 transition sm:gap-1 sm:px-2 sm:py-2 dark:text-zinc-100',
                                    'border-zinc-900 bg-zinc-100 dark:border-white dark:bg-zinc-700' => $linksOnly,
                                    'border-zinc-200 bg-white hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-800 dark:hover:bg-zinc-700' => ! $linksOnly,
                                ])>
                                <span class="hyphens-auto text-center text-[10px] leading-tight font-medium break-words sm:text-sm">{{ __('Links and files') }}</span>
                                <div class="flex h-7 items-center sm:h-10">
                                    <flux:icon name="paper-clip" class="size-6 text-zinc-500 sm:size-9 dark:text-zinc-400" />
                                </div>
                            </button>
                            @endif
                        </div>
                        <flux:error name="format" />
                    </flux:field>
                </div>
            </div>

            @if (! $isGuest && $musicId)
                <div class="mt-4">
                    <div class="flex flex-wrap items-center gap-2 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800/50">
                        <flux:icon name="layers" variant="micro" class="shrink-0 text-zinc-400" />
                        <span class="text-sm font-medium text-zinc-600 dark:text-zinc-400">{{ __('Variations') }}</span>

                        @if ($score)
                            <span class="inline-flex items-center gap-1.5 rounded-md border border-zinc-400 bg-white px-2.5 py-1 text-sm font-semibold text-zinc-900 ring-1 ring-zinc-400 dark:border-zinc-500 dark:bg-zinc-700 dark:text-zinc-100 dark:ring-zinc-500">
                                {{ trim($variationName) !== '' ? $variationName : $title }}
                                <x-score-format-badge :format="$score->format" />
                            </span>
                        @endif

                        @foreach ($this->relatedScores as $relatedScore)
                            <a href="{{ route('scores.edit', $relatedScore) }}" wire:navigate
                               class="inline-flex items-center gap-1.5 rounded-md border border-zinc-200 bg-white px-2.5 py-1 text-sm text-zinc-700 transition hover:border-zinc-400 hover:bg-zinc-100 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:border-zinc-400 dark:hover:bg-zinc-700">
                                {{ $relatedScore->variationLabel() }}
                                <x-score-format-badge :format="$relatedScore->format" />
                            </a>
                        @endforeach

                        @php($variationButtonClasses = 'inline-flex items-center gap-1 rounded-md px-2.5 py-1 text-sm text-zinc-500 transition hover:text-zinc-700 disabled:opacity-50 dark:text-zinc-400 dark:hover:text-zinc-200')

                        {{-- With a score to copy, the new variation starts as a duplicate of it; without one there is nothing to copy yet. --}}
                        @if ($score && $linksOnly)
                            <button type="button" wire:click="addVariation" class="{{ $variationButtonClasses }}">
                                <flux:icon name="plus" variant="micro" class="shrink-0" />
                                {{ __('Add variation') }}
                            </button>
                        @elseif ($score)
                            <button type="button" x-on:click="addVariation()" x-bind:disabled="savingScore"
                                    class="{{ $variationButtonClasses }}">
                                <flux:icon name="plus" variant="micro" class="shrink-0" />
                                {{ __('Add variation') }}
                            </button>
                        @else
                            <a href="{{ route('scores.create', ['music' => $musicId]) }}" wire:navigate
                               class="{{ $variationButtonClasses }}">
                                <flux:icon name="plus" variant="micro" class="shrink-0" />
                                {{ __('Add variation') }}
                            </a>
                        @endif
                    </div>
                </div>
            @endif

            <style>
                .score-editor-source-pane.overflow-hidden textarea {
                    flex: 1 1 0% !important;
                    min-height: 0 !important;
                    max-height: none !important;
                    resize: none !important;
                }
            </style>
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

            <div
                :class="splitScreen ? 'fixed inset-0 z-[60] flex flex-col overflow-hidden bg-white dark:bg-zinc-900' : ''">
                @unless($linksOnly)
                {{-- Compact header shown only in split-screen mode --}}
                <div x-show="splitScreen" x-cloak class="flex h-10 shrink-0 items-center gap-3 border-b border-zinc-200 px-3 dark:border-zinc-700">
                    <span x-text="$wire.title || '…'" class="min-w-0 truncate text-sm font-medium text-zinc-700 dark:text-zinc-200"></span>
                    <span class="shrink-0 rounded bg-zinc-100 px-1.5 py-0.5 font-mono text-xs text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400" x-text="($wire.format ?? '').toUpperCase()"></span>
                    <div class="ml-auto flex shrink-0 items-center gap-1">
                        @if(!$isGuest)
                        <span
                            x-show="autosaveState"
                            x-cloak
                            x-text="autosaveLabel()"
                            class="text-xs text-zinc-500 dark:text-zinc-400"
                            :class="autosaveState === 'failed' ? 'text-red-600 dark:text-red-400' : ''"></span>
                        <flux:button size="sm" variant="primary" icon="pencil" x-on:click="saveScore()" x-bind:disabled="savingScore">{{ __('Save') }}</flux:button>
                        @endif
                        <flux:button size="sm" variant="ghost" icon="arrows-pointing-in" x-on:click="toggleSplitScreen()">{{ __('Exit') }}</flux:button>
                    </div>
                </div>
                <div :class="splitScreen ? 'flex flex-1 flex-col overflow-hidden' : 'xl:grid xl:grid-cols-12 xl:gap-6'">
                    <div
                        class="score-editor-source-pane"
                        :class="splitScreen ? 'shrink-0 overflow-hidden border-b border-zinc-200 p-3 dark:border-zinc-700 flex flex-col' : 'xl:col-span-5'"
                        :style="splitScreen ? 'height:' + splitEditorHeight + 'px' : ''">
                        <div x-show="$wire.format === 'abc'" x-cloak class="mb-2 flex flex-wrap items-center gap-x-4 gap-y-1">
                            <div x-show="!splitScreen">
                                <flux:button size="sm" variant="ghost" icon="arrows-pointing-out" x-on:click="toggleSplitScreen()">
                                    {{ __('Full screen editor')}}
                                </flux:button>
                            </div>
                            <flux:link :href="route('abc.guide')" target="_blank" class="text-sm text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200">
                                <flux:icon name="book-open" class="mr-1 inline" />{{ __('ABC guide') }}
                            </flux:link>
                            <flux:button size="sm" variant="ghost" icon="table-cells" x-on:click="$flux.modal('abc-cheatsheet').show()">
                                {{ __('Cheatsheet') }}
                            </flux:button>
                            <flux:button size="sm" variant="ghost" icon="arrow-down-tray" x-on:click="saveScoreFile()">
                                {{ __('Save as .abc file') }}
                            </flux:button>
                            <flux:button size="sm" variant="ghost" icon="folder-open" x-on:click="openScoreFile()">
                                {{ __('Open .abc file') }}
                            </flux:button>
                            <flux:button size="sm" variant="ghost" icon="arrow-down-on-square" x-on:click="$flux.modal('diatar-import').show()">
                                {{ __('Import from Diatar') }}
                            </flux:button>
                        </div>

                        <flux:modal name="diatar-import" class="max-w-2xl">
                            <div class="space-y-4">
                                <flux:heading size="lg">{{ __('Import from Diatar') }}</flux:heading>
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('Paste a Diatar score below. Converting replaces the editor content with the generated ABC notation. Not everything may be converted correctly, so please check the result.') }}
                                </flux:text>
                                <flux:textarea x-model="diatarSource" rows="8" class="font-mono text-sm" placeholder="\K-5kGE2[?r81f;Ki\K1d;ált\K1f]?;sunk,"></flux:textarea>
                                <div class="flex justify-end gap-2">
                                    <flux:button variant="ghost" x-on:click="$flux.modal('diatar-import').close()">
                                        {{ __('Cancel') }}
                                    </flux:button>
                                    <flux:button variant="primary" icon="arrow-right" x-on:click="convertDiatarToAbc()" x-bind:disabled="!diatarSource.trim()">
                                        {{ __('Convert') }}
                                    </flux:button>
                                </div>
                            </div>
                        </flux:modal>

                        <flux:modal name="abc-cheatsheet" class="max-w-2xl">
                            <div class="space-y-4">
                                <flux:heading size="lg">ABC – gyorssegéd</flux:heading>

                                <div class="prose prose-zinc dark:prose-invert max-w-none
                                        prose-h2:mt-4 prose-h2:text-sm prose-h2:font-semibold prose-h2:border-b prose-h2:border-zinc-200 dark:prose-h2:border-zinc-700 prose-h2:pb-1
                                        prose-table:text-xs prose-td:py-0.5 prose-td:pr-3
                                        prose-code:bg-zinc-100 prose-code:px-1 prose-code:rounded prose-code:text-xs prose-code:text-blue-700 dark:prose-code:bg-zinc-800 dark:prose-code:text-blue-400">
                                    {!! $this->abcCheatsheetHtml !!}
                                </div>

                                <div class="flex justify-end">
                                    <flux:button variant="ghost" x-on:click="$flux.modal('abc-cheatsheet').close()">
                                        {{ __('Close') }}
                                    </flux:button>
                                </div>
                            </div>
                        </flux:modal>

                        <div x-show="$wire.format === 'chordpro'" x-cloak class="mb-2 flex flex-wrap items-center gap-x-4 gap-y-1">
                            <div x-show="!splitScreen">
                                <flux:button size="sm" variant="ghost" icon="arrows-pointing-out" x-on:click="toggleSplitScreen()">
                                    {{ __('Full screen editor')}}
                                </flux:button>
                            </div>
                            <flux:link href="https://www.chordpro.org/chordpro/chordpro-introduction/" target="_blank" class="text-sm text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200">
                                <flux:icon name="book-open" class="mr-1 inline" />{{ __('ChordPro guide') }}
                            </flux:link>
                            <flux:button size="sm" variant="ghost" icon="table-cells" x-on:click="$flux.modal('chordpro-cheatsheet').show()">
                                {{ __('Cheatsheet') }}
                            </flux:button>
                            <flux:button size="sm" variant="ghost" icon="arrow-down-tray" x-on:click="saveScoreFile()">
                                {{ __('Save as .cho file') }}
                            </flux:button>
                            <flux:button size="sm" variant="ghost" icon="folder-open" x-on:click="openScoreFile()">
                                {{ __('Open .cho file') }}
                            </flux:button>
                        </div>

                        <flux:modal name="chordpro-cheatsheet" class="max-w-2xl">
                            <div class="space-y-4">
                                <flux:heading size="lg">ChordPro – gyorssegéd</flux:heading>

                                <div class="prose prose-zinc dark:prose-invert max-w-none
                                        prose-h2:mt-4 prose-h2:text-sm prose-h2:font-semibold prose-h2:border-b prose-h2:border-zinc-200 dark:prose-h2:border-zinc-700 prose-h2:pb-1
                                        prose-table:text-xs prose-td:py-0.5 prose-td:pr-3
                                        prose-code:bg-zinc-100 prose-code:px-1 prose-code:rounded prose-code:text-xs prose-code:text-blue-700 dark:prose-code:bg-zinc-800 dark:prose-code:text-blue-400">
                                    {!! $this->chordproCheatsheetHtml !!}
                                </div>

                                <div class="flex justify-end">
                                    <flux:button variant="ghost" x-on:click="$flux.modal('chordpro-cheatsheet').close()">
                                        {{ __('Close') }}
                                    </flux:button>
                                </div>
                            </div>
                        </flux:modal>

                        <div x-show="$wire.format === 'gabc'" x-cloak class="mb-2 flex flex-wrap items-center gap-x-4 gap-y-1">
                            <div x-show="!splitScreen">
                                <flux:button size="sm" variant="ghost" icon="arrows-pointing-out" x-on:click="toggleSplitScreen()">
                                    {{ __('Full screen editor')}}
                                </flux:button>
                            </div>
                            <flux:link href="https://gregorio-project.github.io/gabc/" target="_blank" class="text-sm text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200">
                                <flux:icon name="book-open" class="mr-1 inline" />{{ __('GABC guide') }}
                            </flux:link>
                            <flux:button size="sm" variant="ghost" icon="table-cells" x-on:click="$flux.modal('gabc-cheatsheet').show()">
                                {{ __('Cheatsheet') }}
                            </flux:button>
                            <flux:button size="sm" variant="ghost" icon="arrow-down-tray" x-on:click="saveScoreFile()">
                                {{ __('Save as .gabc file') }}
                            </flux:button>
                            <flux:button size="sm" variant="ghost" icon="folder-open" x-on:click="openScoreFile()">
                                {{ __('Open .gabc file') }}
                            </flux:button>
                        </div>

                        <flux:modal name="gabc-cheatsheet" class="max-w-2xl">
                            <div class="space-y-4">
                                <flux:heading size="lg">GABC – gyorssegéd</flux:heading>

                                <div class="prose prose-zinc dark:prose-invert max-w-none
                                        prose-h2:mt-4 prose-h2:text-sm prose-h2:font-semibold prose-h2:border-b prose-h2:border-zinc-200 dark:prose-h2:border-zinc-700 prose-h2:pb-1
                                        prose-table:text-xs prose-td:py-0.5 prose-td:pr-3
                                        prose-code:bg-zinc-100 prose-code:px-1 prose-code:rounded prose-code:text-xs prose-code:text-blue-700 dark:prose-code:bg-zinc-800 dark:prose-code:text-blue-400">
                                    {!! $this->gabcCheatsheetHtml !!}
                                </div>

                                <div class="flex justify-end">
                                    <flux:button variant="ghost" x-on:click="$flux.modal('gabc-cheatsheet').close()">
                                        {{ __('Close') }}
                                    </flux:button>
                                </div>
                            </div>
                        </flux:modal>

                        <div x-show="$wire.format === 'aretino'" x-cloak class="mb-2 flex flex-wrap items-center gap-x-4 gap-y-1">
                            <div x-show="!splitScreen">
                                <flux:button size="sm" variant="ghost" icon="arrows-pointing-out" x-on:click="toggleSplitScreen()">
                                    {{ __('Full screen')}}
                                </flux:button>
                            </div>
                            <flux:link :href="route('aretino.guide')" target="_blank" class="text-sm text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200">
                                <flux:icon name="book-open" class="mr-1 inline" />{{ __('Guide') }}
                            </flux:link>
                            <flux:button size="sm" variant="ghost" icon="table-cells" x-on:click="$flux.modal('aretino-cheatsheet').show()">
                                {{ __('Cheatsheet') }}
                            </flux:button>
                            <flux:button size="sm" variant="ghost" icon="arrow-down-tray" x-on:click="saveScoreFile()">
                                {{ __('Save as .aretino file') }}
                            </flux:button>
                            <flux:button size="sm" variant="ghost" icon="folder-open" x-on:click="openScoreFile()">
                                {{ __('Open .aretino file') }}
                            </flux:button>
                            <flux:button size="sm" variant="ghost" icon="arrow-down-on-square" x-on:click="$flux.modal('gabc-import').show()">
                                {{ __('Import from Gregorio') }}
                            </flux:button>
                            <flux:button size="sm" variant="ghost" icon="arrow-down-on-square" x-on:click="$flux.modal('guido-import').show()">
                                {{ __('Import from Guido') }}
                            </flux:button>
                        </div>

                        <flux:modal name="gabc-import" class="max-w-2xl">
                            <div class="space-y-4">
                                <flux:heading size="lg">{{ __('Import from Gregorio') }}</flux:heading>
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('Paste a Gregorio (GABC) score below. Converting replaces the editor content with the generated Aretino notation. Not everything may be converted correctly, so please check the result.') }}
                                </flux:text>
                                <flux:textarea x-model="gabcSource" rows="8" class="font-mono text-sm" placeholder="(c3) Ky(f)ri(g)e(h) e(j)le(i)i(h)son(g.)"></flux:textarea>
                                <div class="flex justify-end gap-2">
                                    <flux:button variant="ghost" x-on:click="$flux.modal('gabc-import').close()">
                                        {{ __('Cancel') }}
                                    </flux:button>
                                    <flux:button variant="primary" icon="arrow-right" x-on:click="convertGabcToAretino()" x-bind:disabled="!gabcSource.trim()">
                                        {{ __('Convert') }}
                                    </flux:button>
                                </div>
                            </div>
                        </flux:modal>

                        <flux:modal name="guido-import" class="max-w-2xl">
                            <div class="space-y-4">
                                <flux:heading size="lg">{{ __('Import from Guido') }}</flux:heading>
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('Paste the Guido notes and lyrics separately below. Converting replaces the editor content with the generated Aretino notation. Not everything may be converted correctly, so please check the result.') }}
                                </flux:text>
                                <flux:textarea x-model="guidoNotesSource" rows="6" class="font-mono text-sm" :label="__('Notation')"></flux:textarea>
                                <flux:textarea x-model="guidoTextSource" rows="6" class="font-mono text-sm" :label="__('Lyrics')"></flux:textarea>
                                <div class="flex justify-end gap-2">
                                    <flux:button variant="ghost" x-on:click="$flux.modal('guido-import').close()">
                                        {{ __('Cancel') }}
                                    </flux:button>
                                    <flux:button variant="primary" icon="arrow-right" x-on:click="convertGuidoToAretino()" x-bind:disabled="!guidoNotesSource.trim() && !guidoTextSource.trim()">
                                        {{ __('Convert') }}
                                    </flux:button>
                                </div>
                            </div>
                        </flux:modal>

                        <flux:modal name="aretino-cheatsheet" class="max-w-2xl">
                            <div class="space-y-4">
                                <flux:heading size="lg">Aretino – gyorssegéd</flux:heading>

                                <div class="prose prose-zinc dark:prose-invert max-w-none
                                        prose-h2:mt-4 prose-h2:text-sm prose-h2:font-semibold prose-h2:border-b prose-h2:border-zinc-200 dark:prose-h2:border-zinc-700 prose-h2:pb-1
                                        prose-table:text-xs prose-td:py-0.5 prose-td:pr-3
                                        prose-code:bg-zinc-100 prose-code:px-1 prose-code:rounded prose-code:text-xs prose-code:text-blue-700 dark:prose-code:bg-zinc-800 dark:prose-code:text-blue-400">
                                    {!! $this->cheatsheetHtml !!}
                                </div>

                                <div class="flex justify-end">
                                    <flux:button variant="ghost" x-on:click="$flux.modal('aretino-cheatsheet').close()">
                                        {{ __('Close') }}
                                    </flux:button>
                                </div>
                            </div>
                        </flux:modal>

                        {{-- Source editor --}}
                        <flux:field required x-bind:class="splitScreen ? 'flex-1 min-h-0 flex flex-col' : ''">
                            <div x-show="$wire.format !== 'aretino'" x-cloak x-bind:class="splitScreen ? 'flex-1 min-h-0 flex flex-col' : ''">
                                <flux:textarea
                                    wire:model="content"
                                    rows="10"
                                    x-bind:class="splitScreen ? 'font-mono text-sm flex-1 min-h-0' : 'font-mono text-sm xl:min-h-[500px]'"
                                    :placeholder="__('Type the score here')"
                                    x-ref="contentTextarea"
                                    x-on:input="handleEditorContentInput($event.target.value)"
                                    x-on:click="updateAretinoHighlight && updateAretinoHighlight()"
                                    x-on:keyup="updateAretinoHighlight && updateAretinoHighlight()"
                                    x-on:select="updateAretinoHighlight && updateAretinoHighlight()"
                                    x-on:focus="updateAretinoHighlight && updateAretinoHighlight()" />
                            </div>
                            <flux:text x-show="$wire.format === 'aretino'">Használd a <kbd>Ctrl</kbd>+<kbd>Space</kbd>-t az automatikus kiegészítéshez</flux:text>
                            <aretino-editor
                                preview="false"
                                toolbar="true"
                                x-show="$wire.format === 'aretino'"
                                x-cloak
                                x-ref="aretinoEditor"
                                wire:ignore
                                :class="splitScreen ? 'score-editor-aretino-source flex-1 min-h-0 split-screen' : 'score-editor-aretino-source'"
                                x-on:change="handleEditorContentInput($event.detail.value)"
                                x-on:selectionchange="updateAretinoHighlight()"
                                x-on:focusout="hideSvgHoverTooltip()"></aretino-editor>

                            <flux:error name="content" />
                        </flux:field>

                        <div x-show="localContent.trim() === '' || localContent === minimalExamples[$wire.format]" class="flex">
                            <flux:button size="sm" variant="ghost" icon="light-bulb" x-on:click="fillExample()">
                                {{ __('Show me an example') }}
                            </flux:button>
                        </div>
                    </div>{{-- end editor col --}}

                    {{-- Drag handle (split-screen mode only) --}}
                    <div
                        x-show="splitScreen"
                        x-cloak
                        class="group flex h-2.5 shrink-0 cursor-row-resize select-none items-center justify-center bg-zinc-200 transition-colors hover:bg-blue-400 dark:bg-zinc-700 dark:hover:bg-blue-600"
                        @mousedown.prevent="splitDragStart($event)"
                        @touchstart.prevent="splitDragStart($event)">
                        <div class="h-1 w-10 rounded-full bg-zinc-400 transition-colors group-hover:bg-white dark:bg-zinc-500 dark:group-hover:bg-white"></div>
                    </div>

                    <div :class="splitScreen ? 'flex-1 overflow-y-auto overflow-x-hidden p-4' : 'mt-4 xl:mt-0 xl:col-span-7'">

                        {{-- GABC Settings Toolbar --}}
                        <div x-show="$wire.format === 'gabc'" x-cloak class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800/50">
                            <div class="flex items-center gap-1">
                                <flux:tooltip :content="__('Page ratio')">
                                    <flux:icon name="proportions" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                </flux:tooltip>
                                <flux:select size="sm" x-model="pageRatio" class="w-28 text-xs">
                                    <flux:select.option value="paper">{{ __('Paper') }}</flux:select.option>
                                    <flux:select.option value="responsive">{{ __('Responsive') }}</flux:select.option>
                                    <flux:select.option value="16/9">16:9</flux:select.option>
                                    <flux:select.option value="4/3">4:3</flux:select.option>
                                    <flux:select.option value="1/1">1:1</flux:select.option>
                                </flux:select>
                            </div>

                            <div class="flex items-center gap-1">
                                <flux:tooltip :content="__('Lyric size (pt)')">
                                    <flux:icon name="a-large-small" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                </flux:tooltip>
                                <flux:input size="sm" type="number" x-model="lyricSize" min="8" max="60" step="1" class="w-16!" />
                            </div>

                            <div class="flex items-center gap-1">
                                <flux:tooltip :content="__('Font')">
                                    <flux:icon name="type-outline" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                </flux:tooltip>
                                <flux:select size="sm" x-model="lyricFont" class="w-40 text-xs">
                                    <flux:select.option value="'EB Garamond'">EB Garamond</flux:select.option>
                                    <flux:select.option value="'Lora'">Lora</flux:select.option>
                                    <flux:select.option value="'Inter'">Inter</flux:select.option>
                                    <flux:select.option value="'Barlow Condensed'">Barlow Condensed</flux:select.option>
                                </flux:select>
                            </div>

                            <div class="flex items-center gap-1">
                                <flux:tooltip :content="__('Drop caps')">
                                    <flux:icon name="text-initial" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                </flux:tooltip>
                                <flux:switch x-model="dropCaps" />
                            </div>

                            <div class="flex items-center gap-1">
                                <flux:tooltip :content="__('Staff size (mm)')">
                                    <flux:icon name="list-chevrons-up-down" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                </flux:tooltip>
                                <flux:input size="sm" type="number" x-model="staffSize" min="30" max="300" step="5" class="w-16!" />
                            </div>

                            <div class="flex items-center gap-1">
                                <flux:tooltip :content="__('Space between lines')">
                                    <flux:icon name="between-horizontal-start" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                </flux:tooltip>
                                <flux:input size="sm" type="number" x-model="spaceBetweenSystems" min="-2" max="2" step="0.1" class="w-16!" />
                            </div>

                            <div class="flex items-center gap-1">
                                <flux:tooltip :content="__('Min. space below staff')">
                                    <flux:icon name="align-vertical-space-around" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                </flux:tooltip>
                                <flux:input size="sm" type="number" x-model="minSpaceBelowStaff" min="-2" max="2" step="0.1" class="w-16!" />
                            </div>

                            <div class="flex items-center gap-1" x-show="!['16/9', '4/3', '1/1'].includes(pageRatio)">
                                <flux:tooltip :content="__('Zoom (%)')">
                                    <flux:icon name="zoom-in" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                </flux:tooltip>
                                <flux:input size="sm" type="number" x-model="zoom" min="50" max="300" step="5" class="w-16!" />
                            </div>

                            <div class="h-5 w-px shrink-0 bg-zinc-300 dark:bg-zinc-600"></div>

                            <div class="flex items-center gap-1">
                                <flux:tooltip :content="__('Word spacing (px)')">
                                    <flux:icon name="space" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                </flux:tooltip>
                                <flux:input size="sm" type="number" x-model="minLyricWordSpacing" min="0" max="40" step="1" class="w-16!" />
                            </div>

                            <div class="flex items-center gap-1">
                                <flux:tooltip :content="__('Hyphen width (px)')">
                                    <flux:icon name="minus" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                </flux:tooltip>
                                <flux:input size="sm" type="number" x-model="hyphenWidth" min="0" max="40" step="1" class="w-16!" />
                            </div>

                            <div class="flex items-center gap-1">
                                <flux:tooltip :content="__('Condensing tolerance')">
                                    <flux:icon name="ruler-dimension-line" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                </flux:tooltip>
                                <flux:input size="sm" type="number" x-model="condensingTolerance" min="0" max="1" step="0.05" class="w-16!" />
                            </div>

                            <flux:tooltip :content="__('Reset to defaults')">
                                <flux:button icon="arrow-path" variant="ghost" x-on:click="resetToDefaults()" />
                            </flux:tooltip>

                            @if(!$isGuest)
                            <flux:tooltip :content="__('Save as my default for this ratio')">
                                <flux:button icon="bookmark" variant="ghost" x-on:click="saveAsDefault()" />
                            </flux:tooltip>
                            @endif

                        </div>

                        {{-- ChordPro Settings Toolbar --}}
                        <div x-show="$wire.format === 'chordpro'" x-cloak class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800/50">
                            <div class="flex items-center gap-1">
                                <flux:tooltip :content="__('Font size (pt)')">
                                    <flux:icon name="a-large-small" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                </flux:tooltip>
                                <flux:input size="sm" type="number" x-model="chordproFontSize" min="10" max="32" step="1" class="w-16!" />
                            </div>

                            <div class="flex items-center gap-1">
                                <flux:tooltip :content="__('Font')">
                                    <flux:icon name="type-outline" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                </flux:tooltip>
                                <flux:select size="sm" x-model="chordproFontFamily" class="w-40 text-xs">
                                    <flux:select.option value="'EB Garamond'">EB Garamond</flux:select.option>
                                    <flux:select.option value="'Lora'">Lora</flux:select.option>
                                    <flux:select.option value="'Inter'">Inter</flux:select.option>
                                    <flux:select.option value="'Barlow Condensed'">Barlow Condensed</flux:select.option>
                                </flux:select>
                            </div>

                            <div class="flex items-center gap-1">
                                <flux:tooltip :content="__('Columns')">
                                    <flux:icon name="view-columns" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                </flux:tooltip>
                                <flux:select size="sm" x-model="chordproColumns" class="w-16 text-xs">
                                    <flux:select.option value="1">1</flux:select.option>
                                    <flux:select.option value="2">2</flux:select.option>
                                    <flux:select.option value="3">3</flux:select.option>
                                </flux:select>
                            </div>

                            <div class="h-5 w-px shrink-0 bg-zinc-300 dark:bg-zinc-600"></div>

                            <div class="flex items-center gap-1">
                                <flux:tooltip :content="__('Transpose (semitones)')">
                                    <flux:icon name="musical-note" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                </flux:tooltip>
                                <flux:input size="sm" type="number" x-model="chordproTranspose" min="-11" max="11" step="1" class="w-16!" />
                            </div>

                            <div class="flex items-center gap-1">
                                <flux:tooltip :content="__('German notation (H = B, B = B♭)')">
                                    <span class="text-xs font-bold text-zinc-500 dark:text-zinc-400">H</span>
                                </flux:tooltip>
                                <flux:switch x-model="chordproGermanNotation" />
                            </div>

                            <flux:tooltip :content="__('Reset to defaults')">
                                <flux:button icon="arrow-path" variant="ghost" x-on:click="resetToDefaults()" />
                            </flux:tooltip>

                            @if(!$isGuest)
                            <flux:tooltip :content="__('Save as my default')">
                                <flux:button icon="bookmark" variant="ghost" x-on:click="saveAsDefault()" />
                            </flux:tooltip>
                            @endif
                        </div>

                        {{-- ABC Settings Toolbar --}}
                        <div x-show="$wire.format === 'abc'" x-cloak class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800/50">
                            <div class="flex items-center gap-1">
                                <flux:tooltip :content="__('Page ratio')">
                                    <flux:icon name="proportions" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                </flux:tooltip>
                                <flux:select size="sm" x-model="abcPageRatio" class="w-28 text-xs">
                                    <flux:select.option value="paper">{{ __('Paper') }}</flux:select.option>
                                    <flux:select.option value="responsive">{{ __('Responsive') }}</flux:select.option>
                                    <flux:select.option value="16/9">16:9</flux:select.option>
                                    <flux:select.option value="4/3">4:3</flux:select.option>
                                    <flux:select.option value="1/1">1:1</flux:select.option>
                                </flux:select>
                            </div>

                            <div class="flex items-center gap-1">
                                <flux:tooltip :content="__('Lyric size (pt)')">
                                    <flux:icon name="a-large-small" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                </flux:tooltip>
                                <flux:input size="sm" type="number" x-model="abcLyricSize" min="8" max="60" step="1" class="w-16!" />
                            </div>

                            <div class="flex items-center gap-1">
                                <flux:tooltip :content="__('Font')">
                                    <flux:icon name="type-outline" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                </flux:tooltip>
                                <flux:select size="sm" x-model="abcLyricFont" class="w-40 text-xs">
                                    <flux:select.option value="EB Garamond">EB Garamond</flux:select.option>
                                    <flux:select.option value="Lora">Lora</flux:select.option>
                                    <flux:select.option value="Inter">Inter</flux:select.option>
                                    <flux:select.option value="Barlow Condensed">Barlow Condensed</flux:select.option>
                                </flux:select>
                            </div>

                            <div class="flex items-center gap-1">
                                <flux:tooltip :content="__('Bold lyrics')">
                                    <flux:icon name="bold" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                </flux:tooltip>
                                <flux:checkbox x-model="abcLyricBold" />
                            </div>

                            <div class="flex items-center gap-1">
                                <flux:tooltip :content="__('Note spacing')">
                                    <flux:icon name="space" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                </flux:tooltip>
                                <flux:input size="sm" type="number" x-model="abcNoteSpacing" min="1" max="3" step="0.1" class="w-16!" />
                            </div>

                            <div class="flex items-center gap-1">
                                <flux:tooltip :content="__('Staff separation')">
                                    <flux:icon name="between-horizontal-start" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                </flux:tooltip>
                                <flux:input size="sm" type="number" x-model="abcStaffSep" min="15" max="120" step="1" class="w-16!" />
                            </div>

                            <div class="flex items-center gap-1">
                                <flux:tooltip :content="__('Vocal space (pt)')">
                                    <flux:icon name="align-vertical-space-around" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                </flux:tooltip>
                                <flux:input size="sm" type="number" x-model="abcVocalSpace" min="0" max="40" step="1" class="w-16!" />
                            </div>

                            <div class="flex items-center gap-1">
                                <flux:tooltip :content="__('Staff size')">
                                    <flux:icon name="list-chevrons-up-down" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                </flux:tooltip>
                                <flux:input size="sm" type="number" x-model="abcPageScale" min="1" max="5" step="0.1" class="w-16!" />
                            </div>

                            <div class="flex items-center gap-1" x-show="abcPageRatio === 'paper'">
                                <flux:tooltip :content="__('Page width (px)')">
                                    <flux:icon name="ruler" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                </flux:tooltip>
                                <flux:input size="sm" type="number" x-model="abcPageWidth" x-on:change="abcPageWidth = normalizeAbcPageWidth(abcPageWidth)" min="400" max="4000" step="10" class="w-20" />
                            </div>

                            <div class="flex items-center gap-1" x-show="!['16/9', '4/3', '1/1'].includes(abcPageRatio)">
                                <flux:tooltip :content="__('Zoom (%)')">
                                    <flux:icon name="zoom-in" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                </flux:tooltip>
                                <flux:input size="sm" type="number" x-model="abcZoom" min="50" max="300" step="5" class="w-16!" />
                            </div>

                            <div class="flex items-center gap-1">
                                <flux:tooltip :content="__('Hide clefs from second measure onward')">
                                    <flux:icon name="clef-none" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                </flux:tooltip>
                                <flux:checkbox x-model="abcNoClef" />
                            </div>

                            <div class="flex items-center gap-1">
                                <flux:tooltip :content="__('Transpose (semitones)')">
                                    <flux:icon name="musical-note" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                </flux:tooltip>
                                <flux:input size="sm" type="number" x-model="abcTranspose" min="-11" max="11" step="1" class="w-16!" />
                            </div>

                            <div class="h-5 w-px shrink-0 bg-zinc-300 dark:bg-zinc-600"></div>

                            <div class="flex items-center gap-1">
                                <flux:tooltip :content="__('Stem width')">
                                    <flux:icon name="pencil-line" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                </flux:tooltip>
                                <flux:input size="sm" type="number" x-model="abcStemWidth" min="0.1" max="3" step="0.1" class="w-16!" />
                            </div>

                            <div class="flex items-center gap-1">
                                <flux:tooltip :content="__('Staff line width')">
                                    <flux:icon name="bars-3" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                </flux:tooltip>
                                <flux:input size="sm" type="number" x-model="abcStaffLineWidth" min="0.1" max="3" step="0.1" class="w-16!" />
                            </div>

                            <flux:tooltip :content="__('Reset to defaults')">
                                <flux:button icon="arrow-path" variant="ghost" x-on:click="resetToDefaults()" />
                            </flux:tooltip>

                            @if(!$isGuest)
                            <flux:tooltip :content="__('Save as my default for this ratio')">
                                <flux:button icon="bookmark" variant="ghost" x-on:click="saveAsDefault()" />
                            </flux:tooltip>
                            @endif

                        </div>

                        {{-- ABC Preview --}}
                        <div x-show="$wire.format === 'abc'" x-cloak class="mt-4">
                            <div x-ref="abcPreview" class="min-h-16 space-y-4" wire:ignore></div>
                        </div>

                        {{-- GABC Preview --}}
                        <div x-show="$wire.format === 'gabc'" x-cloak class="mt-4">
                            <div x-ref="preview" class="min-h-16 space-y-4" wire:ignore></div>
                        </div>

                        {{-- Aretino Settings Toolbar --}}
                        <div x-show="$wire.format === 'aretino'" x-cloak class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800/50">
                            <div class="flex items-center gap-1">
                                <flux:tooltip :content="__('Page ratio')">
                                    <flux:icon name="proportions" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                </flux:tooltip>
                                <flux:select size="sm" x-model="aretinoPageRatio" class="w-28 text-xs">
                                    <flux:select.option value="paper">{{ __('Paper') }}</flux:select.option>
                                    <flux:select.option value="responsive">{{ __('Responsive') }}</flux:select.option>
                                    <flux:select.option value="16/9">16:9</flux:select.option>
                                    <flux:select.option value="4/3">4:3</flux:select.option>
                                    <flux:select.option value="1/1">1:1</flux:select.option>
                                </flux:select>
                            </div>

                            <div class="flex items-center gap-1">
                                <flux:tooltip :content="__('Lyric size (pt)')">
                                    <flux:icon name="a-large-small" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                </flux:tooltip>
                                <flux:input size="sm" type="number" x-model="aretinoLyricSize" min="6" max="80" step="1" class="w-16!" />
                            </div>

                            <div class="flex items-center gap-1">
                                <flux:tooltip :content="__('Font')">
                                    <flux:icon name="type-outline" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                </flux:tooltip>
                                <flux:select size="sm" x-model="aretinoTextFont" class="w-40 text-xs">
                                    <flux:select.option value="'EB Garamond'">EB Garamond</flux:select.option>
                                    <flux:select.option value="'Lora'">Lora</flux:select.option>
                                    <flux:select.option value="'Inter'">Inter</flux:select.option>
                                    <flux:select.option value="'Barlow Condensed'">Barlow Condensed</flux:select.option>
                                </flux:select>
                            </div>

                            <div class="flex items-center gap-1">
                                <flux:tooltip :content="__('Staff size (mm)')">
                                    <flux:icon name="list-chevrons-up-down" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                </flux:tooltip>
                                <flux:input size="sm" type="number" x-model="aretinoStaffSize" min="4" max="20" step="0.1" class="w-16!" />
                            </div>

                            <div class="flex items-center gap-1">
                                <flux:tooltip :content="__('Space between lines')">
                                    <flux:icon name="between-horizontal-start" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                </flux:tooltip>
                                <flux:input size="sm" type="number" x-model="aretinoStaffGap" min="0" max="10" step="0.5" class="w-16!" />
                            </div>

                            <div class="flex items-center gap-1" x-show="aretinoPageRatio === 'paper'">
                                <flux:tooltip :content="__('Staff width (mm)')">
                                    <flux:icon name="ruler" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                </flux:tooltip>
                                <flux:input size="sm" type="number" x-model="aretinoStaffWidth" min="50" max="400" step="1" class="w-16!" />
                            </div>

                            <div class="flex items-center gap-1" x-show="!['16/9', '4/3', '1/1'].includes(aretinoPageRatio)">
                                <flux:tooltip :content="__('Zoom (%)')">
                                    <flux:icon name="zoom-in" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                </flux:tooltip>
                                <flux:input size="sm" type="number" x-model="aretinoZoom" min="50" max="300" step="5" class="w-16!" />
                            </div>

                            <div class="flex items-center gap-1">
                                <flux:tooltip :content="__('Hide clef from second line onwards')">
                                    <flux:icon name="clef-none" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                </flux:tooltip>
                                <flux:checkbox x-model="aretinoHideRepeatClef" />
                            </div>

                            <flux:tooltip :content="__('Reset to defaults')">
                                <flux:button icon="arrow-path" variant="ghost" x-on:click="resetToDefaults()" />
                            </flux:tooltip>

                            @if(!$isGuest)
                            <flux:tooltip :content="__('Save as my default for this ratio')">
                                <flux:button icon="bookmark" variant="ghost" x-on:click="saveAsDefault()" />
                            </flux:tooltip>
                            @endif

                            <flux:tooltip :content="__('Show source tooltip on hover')">
                                <flux:button icon="eye" variant="ghost" x-on:click="svgHoverTooltip = !svgHoverTooltip; svgHoverTooltip ? $nextTick(() => { $refs.aretinoEditor?.focus(); updateAretinoHighlight(); }) : hideSvgHoverTooltip()" x-bind:class="svgHoverTooltip ? '!text-blue-600 dark:!text-blue-400' : ''" />
                            </flux:tooltip>

                            <div class="ml-auto flex items-center gap-1.5 rounded-md bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
                                <flux:icon name="cursor-arrow-rays" variant="micro" class="shrink-0" />
                                {{ __('Click a note/lyrics to jump to it in the editor') }}
                            </div>
                        </div>

                        {{-- Aretino Preview --}}
                        <div x-show="$wire.format === 'aretino'" x-cloak class="mt-4">
                            <div x-ref="aretinoPreview" class="min-h-16 space-y-4" wire:ignore x-on:click="handleAretinoPreviewClick($event)"></div>
                        </div>

                        {{-- ChordPro Preview --}}
                        <div x-show="$wire.format === 'chordpro'" x-cloak class="mt-4">
                            <div x-ref="chordproPreview" class="min-h-16 space-y-4" wire:ignore></div>

                            <div class="mt-2 flex flex-nowrap items-center justify-end gap-2" x-show="hasPages">
                                <span x-show="copyFeedback" x-text="copyFeedback" x-transition class="text-sm text-zinc-600 dark:text-zinc-300"></span>
                                <flux:button icon="link" variant="ghost" x-on:click="openShareModal()">
                                    Megosztás
                                </flux:button>
                                <flux:dropdown align="end">
                                    <flux:button icon="arrow-down-tray" icon:trailing="chevron-down" variant="ghost">
                                        {{ __('Export') }}
                                    </flux:button>
                                    <flux:menu>
                                        <flux:menu.item icon="clipboard-document-list" x-on:click="copyChordproPlainText()">
                                            {{ __('Copy as Text') }}
                                        </flux:menu.item>
                                        <flux:menu.item icon="clipboard" x-on:click="copyChordproHtml()">
                                            {{ __('Copy HTML') }}
                                        </flux:menu.item>
                                        <flux:menu.item icon="arrow-down-tray" x-on:click="exportChordproHtml()">
                                            {{ __('Export HTML') }}
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </div>

                        </div>
                    </div>{{-- end preview col --}}
                </div>{{-- end two-col grid --}}
                @endunless

                <flux:modal name="share-link-modal" class="max-w-md">
                    <div class="space-y-4">
                        <flux:heading size="lg">Kotta kölcsönadása</flux:heading>
                        @unless($linksOnly)
                        <flux:text>
                            {{ __('This link encodes the full score and all settings directly in the URL — no account or registration needed. Anyone with the link can open and preview the score instantly.') }}
                        </flux:text>
                        <div class="flex flex-col gap-3">
                            <flux:button icon="link" variant="primary" x-show="!shareUrlLoading && !shareModalCopied" x-on:click="copyShareLink()">
                                {{ __('Copy Link') }}
                            </flux:button>
                            <flux:button disabled icon="arrow-path" variant="primary" x-show="shareUrlLoading" x-cloak>
                                {{ __('Generating link…') }}
                            </flux:button>
                            <div x-show="shareModalCopied" x-cloak class="flex items-center gap-2 rounded-lg bg-green-50 px-4 py-3 text-green-700 dark:bg-green-900/30 dark:text-green-300">
                                <flux:icon name="check-circle" variant="micro" class="shrink-0" />
                                <span class="text-sm font-medium">{{ __('Link copied! You can now paste it anywhere.') }}</span>
                            </div>
                        </div>
                        @endunless

                        @if($score && !$isGuest)
                        <div class="border-t border-zinc-200 pt-4 dark:border-zinc-700" x-data="{ loanLinkCopied: false }">
                            <div class="flex items-center justify-between gap-2">
                                <flux:subheading class="font-medium">{{ __('Lending Link') }}</flux:subheading>
                                <div class="flex min-w-0 flex-1 items-center gap-2" x-show="$wire.loanLinkUrl" x-cloak>
                                    <flux:input readonly x-bind:value="$wire.loanLinkUrl ?? ''" class="min-w-0 flex-1 font-mono text-sm" />
                                    <flux:button
                                        icon="clipboard"
                                        variant="ghost"
                                        :title="__('Copy link')"
                                        x-on:click="navigator.clipboard.writeText($wire.loanLinkUrl).then(() => { loanLinkCopied = true; setTimeout(() => loanLinkCopied = false, 2000) })"
                                        x-bind:class="loanLinkCopied ? 'text-green-600' : ''" />
                                    <flux:button
                                        icon="trash"
                                        variant="ghost"
                                        :title="__('Recall the loan')"
                                        wire:click="recallLoan"
                                        wire:confirm="{{ __('Recall this loan? Anyone still holding the link will lose access.') }}" />
                                </div>
                                <div x-show="!$wire.loanLinkUrl">
                                    <flux:button icon="link" variant="ghost" wire:click="lendByLink">
                                        {{ __('Lend by link') }}
                                    </flux:button>
                                </div>
                            </div>
                            <flux:text class="mt-1 text-xs text-zinc-500" x-show="$wire.loanLinkUrl" x-cloak>
                                {{ __('Whoever holds this link may read the score and keep it. Recall the link to close it for everyone.') }}
                            </flux:text>
                            <flux:text class="mt-1 text-xs text-zinc-500" x-show="!$wire.loanLinkUrl">
                                {{ __('Lend this score by link: whoever holds it may read it and keep it.') }}
                            </flux:text>

                            @if($this->indirectLoans->isNotEmpty())
                            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-900/50 dark:bg-amber-900/20">
                                <div class="flex items-center gap-1.5">
                                    <flux:icon name="information-circle" variant="micro" class="shrink-0 text-amber-600 dark:text-amber-400" />
                                    <span class="text-sm font-medium text-amber-800 dark:text-amber-200">
                                        {{ __('Also reachable through') }}
                                    </span>
                                </div>
                                <flux:text class="mt-1 text-xs text-amber-700 dark:text-amber-300">
                                    {{ __('These secret links open this score too, even without a link of its own.') }}
                                </flux:text>
                                <div class="mt-2 space-y-1">
                                    @foreach($this->indirectLoans as $indirect)
                                    <div class="flex items-center justify-between gap-2" wire:key="indirect-share-{{ $indirect['revoke_id'] }}">
                                        <span class="min-w-0 truncate text-sm text-amber-900 dark:text-amber-100">{{ $indirect['label'] }}</span>
                                        <flux:button
                                            size="xs"
                                            variant="ghost"
                                            icon="trash"
                                            wire:click="revokeIndirectLoan({{ $indirect['revoke_id'] }})"
                                            wire:confirm="{{ __('Recall that loan entirely? Anyone still holding the link will lose access.') }}">
                                            {{ __('Recall') }}
                                        </flux:button>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif
                        </div>
                        @endif

                        <div class="flex justify-end">
                            <flux:button variant="ghost" x-on:click="$flux.modal('share-link-modal').close()">
                                {{ __('Close') }}
                            </flux:button>
                        </div>
                    </div>
                </flux:modal>

                @if(!$isGuest && $score)
                {{-- Folders modal --}}
                <flux:modal name="folders-modal" class="w-80"
                    x-data="{ newFolderName: '', showNewFolder: false }">
                    <div class="space-y-4">
                        <flux:heading size="lg">{{ __('Folders') }}</flux:heading>

                        @forelse($this->userFolders as $folder)
                            <div>
                                <flux:checkbox
                                    wire:key="folder-toggle-{{ $folder->id }}"
                                    wire:click="toggleFolder({{ $folder->id }})"
                                    :checked="in_array($folder->id, $folderIds)"
                                    :label="$folder->name" />
                            </div>
                        @empty
                            <flux:text class="text-sm text-zinc-500">{{ __('No folders yet.') }}</flux:text>
                        @endforelse

                        <div>
                            <flux:button size="sm" variant="ghost" icon="plus"
                                x-on:click="showNewFolder = !showNewFolder">
                                {{ __('New folder') }}
                            </flux:button>
                            <div x-show="showNewFolder" x-cloak class="mt-2 flex gap-2">
                                <flux:input
                                    x-model="newFolderName"
                                    :placeholder="__('Folder name')"
                                    size="sm"
                                    class="flex-1"
                                    x-on:keydown.enter="$wire.createFolderAndAdd(newFolderName); newFolderName = ''; showNewFolder = false" />
                                <flux:button
                                    size="sm"
                                    variant="primary"
                                    x-on:click="$wire.createFolderAndAdd(newFolderName); newFolderName = ''; showNewFolder = false">
                                    {{ __('Add') }}
                                </flux:button>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <flux:button variant="ghost" x-on:click="$flux.modal('folders-modal').close()">
                                {{ __('Close') }}
                            </flux:button>
                        </div>
                    </div>
                </flux:modal>
                @endif

                @if(!$isGuest)
                {{-- Uploaded sheet music files --}}
                <div
                    class="mt-6 border-t border-zinc-200 pt-6 dark:border-zinc-700"
                    x-show="!splitScreen"
                    x-on:score-file-saved.window="$flux.modal('score-file-edit').close()"
                    x-on:score-file-added.window="$flux.modal('score-file-add').close()"
                >
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <flux:heading size="sm">{{ __('Sheet music files') }}</flux:heading>

                        <flux:modal.trigger name="score-file-add">
                            <flux:button icon="plus" variant="outline" size="sm">{{ __('Add file') }}</flux:button>
                        </flux:modal.trigger>
                    </div>
                    @if($this->scoreFiles->isNotEmpty())
                    <div class="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach($this->scoreFiles as $file)
                        <div wire:key="score-file-{{ $file->id }}" class="flex flex-col justify-between gap-3 rounded-xl border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-800/50">
                            <div class="flex items-start gap-3">
                                @if($file->has_thumbnail)
                                <img src="{{ route('scores.file.thumbnail', ['score' => $score, 'scoreFile' => $file]) }}" alt="" class="h-16 w-auto shrink-0 rounded border border-zinc-200 bg-white object-contain dark:border-zinc-600" />
                                @else
                                <div class="flex h-16 w-12 shrink-0 items-center justify-center rounded border border-zinc-200 bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900">
                                    <flux:icon name="document-text" class="size-6 text-zinc-400" />
                                </div>
                                @endif

                                <div class="min-w-0 flex-1">
                                    <div class="truncate font-medium text-zinc-800 dark:text-zinc-100">{{ $file->displayName() }}</div>

                                    <div class="mt-0.5 flex flex-wrap items-center gap-1.5 text-xs text-zinc-500 dark:text-zinc-400">
                                        @if($file->label)
                                        <span class="truncate">{{ $file->original_name }}</span>
                                        <span>&middot;</span>
                                        @endif
                                        <span>{{ number_format($file->size_bytes / 1024, 0, ',', ' ') }} KB</span>
                                        @if($file->page_count)
                                        <span>&middot;</span>
                                        <span>{{ trans_choice(':count page|:count pages', $file->page_count, ['count' => $file->page_count]) }}</span>
                                        @endif
                                    </div>

                                    <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                        <flux:badge size="sm" color="zinc">{{ $file->rights->label() }}</flux:badge>
                                        @if($file->isRendering())
                                        <flux:badge size="sm" color="blue" class="gap-1">
                                            <flux:icon.loading class="size-3 shrink-0" />
                                            {{ $file->render_status->label() }}
                                        </flux:badge>
                                        @elseif($file->render_status !== \App\Enums\ScoreFileRenderStatus::Ready)
                                        <flux:badge size="sm" color="amber">{{ $file->render_status->label() }}</flux:badge>
                                        @endif
                                    </div>

                                    @if($file->render_error)
                                    <div class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $file->render_error }}</div>
                                    @endif
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-1 border-t border-zinc-100 pt-2 dark:border-zinc-700">
                                <x-score-file-pages
                                    icon-only
                                    name="score-file-pages-{{ $file->id }}"
                                    :pages="$this->filePageUrls[$file->id] ?? []"
                                    :heading="$file->displayName()" />

                                <flux:button
                                    icon="arrow-down-tray"
                                    variant="ghost"
                                    size="sm"
                                    :aria-label="__('Download')"
                                    :href="route('scores.file.download', ['score' => $score, 'scoreFile' => $file])" />

                                <flux:modal.trigger name="score-file-edit">
                                    <flux:button
                                        icon="pencil-square"
                                        variant="ghost"
                                        size="sm"
                                        :aria-label="__('Edit file')"
                                        wire:click="editFile({{ $file->id }})" />
                                </flux:modal.trigger>

                                <flux:button
                                    icon="trash"
                                    variant="ghost"
                                    size="sm"
                                    :aria-label="__('Remove file')"
                                    wire:click="deleteFile({{ $file->id }})"
                                    wire:confirm="{{ __('Remove this file?') }}" />
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endif

                    @if($this->filesRendering)
                    {{-- The render runs on the queue; poll until it lands rather than making the user reload. --}}
                    <div wire:poll.2s class="mb-4 flex items-center gap-2 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800/50">
                        <flux:icon.loading class="size-4 shrink-0 text-zinc-400" />
                        <flux:text class="text-sm">{{ __('Rendering the sheet music — this page updates on its own when it is ready.') }}</flux:text>
                    </div>
                    @endif

                    @if($pendingFile)
                    <div class="mt-4 flex items-center gap-2 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800/50">
                        <flux:icon name="document-text" variant="micro" class="shrink-0 text-zinc-400" />
                        @if($score)
                        <flux:button variant="primary" size="sm" icon="plus" wire:click="addFile">
                            {{ __('Add :name', ['name' => $pendingFile->getClientOriginalName()]) }}
                        </flux:button>
                        @else
                        <flux:text class="text-sm text-zinc-600 dark:text-zinc-300">
                            {{ __('Ready to save: :name', ['name' => $pendingFile->getClientOriginalName()]) }}
                        </flux:text>
                        @endif
                        <flux:button icon="x-mark" variant="ghost" size="sm" :aria-label="__('Remove file')" wire:click="removePendingFile" />
                    </div>
                    @elseif($this->scoreFiles->isEmpty())
                    <flux:text class="text-sm text-zinc-500">{{ __('No files yet.') }}</flux:text>
                    @endif

                    {{-- Staging a file is a dialog of its own: the fields only matter while one is being added. --}}
                    <flux:modal name="score-file-add" class="w-full max-w-lg">
                        <div class="space-y-4">
                            <flux:heading size="lg">{{ __('Add file') }}</flux:heading>

                            <flux:field>
                                <flux:label>{{ __('File') }}</flux:label>
                                <flux:input
                                    type="file"
                                    wire:model="pendingFile"
                                    accept=".mscz,.musicxml,.mxl,.mid,.midi,.pdf" />
                                <flux:description>
                                    {{ __('MuseScore (.mscz), MusicXML, MIDI or PDF. Max 25 MB. Never published — only you and the people you hand a secret link to can open it.') }}
                                </flux:description>
                                <flux:error name="pendingFile" />
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('Label') }}</flux:label>
                                <flux:input wire:model="fileLabel" :placeholder="__('Label, e.g. A4')" />
                                <flux:description>{{ __('What this file is, when a score carries several — the paper size it is cut for, say.') }}</flux:description>
                                <flux:error name="fileLabel" />
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('Rights') }}</flux:label>
                                <flux:select wire:model="fileRights">
                                    @foreach($rightsOptions as $rightsOption)
                                    <flux:select.option value="{{ $rightsOption->value }}">{{ $rightsOption->label() }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="fileRights" />
                            </flux:field>

                            <div wire:loading wire:target="pendingFile">
                                <flux:text class="text-xs text-zinc-500">{{ __('Uploading…') }}</flux:text>
                            </div>

                            @unless($score)
                            <flux:text class="text-sm text-zinc-500">{{ __('The file is stored when you save the score.') }}</flux:text>
                            @endunless

                            <div class="flex justify-end gap-2">
                                <flux:modal.close>
                                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                                </flux:modal.close>
                                @if($score)
                                <flux:button variant="primary" icon="plus" wire:click="addFile">{{ __('Add file') }}</flux:button>
                                @else
                                <flux:modal.close>
                                    <flux:button variant="primary">{{ __('Done') }}</flux:button>
                                </flux:modal.close>
                                @endif
                            </div>
                        </div>
                    </flux:modal>

                    {{-- One dialog serves every row: the button that opens it loads that row first. --}}
                    <flux:modal name="score-file-edit" class="w-full max-w-lg" wire:close="cancelFileEdit">
                        <div class="space-y-4">
                            <flux:heading size="lg">{{ __('Edit file') }}</flux:heading>

                            <flux:field>
                                <flux:label>{{ __('Label') }}</flux:label>
                                <flux:input wire:model="editingLabel" :placeholder="__('Label, e.g. A4')" />
                                <flux:description>{{ __('What this file is, when a score carries several — the paper size it is cut for, say.') }}</flux:description>
                                <flux:error name="editingLabel" />
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('Rights') }}</flux:label>
                                <flux:select wire:model="editingRights">
                                    @foreach($rightsOptions as $rightsOption)
                                    <flux:select.option value="{{ $rightsOption->value }}">{{ $rightsOption->label() }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="editingRights" />
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('Replace the file') }}</flux:label>
                                <flux:input
                                    type="file"
                                    wire:model="replacementFile"
                                    accept=".mscz,.musicxml,.mxl,.mid,.midi,.pdf" />
                                <flux:description>{{ __('Leave this empty to keep the file that is there now.') }}</flux:description>
                                <flux:error name="replacementFile" />
                            </flux:field>

                            <div wire:loading wire:target="replacementFile">
                                <flux:text class="text-xs text-zinc-500">{{ __('Uploading…') }}</flux:text>
                            </div>

                            <div class="flex justify-end gap-2">
                                <flux:modal.close>
                                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                                </flux:modal.close>
                                <flux:button variant="primary" wire:click="updateFile">{{ __('Save file') }}</flux:button>
                            </div>
                        </div>
                    </flux:modal>
                </div>
                @endif

                @if(!$isGuest)
                {{-- URL Management --}}
                <div
                    class="mt-6 border-t border-zinc-200 pt-6 dark:border-zinc-700"
                    x-show="!splitScreen"
                    x-on:score-url-added.window="$flux.modal('score-url-add').close()"
                >
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <flux:heading size="sm">{{ __('Links') }}</flux:heading>

                        <flux:modal.trigger name="score-url-add">
                            <flux:button icon="plus" variant="outline" size="sm">{{ __('Add Link') }}</flux:button>
                        </flux:modal.trigger>
                    </div>

                    @if($this->scoreUrls->isNotEmpty())
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach($this->scoreUrls as $scoreUrl)
                        <div wire:key="score-url-{{ $scoreUrl->exists ? 'saved-'.$scoreUrl->id : 'pending-'.$scoreUrl->pending_index }}" class="flex flex-col justify-between gap-3 rounded-xl border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-800/50">
                            <div class="flex items-start gap-3">
                                @if($scoreUrl->label instanceof \App\MusicUrlLabel)
                                <div class="flex size-10 shrink-0 items-center justify-center rounded border border-zinc-200 bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900">
                                    <flux:icon name="{{ $scoreUrl->label->icon() }}" class="size-5 {{ $scoreUrl->label->color() }}" />
                                </div>
                                @else
                                <div class="flex size-10 shrink-0 items-center justify-center rounded border border-zinc-200 bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900">
                                    <flux:icon name="link" class="size-5 text-zinc-400" />
                                </div>
                                @endif

                                <div class="min-w-0 flex-1">
                                    <a href="{{ $scoreUrl->url }}" target="_blank" rel="noopener noreferrer" class="block truncate text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">
                                        {{ $scoreUrl->url }}
                                    </a>
                                    @if($scoreUrl->comment)
                                    <div class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ $scoreUrl->comment }}</div>
                                    @endif
                                    @if($scoreUrl->label instanceof \App\MusicUrlLabel)
                                    <div class="mt-1.5">
                                        <flux:badge size="sm" color="zinc">{{ $scoreUrl->label->label() }}</flux:badge>
                                    </div>
                                    @endif
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-1 border-t border-zinc-100 pt-2 dark:border-zinc-700">
                                <flux:button
                                    icon="arrow-top-right-on-square"
                                    variant="ghost"
                                    size="sm"
                                    :aria-label="__('Open link')"
                                    :href="$scoreUrl->url"
                                    target="_blank" />

                                <flux:button
                                    icon="trash"
                                    variant="ghost"
                                    size="sm"
                                    :aria-label="__('Remove link')"
                                    wire:click="{{ $scoreUrl->exists ? 'deleteUrl('.$scoreUrl->id.')' : 'removePendingUrl('.$scoreUrl->pending_index.')' }}"
                                    wire:confirm="{{ __('Remove this link?') }}" />
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <flux:text class="text-sm text-zinc-500">{{ __('No links yet.') }}</flux:text>
                    @endif

                    <flux:modal name="score-url-add" class="w-full max-w-lg" wire:close="cancelUrlAdd">
                        <div class="space-y-4">
                            <flux:heading size="lg">{{ __('Add Link') }}</flux:heading>

                            <flux:field>
                                <flux:label>{{ __('Address') }}</flux:label>
                                <flux:input wire:model="newUrl" type="url" :placeholder="__('https://...')" />
                                <flux:error name="newUrl" />
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('Type (optional)') }}</flux:label>
                                <flux:select wire:model="newUrlLabel">
                                    <flux:select.option value="">{{ __('No type') }}</flux:select.option>
                                    @foreach($urlLabels as $urlLabel)
                                    <flux:select.option value="{{ $urlLabel->value }}">{{ $urlLabel->label() }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('Comment (optional)') }}</flux:label>
                                <flux:input wire:model="newUrlComment" />
                                <flux:error name="newUrlComment" />
                            </flux:field>

                            <div class="flex justify-end gap-2">
                                <flux:modal.close>
                                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                                </flux:modal.close>
                                <flux:button variant="primary" icon="plus" wire:click="addUrl">{{ __('Add Link') }}</flux:button>
                            </div>
                        </div>
                    </flux:modal>
                </div>
                @endif

                @if($musicId)
                <div class="mt-6 border-t border-zinc-200 pt-6 dark:border-zinc-700" x-show="!splitScreen">
                <flux:field>
                    <flux:checkbox
                        wire:model="publicPreview"
                        :label="__('Show incipit on public listings of the music')" />
                    <flux:error name="publicPreview" />
                </flux:field>
                </div>
                @endif

                @if($this->canNominate)
                <div
                    class="mt-6 border-t border-zinc-200 pt-6 dark:border-zinc-700"
                    x-show="!splitScreen"
                    x-on:score-publication-submitted.window="$flux.modal('score-publication').close()"
                >
                    @php($publication = $this->publication)

                    <flux:heading size="sm">{{ __('Offer to the public library') }}</flux:heading>
                    <flux:text class="mt-1 text-sm text-zinc-500">
                        {{ __('Only public domain and Creative Commons material can go here. An editor checks every nomination before it is published.') }}
                    </flux:text>

                    {{-- The public reads an approved snapshot, so a render setting
                         changed here shows nowhere until the next submission. --}}
                    @if($publication && $publication->status === \App\Enums\ScorePublicationStatus::Approved)
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <flux:badge color="green" size="sm">{{ __('Published') }}</flux:badge>
                        <flux:button size="sm" variant="outline" :href="route('public-scores.show', ['score' => $score, 'slug' => \Illuminate\Support\Str::slug($score->title)])">
                            {{ __('View public page') }}
                        </flux:button>
                        <flux:button size="sm" variant="danger" wire:click="withdrawPublication">
                            {{ __('Withdraw') }}
                        </flux:button>
                    </div>
                    @if($publication->hasUnpublishedChanges())
                    <div class="mt-2">
                        <flux:badge color="amber" size="sm">{{ __('Newer version awaiting review') }}</flux:badge>
                    </div>
                    @endif
                    <flux:text class="mt-2 text-xs text-zinc-500">
                        {{ __('The public reads the version an editor approved. Changing the notes or the links sends the change back for review, and the approved version stays up meanwhile; changing the display settings does not show publicly until the next submission.') }}
                    </flux:text>
                    @elseif($publication && $publication->status === \App\Enums\ScorePublicationStatus::Submitted)
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <flux:badge size="sm">{{ __('Awaiting review') }}</flux:badge>
                        <flux:button size="sm" variant="outline" wire:click="withdrawPublication">{{ __('Withdraw') }}</flux:button>
                    </div>

                    @elseif($publication && $publication->status === \App\Enums\ScorePublicationStatus::TakenDown)
                    <div class="mt-3 rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-900 dark:bg-red-950/40">
                        <flux:badge color="red" size="sm">{{ __('Taken down') }}</flux:badge>
                        <flux:text class="mt-2 text-sm">{{ $publication->takedown_reason }}</flux:text>
                        <flux:text class="mt-1 text-xs text-zinc-500">{{ __('Contact us if you think this was a mistake.') }}</flux:text>
                    </div>
                    @else
                    @php($wasRejected = $publication && $publication->status === \App\Enums\ScorePublicationStatus::Rejected)

                    @if($wasRejected)
                    <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-900 dark:bg-amber-950/40">
                        <flux:heading size="sm">{{ __('Not published') }}</flux:heading>
                        <flux:text class="mt-1 text-sm">{{ $publication->review_notes }}</flux:text>
                    </div>
                    @endif

                    <div class="mt-3">
                        <flux:modal.trigger name="score-publication">
                            <flux:button size="sm" variant="outline" icon="paper-airplane">
                                {{ $wasRejected ? __('Edit and resend') : __('Offer this score') }}
                            </flux:button>
                        </flux:modal.trigger>
                    </div>

                    <flux:modal name="score-publication" class="w-full max-w-xl">
                        <div class="space-y-4">
                            <flux:heading size="lg">{{ __('Offer to the public library') }}</flux:heading>

                            <flux:field>
                                <flux:label>{{ __('Why may this be published?') }}</flux:label>
                                <flux:select wire:model.live="publicationForm.license">
                                    <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                                    @foreach($licenseOptions as $licenseOption)
                                    <flux:select.option value="{{ $licenseOption->value }}">{{ $licenseOption->label() }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="publicationForm.license" />
                            </flux:field>

                            @php($chosenLicense = \App\Enums\ScoreLicense::tryFrom($publicationForm['license'] ?? ''))

                            @if($chosenLicense?->requiresOutboundLicense())
                            <flux:field>
                                <flux:label>{{ __('What may people do with it?') }}</flux:label>
                                <flux:description>{{ __('Your own work still needs a licence, or nobody may legally reuse it.') }}</flux:description>
                                <flux:select wire:model="publicationForm.outbound_license">
                                    <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                                    @foreach($outboundLicenseOptions as $outboundOption)
                                    <flux:select.option value="{{ $outboundOption->value }}">{{ $outboundOption->label() }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="publicationForm.outbound_license" />
                            </flux:field>
                            @endif

                            @if($chosenLicense?->requiresEditionAffirmation())
                            <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                <flux:checkbox
                                    wire:model="publicationForm.edition_is_free"
                                    :label="__('The engraving is free as well')"
                                    :description="__('Tick this if you typeset it yourself, or copied it from an edition published before :year. Old music may be reprinted by anyone, but a recent edition of it — a critical edition, a fresh typesetting — belongs to whoever made it.', ['year' => $editionFreeBefore])" />
                            </div>
                            @endif

                            @if($chosenLicense?->requiresPermissionEvidence())
                            <flux:field>
                                <flux:label>{{ __('The permission itself') }}</flux:label>
                                <flux:description>{{ __('Who gave it, when, and in what words.') }}</flux:description>
                                <flux:textarea wire:model="publicationForm.permission_evidence" rows="3" />
                                <flux:error name="publicationForm.permission_evidence" />
                            </flux:field>
                            @endif

                            <flux:field>
                                <flux:label>
                                    {{ __('Source link') }}
                                    @unless($chosenLicense?->requiresSourceUrl())
                                    <span class="font-normal text-zinc-500">{{ __('(optional)') }}</span>
                                    @endunless
                                </flux:label>
                                <flux:description>{{ __('The page you took it from, so the reviewer can check it. Leave it empty if this engraving is your own.') }}</flux:description>
                                <flux:input wire:model="publicationForm.source_url" placeholder="https://..." />
                                <flux:error name="publicationForm.source_url" />
                            </flux:field>

                            <details class="rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700">
                                <summary class="cursor-pointer text-sm text-zinc-600 dark:text-zinc-400">
                                    {{ __('Add details (optional)') }}
                                </summary>
                                <div class="mt-3 space-y-3">
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <flux:field>
                                            <flux:label>{{ __('Source name') }}</flux:label>
                                            <flux:input wire:model="publicationForm.source_title" :placeholder="__('e.g. Liber Usualis, 1961')" />
                                            <flux:error name="publicationForm.source_title" />
                                        </flux:field>
                                        <flux:field>
                                            <flux:label>{{ __('Year the composer died') }}</flux:label>
                                            <flux:input wire:model="publicationForm.composer_death_year" type="number" />
                                            <flux:error name="publicationForm.composer_death_year" />
                                        </flux:field>
                                    </div>
                                    <flux:field>
                                        <flux:label>{{ __('Anything the reviewer should know') }}</flux:label>
                                        <flux:textarea wire:model="publicationForm.rights_note" rows="2" />
                                        <flux:error name="publicationForm.rights_note" />
                                    </flux:field>
                                </div>
                            </details>

                            <div class="flex justify-end gap-2">
                                <flux:modal.close>
                                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                                </flux:modal.close>
                                <flux:button variant="primary" wire:click="submitForPublication">
                                    {{ __('Send for review') }}
                                </flux:button>
                            </div>
                        </div>
                    </flux:modal>
                    @endif
                </div>
                @endif


                @if(!$isGuest && $score)
                <div class="mt-6 border-t border-zinc-200 pt-6 dark:border-zinc-700" x-show="!splitScreen">
                    <flux:button icon="folder" variant="outline" x-on:click="$flux.modal('folders-modal').show()">
                        {{ __('Folders') }}
                        @if(count($folderIds))
                            <flux:badge size="sm" class="ml-1">{{ count($folderIds) }}</flux:badge>
                        @endif
                    </flux:button>
                </div>
                @endif

                @if($score)
                <div class="mt-6 border-t border-zinc-200 pt-6 dark:border-zinc-700" x-show="!splitScreen">
                    <flux:button variant="danger" icon="trash" wire:click="delete" wire:confirm="{{ __('Are you sure you want to delete this score?') }}">
                        {{ __('Delete Score') }}
                    </flux:button>
                </div>
                @endif

            </div>
    </div>
    </flux:card>
</div>