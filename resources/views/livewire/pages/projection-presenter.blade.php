@push('page-bundles')
resources/js/projection-presenter.js
@endpush

{{-- The deck on the wall.

     One slide, as large as the screen will take it, on black. Everything else is
     a control that hides itself: the bar at the top fades out while nobody moves
     the mouse, because a projector showing a toolbar during the Sanctus is a
     projector showing the wrong thing.

     The whole deck is engraved once, when the page loads, and then only swapped
     between. Re-engraving a score mid-service — abc2svg and exsurge both block
     the browser for a moment — would be a stutter at exactly the wrong time. --}}
<div
    x-ref="stage"
    data-projection-presenter
    class="fixed inset-0 z-50 bg-black"
    data-projection-config="{{ json_encode([
        'geometry' => $geometry,
        'entries' => $entries,
        'excluded' => $excluded,
    ]) }}"
    x-data="projectionPresenter(JSON.parse($el.dataset.projectionConfig))"
    x-on:projection-updated.window="applyUpdate($event.detail)"
    x-on:keydown.window="onKey($event)"
    x-on:mousemove.window="wake()"
>
    {{-- The two engines that draw two of the four formats are globals rather
         than bundled modules, and must be loaded before anything asks for them. --}}
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

    {{-- The controls. Out of the way of the picture, and out of sight when the
         room has settled. --}}
    <div
        class="absolute inset-x-0 top-0 z-10 flex items-center gap-2 bg-gradient-to-b from-black/80 to-transparent px-4 py-3 transition-opacity duration-500"
        x-bind:class="idle ? 'pointer-events-none opacity-0' : 'opacity-100'"
    >
        <flux:button size="sm" variant="ghost" icon="arrow-left" href="{{ route('projections.edit', ['projection' => $projection->id]) }}" class="!text-white">
            {{ __('Back to the editor') }}
        </flux:button>

        <span class="truncate text-sm text-white/70">{{ $title }}</span>

        <div class="ms-auto flex items-center gap-2">
            <span class="rounded-full bg-white/10 px-2 py-0.5 text-xs font-medium text-white/80" x-show="total > 0" x-cloak>
                <span x-text="index + 1"></span> / <span x-text="total"></span>
            </span>

            <flux:tooltip :content="__('Read the projection again')">
                <flux:button size="sm" variant="ghost" icon="arrow-path" :aria-label="__('Read the projection again')" wire:click="reload" class="!text-white" />
            </flux:tooltip>

            <flux:tooltip :content="__('Full screen')">
                <flux:button size="sm" variant="ghost" icon="arrows-pointing-out" :aria-label="__('Full screen')" x-on:click="toggleFullscreen()" class="!text-white" />
            </flux:tooltip>
        </div>
    </div>

    {{-- The slide itself. The white box is the shape of the screen the deck was
         built for, fitted into whatever shape the projector actually is — so a
         16:9 deck on a 4:3 beamer is letterboxed rather than stretched. --}}
    <div class="absolute inset-0 flex items-center justify-center" x-on:click="next()">
        <div
            x-ref="stageBox"
            class="bg-white"
            x-show="!blanked"
            x-bind:style="`aspect-ratio: ${aspectRatio}; height: 100%; max-width: 100%; max-height: 100%;`"
            wire:ignore
        ></div>
    </div>

    {{-- Nothing at all: the key a cantor presses when the sermon starts. --}}
    <div class="pointer-events-none absolute inset-0 bg-black" x-show="blanked" x-cloak></div>

    <div
        class="absolute inset-x-0 bottom-0 z-10 flex items-center justify-center gap-3 bg-gradient-to-t from-black/80 to-transparent px-4 py-3 text-xs text-white/60 transition-opacity duration-500"
        x-bind:class="idle ? 'pointer-events-none opacity-0' : 'opacity-100'"
    >
        <span>{{ __('Space / → next · ← back · B blank · F full screen · Esc exit') }}</span>
    </div>

    <div class="absolute inset-0 flex items-center justify-center text-sm text-white/50" x-show="total === 0 && !busy" x-cloak>
        {{ __('This projection has no slides yet.') }}
    </div>
</div>
