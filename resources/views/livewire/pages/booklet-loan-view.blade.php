@php
    use App\Support\BookletSettingFields;
    use Illuminate\Support\Arr;
    use Illuminate\Support\Js;

    $panels = $this->panels();
@endphp

{{-- The booklet as the band reads it.

     Nothing on this page belongs to the booklet's owner but the content. Every
     control here changes what this one screen shows and is kept on this one
     device, so twenty musicians can each set the size their own eyes want
     without any of them touching the handout. --}}
<div
    x-ref="reader"
    data-booklet-reader
    class="pb-16"
    {{-- Handed over in an attribute of its own, for the reason the editor's is:
         Alpine re-runs a directive whose attribute it sees change, and reading
         the booklet again rewrites this element — so a payload written into
         x-data built the whole reader again, laying every score out a second
         time on a phone in the middle of a rehearsal. --}}
    data-booklet-config="{{ json_encode([
        'token' => $loanToken,
        'geometry' => $geometry,
        'entries' => $entries,
    ]) }}"
    x-data="bookletReader(JSON.parse($el.dataset.bookletConfig))"
    x-on:booklet-updated.window="applyUpdate($event.detail)"
>
    {{-- abc2svg and exsurge draw two of the four formats, and both are globals
         rather than bundled modules. Loaded exactly as the editor loads them,
         including the off-screen span abc2svg measures text with — it must exist
         before the library runs. --}}
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
    <script src="{{ \App\Support\VendorAsset::url('js/abc2svg-1.js') }}"></script>

    {{-- Off-screen but laid out: exsurge's chant lines are measured here, and a
         display:none element has no measurable box. --}}
    <div x-ref="measure" aria-hidden="true" class="pointer-events-none absolute -left-[10000px] top-0 w-[2400px] opacity-0"></div>

    {{-- The bar follows the reader down the booklet. On a music stand the thing
         you reach for mid-piece is the size, and a control that has scrolled off
         the top is a control that is not there. --}}
    <div class="sticky top-0 z-20 border-b border-zinc-200 bg-white/95 backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/95">
        <div class="mx-auto flex max-w-3xl flex-wrap items-center gap-x-2 gap-y-1 px-3 py-2">
            <div class="min-w-0 flex-1">
                <div class="truncate text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ $title }}</div>
                @if($ownerName !== '')
                    <div class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $ownerName }}</div>
                @endif
            </div>

            {{-- One size for the whole booklet, in two buttons. A percentage is
                 shown rather than a point size because the reader is not choosing
                 type, they are choosing how much bigger than the printed booklet
                 this should be. --}}
            <div class="flex shrink-0 items-center gap-0.5 rounded-lg border border-zinc-200 px-1 dark:border-zinc-700">
                <flux:tooltip :content="__('Smaller')">
                    <flux:button size="sm" variant="ghost" icon="minus" :aria-label="__('Smaller')"
                        x-on:click="nudgeZoom(-zoomStep)" x-bind:disabled="!canZoomOut" />
                </flux:tooltip>
                <span class="w-12 text-center text-xs tabular-nums text-zinc-500 dark:text-zinc-400" x-text="zoomPercent + '%'"></span>
                <flux:tooltip :content="__('Bigger')">
                    <flux:button size="sm" variant="ghost" icon="plus" :aria-label="__('Bigger')"
                        x-on:click="nudgeZoom(zoomStep)" x-bind:disabled="!canZoomIn" />
                </flux:tooltip>
            </div>

            <flux:tooltip :content="__('Text font')">
                <flux:select size="sm" class="w-32 shrink-0 text-xs" :aria-label="__('Text font')"
                    x-bind:value="textFont ?? booklet.textFont"
                    x-on:change="setFont($event.target.value)"
                >
                    @foreach(BookletSettingFields::fontOptions() as $font)
                        <flux:select.option value="{{ $font }}">{{ $font }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:tooltip>

            {{-- The booklet is live: the cantor writes a verse in during the
                 rehearsal and this is how it arrives, without anyone losing the
                 size they set. --}}
            <flux:tooltip :content="__('Refresh the booklet')">
                <flux:button size="sm" variant="ghost" icon="arrow-path" :aria-label="__('Refresh the booklet')" wire:click="reload" />
            </flux:tooltip>

            {{-- The one full screen on the page, and it takes the whole reader:
                 the browser's bars and the site's own menu go, the booklet and
                 its bar stay, and the whole thing still scrolls. Two buttons
                 rather than one label, because a phone has no escape key and an
                 "expand" icon on an already expanded screen tells nobody how to
                 get out. --}}
            <flux:tooltip :content="__('Full screen')">
                <flux:button size="sm" variant="ghost" icon="arrows-pointing-out" :aria-label="__('Full screen')"
                    x-show="!isFullscreen" x-on:click="toggleFullscreen()" />
            </flux:tooltip>

            <flux:tooltip :content="__('Leave full screen')">
                <flux:button size="sm" variant="ghost" icon="arrows-pointing-in" :aria-label="__('Leave full screen')"
                    x-show="isFullscreen" x-cloak x-on:click="toggleFullscreen()" />
            </flux:tooltip>

            <flux:tooltip :content="__('Back to the booklet as it was made')">
                <flux:button size="sm" variant="ghost" icon="arrow-uturn-left" :aria-label="__('Back to the booklet as it was made')"
                    x-on:click="resetAll()" />
            </flux:tooltip>

            <span
                class="flex items-center gap-1 text-xs text-zinc-500 dark:text-zinc-400"
                role="status"
                x-show="busy"
                x-cloak
            >
                <flux:icon name="loading" variant="micro" />
            </span>
        </div>
    </div>

    {{-- The padding is on the outside, so what the browser measures is the width
         a sheet actually gets — a column measured with its own padding inside it
         engraves every score a few millimetres wider than the page it lands on. --}}
    <div class="mx-auto mt-4 max-w-3xl px-2">
    <div x-ref="pages">
            @forelse($entries as $entry)
                @php
                    $format = $entry['kind'] === 'file' ? 'file' : ($entry['format'] ?? null);
                    $panel = $entry['kind'] === 'text' ? [] : ($panels[$format] ?? []);
                    $name = trim((string) ($entry['slot'] ?? '')) ?: trim((string) ($entry['music'] ?? '')) ?: __('Text');
                @endphp

                {{-- One article per entry, which is what makes a toolbar per score
                     possible at all: the booklet is drawn as one engraving per row
                     rather than as pages, so each of them has an element of its own
                     to carry its controls. --}}
                <article
                    wire:key="reader-entry-{{ $entry['id'] }}"
                    class="mb-4 overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-zinc-200 dark:ring-zinc-700"
                    x-data="{ open: false }"
                >
                    <div class="flex items-center gap-1 border-b border-zinc-100 bg-zinc-50 px-2 py-1 dark:border-zinc-700 dark:bg-zinc-800">
                        <span class="min-w-0 flex-1 truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $name }}</span>

                        @if($panel !== [])
                            <flux:tooltip :content="__('Adjust this score')">
                                <flux:button size="sm" variant="ghost" icon="adjustments-horizontal" :aria-label="__('Adjust this score')"
                                    x-on:click="open = !open" x-bind:aria-expanded="open ? 'true' : 'false'" />
                            </flux:tooltip>
                        @endif
                    </div>

                    @if($panel !== [])
                        {{-- Two knobs at most, and both of them are steps rather
                             than numbers: a musician mid-piece presses "bigger"
                             until it is big enough, and never wants to know that
                             the lyric size behind it now reads 12.5. Width is the
                             screen's to decide, the face is chosen once in the bar
                             above, and everything else in the editor's panel is
                             about a sheet of paper this reader is not holding. --}}
                        <div class="border-b border-zinc-100 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800" x-show="open" x-cloak>
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 px-2 py-1">
                                @foreach($panel as $field)
                                    @php
                                        $down = $field['role'] === 'transpose' ? __('Down a semitone') : __('Smaller');
                                        $up = $field['role'] === 'transpose' ? __('Up a semitone') : __('Bigger');

                                        // Encoded here rather than with @js() inside the
                                        // attribute: Blade compiles an echo in a component's
                                        // attribute but not a directive, and a knob whose
                                        // bounds arrive as the literal text "@js(...)" is a
                                        // button that throws.
                                        $knob = Js::from(Arr::only($field, ['key', 'role', 'min', 'max', 'step']));
                                    @endphp

                                    <div class="flex shrink-0 items-center gap-0.5 rounded-lg border border-zinc-200 px-1 dark:border-zinc-700">
                                        <flux:tooltip :content="$field['label']">
                                            <flux:icon
                                                :name="$field['icon']"
                                                variant="micro"
                                                class="mr-1 shrink-0 text-zinc-500 dark:text-zinc-400"
                                                x-bind:class="isOverridden({{ $entry['id'] }}, '{{ $field['key'] }}') ? '!text-blue-600 dark:!text-blue-400' : ''"
                                            />
                                        </flux:tooltip>

                                        <flux:tooltip :content="$down">
                                            <flux:button size="sm" variant="ghost" icon="minus"
                                                aria-label="{{ $field['label'] }}: {{ $down }}"
                                                x-on:click="nudgeOverride({{ $entry['id'] }}, {{ $knob }}, -1)"
                                                x-bind:disabled="atLimit({{ $entry['id'] }}, {{ $knob }}, -1)" />
                                        </flux:tooltip>

                                        @if($field['role'] === 'transpose')
                                            {{-- The one value worth reading back: how far
                                                 from the written key this is now being sung. --}}
                                            <span
                                                class="w-6 text-center text-xs tabular-nums text-zinc-500 dark:text-zinc-400"
                                                x-text="signed(settingsOf({{ $entry['id'] }})['{{ $field['key'] }}'])"
                                            ></span>
                                        @endif

                                        <flux:tooltip :content="$up">
                                            <flux:button size="sm" variant="ghost" icon="plus"
                                                aria-label="{{ $field['label'] }}: {{ $up }}"
                                                x-on:click="nudgeOverride({{ $entry['id'] }}, {{ $knob }}, 1)"
                                                x-bind:disabled="atLimit({{ $entry['id'] }}, {{ $knob }}, 1)" />
                                        </flux:tooltip>
                                    </div>
                                @endforeach

                                <flux:tooltip :content="__('Back to the booklet as it was made')">
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        icon="arrow-path"
                                        class="shrink-0"
                                        :aria-label="__('Back to the booklet as it was made')"
                                        x-on:click="resetOverride({{ $entry['id'] }})"
                                    />
                                </flux:tooltip>
                            </div>
                        </div>
                    @endif

                    {{-- Where the engraving lands. Filled by the browser, so Livewire
                         is kept off it: a refresh replaces the booklet's words, not
                         the drawing someone is in the middle of resizing. --}}
                    <div data-reader-sheet="{{ $entry['id'] }}" wire:ignore class="bg-white"></div>
                </article>
            @empty
                <flux:text class="py-8 text-center text-sm text-zinc-500">
                    {{ __('This booklet is empty.') }}
                </flux:text>
            @endforelse

            <flux:text class="py-8 text-center text-sm text-zinc-500" x-show="!ready" x-cloak>
                {{ __('Laying out…') }}
            </flux:text>
        </div>
    </div>
</div>
