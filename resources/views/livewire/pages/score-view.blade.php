<div class="py-8">
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        <flux:card class="p-4 lg:p-6">
            <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <flux:heading size="2xl">{{ $this->score->title }}</flux:heading>
                    <flux:subheading>{{ __('Read-only preview') }}</flux:subheading>
                </div>
            </div>

<script src="https://cdn.jsdelivr.net/gh/bbloomf/exsurge@v1.22.1/dist/exsurge.min.js"></script>
<script src="{{ asset('js/abc2svg-1.js') }}"></script>

            @if($this->score->urls->isNotEmpty())
            <div class="mb-6 flex flex-wrap gap-2">
                @foreach($this->score->urls as $scoreUrl)
                <a
                    href="{{ $scoreUrl->url }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-1.5 rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1.5 text-sm text-zinc-700 hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700"
                >
                    @if($scoreUrl->label instanceof \App\MusicUrlLabel)
                    <flux:icon name="{{ $scoreUrl->label->icon() }}" variant="micro" class="shrink-0 {{ $scoreUrl->label->color() }}" />
                    @else
                    <flux:icon name="link" variant="micro" class="shrink-0 text-zinc-400" />
                    @endif
                    <span class="max-w-xs truncate">{{ $scoreUrl->comment ?: ($scoreUrl->label instanceof \App\MusicUrlLabel ? $scoreUrl->label->label() : $scoreUrl->url) }}</span>
                    <flux:icon name="arrow-top-right-on-square" variant="micro" class="shrink-0 text-zinc-400" />
                </a>
                @endforeach
            </div>
            @endif

            @if($this->scoreFiles->isNotEmpty())
            <div class="mb-6 space-y-2">
                @foreach($this->scoreFiles as $file)
                <div wire:key="share-score-file-{{ $file->id }}" class="flex flex-wrap items-center gap-2">
                    @if($file->isReady())
                    <x-score-file-pages
                        name="score-file-pages-share-{{ $file->id }}"
                        :pages="$this->filePageUrls[$file->id] ?? []"
                        :heading="$file->displayName()"
                        :label="__('View :name', ['name' => $file->displayName()])" />

                    @if($allowDownload)
                    <flux:button
                        icon="arrow-down-tray"
                        variant="outline"
                        size="sm"
                        :href="route('share.score.file.download', ['token' => $shareToken, 'score' => $this->score, 'scoreFile' => $file])">
                        {{ __('Download :name', ['name' => $file->displayName()]) }}
                    </flux:button>
                    @endif
                    @elseif(! $file->isRendering())
                    <flux:text class="text-sm text-zinc-500">{{ $file->displayName() }} — {{ $file->render_status->label() }}</flux:text>
                    @endif
                </div>
                @endforeach

                @if($this->filesRendering)
                {{-- The render runs on the queue; poll until it lands rather than making the reader reload. --}}
                <div wire:poll.2s class="flex items-center gap-2">
                    <flux:icon.loading class="size-4 shrink-0 text-zinc-400" />
                    <flux:text class="text-sm text-zinc-500">{{ __('Rendering the sheet music — this page updates on its own when it is ready.') }}</flux:text>
                </div>
                @endif
            </div>
            @endif

            @if($this->content !== '')
            <div
                x-data="scoreEditor({
                    scoreSettings: @js($this->settings ?: (object) []),
                    userDefaults: @js((object) []),
                    clippedWarningText: @js(__('Content does not fit on page')),
                    clipboardNotSupported: @js(__('Clipboard not supported in this browser')),
                    firstPageCopied: @js(__('First page copied to clipboard')),
                    imageCopied: @js(__('Image copied to clipboard')),
                    failedToCopy: @js(__('Failed to copy image')),
                    shareLinkCopied: @js(''),
                    linkCopyFailed: @js(__('Failed to copy link')),
                    htmlCopied: @js(__('HTML copied to clipboard!')),
                    plainTextCopied: @js(__('Plain text copied to clipboard!')),
                    copyAsImageText: @js(__('Copy as Image')),
                    exportPngText: @js(__('Export PNG')),
                    exportSvgText: @js(__('Export SVG')),
                    fullscreenText: @js(__('Fullscreen')),
                })"
            >
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
                        <flux:tooltip :content="__('Staff size')">
                            <flux:icon name="list-chevrons-up-down" variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                        </flux:tooltip>
                        <flux:input size="sm" type="number" x-model="abcPageScale" min="1" max="5" step="0.1" class="w-16" />
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
                        <flux:input size="sm" type="number" x-model="abcZoom" min="50" max="300" step="5" class="w-16" />
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
                        <flux:input size="sm" type="number" x-model="abcTranspose" min="-11" max="11" step="1" class="w-16" />
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
                </div>

                {{-- ABC Preview --}}
                <div x-show="$wire.format === 'abc'" x-cloak class="mt-4">
                    <div x-ref="abcPreview" class="min-h-16 space-y-4 overflow-x-auto"></div>
                </div>

                {{-- GABC Preview --}}
                <div x-show="$wire.format === 'gabc'" x-cloak class="mt-4">
                    <div x-ref="preview" class="min-h-16 space-y-4 overflow-x-auto"></div>
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
                </div>

                {{-- Aretino Preview --}}
                <div x-show="$wire.format === 'aretino'" x-cloak class="mt-4">
                    <div x-ref="aretinoPreview" class="min-h-16 space-y-4 overflow-x-auto"></div>
                </div>

                {{-- ChordPro Preview --}}
                <div x-show="$wire.format === 'chordpro'" x-cloak class="mt-4">
                    <div x-ref="chordproPreview" class="min-h-16 space-y-4 overflow-x-auto"></div>

                    <div class="mt-2 flex flex-wrap items-center justify-end gap-2" x-show="hasPages">
                        <span x-show="copyFeedback" x-text="copyFeedback" x-transition class="text-sm text-zinc-600 dark:text-zinc-300"></span>
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
            </div>
            @endif
        </flux:card>
    </div>
</div>
