<div class="py-8">
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        <flux:card class="p-4 lg:p-6">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
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
                            {{ __('Scores are always private and visible only to you.') }}
                        @endif
                    </flux:subheading>
                </div>

                <div class="flex flex-wrap gap-2">
                    @if(!$isGuest)
                        <flux:button variant="ghost" icon="arrow-left" :href="route('scores')" wire:navigate>
                            {{ __('Back to Scores') }}
                        </flux:button>
                    @endif
                    @if($score)
                        <flux:button variant="danger" icon="trash" wire:click="delete" wire:confirm="{{ __('Are you sure you want to delete this score?') }}">
                            {{ __('Delete') }}
                        </flux:button>
                    @endif
                </div>
            </div>

            <div class="mb-4 flex justify-end">
                <x-action-message on="score-created">
                    {{ __('Score created.') }}
                </x-action-message>
                <x-action-message on="score-updated">
                    {{ __('Score updated.') }}
                </x-action-message>
                <x-action-message on="score-defaults-saved">
                    {{ __('Saved as your default for this ratio.') }}
                </x-action-message>
            </div>

            <div class="space-y-4">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <flux:field required>
                        <flux:label class="inline">{{ __('Score title') }}</flux:label>
                        <flux:input wire:model="title" :placeholder="__('Score title')" autofocus />
                        <flux:error name="title" />
                    </flux:field>

                    <flux:field required>
                        <flux:label class="inline">{{ __('Format') }}</flux:label>
                        <flux:select wire:model="format">
                            @foreach($formats as $formatOption)
                                <flux:select.option value="{{ $formatOption->value }}">{{ $formatOption->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="format" />
                    </flux:field>
                </div>

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

                <flux:modal name="score-music-search" class="max-w-4xl">
                    <livewire:music-search lazy selectable="true" source=".score" wire:key="score-music-search" />
                    <div class="mt-6 flex justify-end">
                        <flux:button x-on:click="$flux.modal('score-music-search').close()" variant="outline">
                            {{ __('Cancel') }}
                        </flux:button>
                    </div>
                </flux:modal>
                @endif

<script src="https://cdn.jsdelivr.net/gh/bbloomf/exsurge@v1.22.1/dist/exsurge.min.js"></script>
<script>
window.abc2svg = window.abc2svg || {};
(function () {
    var el = document.createElement('span');
    el.style.cssText = 'position:absolute;top:-9999px;left:-9999px;visibility:hidden;white-space:nowrap;';
    document.body.appendChild(el);
    window.abc2svg.el = el;
})();
</script>
<script src="{{ asset('js/abc2svg-1.js') }}"></script>

                <div
                    x-data="scoreEditor({
                        scoreSettings: @js($settings ?: (object) []),
                        userDefaults: @js($userDefaults ?: (object) []),
                        clippedWarningText: @js(__('Content does not fit on page')),
                        clipboardNotSupported: @js(__('Clipboard not supported in this browser')),
                        imageCopied: @js(__('Image copied to clipboard')),
                        failedToCopy: @js(__('Failed to copy image')),
                        shareLinkCopied: @js(__('Share link copied!')),
                        linkCopyFailed: @js(__('Failed to copy link')),
                        htmlCopied: @js(__('HTML copied to clipboard!')),
                        plainTextCopied: @js(__('Plain text copied to clipboard!')),
                        copyAsImageText: @js(__('Copy as Image')),
                        exportPngText: @js(__('Export PNG')),
                        exportSvgText: @js(__('Export SVG')),
                        fullscreenText: @js(__('Fullscreen')),
                    })"
                >
                    <div x-show="$wire.format === 'abc'" x-cloak class="mb-2 flex items-center gap-4">
                        <flux:link href="https://abcplus.sourceforge.net" target="_blank" class="text-sm text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200">
                            <flux:icon name="book-open" class="mr-1 inline" />{{ __('ABC guide') }}
                        </flux:link>
                        <flux:button size="sm" variant="ghost" icon="table-cells" x-on:click="$flux.modal('abc-cheatsheet').show()">
                            {{ __('Cheatsheet') }}
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

                    <div x-show="$wire.format === 'chordpro'" x-cloak class="mb-2 flex items-center gap-4">
                        <flux:link href="https://www.chordpro.org/chordpro/chordpro-introduction/" target="_blank" class="text-sm text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200">
                            <flux:icon name="book-open" class="mr-1 inline" />{{ __('ChordPro guide') }}
                        </flux:link>
                        <flux:button size="sm" variant="ghost" icon="table-cells" x-on:click="$flux.modal('chordpro-cheatsheet').show()">
                            {{ __('Cheatsheet') }}
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

                    <div x-show="$wire.format === 'gabc'" x-cloak class="mb-2 flex items-center gap-4">
                        <flux:link href="https://gregorio-project.github.io/gabc/" target="_blank" class="text-sm text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200">
                            <flux:icon name="book-open" class="mr-1 inline" />{{ __('GABC guide') }}
                        </flux:link>
                        <flux:button size="sm" variant="ghost" icon="table-cells" x-on:click="$flux.modal('gabc-cheatsheet').show()">
                            {{ __('Cheatsheet') }}
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

                    <div x-show="$wire.format === 'aretino'" x-cloak class="mb-2 flex items-center gap-4">
                        <flux:link href="/aretino/guide" target="_blank" class="text-sm text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200">
                            <flux:icon name="book-open" class="mr-1 inline" />{{ __('Aretino guide') }}
                        </flux:link>
                        <flux:button size="sm" variant="ghost" icon="table-cells" x-on:click="$flux.modal('aretino-cheatsheet').show()">
                            {{ __('Cheatsheet') }}
                        </flux:button>
                    </div>

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

                    {{-- Textarea --}}
                    <flux:field required>
                        <flux:textarea
                            wire:model="content"
                            rows="10"
                            class="font-mono text-sm"
                            :placeholder="__('Type the score here')"
                            x-ref="contentTextarea"
                            x-on:input="isContentUserModified = true; localContent = $event.target.value; scheduleRender(); updateAretinoHighlight && updateAretinoHighlight()"
                            x-on:click="updateAretinoHighlight && updateAretinoHighlight()"
                            x-on:keyup="updateAretinoHighlight && updateAretinoHighlight()"
                            x-on:select="updateAretinoHighlight && updateAretinoHighlight()"
                            x-on:focus="updateAretinoHighlight && updateAretinoHighlight()"
                        />
                        <flux:error name="content" />
                    </flux:field>

                    <div x-show="localContent.trim() === '' || localContent === minimalExamples[$wire.format]" class="flex">
                        <flux:button size="sm" variant="ghost" icon="light-bulb" x-on:click="fillExample()">
                            {{ __('Show me an example') }}
                        </flux:button>
                    </div>

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
                            <flux:input size="sm" type="number" x-model="lyricSize" min="8" max="60" step="1" class="w-16" />
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
                            <flux:input size="sm" type="number" x-model="staffSize" min="30" max="300" step="5" class="w-16" />
                        </div>

                        <div class="flex items-center gap-1">
                            <flux:tooltip :content="__('Space between lines')">
                                <flux:icon name="between-horizontal-start" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                            </flux:tooltip>
                            <flux:input size="sm" type="number" x-model="spaceBetweenSystems" min="-2" max="2" step="0.1" class="w-16" />
                        </div>

                        <div class="flex items-center gap-1">
                            <flux:tooltip :content="__('Min. space below staff')">
                                <flux:icon name="align-vertical-space-around" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                            </flux:tooltip>
                            <flux:input size="sm" type="number" x-model="minSpaceBelowStaff" min="-2" max="2" step="0.1" class="w-16" />
                        </div>

                        <div class="flex items-center gap-1" x-show="!['16/9', '4/3', '1/1'].includes(pageRatio)">
                            <flux:tooltip :content="__('Zoom (%)')">
                                <flux:icon name="zoom-in" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                            </flux:tooltip>
                            <flux:input size="sm" type="number" x-model="zoom" min="50" max="300" step="5" class="w-16" />
                        </div>

                        <div class="h-5 w-px shrink-0 bg-zinc-300 dark:bg-zinc-600"></div>

                        <div class="flex items-center gap-1">
                            <flux:tooltip :content="__('Word spacing (px)')">
                                <flux:icon name="space" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                            </flux:tooltip>
                            <flux:input size="sm" type="number" x-model="minLyricWordSpacing" min="0" max="40" step="1" class="w-16" />
                        </div>

                        <div class="flex items-center gap-1">
                            <flux:tooltip :content="__('Hyphen width (px)')">
                                <flux:icon name="minus" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                            </flux:tooltip>
                            <flux:input size="sm" type="number" x-model="hyphenWidth" min="0" max="40" step="1" class="w-16" />
                        </div>

                        <div class="flex items-center gap-1">
                            <flux:tooltip :content="__('Condensing tolerance')">
                                <flux:icon name="ruler-dimension-line" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                            </flux:tooltip>
                            <flux:input size="sm" type="number" x-model="condensingTolerance" min="0" max="1" step="0.05" class="w-16" />
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
                            <flux:input size="sm" type="number" x-model="chordproFontSize" min="10" max="32" step="1" class="w-16" />
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
                            <flux:input size="sm" type="number" x-model="chordproTranspose" min="-11" max="11" step="1" class="w-16" />
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
                            <flux:input size="sm" type="number" x-model="abcLyricSize" min="8" max="60" step="1" class="w-16" />
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
                            <flux:input size="sm" type="number" x-model="abcNoteSpacing" min="1" max="3" step="0.1" class="w-16" />
                        </div>

                        <div class="flex items-center gap-1">
                            <flux:tooltip :content="__('Staff separation')">
                                <flux:icon name="between-horizontal-start" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                            </flux:tooltip>
                            <flux:input size="sm" type="number" x-model="abcStaffSep" min="15" max="120" step="1" class="w-16" />
                        </div>

                        <div class="flex items-center gap-1">
                            <flux:tooltip :content="__('Vocal space (pt)')">
                                <flux:icon name="align-vertical-space-around" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                            </flux:tooltip>
                            <flux:input size="sm" type="number" x-model="abcVocalSpace" min="0" max="40" step="1" class="w-16" />
                        </div>

                        <div class="flex items-center gap-1">
                            <flux:tooltip :content="__('Page scale')">
                                <flux:icon name="zoom-in" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                            </flux:tooltip>
                            <flux:input size="sm" type="number" x-model="abcPageScale" min="1" max="5" step="0.1" class="w-16" />
                        </div>

                        <div class="flex items-center gap-1">
                            <flux:tooltip :content="__('Hide clefs from second measure onward')">
                                <flux:icon name="clef-none" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                            </flux:tooltip>
                            <flux:checkbox x-model="abcNoClef" />
                        </div>

                        <div class="h-5 w-px shrink-0 bg-zinc-300 dark:bg-zinc-600"></div>

                        <div class="flex items-center gap-1">
                            <flux:tooltip :content="__('Stem width')">
                                <flux:icon name="pencil-line" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                            </flux:tooltip>
                            <flux:input size="sm" type="number" x-model="abcStemWidth" min="0.1" max="3" step="0.1" class="w-16" />
                        </div>

                        <div class="flex items-center gap-1">
                            <flux:tooltip :content="__('Staff line width')">
                                <flux:icon name="bars-3" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                            </flux:tooltip>
                            <flux:input size="sm" type="number" x-model="abcStaffLineWidth" min="0.1" max="3" step="0.1" class="w-16" />
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

                        <div class="mt-2 flex flex-wrap items-center justify-end gap-2" x-show="hasPages">
                            <flux:button icon="link" variant="ghost" x-on:click="openShareModal()">
                                Megosztás
                            </flux:button>
                        </div>
                    </div>

                    {{-- GABC Preview --}}
                    <div x-show="$wire.format === 'gabc'" x-cloak class="mt-4">
                        <div x-ref="preview" class="min-h-16 space-y-4" wire:ignore></div>

                        <div class="mt-2 flex flex-wrap items-center justify-end gap-2" x-show="hasPages">
                            <flux:button icon="link" variant="ghost" x-on:click="openShareModal()">
                                Megosztás
                            </flux:button>
                        </div>
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
                            <flux:input size="sm" type="number" x-model="aretinoLyricSize" min="6" max="40" step="1" class="w-16" />
                        </div>

                        <div class="flex items-center gap-1">
                            <flux:tooltip :content="__('Font')">
                                <flux:icon name="type-outline" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                            </flux:tooltip>
                            <flux:select size="sm" x-model="aretinoLyricFont" class="w-40 text-xs">
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
                            <flux:input size="sm" type="number" x-model="aretinoStaffSize" min="4" max="14" step="0.1" class="w-16" />
                        </div>

                        <div class="flex items-center gap-1">
                            <flux:tooltip :content="__('Space between lines')">
                                <flux:icon name="between-horizontal-start" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                            </flux:tooltip>
                            <flux:input size="sm" type="number" x-model="aretinoStaffGap" min="0" max="10" step="0.5" class="w-16" />
                        </div>

                        <div class="flex items-center gap-1" x-show="aretinoPageRatio === 'paper'">
                            <flux:tooltip :content="__('Staff width (mm)')">
                                <flux:icon name="ruler" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                            </flux:tooltip>
                            <flux:input size="sm" type="number" x-model="aretinoStaffWidth" min="50" max="400" step="1" class="w-16" />
                        </div>

                        <div class="flex items-center gap-1" x-show="!['16/9', '4/3', '1/1'].includes(aretinoPageRatio)">
                            <flux:tooltip :content="__('Zoom (%)')">
                                <flux:icon name="zoom-in" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                            </flux:tooltip>
                            <flux:input size="sm" type="number" x-model="aretinoZoom" min="50" max="300" step="5" class="w-16" />
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
                    </div>

                    {{-- Aretino Preview --}}
                    <div x-show="$wire.format === 'aretino'" x-cloak class="mt-4">
                        <div x-ref="aretinoPreview" class="min-h-16 space-y-4" wire:ignore></div>

                        <div class="mt-2 flex flex-wrap items-center justify-end gap-2" x-show="hasPages">
                            <flux:button icon="link" variant="ghost" x-on:click="openShareModal()">
                                Megosztás
                            </flux:button>
                        </div>
                    </div>

                    {{-- ChordPro Preview --}}
                    <div x-show="$wire.format === 'chordpro'" x-cloak class="mt-4">
                        <div x-ref="chordproPreview" class="min-h-16 space-y-4" wire:ignore></div>

                        <div class="mt-2 flex flex-wrap items-center justify-end gap-2" x-show="hasPages">
                            <span x-show="copyFeedback" x-text="copyFeedback" x-transition class="text-sm text-zinc-600 dark:text-zinc-300"></span>
                            <flux:button icon="link" variant="ghost" x-on:click="openShareModal()">
                                Megosztás
                            </flux:button>
                            <flux:button icon="clipboard-document-list" variant="ghost" x-on:click="copyChordproPlainText()">
                                {{ __('Copy as Text') }}
                            </flux:button>
                            <flux:button icon="clipboard" variant="ghost" x-on:click="copyChordproHtml()">
                                {{ __('Copy HTML') }}
                            </flux:button>
                            <flux:button icon="arrow-down-tray" variant="ghost" x-on:click="exportChordproHtml()">
                                {{ __('Export HTML') }}
                            </flux:button>
                        </div>

                    </div>

                    <flux:modal name="share-link-modal" class="max-w-md">
                        <div class="space-y-4">
                            <flux:heading size="lg">Kotta megosztása</flux:heading>
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

                            @if($score && !$isGuest)
                            <div class="border-t border-zinc-200 pt-4 dark:border-zinc-700" x-data="{ secretLinkCopied: false }">
                                <div class="flex items-center justify-between gap-2">
                                    <flux:subheading class="font-medium">{{ __('Secret Link') }}</flux:subheading>
                                    <div class="flex min-w-0 flex-1 items-center gap-2" x-show="$wire.secretLinkUrl" x-cloak>
                                        <flux:input readonly x-bind:value="$wire.secretLinkUrl ?? ''" class="min-w-0 flex-1 font-mono text-sm" />
                                        <flux:button
                                            icon="clipboard"
                                            variant="ghost"
                                            :title="__('Copy link')"
                                            x-on:click="navigator.clipboard.writeText($wire.secretLinkUrl).then(() => { secretLinkCopied = true; setTimeout(() => secretLinkCopied = false, 2000) })"
                                            x-bind:class="secretLinkCopied ? 'text-green-600' : ''"
                                        />
                                        <flux:button
                                            icon="trash"
                                            variant="ghost"
                                            :title="__('Delete link')"
                                            wire:click="deleteSecretLink"
                                            wire:confirm="{{ __('This will invalidate the current link. Are you sure?') }}"
                                        />
                                    </div>
                                    <div x-show="!$wire.secretLinkUrl">
                                        <flux:button icon="link" variant="ghost" wire:click="generateSecretLink">
                                            {{ __('Generate Secret Link') }}
                                        </flux:button>
                                    </div>
                                </div>
                                <flux:text class="mt-1 text-xs text-zinc-500" x-show="$wire.secretLinkUrl" x-cloak>
                                    {{ __('Anyone with this link can view the score (read-only). Delete the link to revoke access.') }}
                                </flux:text>
                                <flux:text class="mt-1 text-xs text-zinc-500" x-show="!$wire.secretLinkUrl">
                                    {{ __('Generate a secret link to share this score as a read-only preview.') }}
                                </flux:text>
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
                    {{-- URL Management --}}
                    <div class="mt-6 border-t border-zinc-200 pt-6 dark:border-zinc-700">
                        <flux:heading size="sm" class="mb-3">{{ __('Links') }}</flux:heading>

                        @if($this->scoreUrls->isNotEmpty())
                        <div class="mb-4 space-y-2">
                            @foreach($this->scoreUrls as $scoreUrl)
                            <div class="flex items-center gap-3 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800/50">
                                @if($scoreUrl->label instanceof \App\MusicUrlLabel)
                                <flux:icon name="{{ $scoreUrl->label->icon() }}" variant="micro" class="shrink-0 {{ $scoreUrl->label->color() }}" />
                                @else
                                <flux:icon name="link" variant="micro" class="shrink-0 text-zinc-400" />
                                @endif
                                <div class="min-w-0 flex-1">
                                    <a href="{{ $scoreUrl->url }}" target="_blank" rel="noopener noreferrer" class="block truncate text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">
                                        {{ $scoreUrl->url }}
                                    </a>
                                    @if($scoreUrl->comment)
                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $scoreUrl->comment }}</span>
                                    @endif
                                </div>
                                @if($scoreUrl->label instanceof \App\MusicUrlLabel)
                                <span class="shrink-0 text-xs text-zinc-500 dark:text-zinc-400">{{ $scoreUrl->label->label() }}</span>
                                @endif
                                <flux:button
                                    icon="trash"
                                    variant="ghost"
                                    size="sm"
                                    wire:click="deleteUrl({{ $scoreUrl->id }})"
                                    wire:confirm="{{ __('Remove this link?') }}"
                                />
                            </div>
                            @endforeach
                        </div>
                        @endif

                        <div class="flex flex-wrap items-end gap-2">
                            <flux:field class="flex-1 min-w-48">
                                <flux:input
                                    wire:model="newUrl"
                                    type="url"
                                    :placeholder="__('https://...')"
                                    size="sm"
                                />
                                <flux:error name="newUrl" />
                            </flux:field>
                            <flux:field class="w-40">
                                <flux:select wire:model="newUrlLabel" size="sm">
                                    <flux:select.option value="">{{ __('No type') }}</flux:select.option>
                                    @foreach($urlLabels as $urlLabel)
                                    <flux:select.option value="{{ $urlLabel->value }}">{{ $urlLabel->label() }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            </flux:field>
                            <flux:field class="flex-1 min-w-32">
                                <flux:input
                                    wire:model="newUrlComment"
                                    :placeholder="__('Comment (optional)')"
                                    size="sm"
                                />
                                <flux:error name="newUrlComment" />
                            </flux:field>
                            <flux:button icon="plus" variant="outline" size="sm" wire:click="addUrl">
                                {{ __('Add Link') }}
                            </flux:button>
                        </div>
                    </div>
                    @endif

                    @if(!$isGuest)
                    <div class="mt-4 flex justify-end gap-3">
                        <flux:button variant="ghost" :href="route('scores')" wire:navigate>
                            {{ __('Cancel') }}
                        </flux:button>
                        <flux:button variant="primary" icon="pencil" x-on:click="saveScore()">
                            {{ __('Save Score') }}
                        </flux:button>
                    </div>
                    @endif
                </div>
            </div>
        </flux:card>
    </div>
</div>
