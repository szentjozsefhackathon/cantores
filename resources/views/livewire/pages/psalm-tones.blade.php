@push('page-bundles')
resources/js/aretino-mini-editor.js
@endpush

@php
    $selected = $this->selectedTone;
    $setting = $this->setting;
@endphp

<div class="py-8">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <flux:heading size="xl" level="1">{{ __('Psalm tones') }}</flux:heading>
        <flux:text class="mt-2 max-w-3xl">
            {{ __('Choose a tone and type the verses, one to a line, with * between the halves and + or † at the flexa. A \' before a syllable stresses it.') }}
        </flux:text>

        <div class="mt-6 grid gap-8 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
            <div class="space-y-5">
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:select wire:model.live="collection" :label="__('Collection')" :disabled="trim($customFormula) !== ''">
                        @foreach ($this->collections as $key => $option)
                            <flux:select.option value="{{ $key }}">{{ $option['name'] }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model.live="tone" :label="__('Tone')" :disabled="trim($customFormula) !== ''">
                        @foreach (array_keys($this->collections[$this->collection]['tones'] ?? []) as $name)
                            <flux:select.option value="{{ $name }}">{{ $name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <flux:input
                    wire:model.live.debounce.400ms="customFormula"
                    :label="__('Custom formula')"
                    :description="__('Overrides the tone above. Written as: initium | flexa , mediatio | terminatio ||')"
                    placeholder="(Kb) f g | at g , at b- a as g- as a | at g f a- gs g ||"
                    class="font-mono"
                />

                @if ($selected['error'])
                    <flux:text class="text-sm text-red-600 dark:text-red-400">{{ $selected['error'] }}</flux:text>
                @endif

                @if ($this->formulaSource !== '')
                    <div
                        wire:key="formula-{{ md5($this->formulaSource) }}"
                        x-data="aretinoMiniEditor({{ Js::from($this->formulaSource) }})"
                        class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-700"
                    >
                        <div x-ref="preview" wire:ignore class="min-h-10"></div>
                    </div>
                @endif

                <flux:textarea
                    wire:model.live.debounce.500ms="verses"
                    :label="__('Verses')"
                    rows="12"
                    placeholder="Uram, te lettél menedékünk * nemzedékről nemzedékre."
                />

                <flux:select wire:model.live="initiumSetting" :label="__('Initium')">
                    @foreach (\App\Enums\InitiumSetting::cases() as $option)
                        <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="space-y-4">
                @if ($setting['error'])
                    <flux:text class="text-sm text-red-600 dark:text-red-400">{{ $setting['error'] }}</flux:text>
                @elseif ($setting['source'] === '')
                    <flux:text>{{ __('The verses set to the tone appear here.') }}</flux:text>
                @else
                    <div
                        wire:key="setting-{{ md5($setting['source']) }}"
                        x-data="aretinoMiniEditor({{ Js::from($setting['source']) }})"
                        class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-700"
                    >
                        <div x-ref="preview" wire:ignore class="min-h-10"></div>
                    </div>

                    @if ($setting['unsure'] !== [])
                        <flux:callout variant="warning" icon="exclamation-triangle">
                            <flux:callout.heading>{{ __('No stressed syllable for an accent') }}</flux:callout.heading>
                            <flux:callout.text>
                                <ul class="list-disc ps-5">
                                    @foreach ($setting['unsure'] as $unsure)
                                        <li>{{ __('Verse :verse: „:syllable”', $unsure) }}</li>
                                    @endforeach
                                </ul>
                                {{ __('Mark the syllable to stress with \' in the text if the setting is wrong.') }}
                            </flux:callout.text>
                        </flux:callout>
                    @endif

                    <div x-data="{ copied: false }" class="space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:button size="sm" icon="clipboard" x-on:click="navigator.clipboard.writeText($refs.source.value); copied = true; setTimeout(() => copied = false, 1500)">
                                <span x-show="!copied">{{ __('Copy') }}</span>
                                <span x-show="copied" x-cloak>{{ __('Copied') }}</span>
                            </flux:button>
                            <flux:button size="sm" variant="primary" icon="pencil-square" :href="$this->editorUrl" target="_blank">
                                {{ __('Open in score editor') }}
                            </flux:button>
                        </div>
                        <flux:textarea x-ref="source" readonly rows="10" class="font-mono text-sm">{{ $setting['source'] }}</flux:textarea>
                    </div>

                    <div x-data="{ copied: false }" class="space-y-2">
                        <flux:heading size="lg" level="2">{{ __('Text with the tenor departures and accents underlined') }}</flux:heading>
                        <x-mary-tabs wire:model="textFormat">
                            <x-mary-tab name="aretino" label="Aretino">
                                <div class="space-y-2">
                                    <flux:button size="sm" icon="clipboard" x-on:click="navigator.clipboard.writeText($refs.aretino.value); copied = true; setTimeout(() => copied = false, 1500)">
                                        <span x-show="!copied">{{ __('Copy') }}</span>
                                        <span x-show="copied" x-cloak>{{ __('Copied') }}</span>
                                    </flux:button>
                                    <flux:textarea x-ref="aretino" readonly rows="6" class="font-mono text-sm">{{ $setting['aretinoText'] }}</flux:textarea>
                                </div>
                            </x-mary-tab>

                            <x-mary-tab name="chordpro" label="ChordPro">
                                <div class="space-y-2">
                                    <flux:button size="sm" icon="clipboard" x-on:click="navigator.clipboard.writeText($refs.chordpro.value); copied = true; setTimeout(() => copied = false, 1500)">
                                        <span x-show="!copied">{{ __('Copy') }}</span>
                                        <span x-show="copied" x-cloak>{{ __('Copied') }}</span>
                                    </flux:button>
                                    <flux:textarea x-ref="chordpro" readonly rows="6" class="font-mono text-sm">{{ $setting['chordProText'] }}</flux:textarea>
                                </div>
                            </x-mary-tab>

                            <x-mary-tab name="html" label="HTML">
                                <div class="space-y-2">
                                    <div x-ref="html" class="space-y-1 rounded-lg border border-zinc-200 bg-white p-4 text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                                        {!! $setting['html'] !!}
                                    </div>
                                    <flux:button size="sm" icon="clipboard" x-on:click="navigator.clipboard.write([new ClipboardItem({ 'text/html': new Blob([$refs.html.innerHTML], { type: 'text/html' }), 'text/plain': new Blob([$refs.html.innerText], { type: 'text/plain' }) })]); copied = true; setTimeout(() => copied = false, 1500)">
                                        <span x-show="!copied">{{ __('Copy') }}</span>
                                        <span x-show="copied" x-cloak>{{ __('Copied') }}</span>
                                    </flux:button>
                                    <flux:textarea readonly rows="6" class="font-mono text-sm">{{ $setting['html'] }}</flux:textarea>
                                </div>
                            </x-mary-tab>
                        </x-mary-tabs>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
