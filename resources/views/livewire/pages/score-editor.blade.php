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
                <x-action-message on="score-defaults-saved">
                    {{ __('Saved as your default for this ratio.') }}
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
<script src="https://cdn.jsdelivr.net/npm/abcjs@6/dist/abcjs-basic-min.js"></script>

                <div
                    x-data="{
                        hasPages: false,
                        localContent: '',
                        clippedWarningText: @js(__('Content does not fit on page')),
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
                        abcScale: 1,
                        abcStaffWidth: 740,
                        abcTranspose: 0,
                        abcPageRatio: 'auto',
                        scoreSettings: @js($settings ?: (object) []),
                        userDefaults: @js($userDefaults ?: (object) []),
                        gabcFields: ['zoom','lyricSize','staffSize','dropCapSize','dropCaps','lyricFont','minLyricWordSpacing','hyphenWidth','condensingTolerance'],
                        abcFields: ['abcScale','abcStaffWidth','abcTranspose'],
                        collectSettings() {
                            if ($wire.format === 'gabc') {
                                return {
                                    settings: {
                                        zoom: Number(this.zoom),
                                        lyricSize: Number(this.lyricSize),
                                        staffSize: Number(this.staffSize),
                                        dropCapSize: Number(this.dropCapSize),
                                        dropCaps: !!this.dropCaps,
                                        lyricFont: this.lyricFont,
                                        minLyricWordSpacing: Number(this.minLyricWordSpacing),
                                        hyphenWidth: Number(this.hyphenWidth),
                                        condensingTolerance: Number(this.condensingTolerance),
                                    },
                                    ratio: this.pageRatio,
                                };
                            }
                            return {
                                settings: {
                                    abcScale: Number(this.abcScale),
                                    abcStaffWidth: Number(this.abcStaffWidth),
                                    abcTranspose: Number(this.abcTranspose),
                                },
                                ratio: this.abcPageRatio,
                            };
                        },
                        applyRatioSettings(format, ratio) {
                            const score = (this.scoreSettings && this.scoreSettings[format] && this.scoreSettings[format][ratio]) || null;
                            const user = (this.userDefaults && this.userDefaults[format] && this.userDefaults[format][ratio]) || null;
                            const merged = { ...(user || {}), ...(score || {}) };
                            Object.keys(merged).forEach(k => { if (k in this) { this[k] = merged[k]; } });
                        },
                        applyInitialSettings() {
                            this.applyRatioSettings('gabc', this.pageRatio);
                            this.applyRatioSettings('abc', this.abcPageRatio);
                        },
                        saveScore() {
                            const c = this.collectSettings();
                            $wire.call('save', c.settings, c.ratio);
                        },
                        saveAsDefault() {
                            const c = this.collectSettings();
                            $wire.call('saveAsDefault', c.settings, c.ratio, $wire.format);
                        },
                        getRenderWidth() {
                            const widths = { '16/9': 1920, '4/3': 1440, '1/1': 1080 };
                            return widths[this.pageRatio] || 1200;
                        },
                        splitPages(content, format, ratio) {
                            const ratioSuffix = { '16/9': '169', '4/3': '43', '1/1': '11' };
                            const targetSuffix = ratioSuffix[ratio];
                            const isAuto = !targetSuffix;
                            const lines = content.split('\n');
                            let headerEnd = -1;
                            if (format === 'gabc') {
                                for (let i = 0; i < lines.length; i++) {
                                    if (/^%%\s*$/.test(lines[i])) { headerEnd = i; break; }
                                }
                            } else {
                                for (let i = 0; i < lines.length; i++) {
                                    if (/^K:/.test(lines[i])) { headerEnd = i; break; }
                                }
                            }
                            const header = headerEnd >= 0 ? lines.slice(0, headerEnd + 1).join('\n') + '\n' : '';
                            const bodyLines = headerEnd >= 0 ? lines.slice(headerEnd + 1) : lines.slice();
                            const breakRe = /^\s*%pagebreak(\d*)\s*$/;
                            if (isAuto) {
                                const stripped = bodyLines.filter(l => !breakRe.test(l)).join('\n');
                                return [header + stripped];
                            }
                            const pages = [];
                            let current = [];
                            for (const line of bodyLines) {
                                const m = line.match(breakRe);
                                if (m) {
                                    const suffix = m[1];
                                    if (suffix === '' || suffix === targetSuffix) {
                                        pages.push(current.join('\n'));
                                        current = [];
                                    }
                                    continue;
                                }
                                current.push(line);
                            }
                            pages.push(current.join('\n'));
                            return pages.map(p => header + p);
                        },
                        appendClipWarning(pageEl) {
                            const warn = document.createElement('div');
                            warn.className = 'mt-2 flex justify-center';
                            const span = document.createElement('span');
                            span.className = 'rounded bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900 dark:text-red-300';
                            span.textContent = this.clippedWarningText;
                            warn.appendChild(span);
                            pageEl.insertAdjacentElement('afterend', warn);
                        },
                        fixAbcLyrics(pageEl) {
                            const noteXMap = new Map();
                            pageEl.querySelectorAll('.abcjs-note .abcjs-notehead').forEach(nh => {
                                const bbox = nh.getBBox();
                                const noteCenter = bbox.x + bbox.width / 2;
                                const noteGroup = nh.closest('.abcjs-note');
                                if (noteGroup) {
                                    const lyric = noteGroup.querySelector('text.abcjs-lyric');
                                    if (lyric) {
                                        noteXMap.set(lyric, noteCenter);
                                    }
                                }
                            });
                            pageEl.querySelectorAll('text.abcjs-lyric').forEach(t => {
                                const txt = t.textContent || '';
                                const tspans = t.querySelectorAll('tspan');
                                if (txt.startsWith('\u200B')) {
                                    t.textContent = txt.replace('\u200B', '');
                                    t.setAttribute('text-anchor', 'start');
                                } else {
                                    const noteCenter = noteXMap.get(t);
                                    if (noteCenter !== undefined) {
                                        const cx = String(noteCenter + (txt.includes('-') ? 4 : 0));
                                        t.setAttribute('text-anchor', 'middle');
                                        t.setAttribute('x', cx);
                                        tspans.forEach(ts => ts.setAttribute('x', cx));
                                    }
                                }
                            });
                        },
                        renderAbcPreview() {
                            if (!window.ABCJS) { return; }
                            const container = this.$refs.abcPreview;
                            if (!container) { return; }
                            container.innerHTML = '';
                            const content = this.localContent;
                            if (!content || !content.trim()) {
                                this.hasPages = false;
                                return;
                            }
                            const pages = this.splitPages(content, 'abc', this.abcPageRatio);
                            const options = {
                                scale: Number(this.abcScale),
                                staffwidth: Number(this.abcStaffWidth),
                                visualTranspose: Number(this.abcTranspose),
                                add_classes: true,
                                paddingtop: 15,
                                paddingbottom: 30,
                                paddingleft: 15,
                                paddingright: 50,
                            };
                            pages.forEach((pageSource) => {
                                const pageEl = document.createElement('div');
                                pageEl.className = 'overflow-hidden rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900 [&_svg]:max-w-full [&_svg]:h-auto';
                                if (this.abcPageRatio !== 'auto') {
                                    pageEl.style.aspectRatio = this.abcPageRatio;
                                }
                                container.appendChild(pageEl);
                                try {
                                    const rendered = pageSource.replace(/^(w:\s*)(.*)$/gm, (match, prefix, lyrics) => {
                                        return prefix + lyrics.replace(/<([^ |*~_-]+)/g, '\u200B$1');
                                    });
                                    ABCJS.renderAbc(pageEl, rendered, options);
                                    this.fixAbcLyrics(pageEl);
                                    if (this.abcPageRatio !== 'auto' && pageEl.scrollHeight > pageEl.clientHeight + 2) {
                                        this.appendClipWarning(pageEl);
                                    }
                                } catch (e) {
                                    console.error('[score-editor] abcjs error:', e);
                                }
                            });
                            this.hasPages = pages.length > 0;
                        },
                        renderPreview() {
                            if ($wire.format === 'abc') {
                                this.renderAbcPreview();
                                return;
                            }
                            const container = this.$refs.preview;
                            if (!container) { return; }
                            container.innerHTML = '';
                            this.hasPages = false;
                            if ($wire.format !== 'gabc') { return; }
                            if (!window.exsurge) { return; }
                            const content = this.localContent;
                            if (!content || !content.trim()) { return; }
                            const pages = this.splitPages(content, 'gabc', this.pageRatio);
                            const width = this.getRenderWidth();
                            const pageEls = pages.map(() => {
                                const pageEl = document.createElement('div');
                                pageEl.className = 'overflow-hidden rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900 [&_svg]:max-w-full [&_svg]:h-auto';
                                if (this.pageRatio !== 'auto') {
                                    pageEl.style.aspectRatio = this.pageRatio;
                                }
                                container.appendChild(pageEl);
                                return pageEl;
                            });
                            this.hasPages = true;
                            pages.forEach((pageSource, idx) => {
                                const pageEl = pageEls[idx];
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
                                    const mappings = exsurge.Gabc.createMappingsFromSource(ctxt, pageSource);
                                    const score = new exsurge.ChantScore(ctxt, mappings, this.dropCaps);
                                    score.performLayoutAsync(ctxt, () => {
                                        score.layoutChantLines(ctxt, width, () => {
                                            let html = score.createSvg(ctxt);
                                            const parser = new DOMParser();
                                            const doc = parser.parseFromString(html, 'image/svg+xml');
                                            const svg = doc.querySelector('svg');
                                            let viewBoxW = 0;
                                            let contentH = 0;
                                            if (svg) {
                                                const w = svg.getAttribute('width');
                                                const h = svg.getAttribute('height');
                                                if (w && h) {
                                                    viewBoxW = parseFloat(w);
                                                    contentH = parseFloat(h);
                                                    svg.setAttribute('viewBox', '0 0 ' + viewBoxW + ' ' + (contentH + 20));
                                                    svg.removeAttribute('width');
                                                    svg.removeAttribute('height');
                                                }
                                                svg.style.overflow = 'visible';
                                                html = new XMLSerializer().serializeToString(svg);
                                            }
                                            pageEl.innerHTML = html;
                                            if (this.pageRatio !== 'auto' && viewBoxW > 0) {
                                                const padding = 32;
                                                const renderWidthPx = Math.max(0, pageEl.clientWidth - padding);
                                                const scale = renderWidthPx / viewBoxW;
                                                const contentHeightPx = contentH * scale;
                                                const availableHeightPx = pageEl.clientHeight - padding;
                                                if (contentHeightPx > availableHeightPx + 2) {
                                                    this.appendClipWarning(pageEl);
                                                }
                                            }
                                        });
                                    });
                                } catch (e) {
                                    console.error('[score-editor] exsurge error:', e);
                                }
                            });
                        },
                        scheduleRender() {
                            clearTimeout(this.renderTimer);
                            this.renderTimer = setTimeout(() => this.renderPreview(), 600);
                        },
                        copyFeedback: '',
                        copyFeedbackTimer: null,
                        showCopyFeedback(msg) {
                            this.copyFeedback = msg;
                            clearTimeout(this.copyFeedbackTimer);
                            this.copyFeedbackTimer = setTimeout(() => { this.copyFeedback = ''; }, 2500);
                        },
                        svgToCanvas(svgEl) {
                            const renderWidth = this.getRenderWidth();
                            const scale = $wire.format === 'abc' ? 2 : 1;
                            const margin = 20;
                            const viewBox = svgEl.getAttribute('viewBox');
                            let svgWidth = renderWidth;
                            let svgHeight = renderWidth * 9 / 16;
                            let paddedViewBox = null;
                            if (viewBox) {
                                const parts = viewBox.split(/\s+/).map(Number);
                                if (parts.length === 4) {
                                    svgWidth = parts[2];
                                    svgHeight = parts[3];
                                    paddedViewBox = `${parts[0] - margin} ${parts[1] - margin} ${parts[2] + margin * 2} ${parts[3] + margin * 2}`;
                                }
                            } else {
                                const bbox = svgEl.getBBox();
                                const w = svgEl.getAttribute('width');
                                const h = svgEl.getAttribute('height');
                                svgWidth = w ? parseFloat(w) : bbox.width + bbox.x;
                                svgHeight = h ? parseFloat(h) : bbox.height + bbox.y;
                            }
                            const paddedWidth = svgWidth + margin * 2;
                            const paddedHeight = svgHeight + margin * 2;
                            const clonedSvg = svgEl.cloneNode(true);
                            clonedSvg.setAttribute('width', String(paddedWidth));
                            clonedSvg.setAttribute('height', String(paddedHeight));
                            if (paddedViewBox) {
                                clonedSvg.setAttribute('viewBox', paddedViewBox);
                            }
                            const svgData = new XMLSerializer().serializeToString(clonedSvg);
                            const svgBlob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
                            const url = URL.createObjectURL(svgBlob);
                            return new Promise((resolve, reject) => {
                                const img = new Image();
                                img.onload = () => {
                                    const canvas = document.createElement('canvas');
                                    canvas.width = paddedWidth * scale;
                                    canvas.height = paddedHeight * scale;
                                    const ctx = canvas.getContext('2d');
                                    ctx.fillStyle = '#ffffff';
                                    ctx.fillRect(0, 0, canvas.width, canvas.height);
                                    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                                    URL.revokeObjectURL(url);
                                    resolve(canvas);
                                };
                                img.onerror = (e) => { URL.revokeObjectURL(url); reject(e); };
                                img.src = url;
                            });
                        },
                        exportPng() {
                            const previewEl = $wire.format === 'abc' ? this.$refs.abcPreview : this.$refs.preview;
                            if (!previewEl) { return; }
                            const svgs = previewEl.querySelectorAll('svg');
                            if (!svgs.length) { return; }
                            const total = svgs.length;
                            svgs.forEach((svgEl, idx) => {
                                this.svgToCanvas(svgEl).then(canvas => {
                                    const a = document.createElement('a');
                                    a.download = total > 1 ? 'score-page-' + (idx + 1) + '.png' : 'score.png';
                                    a.href = canvas.toDataURL('image/png');
                                    a.click();
                                }).catch(e => console.error('[score-editor] export error:', e));
                            });
                        },
                        async copyImage() {
                            const previewEl = $wire.format === 'abc' ? this.$refs.abcPreview : this.$refs.preview;
                            if (!previewEl) { return; }
                            const svgs = previewEl.querySelectorAll('svg');
                            if (!svgs.length) { return; }
                            if (!navigator.clipboard || !window.ClipboardItem) {
                                this.showCopyFeedback(@js(__('Clipboard not supported in this browser')));
                                return;
                            }
                            try {
                                const blobPromise = this.svgToCanvas(svgs[0]).then(canvas =>
                                    new Promise(resolve => canvas.toBlob(resolve, 'image/png'))
                                );
                                await navigator.clipboard.write([new ClipboardItem({ 'image/png': blobPromise })]);
                                const msg = svgs.length > 1
                                    ? @js(__('First page copied to clipboard'))
                                    : @js(__('Image copied to clipboard'));
                                this.showCopyFeedback(msg);
                            } catch (e) {
                                console.error('[score-editor] copy image error:', e);
                                this.showCopyFeedback(@js(__('Failed to copy image')));
                            }
                        }
                    }"
                    x-init="
                        console.log('[score-editor] init, exsurge available:', !!window.exsurge, 'format:', $wire.format);
                        applyInitialSettings();
                        localContent = $wire.content;
                        $watch('$wire.content', (val) => { console.log('[score-editor] $wire.content changed, len:', val?.length); localContent = val; scheduleRender(); });
                        $watch('$wire.format', (val) => { console.log('[score-editor] $wire.format changed:', val); scheduleRender(); });
                        $watch('lyricSize', () => scheduleRender());
                        $watch('staffSize', () => scheduleRender());
                        $watch('dropCapSize', () => scheduleRender());
                        $watch('lyricFont', () => scheduleRender());
                        $watch('pageRatio', (val) => { applyRatioSettings('gabc', val); $nextTick(() => scheduleRender()); });
                        $watch('dropCaps', () => scheduleRender());
                        $watch('minLyricWordSpacing', () => scheduleRender());
                        $watch('hyphenWidth', () => scheduleRender());
                        $watch('condensingTolerance', () => scheduleRender());
                        $watch('zoom', () => scheduleRender());
                        $watch('abcScale', () => scheduleRender());
                        $watch('abcStaffWidth', () => scheduleRender());
                        $watch('abcTranspose', () => scheduleRender());
                        $watch('abcPageRatio', (val) => { applyRatioSettings('abc', val); $nextTick(() => scheduleRender()); });

                        $nextTick(() => { console.log('[score-editor] nextTick, exsurge available:', !!window.exsurge); scheduleRender(); });
                    "
                >
                    <flux:field required>
                        <flux:label>{{ __('Score Content') }}</flux:label>
                        <flux:textarea wire:model="content" rows="10" class="font-mono text-sm" :placeholder="__('Paste or type your ABC or GABC source here')" x-on:input="localContent = $event.target.value; scheduleRender()" />
                        <flux:error name="content" />
                    </flux:field>

                    <div x-show="$wire.format === 'abc'" x-cloak class="mt-4">
                        <div x-ref="abcPreview" class="min-h-16 space-y-4"></div>

                        <div class="mt-2 flex flex-wrap items-center justify-end gap-2" x-show="hasPages">
                            <span x-show="copyFeedback" x-text="copyFeedback" x-transition class="text-sm text-zinc-600 dark:text-zinc-300"></span>
                            <flux:button icon="bookmark" variant="ghost" x-on:click="saveAsDefault()">
                                {{ __('Save as my default for this ratio') }}
                            </flux:button>
                            <flux:button icon="clipboard" variant="ghost" x-on:click="copyImage()">
                                {{ __('Copy as Image') }}
                            </flux:button>
                            <flux:button icon="arrow-down-tray" variant="ghost" x-on:click="exportPng()">
                                {{ __('Export PNG') }}
                            </flux:button>
                        </div>

                        <flux:separator class="my-4" />

                        <flux:heading size="sm">{{ __('Preview Settings') }}</flux:heading>

                        <div class="mt-2 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <flux:field>
                                <flux:label>{{ __('Scale') }} (<span x-text="abcScale"></span>x)</flux:label>
                                <input type="range" x-model="abcScale" min="0.5" max="3" step="0.1" class="w-full accent-zinc-800 dark:accent-zinc-200" />
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('Staff Width') }} (<span x-text="abcStaffWidth"></span>px)</flux:label>
                                <input type="range" x-model="abcStaffWidth" min="400" max="1600" step="10" class="w-full accent-zinc-800 dark:accent-zinc-200" />
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('Transpose') }} (<span x-text="abcTranspose"></span> {{ __('semitones') }})</flux:label>
                                <input type="range" x-model="abcTranspose" min="-12" max="12" step="1" class="w-full accent-zinc-800 dark:accent-zinc-200" />
                            </flux:field>

                            <flux:field class="w-auto">
                                <flux:label>{{ __('Page Ratio') }}</flux:label>
                                <flux:select x-model="abcPageRatio" class="w-36">
                                    <flux:select.option value="auto">{{ __('Auto') }}</flux:select.option>
                                    <flux:select.option value="16/9">16:9</flux:select.option>
                                    <flux:select.option value="4/3">4:3</flux:select.option>
                                    <flux:select.option value="1/1">1:1</flux:select.option>
                                </flux:select>
                            </flux:field>


                        </div>
                    </div>

                    <div x-show="$wire.format === 'gabc'" x-cloak class="mt-4">
                        <div x-ref="preview" class="min-h-16 space-y-4"></div>

                        <div class="mt-2 flex flex-wrap items-center justify-end gap-2" x-show="hasPages">
                            <span x-show="copyFeedback" x-text="copyFeedback" x-transition class="text-sm text-zinc-600 dark:text-zinc-300"></span>
                            <flux:button icon="bookmark" variant="ghost" x-on:click="saveAsDefault()">
                                {{ __('Save as my default for this ratio') }}
                            </flux:button>
                            <flux:button icon="clipboard" variant="ghost" x-on:click="copyImage()">
                                {{ __('Copy as Image') }}
                            </flux:button>
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

                <div class="flex justify-end gap-3">
                    <flux:button variant="ghost" :href="route('scores')" wire:navigate>
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button variant="primary" icon="pencil" x-on:click="saveScore()">
                        {{ __('Save Score') }}
                    </flux:button>
                </div>
                </div>
            </div>
        </flux:card>
    </div>
</div>
