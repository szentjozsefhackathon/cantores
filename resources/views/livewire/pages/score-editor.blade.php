<div class="py-8">
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        <flux:card class="p-4 lg:p-6">
            <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <flux:heading size="2xl">{{ $score ? __('Edit Score') : __('Create Score') }}</flux:heading>
                    <flux:subheading>{{ __('Scores are always private and visible only to you.') }}</flux:subheading>
                </div>

                <div class="flex flex-wrap gap-2">
                    <flux:button variant="ghost" icon="arrow-left" :href="route('scores')" wire:navigate>
                        {{ __('Back to Scores') }}
                    </flux:button>
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
            </div>

            <div class="space-y-6">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <flux:field required>
                        <flux:label>{{ __('Title') }}</flux:label>
                        <flux:input wire:model="title" :placeholder="__('Score title')" autofocus />
                        <flux:error name="title" />
                    </flux:field>

                    <flux:field required>
                        <flux:label>{{ __('Format') }}</flux:label>
                        <flux:select wire:model="format">
                            @foreach($formats as $formatOption)
                                <flux:select.option value="{{ $formatOption->value }}">{{ $formatOption->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="format" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>{{ __('Attached Music') }}</flux:label>
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

<script src="https://ex.surge.sh/exsurge.js"></script>

                <div
                    x-data="{
                        previewHtml: '',
                        localContent: '',
                        renderTimer: null,
                        zoom: 100,
                        lyricSize: 16,
                        staffSize: 100,
                        dropCapSize: 64,
                        minLyricWordSpacing: 0,
                        hyphenWidth: 0,
                        condensingTolerance: 0.9,
                        pageRatio: 'auto',
                        dropCaps: false,
                        lyricFont: &quot;'Palatino Linotype', 'Book Antiqua', Palatino, serif&quot;,
                        getRenderWidth() {
                            const widths = { '16/9': 1920, '4/3': 1440, '1/1': 1080 };
                            return widths[this.pageRatio] || 1200;
                        },
                        renderPreview() {
                            console.log('[score-editor] renderPreview called', { format: $wire.format, exsurgeLoaded: !!window.exsurge, contentLength: this.localContent?.length });
                            if ($wire.format !== 'gabc') {
                                console.log('[score-editor] skipping: format is not gabc');
                                this.previewHtml = '';
                                return;
                            }
                            if (!window.exsurge) {
                                console.warn('[score-editor] skipping: window.exsurge is not available');
                                this.previewHtml = '';
                                return;
                            }
                            const content = this.localContent;
                            if (!content || !content.trim()) {
                                console.log('[score-editor] skipping: content is empty');
                                this.previewHtml = '';
                                return;
                            }
                            console.log('[score-editor] calling exsurge with content:', content.substring(0, 100));
                            try {
                                const ctxt = new exsurge.ChantContext();
                                const z = Number(this.zoom) / 100;
                                ctxt.lyricTextSize = Number(this.lyricSize) * z;
                                ctxt.lyricTextFont = this.lyricFont;
                                ctxt.dropCapTextFont = this.lyricFont;
                                ctxt.annotationTextFont = this.lyricFont;
                                ctxt.dropCapTextSize = Number(this.dropCapSize) * z;
                                ctxt.glyphScaling = (1.0 / 16.0) * (Number(this.staffSize) / 100) * z;
                                ctxt.staffInterval = ctxt.glyphPunctumWidth * ctxt.glyphScaling;
                                ctxt.staffLineWeight = Math.round(ctxt.glyphPunctumWidth * ctxt.glyphScaling / 8);
                                ctxt.neumeLineWeight = ctxt.staffLineWeight;
                                ctxt.dividerLineWeight = ctxt.neumeLineWeight;
                                ctxt.episemaLineWeight = ctxt.neumeLineWeight;
                                if (Number(this.minLyricWordSpacing) > 0) {
                                    ctxt.minLyricWordSpacing = Number(this.minLyricWordSpacing) * z;
                                }
                                if (Number(this.hyphenWidth) > 0) {
                                    ctxt.hyphenWidth = Number(this.hyphenWidth) * z;
                                }
                                ctxt.condensingTolerance = Number(this.condensingTolerance);
                                const mappings = exsurge.Gabc.createMappingsFromSource(ctxt, content);
                                const score = new exsurge.ChantScore(ctxt, mappings, this.dropCaps);
                                const width = this.getRenderWidth();
                                console.log('[score-editor] performLayoutAsync starting, width:', width);
                                score.performLayoutAsync(ctxt, () => {
                                    console.log('[score-editor] layoutChantLines starting');
                                    score.layoutChantLines(ctxt, width, () => {
                                        let html = score.createSvg(ctxt);
                                        // Add viewBox to SVG so it scales to fit the container
                                        const parser = new DOMParser();
                                        const doc = parser.parseFromString(html, 'image/svg+xml');
                                        const svg = doc.querySelector('svg');
                                        if (svg) {
                                            const w = svg.getAttribute('width');
                                            const h = svg.getAttribute('height');
                                            if (w && h) {
                                                svg.setAttribute('viewBox', '0 0 ' + parseFloat(w) + ' ' + parseFloat(h));
                                                svg.removeAttribute('width');
                                                svg.removeAttribute('height');
                                            }
                                            html = new XMLSerializer().serializeToString(svg);
                                        }
                                        console.log('[score-editor] render complete, html length:', html?.length);
                                        this.previewHtml = html;
                                    });
                                });
                            } catch (e) {
                                console.error('[score-editor] exsurge error:', e);
                                this.previewHtml = '';
                            }
                        },
                        scheduleRender() {
                            clearTimeout(this.renderTimer);
                            this.renderTimer = setTimeout(() => this.renderPreview(), 600);
                        },
                        exportPng() {
                            const previewEl = this.$refs.preview;
                            const svgEl = previewEl ? previewEl.querySelector('svg') : null;
                            if (!svgEl) { return; }
                            const renderWidth = this.getRenderWidth();
                            const viewBox = svgEl.getAttribute('viewBox');
                            let svgWidth = renderWidth;
                            let svgHeight = renderWidth * 9 / 16;
                            if (viewBox) {
                                const parts = viewBox.split(/\s+/).map(Number);
                                if (parts.length === 4) {
                                    svgWidth = parts[2];
                                    svgHeight = parts[3];
                                }
                            }
                            const clonedSvg = svgEl.cloneNode(true);
                            clonedSvg.setAttribute('width', String(svgWidth));
                            clonedSvg.setAttribute('height', String(svgHeight));
                            const svgData = new XMLSerializer().serializeToString(clonedSvg);
                            const svgBlob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
                            const url = URL.createObjectURL(svgBlob);
                            const img = new Image();
                            img.onload = () => {
                                const canvas = document.createElement('canvas');
                                canvas.width = svgWidth;
                                canvas.height = svgHeight;
                                const ctx = canvas.getContext('2d');
                                ctx.fillStyle = '#ffffff';
                                ctx.fillRect(0, 0, canvas.width, canvas.height);
                                ctx.drawImage(img, 0, 0, svgWidth, svgHeight);
                                URL.revokeObjectURL(url);
                                const a = document.createElement('a');
                                a.download = 'score.png';
                                a.href = canvas.toDataURL('image/png');
                                a.click();
                            };
                            img.src = url;
                        }
                    }"
                    x-init="
                        console.log('[score-editor] init, exsurge available:', !!window.exsurge, 'format:', $wire.format);
                        localContent = $wire.content;
                        $watch('$wire.content', (val) => { console.log('[score-editor] $wire.content changed, len:', val?.length); localContent = val; scheduleRender(); });
                        $watch('$wire.format', (val) => { console.log('[score-editor] $wire.format changed:', val); scheduleRender(); });
                        $watch('lyricSize', () => scheduleRender());
                        $watch('staffSize', () => scheduleRender());
                        $watch('dropCapSize', () => scheduleRender());
                        $watch('lyricFont', () => scheduleRender());
                        $watch('pageRatio', () => { $nextTick(() => scheduleRender()); });
                        $watch('dropCaps', () => scheduleRender());
                        $watch('minLyricWordSpacing', () => scheduleRender());
                        $watch('hyphenWidth', () => scheduleRender());
                        $watch('condensingTolerance', () => scheduleRender());
                        $watch('zoom', () => scheduleRender());
                        $nextTick(() => { console.log('[score-editor] nextTick, exsurge available:', !!window.exsurge); scheduleRender(); });
                    "
                >
                    <flux:field required>
                        <flux:label>{{ __('Score Content') }}</flux:label>
                        <flux:textarea wire:model="content" rows="10" class="font-mono text-sm" :placeholder="__('Paste or type your ABC or GABC source here')" x-on:input="localContent = $event.target.value; scheduleRender()" />
                        <flux:error name="content" />
                    </flux:field>

                    <div x-show="$wire.format === 'gabc'" x-cloak class="mt-4">
                        <div
                            x-ref="preview"
                            class="min-h-16 overflow-hidden rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900 [&_svg]:max-w-full [&_svg]:h-auto"
                            :style="pageRatio !== 'auto' ? 'aspect-ratio: ' + pageRatio : ''"
                        >
                            <div x-html="previewHtml"></div>
                        </div>

                        <div class="mt-2 flex justify-end" x-show="previewHtml">
                            <flux:button icon="arrow-down-tray" variant="ghost" x-on:click="exportPng()">
                                {{ __('Export PNG') }}
                            </flux:button>
                        </div>

                        <flux:separator class="my-4" />

                        <flux:heading size="sm">{{ __('Preview Settings') }}</flux:heading>

                        <div class="mt-2 mb-4">
                            <flux:field>
                                <flux:label>{{ __('Zoom') }} (<span x-text="zoom"></span>%)</flux:label>
                                <input type="range" x-model="zoom" min="25" max="400" step="5" class="w-full accent-zinc-800 dark:accent-zinc-200" />
                            </flux:field>
                        </div>

                        <div class="mt-2 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <flux:field>
                                <flux:label>{{ __('Lyric Size') }} (<span x-text="lyricSize"></span>pt)</flux:label>
                                <input type="range" x-model="lyricSize" min="8" max="60" step="1" class="w-full accent-zinc-800 dark:accent-zinc-200" />
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('Staff Size') }} (<span x-text="staffSize"></span>%)</flux:label>
                                <input type="range" x-model="staffSize" min="30" max="300" step="5" class="w-full accent-zinc-800 dark:accent-zinc-200" />
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('Drop Cap Size') }} (<span x-text="dropCapSize"></span>pt)</flux:label>
                                <input type="range" x-model="dropCapSize" min="16" max="120" step="1" class="w-full accent-zinc-800 dark:accent-zinc-200" />
                            </flux:field>

                            <flux:field class="w-auto">
                                <flux:label>{{ __('Page Ratio') }}</flux:label>
                                <flux:select x-model="pageRatio" class="w-36">
                                    <flux:select.option value="auto">{{ __('Auto') }}</flux:select.option>
                                    <flux:select.option value="16/9">16:9</flux:select.option>
                                    <flux:select.option value="4/3">4:3</flux:select.option>
                                    <flux:select.option value="1/1">1:1</flux:select.option>
                                </flux:select>
                            </flux:field>

                            <flux:field class="w-auto">
                                <flux:label>{{ __('Drop Caps') }}</flux:label>
                                <flux:switch x-model="dropCaps" />
                            </flux:field>

                            <flux:field class="col-span-full sm:col-span-2 lg:col-span-2">
                                <flux:label>{{ __('Font') }}</flux:label>
                                <flux:select x-model="lyricFont">
                                    <flux:select.option value="'Palatino Linotype', 'Book Antiqua', Palatino, serif">Palatino (default)</flux:select.option>
                                    <flux:select.option value="Garamond, 'EB Garamond', serif">Garamond</flux:select.option>
                                    <flux:select.option value="'Times New Roman', Times, serif">Times New Roman</flux:select.option>
                                    <flux:select.option value="'Franklin Gothic Book', 'Franklin Gothic Medium', 'ITC Franklin Gothic', Arial, sans-serif">Franklin Gothic Book</flux:select.option>
                                </flux:select>
                            </flux:field>
                        </div>

                        <flux:separator class="my-4" />

                        <flux:heading size="sm">{{ __('Lyric Spacing') }}</flux:heading>

                        <div class="mt-2 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <flux:field>
                                <flux:label>{{ __('Min Word Spacing') }} (<span x-text="minLyricWordSpacing == 0 ? 'auto' : minLyricWordSpacing + 'px'"></span>)</flux:label>
                                <input type="range" x-model="minLyricWordSpacing" min="0" max="40" step="1" class="w-full accent-zinc-800 dark:accent-zinc-200" />
                                <flux:description>{{ __('0 = auto (derived from font). Controls minimum space between lyric words.') }}</flux:description>
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('Hyphen Width') }} (<span x-text="hyphenWidth == 0 ? 'auto' : hyphenWidth + 'px'"></span>)</flux:label>
                                <input type="range" x-model="hyphenWidth" min="0" max="40" step="1" class="w-full accent-zinc-800 dark:accent-zinc-200" />
                                <flux:description>{{ __('0 = auto (derived from font). Width allocated for hyphens between syllables.') }}</flux:description>
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('Condensing Tolerance') }} (<span x-text="condensingTolerance"></span>)</flux:label>
                                <input type="range" x-model="condensingTolerance" min="0" max="1" step="0.05" class="w-full accent-zinc-800 dark:accent-zinc-200" />
                                <flux:description>{{ __('How aggressively neume spacing can shrink to fit lyrics (0–1).') }}</flux:description>
                            </flux:field>
                        </div>
                    </div>
                </div>


                <div class="flex justify-end gap-3">
                    <flux:button variant="ghost" :href="route('scores')" wire:navigate>
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button variant="primary" icon="pencil" wire:click="save">
                        {{ __('Save Score') }}
                    </flux:button>
                </div>
            </div>
        </flux:card>
    </div>
</div>
