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
                        @else
                            {{ __('Create Score') }}
                        @endif
                    </flux:heading>
                    <flux:subheading>
                        @if($isSharedLink && $isGuest)
                            {{ __('Sign in to save this score to your library.') }}
                        @elseif($isSharedLink)
                            {{ __('Saving will create a new score in your library.') }}
                        @else
                            {{ __('Scores are always private and visible only to you.') }}
                        @endif
                    </flux:subheading>
                </div>

                <div class="flex flex-wrap gap-2">
                    @if(!($isSharedLink && $isGuest))
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

                @if(!($isSharedLink && $isGuest))
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
<script src="http://moinejf.free.fr/js/abc2svg-1.js"></script>

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
                    })"
                >
                    <div class="md:flex md:gap-6">
                        {{-- Textarea --}}
                        <div class="min-w-0 flex-1">
                            <flux:field required>
                                <flux:textarea wire:model="content" rows="10" class="font-mono text-sm" :placeholder="__('Paste or type your ABC or GABC source here')" x-on:input="localContent = $event.target.value; scheduleRender()" />
                                <flux:error name="content" />
                            </flux:field>
                        </div>

                        {{-- GABC Settings --}}
                        <div x-show="$wire.format === 'gabc'" x-cloak class="mt-4 shrink-0 space-y-3 md:mt-0 md:w-44">
                            <flux:heading size="sm">{{ __('Settings') }}</flux:heading>

                            <div class="flex items-center gap-2">
                                <flux:icon name="zoom-in" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" :title="__('Zoom')" />
                                <flux:input size="sm" type="number" x-model="zoom" min="50" max="300" step="5" class="flex-1" />
                                <span class="shrink-0 text-xs text-zinc-500 dark:text-zinc-400">%</span>
                            </div>

                            <div class="flex items-center gap-2">
                                <flux:icon name="a-large-small" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" :title="__('Lyric Size')" />
                                <flux:input size="sm" type="number" x-model="lyricSize" min="8" max="60" step="1" class="flex-1" />
                                <span class="shrink-0 text-xs text-zinc-500 dark:text-zinc-400">pt</span>
                            </div>

                            <div class="flex items-center gap-2">
                                <flux:icon name="list-chevrons-up-down" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" :title="__('Staff Size')" />
                                <flux:input size="sm" type="number" x-model="staffSize" min="30" max="300" step="5" class="flex-1" />
                                <span class="shrink-0 text-xs text-zinc-500 dark:text-zinc-400">%</span>
                            </div>

                            <div class="flex items-center gap-2">
                                <flux:icon name="proportions" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" :title="__('Page Ratio')" />
                                <flux:select size="sm" x-model="pageRatio" class="flex-1 text-xs">
                                    <flux:select.option value="auto">{{ __('Auto') }}</flux:select.option>
                                    <flux:select.option value="16/9">16:9</flux:select.option>
                                    <flux:select.option value="4/3">4:3</flux:select.option>
                                    <flux:select.option value="1/1">1:1</flux:select.option>
                                </flux:select>
                            </div>

                            <div class="flex items-center gap-2">
                                <flux:icon name="text-initial" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" :title="__('Drop Caps')" />
                                <flux:switch x-model="dropCaps" />
                            </div>

                            <div class="flex items-center gap-2">
                                <flux:icon name="type-outline" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" :title="__('Font')" />
                                <flux:select size="sm" x-model="lyricFont" class="flex-1 text-xs">
                                    <flux:select.option value="'Palatino Linotype', 'Book Antiqua', Palatino, serif">Palatino</flux:select.option>
                                    <flux:select.option value="Garamond, 'EB Garamond', serif">Garamond</flux:select.option>
                                    <flux:select.option value="'Times New Roman', Times, serif">Times New Roman</flux:select.option>
                                    <flux:select.option value="'Franklin Gothic Book', 'Franklin Gothic Medium', 'ITC Franklin Gothic', Arial, sans-serif">Franklin Gothic</flux:select.option>
                                </flux:select>
                            </div>

                            <flux:separator />
                            <flux:heading size="xs">{{ __('Lyric Spacing') }}</flux:heading>

                            <div class="flex items-center gap-2">
                                <flux:icon name="space" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" :title="__('Word Spacing')" />
                                <flux:input size="sm" type="number" x-model="minLyricWordSpacing" min="0" max="40" step="1" class="flex-1" />
                                <span class="shrink-0 text-xs text-zinc-500 dark:text-zinc-400">px</span>
                            </div>

                            <div class="flex items-center gap-2">
                                <flux:icon name="minus" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" :title="__('Hyphen Width')" />
                                <flux:input size="sm" type="number" x-model="hyphenWidth" min="0" max="40" step="1" class="flex-1" />
                                <span class="shrink-0 text-xs text-zinc-500 dark:text-zinc-400">px</span>
                            </div>

                            <div class="flex items-center gap-2">
                                <flux:icon name="ruler-dimension-line" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" :title="__('Condensing')" />
                                <flux:input size="sm" type="number" x-model="condensingTolerance" min="0" max="1" step="0.05" class="flex-1" />
                            </div>

                            <flux:separator />
                            <flux:heading size="xs">{{ __('Line Spacing') }}</flux:heading>

                            <div class="flex items-center gap-2">
                                <flux:icon name="between-horizontal-start" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" :title="__('Between Lines')" />
                                <flux:input size="sm" type="number" x-model="spaceBetweenSystems" min="-2" max="2" step="0.1" class="flex-1" />
                            </div>

                            <div class="flex items-center gap-2">
                                <flux:icon name="align-vertical-space-around" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" :title="__('Notes to Text')" />
                                <flux:input size="sm" type="number" x-model="minSpaceBelowStaff" min="-2" max="2" step="0.1" class="flex-1" />
                            </div>
                        </div>

                        {{-- ABC Settings --}}
                        <div x-show="$wire.format === 'abc'" x-cloak class="mt-4 shrink-0 space-y-3 md:mt-0 md:w-44">
                            <flux:heading size="sm">{{ __('Settings') }}</flux:heading>

                            <div class="flex items-center gap-2">
                                <flux:icon name="proportions" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" :title="__('Page Ratio')" />
                                <flux:select size="sm" x-model="abcPageRatio" class="flex-1 text-xs">
                                    <flux:select.option value="auto">{{ __('Auto') }}</flux:select.option>
                                    <flux:select.option value="16/9">16:9</flux:select.option>
                                    <flux:select.option value="4/3">4:3</flux:select.option>
                                    <flux:select.option value="1/1">1:1</flux:select.option>
                                </flux:select>
                            </div>

                            <div class="flex items-center gap-2">
                                <flux:icon name="type-outline" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" :title="__('Font')" />
                                <flux:select size="sm" x-model="abcLyricFont" class="flex-1 text-xs">
                                    <flux:select.option value="Palatino Linotype">Palatino</flux:select.option>
                                    <flux:select.option value="Garamond">Garamond</flux:select.option>
                                    <flux:select.option value="Times New Roman">Times New Roman</flux:select.option>
                                    <flux:select.option value="Franklin Gothic Book">Franklin Gothic</flux:select.option>
                                </flux:select>
                            </div>

                            <div class="flex items-center gap-2">
                                <flux:icon name="a-large-small" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" :title="__('Lyric Size')" />
                                <flux:input size="sm" type="number" x-model="abcLyricSize" min="8" max="40" step="1" class="flex-1" />
                                <span class="shrink-0 text-xs text-zinc-500 dark:text-zinc-400">pt</span>
                            </div>

                            <div class="flex items-center gap-2">
                                <flux:icon name="bold" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" :title="__('Bold')" />
                                <flux:checkbox x-model="abcLyricBold" />
                            </div>

                            <div class="flex items-center gap-2">
                                <flux:icon name="space" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" :title="__('Note Spacing')" />
                                <flux:input size="sm" type="number" x-model="abcNoteSpacing" min="1" max="3" step="0.1" class="flex-1" />
                            </div>

                            <div class="flex items-center gap-2">
                                <flux:icon name="between-horizontal-start" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" :title="__('Staff Separation')" />
                                <flux:input size="sm" type="number" x-model="abcStaffSep" min="20" max="120" step="1" class="flex-1" />
                            </div>

                            <div class="flex items-center gap-2">
                                <flux:icon name="align-vertical-space-around" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" :title="__('Vocal Space')" />
                                <flux:input size="sm" type="number" x-model="abcVocalSpace" min="0" max="40" step="1" class="flex-1" />
                                <span class="shrink-0 text-xs text-zinc-500 dark:text-zinc-400">pt</span>
                            </div>
                        </div>
                    </div>

                    {{-- ABC Preview --}}
                    <div x-show="$wire.format === 'abc'" x-cloak class="mt-4">
                        <div x-ref="abcPreview" class="min-h-16 space-y-4"></div>

                        <div class="mt-2 flex flex-wrap items-center justify-end gap-2" x-show="hasPages">
                            <span x-show="copyFeedback" x-text="copyFeedback" x-transition class="text-sm text-zinc-600 dark:text-zinc-300"></span>
                            @if(!($isSharedLink && $isGuest))
                            <flux:button icon="bookmark" variant="ghost" x-on:click="saveAsDefault()">
                                {{ __('Save as my default for this ratio') }}
                            </flux:button>
                            @endif
                            <flux:button icon="link" variant="ghost" x-on:click="generateShareUrl()">
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
                        <div x-ref="preview" class="min-h-16 space-y-4"></div>

                        <div class="mt-2 flex flex-wrap items-center justify-end gap-2" x-show="hasPages">
                            <span x-show="copyFeedback" x-text="copyFeedback" x-transition class="text-sm text-zinc-600 dark:text-zinc-300"></span>
                            @if(!($isSharedLink && $isGuest))
                            <flux:button icon="bookmark" variant="ghost" x-on:click="saveAsDefault()">
                                {{ __('Save as my default for this ratio') }}
                            </flux:button>
                            @endif
                            <flux:button icon="link" variant="ghost" x-on:click="generateShareUrl()">
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

                    @if(!($isSharedLink && $isGuest))
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
