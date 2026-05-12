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
                                const mappings = exsurge.Gabc.createMappingsFromSource(ctxt, content);
                                const score = new exsurge.ChantScore(ctxt, mappings, true);
                                const width = this.$refs.preview ? this.$refs.preview.offsetWidth : 800;
                                console.log('[score-editor] performLayoutAsync starting, width:', width);
                                score.performLayoutAsync(ctxt, () => {
                                    console.log('[score-editor] layoutChantLines starting');
                                    score.layoutChantLines(ctxt, width || 800, () => {
                                        const html = score.createSvg(ctxt);
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
                        }
                    }"
                    x-init="
                        console.log('[score-editor] init, exsurge available:', !!window.exsurge, 'format:', $wire.format);
                        localContent = $wire.content;
                        $watch('$wire.content', (val) => { console.log('[score-editor] $wire.content changed, len:', val?.length); localContent = val; scheduleRender(); });
                        $watch('$wire.format', (val) => { console.log('[score-editor] $wire.format changed:', val); scheduleRender(); });
                        $nextTick(() => { console.log('[score-editor] nextTick, exsurge available:', !!window.exsurge); scheduleRender(); });
                    "
                >
                    <flux:field required>
                        <flux:label>{{ __('Score Content') }}</flux:label>
                        <flux:textarea wire:model="content" rows="24" class="font-mono text-sm" :placeholder="__('Paste or type your ABC or GABC source here')" x-on:input="localContent = $event.target.value; scheduleRender()" />
                        <flux:error name="content" />
                    </flux:field>

                    <div x-show="$wire.format === 'gabc'" x-cloak class="mt-4">
                        <flux:heading size="sm">{{ __('Preview') }}</flux:heading>
                        <div
                            x-ref="preview"
                            class="mt-2 min-h-16 overflow-x-auto rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900"
                            x-html="previewHtml"
                        ></div>
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
