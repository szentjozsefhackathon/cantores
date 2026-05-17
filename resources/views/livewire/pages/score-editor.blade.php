<div class="py-8">
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        <flux:card class="p-4 lg:p-6">
            <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <flux:heading size="2xl">
                        @if($score)
                            {{ __('Edit Score') }}
                        @elseif($isSharedLink && $isGuest)
                            {{ __('Score Preview') }}
                        @elseif($isGuest)
                            Kottaszerkesztő
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
                        <flux:input wire:model="title" :placeholder="__('Score title')" autofocus />
                        <flux:error name="title" />
                    </flux:field>

                    <flux:field required>
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
<script src="{{ asset('js/abc2svg-1.js') }}"></script>

                <div
                    x-data="scoreEditor({
                        scoreSettings: @js($settings ?: (object) []),
                        userDefaults: @js($userDefaults ?: (object) []),
                        clippedWarningText: @js(__('Content does not fit on page')),
                        clipboardNotSupported: @js(__('Clipboard not supported in this browser')),
                        firstPageCopied: @js(__('First page copied to clipboard')),
                        imageCopied: @js(__('Image copied to clipboard')),
                        failedToCopy: @js(__('Failed to copy image')),
                        shareLinkCopied: @js(__('Share link copied!')),
                        linkCopyFailed: @js(__('Failed to copy link')),
                        htmlCopied: @js(__('HTML copied to clipboard!')),
                        plainTextCopied: @js(__('Plain text copied to clipboard!')),
                    })"
                >
                    {{-- Textarea --}}
                    <flux:field required>
                        <flux:textarea wire:model="content" rows="10" class="font-mono text-sm" :placeholder="__('Type the score here')" x-on:input="localContent = $event.target.value; scheduleRender()" />
                        <flux:error name="content" />
                    </flux:field>

                    <div x-show="localContent.trim() === ''" class="flex">
                        <flux:button size="sm" variant="ghost" icon="light-bulb" x-on:click="fillExample()">
                            {{ __('Show me an example') }}
                        </flux:button>
                    </div>

                    {{-- GABC Settings Toolbar --}}
                    <div x-show="$wire.format === 'gabc'" x-cloak class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800/50">
                        <flux:tooltip :content="__('Zoom (%)')">
                            <div class="flex items-center gap-1">
                                <flux:icon name="zoom-in" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                <flux:input size="sm" type="number" x-model="zoom" min="50" max="300" step="5" class="w-16" />
                            </div>
                        </flux:tooltip>

                        <flux:tooltip :content="__('Lyric size (pt)')">
                            <div class="flex items-center gap-1">
                                <flux:icon name="a-large-small" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                <flux:input size="sm" type="number" x-model="lyricSize" min="8" max="60" step="1" class="w-16" />
                            </div>
                        </flux:tooltip>

                        <flux:tooltip :content="__('Staff size (%)')">
                            <div class="flex items-center gap-1">
                                <flux:icon name="list-chevrons-up-down" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                <flux:input size="sm" type="number" x-model="staffSize" min="30" max="300" step="5" class="w-16" />
                            </div>
                        </flux:tooltip>

                        <flux:tooltip :content="__('Page ratio')">
                            <div class="flex items-center gap-1">
                                <flux:icon name="proportions" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                <flux:select size="sm" x-model="pageRatio" class="w-20 text-xs">
                                    <flux:select.option value="auto">{{ __('Auto') }}</flux:select.option>
                                    <flux:select.option value="16/9">16:9</flux:select.option>
                                    <flux:select.option value="4/3">4:3</flux:select.option>
                                    <flux:select.option value="1/1">1:1</flux:select.option>
                                </flux:select>
                            </div>
                        </flux:tooltip>

                        <flux:tooltip :content="__('Drop caps')">
                            <div class="flex items-center gap-1">
                                <flux:icon name="text-initial" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                <flux:switch x-model="dropCaps" />
                            </div>
                        </flux:tooltip>

                        <flux:tooltip :content="__('Font')">
                            <div class="flex items-center gap-1">
                                <flux:icon name="type-outline" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                <flux:select size="sm" x-model="lyricFont" class="w-32 text-xs">
                                    <flux:select.option value="'Palatino Linotype', 'Book Antiqua', Palatino, serif">Palatino</flux:select.option>
                                    <flux:select.option value="'Barlow Condensed', sans-serif">Barlow Condensed</flux:select.option>
                                    <flux:select.option value="Garamond, 'EB Garamond', serif">Garamond</flux:select.option>
                                    <flux:select.option value="'Times New Roman', Times, serif">Times New Roman</flux:select.option>
                                    <flux:select.option value="'Franklin Gothic Book', 'Franklin Gothic Medium', 'ITC Franklin Gothic', Arial, sans-serif">Franklin Gothic</flux:select.option>
                                </flux:select>
                            </div>
                        </flux:tooltip>

                        <div class="h-5 w-px shrink-0 bg-zinc-300 dark:bg-zinc-600"></div>

                        <flux:tooltip :content="__('Word spacing (px)')">
                            <div class="flex items-center gap-1">
                                <flux:icon name="space" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                <flux:input size="sm" type="number" x-model="minLyricWordSpacing" min="0" max="40" step="1" class="w-16" />
                            </div>
                        </flux:tooltip>

                        <flux:tooltip :content="__('Hyphen width (px)')">
                            <div class="flex items-center gap-1">
                                <flux:icon name="minus" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                <flux:input size="sm" type="number" x-model="hyphenWidth" min="0" max="40" step="1" class="w-16" />
                            </div>
                        </flux:tooltip>

                        <flux:tooltip :content="__('Condensing tolerance')">
                            <div class="flex items-center gap-1">
                                <flux:icon name="ruler-dimension-line" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                <flux:input size="sm" type="number" x-model="condensingTolerance" min="0" max="1" step="0.05" class="w-16" />
                            </div>
                        </flux:tooltip>

                        <div class="h-5 w-px shrink-0 bg-zinc-300 dark:bg-zinc-600"></div>

                        <flux:tooltip :content="__('Space between lines')">
                            <div class="flex items-center gap-1">
                                <flux:icon name="between-horizontal-start" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                <flux:input size="sm" type="number" x-model="spaceBetweenSystems" min="-2" max="2" step="0.1" class="w-16" />
                            </div>
                        </flux:tooltip>

                        <flux:tooltip :content="__('Min. space below staff')">
                            <div class="flex items-center gap-1">
                                <flux:icon name="align-vertical-space-around" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                <flux:input size="sm" type="number" x-model="minSpaceBelowStaff" min="-2" max="2" step="0.1" class="w-16" />
                            </div>
                        </flux:tooltip>
                        
                        @if(!$isGuest)
                        <flux:tooltip :content="__('Save as my default for this ratio')">
                            <flux:button icon="bookmark" variant="ghost" x-on:click="saveAsDefault()" />
                        </flux:tooltip>
                        @endif

                    </div>

                    {{-- ChordPro Settings Toolbar --}}
                    <div x-show="$wire.format === 'chordpro'" x-cloak class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800/50">
                        <flux:tooltip :content="__('Font size (pt)')">
                            <div class="flex items-center gap-1">
                                <flux:icon name="a-large-small" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                <flux:input size="sm" type="number" x-model="chordproFontSize" min="10" max="32" step="1" class="w-16" />
                            </div>
                        </flux:tooltip>

                        <flux:tooltip :content="__('Font')">
                            <div class="flex items-center gap-1">
                                <flux:icon name="type-outline" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                <flux:select size="sm" x-model="chordproFontFamily" class="w-32 text-xs">
                                    <flux:select.option value="'Palatino Linotype', 'Book Antiqua', Palatino, serif">Palatino</flux:select.option>
                                    <flux:select.option value="'Barlow Condensed', sans-serif">Barlow Condensed</flux:select.option>
                                    <flux:select.option value="Garamond, 'EB Garamond', serif">Garamond</flux:select.option>
                                    <flux:select.option value="'Times New Roman', Times, serif">Times New Roman</flux:select.option>
                                    <flux:select.option value="Arial, Helvetica, sans-serif">Arial</flux:select.option>
                                </flux:select>
                            </div>
                        </flux:tooltip>

                        <flux:tooltip :content="__('Columns')">
                            <div class="flex items-center gap-1">
                                <flux:icon name="view-columns" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                <flux:select size="sm" x-model="chordproColumns" class="w-16 text-xs">
                                    <flux:select.option value="1">1</flux:select.option>
                                    <flux:select.option value="2">2</flux:select.option>
                                    <flux:select.option value="3">3</flux:select.option>
                                </flux:select>
                            </div>
                        </flux:tooltip>

                        <div class="h-5 w-px shrink-0 bg-zinc-300 dark:bg-zinc-600"></div>

                        <flux:tooltip :content="__('Transpose (semitones)')">
                            <div class="flex items-center gap-1">
                                <flux:icon name="musical-note" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                <flux:input size="sm" type="number" x-model="chordproTranspose" min="-11" max="11" step="1" class="w-16" />
                            </div>
                        </flux:tooltip>

                        <flux:tooltip :content="__('German notation (H = B, B = B♭)')">
                            <div class="flex items-center gap-1">
                                <span class="text-xs font-bold text-zinc-500 dark:text-zinc-400">H</span>
                                <flux:switch x-model="chordproGermanNotation" />
                            </div>
                        </flux:tooltip>

                        @if(!$isGuest)
                        <flux:tooltip :content="__('Save as my default')">
                            <flux:button icon="bookmark" variant="ghost" x-on:click="saveAsDefault()" />
                        </flux:tooltip>
                        @endif
                    </div>

                    {{-- ABC Settings Toolbar --}}
                    <div x-show="$wire.format === 'abc'" x-cloak class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800/50">
                        <flux:tooltip :content="__('Page ratio')">
                            <div class="flex items-center gap-1">
                                <flux:icon name="proportions" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                <flux:select size="sm" x-model="abcPageRatio" class="w-20 text-xs">
                                    <flux:select.option value="auto">{{ __('Auto') }}</flux:select.option>
                                    <flux:select.option value="16/9">16:9</flux:select.option>
                                    <flux:select.option value="4/3">4:3</flux:select.option>
                                    <flux:select.option value="1/1">1:1</flux:select.option>
                                </flux:select>
                            </div>
                        </flux:tooltip>

                        <flux:tooltip :content="__('Page scale')">
                            <div class="flex items-center gap-1">
                                <flux:icon name="zoom-in" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                <flux:input size="sm" type="number" x-model="abcPageScale" min="1" max="5" step="0.1" class="w-16" />
                            </div>
                        </flux:tooltip>

                        <flux:tooltip :content="__('Font')">
                            <div class="flex items-center gap-1"
                                x-data="{
                                    fontPresets: ['Palatino Linotype', 'Barlow Condensed', 'Garamond', 'Times New Roman', 'Franklin Gothic Book', 'Franklin Gothic Book Medium Cond'],
                                    get fontSelect() {
                                        return this.fontPresets.includes(this.abcLyricFont) ? this.abcLyricFont : 'custom';
                                    },
                                    set fontSelect(val) {
                                        if (val !== 'custom') this.abcLyricFont = val;
                                        else this.abcLyricFont = '';
                                    },
                                    get isCustom() { return !this.fontPresets.includes(this.abcLyricFont); }
                                }"
                            >
                                <flux:icon name="type-outline" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                <flux:select size="sm" x-model="fontSelect" class="w-40 text-xs">
                                    <flux:select.option value="Palatino Linotype">Palatino Linotype</flux:select.option>
                                    <flux:select.option value="Barlow Condensed">Barlow Condensed</flux:select.option>
                                    <flux:select.option value="Garamond">Garamond</flux:select.option>
                                    <flux:select.option value="Times New Roman">Times New Roman</flux:select.option>
                                    <flux:select.option value="Franklin Gothic Book">Franklin Gothic Book</flux:select.option>
                                    <flux:select.option value="custom">Custom…</flux:select.option>
                                </flux:select>
                                <flux:input size="sm" x-show="isCustom" x-model="abcLyricFont" placeholder="Font name" class="w-36 text-xs" />
                            </div>
                        </flux:tooltip>

                        <flux:tooltip :content="__('Lyric size (pt)')">
                            <div class="flex items-center gap-1">
                                <flux:icon name="a-large-small" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                <flux:input size="sm" type="number" x-model="abcLyricSize" min="8" max="60" step="1" class="w-16" />
                            </div>
                        </flux:tooltip>

                        <flux:tooltip :content="__('Bold lyrics')">
                            <div class="flex items-center gap-1">
                                <flux:icon name="bold" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                <flux:checkbox x-model="abcLyricBold" />
                            </div>
                        </flux:tooltip>

                        <div class="h-5 w-px shrink-0 bg-zinc-300 dark:bg-zinc-600"></div>

                        <flux:tooltip :content="__('Note spacing')">
                            <div class="flex items-center gap-1">
                                <flux:icon name="space" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                <flux:input size="sm" type="number" x-model="abcNoteSpacing" min="1" max="3" step="0.1" class="w-16" />
                            </div>
                        </flux:tooltip>

                        <flux:tooltip :content="__('Staff separation')">
                            <div class="flex items-center gap-1">
                                <flux:icon name="between-horizontal-start" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                <flux:input size="sm" type="number" x-model="abcStaffSep" min="15" max="120" step="1" class="w-16" />
                            </div>
                        </flux:tooltip>

                        <flux:tooltip :content="__('Vocal space (pt)')">
                            <div class="flex items-center gap-1">
                                <flux:icon name="align-vertical-space-around" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                <flux:input size="sm" type="number" x-model="abcVocalSpace" min="0" max="40" step="1" class="w-16" />
                            </div>
                        </flux:tooltip>

                        <flux:tooltip :content="__('Stem width')">
                            <div class="flex items-center gap-1">
                                <flux:icon name="pencil-line" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                <flux:input size="sm" type="number" x-model="abcStemWidth" min="0.1" max="3" step="0.1" class="w-16" />
                            </div>
                        </flux:tooltip>

                        <flux:tooltip :content="__('Staff line width')">
                            <div class="flex items-center gap-1">
                                <flux:icon name="bars-3" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                <flux:input size="sm" type="number" x-model="abcStaffLineWidth" min="0.1" max="3" step="0.1" class="w-16" />
                            </div>
                        </flux:tooltip>

                        <flux:tooltip :content="__('Hide clefs from second measure onward')">
                            <div class="flex items-center gap-1">
                                <flux:icon name="clef-none" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                <flux:checkbox x-model="abcNoClef" />
                            </div>
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
                            <span x-show="copyFeedback" x-text="copyFeedback" x-transition class="text-sm text-zinc-600 dark:text-zinc-300"></span>
                            <flux:button icon="link" variant="ghost" x-on:click="openShareModal()">
                                {{ __('Share') }}
                            </flux:button>
                            <flux:button icon="clipboard" variant="ghost" x-on:click="copyImage()">
                                {{ __('Copy as Image') }}
                            </flux:button>
                            <flux:button icon="arrow-down-tray" variant="ghost" x-on:click="exportPng()">
                                {{ __('Export PNG') }}
                            </flux:button>
                        </div>
                    </div>

                    {{-- GABC Preview --}}
                    <div x-show="$wire.format === 'gabc'" x-cloak class="mt-4">
                        <div x-ref="preview" class="min-h-16 space-y-4" wire:ignore></div>

                        <div class="mt-2 flex flex-wrap items-center justify-end gap-2" x-show="hasPages">
                            <span x-show="copyFeedback" x-text="copyFeedback" x-transition class="text-sm text-zinc-600 dark:text-zinc-300"></span>
                            <flux:button icon="link" variant="ghost" x-on:click="openShareModal()">
                                {{ __('Share') }}
                            </flux:button>
                            <flux:button icon="clipboard" variant="ghost" x-on:click="copyImage()">
                                {{ __('Copy as Image') }}
                            </flux:button>
                            <flux:button icon="arrow-down-tray" variant="ghost" x-on:click="exportPng()">
                                {{ __('Export PNG') }}
                            </flux:button>
                        </div>
                    </div>

                    {{-- Aretino Settings Toolbar --}}
                    <div x-show="$wire.format === 'aretino'" x-cloak class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800/50">
                        <flux:tooltip :content="__('Zoom (%)')">
                            <div class="flex items-center gap-1">
                                <flux:icon name="zoom-in" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                <flux:input size="sm" type="number" x-model="aretinoZoom" min="50" max="300" step="5" class="w-16" />
                            </div>
                        </flux:tooltip>

                        <flux:tooltip :content="__('Staff size (%)')">
                            <div class="flex items-center gap-1">
                                <flux:icon name="list-chevrons-up-down" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                <flux:input size="sm" type="number" x-model="aretinoStaffSize" min="30" max="300" step="5" class="w-16" />
                            </div>
                        </flux:tooltip>

                        <flux:tooltip :content="__('Lyric size (pt)')">
                            <div class="flex items-center gap-1">
                                <flux:icon name="a-large-small" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                <flux:input size="sm" type="number" x-model="aretinoLyricSize" min="8" max="60" step="1" class="w-16" />
                            </div>
                        </flux:tooltip>

                        <flux:tooltip :content="__('Note spacing')">
                            <div class="flex items-center gap-1">
                                <flux:icon name="space" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                <flux:input size="sm" type="number" x-model="aretinoNoteSpacing" min="0.5" max="3" step="0.1" class="w-16" />
                            </div>
                        </flux:tooltip>

                        <flux:tooltip :content="__('Font')">
                            <div class="flex items-center gap-1">
                                <flux:icon name="type-outline" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                <flux:select size="sm" x-model="aretinoLyricFont" class="w-32 text-xs">
                                    <flux:select.option value="'Palatino Linotype', 'Book Antiqua', Palatino, serif">Palatino</flux:select.option>
                                    <flux:select.option value="'Barlow Condensed', sans-serif">Barlow Condensed</flux:select.option>
                                    <flux:select.option value="Garamond, 'EB Garamond', serif">Garamond</flux:select.option>
                                    <flux:select.option value="'Times New Roman', Times, serif">Times New Roman</flux:select.option>
                                    <flux:select.option value="'Franklin Gothic Book', 'Franklin Gothic Medium', 'ITC Franklin Gothic', Arial, sans-serif">Franklin Gothic</flux:select.option>
                                </flux:select>
                            </div>
                        </flux:tooltip>

                        <flux:tooltip :content="__('Page ratio')">
                            <div class="flex items-center gap-1">
                                <flux:icon name="proportions" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                <flux:select size="sm" x-model="aretinoPageRatio" class="w-20 text-xs">
                                    <flux:select.option value="auto">{{ __('Auto') }}</flux:select.option>
                                    <flux:select.option value="16/9">16:9</flux:select.option>
                                    <flux:select.option value="4/3">4:3</flux:select.option>
                                    <flux:select.option value="1/1">1:1</flux:select.option>
                                </flux:select>
                            </div>
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
                            <span x-show="copyFeedback" x-text="copyFeedback" x-transition class="text-sm text-zinc-600 dark:text-zinc-300"></span>
                            <flux:button icon="link" variant="ghost" x-on:click="openShareModal()">
                                {{ __('Share') }}
                            </flux:button>
                            <flux:button icon="clipboard" variant="ghost" x-on:click="copyImage()">
                                {{ __('Copy as Image') }}
                            </flux:button>
                            <flux:button icon="arrow-down-tray" variant="ghost" x-on:click="exportPng()">
                                {{ __('Export PNG') }}
                            </flux:button>
                        </div>
                    </div>

                    {{-- ChordPro Preview --}}
                    <div x-show="$wire.format === 'chordpro'" x-cloak class="mt-4">
                        <div x-ref="chordproPreview" class="min-h-16 space-y-4" wire:ignore></div>

                        <div class="mt-2 flex flex-wrap items-center justify-end gap-2" x-show="hasPages">
                            <span x-show="copyFeedback" x-text="copyFeedback" x-transition class="text-sm text-zinc-600 dark:text-zinc-300"></span>
                            <flux:button icon="link" variant="ghost" x-on:click="openShareModal()">
                                {{ __('Share') }}
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
                            <flux:heading size="lg">{{ __('Share Score') }}</flux:heading>
                            <flux:text>
                                {{ __('This link encodes the full score and all settings directly in the URL — no account or registration needed. Anyone with the link can open and preview the score instantly.') }}
                            </flux:text>
                            <div class="flex flex-col gap-3 pt-2">
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
                                <flux:button variant="ghost" x-on:click="$flux.modal('share-link-modal').close()">
                                    {{ __('Close') }}
                                </flux:button>
                            </div>
                        </div>
                    </flux:modal>

                    @if($score)
                    <div class="mt-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" x-data="{ copied: false }">
                        <div class="flex items-center justify-between gap-2">
                            <flux:subheading class="font-medium">{{ __('Secret Link') }}</flux:subheading>
                            <div class="flex min-w-0 flex-1 items-center gap-2" x-show="$wire.secretLinkUrl" x-cloak>
                                <flux:input readonly x-bind:value="$wire.secretLinkUrl ?? ''" class="min-w-0 flex-1 font-mono text-sm" />
                                <flux:button
                                    icon="clipboard"
                                    variant="ghost"
                                    :title="__('Copy link')"
                                    x-on:click="navigator.clipboard.writeText($wire.secretLinkUrl).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                                    x-bind:class="copied ? 'text-green-600' : ''"
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
