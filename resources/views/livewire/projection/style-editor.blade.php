@php
    use App\Enums\ProjectionRatio;
    use App\Enums\ProjectionTextTheme;
    use App\Enums\ScoreFormat;
    use App\Support\ProjectionSettingFields;
@endphp

{{-- The slide styles, edited beside the deck they are judged on.

     A flyout rather than a centred dialog, so the slides stay in view: a style
     is chosen by looking at what it does to a hymn on the screen, and every
     change here is drawn there as it is saved. --}}
<div>
    <flux:modal wire:model="open" variant="flyout" class="w-full md:w-[30rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingId === null ? __('New slide style') : __('Slide style') }}</flux:heading>
                <flux:subheading>
                    {{ __('How every slide is set on one screen. A score already laid out for this screen shape in the score editor keeps its own layout, unless its slide is set to follow the style.') }}
                </flux:subheading>
            </div>

            @if($editingId !== null && $this->styles->count() > 1)
                <flux:select size="sm" wire:model.live="editingId" :label="__('Editing')">
                    @foreach($this->styles as $style)
                        <flux:select.option :value="$style->id">{{ $style->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:input wire:model.live.debounce.600ms="name" :label="__('Name')" maxlength="100" :placeholder="__('e.g. Parish church, large 4:3 screen')" />

            {{-- Fixed once made: every number below was chosen against this
                 shape. A variant for another screen is a copy. --}}
            <flux:select size="sm" wire:model="ratio" :label="__('Screen shape')" :disabled="$editingId !== null">
                @foreach(ProjectionRatio::cases() as $case)
                    <flux:select.option value="{{ $case->value }}">{{ $case->label() }} — {{ $case->description() }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="grid grid-cols-2 gap-3">
                <flux:select size="sm" wire:model.live="textTheme" :label="__('Text slides')">
                    @foreach(ProjectionTextTheme::cases() as $case)
                        <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input size="sm" type="number" wire:model.live.debounce.500ms="minPercent" :label="__('Shrink to save a slide (%)')" min="50" max="100" step="1" />

                <flux:input size="sm" type="number" wire:model.live.debounce.500ms="textSizeScale" :label="__('Text size (×)')" min="0.3" max="4" step="0.05" />

                <flux:input size="sm" type="number" wire:model.live.debounce.500ms="textLineHeight" :label="__('Text line spacing')" min="0.8" max="3" step="0.05" />
            </div>

            <flux:text class="text-xs text-zinc-500">
                {{ __('A slide that would just not fit is set smaller, down to the percentage above, before it is cut in two.') }}
            </flux:text>

            @if($editingId === null)
                <div class="flex justify-end">
                    <flux:button variant="primary" wire:click="create">{{ __('Create and use on this deck') }}</flux:button>
                </div>
            @else
                {{-- Rebuilt whenever another style is opened, so the browser's copy
                     of the knobs is never the last style's. --}}
                <div
                    wire:key="style-formats-{{ $editingId }}"
                    data-style-config="{{ json_encode(['ratio' => $ratio, 'settings' => (object) $settings]) }}"
                    x-data="projectionStyleFormats(JSON.parse($el.dataset.styleConfig))"
                    class="space-y-4"
                >
                    @foreach($formats as $format => $fields)
                        <div wire:key="style-format-{{ $editingId }}-{{ $format }}">
                            <flux:heading size="sm" class="mb-2">{{ ScoreFormat::from($format)->label() }}</flux:heading>

                            <div class="space-y-1.5 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800/50">
                                @foreach($fields as $field)
                                    @php $knob = Js::from(Arr::only($field, ['key', 'min', 'max', 'step', 'percent'])); @endphp

                                    <div class="flex items-center gap-2" data-style-field="{{ $format }}.{{ $field['key'] }}">
                                        <flux:icon
                                            :name="$field['icon'] ?? 'adjustments-horizontal'"
                                            variant="micro"
                                            class="shrink-0 text-zinc-500 dark:text-zinc-400"
                                            x-bind:class="isSet('{{ $format }}', '{{ $field['key'] }}') ? '!text-violet-600 dark:!text-violet-400' : ''"
                                        />
                                        <span class="min-w-0 flex-1 truncate text-xs text-zinc-600 dark:text-zinc-300">{{ $field['label'] }}</span>

                                        @if($field['type'] === 'font')
                                            @php $quote = ($field['quoted'] ?? true) ? "'" : ''; @endphp
                                            <select
                                                class="w-40 rounded-md border border-zinc-200 bg-white px-2 py-1 text-xs dark:border-zinc-600 dark:bg-zinc-800"
                                                aria-label="{{ $field['label'] }}"
                                                x-on:change="set('{{ $format }}', '{{ $field['key'] }}', $event.target.value)"
                                            >
                                                @foreach(ProjectionSettingFields::fontOptions() as $font)
                                                    <option
                                                        value="{{ $quote.$font.$quote }}"
                                                        x-bind:selected="String(knobValue('{{ $format }}', '{{ $field['key'] }}') ?? '').replace(/['&quot;]/g, '') === @js($font)"
                                                    >{{ $font }}</option>
                                                @endforeach
                                            </select>
                                        @elseif($field['type'] === 'number')
                                            @if(($field['control'] ?? null) === 'step')
                                                <flux:button size="xs" variant="ghost" icon="minus"
                                                    aria-label="{{ $field['label'] }}: {{ __('Smaller') }}"
                                                    x-on:click="nudge('{{ $format }}', {{ $knob }}, -1)"
                                                    x-bind:disabled="atLimit('{{ $format }}', {{ $knob }}, -1)" />
                                            @endif

                                            <input
                                                type="number"
                                                class="w-20 rounded-md border border-zinc-200 bg-white px-2 py-1 text-right text-xs dark:border-zinc-600 dark:bg-zinc-800"
                                                aria-label="{{ $field['label'] }}"
                                                min="{{ $field['min'] }}"
                                                max="{{ $field['max'] }}"
                                                step="any"
                                                x-bind:value="shownValue('{{ $format }}', '{{ $field['key'] }}')"
                                                x-on:change="set('{{ $format }}', '{{ $field['key'] }}', Number($event.target.value))"
                                            />

                                            @if(($field['control'] ?? null) === 'step')
                                                <flux:button size="xs" variant="ghost" icon="plus"
                                                    aria-label="{{ $field['label'] }}: {{ __('Bigger') }}"
                                                    x-on:click="nudge('{{ $format }}', {{ $knob }}, 1)"
                                                    x-bind:disabled="atLimit('{{ $format }}', {{ $knob }}, 1)" />
                                            @endif
                                        @elseif($field['type'] === 'boolean')
                                            <flux:switch
                                                :aria-label="$field['label']"
                                                x-bind:checked="!!knobValue('{{ $format }}', '{{ $field['key'] }}')"
                                                x-on:change="set('{{ $format }}', '{{ $field['key'] }}', $event.target.checked)"
                                            />
                                        @endif

                                        <flux:button size="xs" variant="ghost" icon="arrow-path"
                                            :aria-label="__('Back to the default')"
                                            x-bind:class="isSet('{{ $format }}', '{{ $field['key'] }}') ? '' : 'invisible'"
                                            x-on:click="reset('{{ $format }}', '{{ $field['key'] }}')" />
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="flex items-center justify-between gap-2">
                    <flux:button size="sm" variant="ghost" icon="document-duplicate" wire:click="duplicate">{{ __('Copy') }}</flux:button>
                    <flux:button size="sm" variant="danger" icon="trash" wire:click="delete" wire:confirm="{{ __('Delete this style? Decks in it go back to the default look.') }}">{{ __('Delete') }}</flux:button>
                </div>
            @endif
        </div>
    </flux:modal>
</div>
